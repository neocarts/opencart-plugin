<?php
namespace Opencart\Admin\Controller\Extension\Neocarts\Module;

require_once DIR_EXTENSION . 'neocarts/system/library/neocarts/php/autoload.php';

use Neocarts\Client;
use Neocarts\Lang;
use Neocarts\Links;
use Neocarts\Locale;
use Neocarts\Settings;

/**
 * The admin half: the settings page and the product-form block (switches + card sync on save). docs/ai/adapter-contract.md
 */
class Neocarts extends \Opencart\System\Engine\Controller {
	private const BASE = 'extension/neocarts/module/neocarts';
	private const SYNC_BATCH = 50;

	private function route(string $method = ''): string {
		return self::BASE . ($method !== '' ? \Opencart\Admin\Model\Extension\Neocarts\Module\Neocarts::sep() . $method : '');
	}

	private function link(string $method = '', string $args = ''): string {
		return $this->url->link($this->route($method), 'user_token=' . $this->session->data['user_token'] . $args, true);
	}

	private function t(): array {
		return Lang::load((string)$this->config->get('config_language'));
	}

	private function nc(): \Opencart\System\Engine\Proxy {
		$this->load->model(self::BASE);

		return $this->model_extension_neocarts_module_neocarts;
	}

	private function client(array $s): Client {
		return new Client($s['api_url'], $s['api_key'], Locale::resolve((string)$this->config->get('config_language')), 'oc ' . VERSION);
	}

	private function json(array $body): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/** The support footer every plugin admin page shows (Links, never hard-coded). */
	private function support(array $t): array {
		$mail = '<a href="mailto:' . Links::SUPPORT_EMAIL . '">' . Links::SUPPORT_EMAIL . '</a>';

		return [
			'support' => sprintf(htmlspecialchars($t['text_support']), $mail),
			'docs_url' => Links::docs((string)$this->config->get('config_language')),
		];
	}

	// ---- settings page --------------------------------------------------------------------------

