<?php
namespace Opencart\Catalog\Controller\Extension\Neocarts\Module;

require_once DIR_EXTENSION . 'neocarts/system/library/neocarts/php/autoload.php';

use Neocarts\ApiError;
use Neocarts\Client;
use Neocarts\Items;
use Neocarts\Locale;
use Neocarts\Settings;
use Neocarts\Webhook;

/**
 * The storefront half: the header event, the account link and the JSON routes neocarts.js calls,
 * plus the webhook endpoint. docs/ai/adapter-contract.md
 */
class Neocarts extends \Opencart\System\Engine\Controller {
	private const BASE = 'extension/neocarts/module/neocarts';
	private const BUNDLE = 'https://cdn.neocarts.com/bundle.js';

	/** 4.0.2+ separate the method with `.`, 4.0.0–4.0.1 with `|`. */
	public static function sep(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
	}

	private function route(string $method): string {
		return self::BASE . self::sep() . $method;
	}

	private function model(): \Opencart\Catalog\Model\Extension\Neocarts\Module\Neocarts {
		return new \Opencart\Catalog\Model\Extension\Neocarts\Module\Neocarts($this->registry);
	}

	private function json(array $body, int $status = 200): void {
		http_response_code($status);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($body ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function locale(array $s): string {
		return Locale::resolve((string)$this->config->get('config_language'));
	}

	// ---- events ---------------------------------------------------------------------------------

	/** catalog/view/common/header/after */
	public function header(string &$route, array &$data, string &$output): void {
		$model = $this->model();
		$s = $model->settings();
		if (!$s['status'] || $s['shop_id'] === '' || $s['api_key'] === '' || strpos($output, self::BUNDLE) !== false) {
			return;
		}
		$texts = [];
		foreach ($model->texts() as $key => $text) {
			if (strpos($key, 'sf_') === 0) {
				$texts[$key] = $text;
			}
		}
		$page = (string)($this->request->get['route'] ?? 'common/home');
		$config = [
			'route' => $page,
			'locale' => $this->locale($s),
			'cardsOn' => Settings::cardsOn($s, $page),
			'settings' => Settings::storefront($s),
			'texts' => $texts,
			'urls' => [
				'products' => $this->url->link($this->route('products'), '', true),
				'cart' => $this->url->link($this->route('cart'), '', true),
				'order' => $this->url->link($this->route('order'), '', true),
				'clear' => $this->url->link($this->route('clear'), '', true),
				'cartAdd' => $this->url->link('checkout/cart' . self::sep() . 'add', '', true),
				'miniCart' => $this->url->link('common/cart' . self::sep() . 'info', '', true),
				'cartPage' => $this->url->link('checkout/cart', '', true),
				'success' => $this->url->link('checkout/success', '', true),
				'checkout' => html_entity_decode($this->url->link('checkout/checkout', '', true), ENT_QUOTES, 'UTF-8'),
			],
		];
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
		$js = HTTP_SERVER . 'extension/neocarts/catalog/view/javascript/neocarts/neocarts.js?v=' . rawurlencode(NEOCARTS_VERSION . '-' . (int) @filemtime(DIR_EXTENSION . 'neocarts/catalog/view/javascript/neocarts/neocarts.js'));
		$html = '<script>window.NeocartsOpencart = ' . json_encode($config, $flags) . ';</script>' . "\n"
			. '<script src="' . self::BUNDLE . '" data-shop-id="' . htmlspecialchars($s['shop_id'], ENT_QUOTES) . '" defer></script>' . "\n"
			. '<script src="' . htmlspecialchars($js, ENT_QUOTES) . '" defer></script>' . "\n";
		$pos = stripos($output, '</head>');
		$output = $pos === false ? $html . $output : substr_replace($output, $html, $pos, 0);
	}

	/** catalog/view/account/account/after: «My subscriptions» in the orders list */
	public function account(string &$route, array &$data, string &$output): void {
		$model = $this->model();
		$s = $model->settings();
		if (!$s['status'] || !$s['account_link']) {
			return;
		}
		$li = '<li><a href="' . \Neocarts\Links::CUSTOMER_URL . '" target="_blank" rel="noopener">' . htmlspecialchars($model->texts()['account_subscriptions']) . '</a></li>';
		// the second list is «My orders» in the default theme; otherwise the first one
		$lists = preg_split('/(<ul class="list-unstyled">)/', $output, 3, PREG_SPLIT_DELIM_CAPTURE);
		if (count($lists) >= 5) {
			$output = $lists[0] . $lists[1] . $lists[2] . $lists[3] . $li . $lists[4];
		} elseif (count($lists) >= 3) {
			$output = $lists[0] . $lists[1] . $li . $lists[2];
		}
	}

	// ---- JSON routes ----------------------------------------------------------------------------

	public function products(): void {
		$ids = array_slice(array_unique(array_filter(array_map('intval', explode(',', (string)($this->request->get['ids'] ?? ''))))), 0, 100);
		$this->json(['products' => (object)$this->model()->products($ids)]);
	}

	public function cart(): void {
		$lines = [];
		foreach ($this->model()->cartLines() as $l) {
			$lines[] = [
				'key' => $l['key'],
				'external_id' => Items::externalId($l['product_id'], $l['option']),
				'title' => $l['name'],
				'variant' => Items::variant($l['option']),
				'image_url' => $l['image_url'],
				'product_url' => $l['product_url'],
				'price' => \Neocarts\Money::minor($l['unit_price']),
				'quantity' => $l['quantity'],
				'subscribable' => $l['subscribable'],
			];
		}
		$this->json(['lines' => $lines]);
	}

	public function order(): void {
		$model = $this->model();
		$s = $model->settings();
		$t = $model->texts();
		$body = json_decode((string)file_get_contents('php://input'), true);
		$selection = is_array($body) && isset($body['selection']) && is_array($body['selection']) ? $body['selection'] : [];

		try {
			$items = Items::build($model->cartLines(), $selection);
			$client = new Client($s['api_url'], $s['api_key'], $this->locale($s), 'oc ' . VERSION);
			$order = null;
			if (!empty($this->session->data['neocarts_order_id'])) {
				try {
					$order = $client->putItems((string)$this->session->data['neocarts_order_id'], $items);
				} catch (ApiError $e) {
					if ($e->status !== 404 && $e->status !== 409) {
						throw $e;
					}
				}
			}
			if (!$order) {
				$redirect = $this->url->link('checkout/cart', 'neocarts=checkout', true);
				$order = $client->createOrder($items, $redirect);
			}
			$this->session->data['neocarts_order_id'] = (string)$order['id'];
			$this->json(['orderId' => (string)$order['id']]);
		} catch (ApiError $e) {
			$key = $e->getMessage();
			if (isset($t[$key])) {
				$this->json(['message' => sprintf($t[$key], (string)($e->errors['name'] ?? ''))], 422);
			} else {
				if ($s['debug']) {
					$this->log->write('Neocarts: Shop API ' . $e->status . ': ' . $key);
				}
				$this->json(['message' => $e->status === 0 ? $t['sf_error'] : $key], 502);
			}
		}
	}

	public function clear(): void {
		$this->cart->clear();
		unset($this->session->data['neocarts_order_id']);
		$this->json(['ok' => true]);
	}

	public function webhook(): void {
		$model = $this->model();
		$s = $model->settings();
		$status = Webhook::handle(
			(string)file_get_contents('php://input'),
			(string)($this->request->server['HTTP_X_NEOCARTS_SIGNATURE'] ?? ''),
			$s['webhook_secret'],
			$model,
			$model
		);
		$error = Webhook::$lastError;
		if ($s['debug']) {
			$this->log->write('Neocarts webhook: ' . ($this->request->server['HTTP_X_NEOCARTS_EVENT'] ?? '') . ' ' . ($this->request->server['HTTP_X_NEOCARTS_DELIVERY'] ?? '') . ' → ' . $status . ($error !== null ? ': ' . $error : ''));
		}
		// the error goes back to Neocarts: the merchant reads it in the panel's webhook log
		$this->json($error !== null ? ['error' => $error] : [], $status);
	}
}