	public function index(): void {
		$this->load->language(self::BASE);
		$nc = $this->nc();
		$nc->migrate();
		$t = $this->t();
		$s = $nc->settings();
		$this->document->setTitle($t['heading_title']);

		$data['t'] = $t;
		$data += $this->support($t);
		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $t['text_extension'], 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')],
			['text' => $t['heading_title'], 'href' => $this->link()],
		];
		$data['save'] = $this->link('save');
		$data['check'] = $this->link('check');
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['webhook_url'] = HTTP_CATALOG . 'index.php?route=' . $this->route('webhook');
		$data['version'] = NEOCARTS_VERSION;
		$data['maintenance'] = (bool)$this->config->get('config_maintenance');
		$data['admin_url'] = Links::ADMIN_URL;
		$data['prefix'] = \Opencart\Admin\Model\Extension\Neocarts\Module\Neocarts::PREFIX;
		$data['key_set'] = $s['api_key'] !== '';
		$data['secret_set'] = $s['webhook_secret'] !== '';
		$data['tabs'] = $this->tabs($s, $t);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view(self::BASE, $data));
	}

	/** Every key of Settings::defaults(4), grouped by tab in the contract's order. */
	private function tabs(array $s, array $t): array {
		$theme = (string)$this->config->get('config_theme');
		$this->load->model('localisation/order_status');
		$statuses = [];
		foreach ($this->model_localisation_order_status->getOrderStatuses() as $os) {
			$statuses[(int)$os['order_status_id']] = $os['name'];
		}
		$positions = [];
		foreach (Settings::POSITIONS as $p) {
			$positions[$p] = $t['text_position_' . $p];
		}
		$f = function (string $key, string $type, string $label, array $options = [], string $help = '') use ($s) {
			return ['key' => $key, 'type' => $type, 'label' => $label, 'value' => $s[$key], 'options' => $options, 'help' => $help];
		};
		$legend = function (string $text) {
			return ['type' => 'legend', 'label' => $text];
		};
		$status = function (string $key) use ($f, $t, $statuses) {
			return $f($key, 'select', $t['entry_' . $key], $key === 'status_partially_refunded' ? [0 => $t['text_none']] + $statuses : $statuses);
		};
		$pages = [];
		foreach (array_keys(Settings::CARD_PAGES) as $key) {
			$pages[] = $f($key, 'switch', $t['entry_card_pages'] . ': ' . $t['text_page_' . substr($key, 8)]);
		}

		return [
			'connection' => [
				$f('status', 'switch', $t['entry_status']),
				$f('shop_id', 'text', $t['entry_shop_id'], [], $t['help_shop_id']),
				$f('api_key', 'secret', $t['entry_api_key'], [], $t['help_api_key']),
				$f('webhook_secret', 'secret', $t['entry_webhook_secret'], [], $t['help_webhook_secret']),
				$f('api_url', 'text', $t['entry_api_url'], [], $t['help_api_url']),
				$f('debug', 'switch', $t['entry_debug']),
			],
			'placement' => array_merge(
				[
					['type' => 'note', 'label' => ($preset = Settings::preset($theme))
						? sprintf($t['text_theme_preset'], $preset)
						: sprintf($t['text_theme_no_preset'], $theme)],
					['type' => 'note', 'label' => $t['help_placement'] . ' ' . $t['help_selector_fallback']],
					['type' => 'code', 'label' => $t['help_slot']],
					$legend($t['legend_product']),
				],
				[
					$f('product_status', 'switch', $t['entry_status'], [], $t['help_options']),
					$f('product_anchor', 'text', $t['entry_anchor']),
					$f('product_position', 'select', $t['entry_position'], $positions),
					$f('product_class', 'text', $t['entry_class']),
					$f('product_style', 'text', $t['entry_style'], [], $t['help_style']),
					$legend($t['legend_cards']),
					$f('card_selector', 'text', $t['entry_card_selector']),
					$f('card_id_regex', 'text', $t['entry_card_id_regex']),
					$f('card_anchor', 'text', $t['entry_anchor']),
					$f('card_position', 'select', $t['entry_position'], $positions),
					$f('card_class', 'text', $t['entry_class']),
					$f('card_style', 'text', $t['entry_style'], [], $t['help_style']),
				],
				$pages,
				[
					$legend($t['legend_cart']),
					$f('cart_status', 'switch', $t['entry_status']),
					$f('cart_row_selector', 'text', $t['entry_cart_row_selector']),
					$f('cart_key_regex', 'text', $t['entry_cart_key_regex']),
					$f('cart_anchor', 'text', $t['entry_anchor']),
					$f('cart_position', 'select', $t['entry_position'], $positions),
					$f('cart_class', 'text', $t['entry_class']),
					$f('cart_style', 'text', $t['entry_style'], [], $t['help_style']),
					$f('quickview_anchor', 'text', $t['entry_quickview_anchor'], [], $t['help_quickview_anchor']),
					$f('minicart_selector', 'text', $t['entry_minicart_selector']),
					$f('minicart_url', 'text', $t['entry_minicart_url'], [], $t['help_minicart_url']),
					$f('oneclick_selector', 'text', $t['entry_oneclick_selector'], [], $t['help_oneclick_selector']),
					$legend($t['entry_custom_css']),
					$f('custom_css', 'textarea', $t['entry_custom_css'], [], $t['help_custom_css']),
				]
			),
			'checkout' => [
				$f('checkout_intercept', 'switch', $t['entry_checkout_intercept'], [], $t['help_checkout_intercept']),
				$f('checkout_link_selector', 'text', $t['entry_checkout_link_selector']),
				$f('checkout_routes', 'text', $t['entry_checkout_routes']),
				$f('checkout_banner_status', 'switch', $t['entry_checkout_banner']),
				$f('checkout_banner_anchor', 'text', $t['entry_checkout_banner'] . ' — ' . $t['entry_anchor']),
				$f('checkout_banner_position', 'select', $t['entry_checkout_banner'] . ' — ' . $t['entry_position'], $positions),
				$f('button_checkout_url', 'switch', $t['entry_button_checkout_url']),
				$f('auto_add', 'switch', $t['entry_auto_add']),
				$f('account_link', 'switch', $t['entry_account_link']),
			],
			'orders' => [
				$f('orders_widget', 'switch', $t['entry_orders_widget']),
				$f('orders_renewal', 'switch', $t['entry_orders_renewal']),
				$status('status_paid'),
				$status('status_manual'),
				$status('status_cancelled'),
				$status('status_refunded'),
				$status('status_partially_refunded'),
				$f('notify_customer', 'switch', $t['entry_notify_customer']),
				$f('product_sync', 'switch', $t['entry_product_sync'], [], $t['help_product_sync']),
			],
		];
	}

	public function save(): void {
		$t = $this->t();
		if (!$this->user->hasPermission('modify', self::BASE)) {
			$this->json(['error' => $t['error_permission']]);

			return;
		}
		$this->nc()->save($this->request->post);
		$this->json(['success' => $t['text_success']]);
	}

	/** «Test connection» with the stored key */
	public function check(): void {
		$t = $this->t();
		$result = $this->client($this->nc()->settings())->check();
		$this->json($result === 'ok'
			? ['success' => $t['text_check_ok']]
			: ['error' => $t['text_check_' . $result] . ' ' . sprintf($t['text_check_support'], Links::SUPPORT_EMAIL)]);
	}

	// ---- install --------------------------------------------------------------------------------

	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/module')) {
			$this->nc()->install();
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/module')) {
			$this->nc()->uninstall();
		}
	}

	// ---- events ---------------------------------------------------------------------------------

	/** admin/model/catalog/product.addProduct/after — the new id is the output */
	public function eventProductAdd(string &$route, array &$args, &$output): void {
		$this->saveSwitch((int)$output);
		$this->syncProduct((int)$output);
	}

	/** admin/model/catalog/product.editProduct/after — args[0] is the id */
	public function eventProductEdit(string &$route, array &$args, &$output): void {
		$this->saveSwitch((int)($args[0] ?? 0));
		$this->syncProduct((int)($args[0] ?? 0));
	}

	/**
	 * The «Subscription available» checkbox of the product form. 4.1 posts the form serialized, so an
	 * unchecked box is simply absent: the hidden marker tells «off» from «not our form» (API, import).
	 */
	private function saveSwitch(int $productId): void {
		if (!$productId || !isset($this->request->post['neocarts_subscription_present'])) {
			return;
		}
		$on = [];
		foreach (array_keys((array)($this->request->post['neocarts_subscription'] ?? [])) as $id) {
			// a new product's form names its switch by id 0
			$on[] = (string)$id === '0' ? (string)$productId : (string)$id;
		}
		$all = [(string)$productId];
		foreach ($this->nc()->choiceValues($productId) as $v) {
			$all[] = $productId . '-' . $v['product_option_value_id'];
		}
		$this->nc()->setOffIds($productId, array_values(array_diff($all, $on)));
	}

	private function syncProduct(int $productId): void {
		$s = $this->nc()->settings();
		if ($productId && $s['status'] && $s['product_sync'] && $s['api_key'] !== '') {
			$this->putProduct($this->client($s), $productId);
		}
	}

	/** The card (never the price) for the product and every variant from the mirror. A failure is logged, never thrown. */
	private function putProduct(Client $client, int $productId): bool {
		$ok = true;
		try {
			$card = $this->nc()->card($productId);
			if ($card) {
				$client->putProduct((string)$productId, $card);
			}
		} catch (\Throwable $e) {
			$ok = false;
			$this->log->write('Neocarts: putProduct ' . $productId . ': ' . $e->getMessage());
		}

		return $ok;
	}

	/** admin/view/catalog/product_form/after */
	public function eventProductForm(string &$route, array &$data, string &$output): void {
		$nc = $this->nc();
		$productId = (int)($data['product_id'] ?? 0);
		$t = $this->t();
		$off = $productId ? $nc->offIds($productId) : [];
		$switches = [['id' => (string)$productId, 'label' => $t['entry_product_subscription'], 'on' => !in_array((string)$productId, $off, true)]];
		foreach ($productId ? $nc->choiceValues($productId) : [] as $v) {
			$id = $productId . '-' . $v['product_option_value_id'];
			$switches[] = ['id' => $id, 'label' => $v['name'] . ': ' . $v['value'], 'on' => !in_array($id, $off, true)];
		}
		$block = $this->load->view(self::BASE . '_product', $this->support($t) + [
			't' => $t,
			'switches' => $switches,
			'admin_url' => Links::ADMIN_URL,
		]);
		// inside the product <form>: the switches are posted with the product (4.1 serializes the form)
		$form = strpos($output, 'id="form-product"');
		$pos = $form === false ? false : strpos($output, '</form>', $form);
		$output = $pos === false ? $output . $block : substr_replace($output, $block, $pos, 0);
	}
}
