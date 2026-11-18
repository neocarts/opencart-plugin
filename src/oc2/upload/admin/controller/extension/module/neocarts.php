<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Links;
use Neocarts\Settings;

/** The settings page, «Test connection» and the product-form events, OpenCart 2.3. */
class ControllerExtensionModuleNeocarts extends Controller {
	const ROUTE = 'extension/module/neocarts';

	private function model() {
		$this->load->model('extension/module/neocarts');

		return $this->model_extension_module_neocarts;
	}

	private function t() {
		return $this->model()->texts();
	}

	private function link($route, $args = '') {
		return $this->url->link($route, 'token=' . $this->session->data['token'] . $args, true);
	}

	private function mailto() {
		return '<a href="mailto:' . Links::SUPPORT_EMAIL . '">' . Links::SUPPORT_EMAIL . '</a>';
	}

	/** «Write to support» + the docs link: the footer of the page and the product block. */
	private function supportFooter(array $t) {
		return $this->load->view('extension/module/neocarts_support', array(
			'support' => sprintf(htmlspecialchars($t['text_support'], ENT_QUOTES, 'UTF-8'), $this->mailto()),
			'docs_url' => Links::docs((string)$this->config->get('config_admin_language')),
			'text_docs' => $t['text_docs'],
		));
	}

	// ---- install ------------------------------------------------------------------------------

	public function install() {
		$this->model()->install();
	}

	public function uninstall() {
		$this->model()->uninstall();
	}

	// ---- settings page ------------------------------------------------------------------------

	public function index() {
		$model = $this->model();
		$model->migrate();
		$t = $this->t();
		$this->document->setTitle($t['heading_title']);

		if ($this->request->server['REQUEST_METHOD'] === 'POST') {
			if ($this->user->hasPermission('modify', self::ROUTE)) {
				$model->save($this->request->post);
				$this->session->data['neocarts_success'] = $t['text_success'];
				$this->response->redirect($this->link(self::ROUTE));
			}
			$error = $t['error_permission'];
		}

		$s = $model->settings();
		$data = array('t' => $t, 'tabs' => $this->tabs($s, $t));
		$data['error'] = isset($error) ? $error : '';
		$data['success'] = isset($this->session->data['neocarts_success']) ? $this->session->data['neocarts_success'] : '';
		unset($this->session->data['neocarts_success']);
		$data['action'] = $this->link(self::ROUTE);
		$data['back'] = $this->link('extension/extension', '&type=module');
		$data['check'] = $this->link(self::ROUTE . '/check');
		$data['webhook_url'] = (defined('HTTPS_CATALOG') && HTTPS_CATALOG ? HTTPS_CATALOG : HTTP_CATALOG) . 'index.php?route=' . self::ROUTE . '/webhook';
		$data['version'] = NEOCARTS_VERSION;
		$data['admin_url'] = Links::ADMIN_URL;
		$data['maintenance'] = (bool)$this->config->get('config_maintenance');
		$data['key_set'] = $s['api_key'] !== '';
		$data['secret_set'] = $s['webhook_secret'] !== '';
		$data['breadcrumbs'] = array(
			array('text' => $t['text_extension'], 'href' => $this->link('extension/extension', '&type=module')),
		);
		$data['support_footer'] = $this->supportFooter($t);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/module/neocarts', $data));
	}

	/** Every key of Settings::defaults(2), grouped by tab, as the template draws them. */
	private function tabs(array $s, array $t) {
		$this->load->model('localisation/order_status');
		$yesNo = array(1 => $t['text_yes'], 0 => $t['text_no']);
		$theme = $this->model()->theme();
		$preset = Settings::preset($theme);
		$positions = array();
		foreach (Settings::POSITIONS as $p) {
			$positions[$p] = $t['text_position_' . $p];
		}
		$statuses = array(0 => $t['text_none']);
		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$statuses[$status['order_status_id']] = $status['name'];
		}

		$field = function ($key, $type, $label = null, $options = array()) use ($s, $t) {
			return array(
				'key' => $key,
				'name' => 'neocarts_' . $key,
				'type' => $type,
				'label' => $label !== null ? $label : $t['entry_' . $key],
				'help' => isset($t['help_' . $key]) ? $t['help_' . $key] : '',
				'value' => (string)$s[$key],
				'options' => $options,
			);
		};
		$legend = function ($text, $help = '') {
			return array('type' => 'legend', 'label' => $text, 'help' => $help);
		};
		$placement = function ($prefix) use ($field, $t, $positions) {
			return array(
				$field($prefix . 'anchor', 'text', $t['entry_anchor']),
				$field($prefix . 'position', 'select', $t['entry_position'], $positions),
				$field($prefix . 'class', 'text', $t['entry_class']),
				$field($prefix . 'style', 'text', $t['entry_style']),
			);
		};

		$cards = array();
		foreach (Settings::CARD_PAGES as $key => $route) {
			$cards[] = $field($key, 'select', $t['entry_card_pages'] . ': ' . $t['text_page_' . substr($key, strlen('card_on_'))], $yesNo);
		}

		return array(
			'connection' => array(
				$field('status', 'select', null, array(1 => $t['text_enabled'], 0 => $t['text_disabled'])),
				$field('shop_id', 'text'),
				$field('api_key', 'secret'),
				$field('webhook_secret', 'secret'),
				$field('api_url', 'text'),
				$field('debug', 'select', null, $yesNo),
			),
			'placement' => array_merge(
				array(array('type' => 'note', 'label' => $preset ? sprintf($t['text_theme_preset'], $preset) : sprintf($t['text_theme_no_preset'], $theme), 'help' => $t['help_selector_fallback'])),
				array($legend($t['legend_product'], $t['help_options'])),
				array($field('product_status', 'select', $t['entry_status'], $yesNo)),
				$placement('product_'),
				array($field('quickview_anchor', 'text')),
				array($legend($t['legend_cards'], $t['help_placement'])),
				array($field('card_selector', 'text'), $field('card_id_regex', 'text')),
				$placement('card_'),
				$cards,
				array($legend($t['legend_cart'])),
				array($field('cart_status', 'select', $t['entry_status'], $yesNo), $field('cart_row_selector', 'text'), $field('cart_key_regex', 'text')),
				$placement('cart_'),
				array($field('minicart_selector', 'text'), $field('minicart_url', 'text'), $field('oneclick_selector', 'text')),
				array(array('type' => 'note', 'label' => '', 'help' => $t['help_slot'], 'code' => true)),
				array($field('custom_css', 'textarea'))
			),
			'checkout' => array(
				$field('checkout_intercept', 'select', null, $yesNo),
				$field('checkout_link_selector', 'text'),
				$field('checkout_routes', 'text'),
				$field('checkout_banner_status', 'select', $t['entry_checkout_banner'], $yesNo),
				$field('checkout_banner_anchor', 'text', $t['entry_checkout_banner'] . ': ' . $t['entry_anchor']),
				$field('checkout_banner_position', 'select', $t['entry_checkout_banner'] . ': ' . $t['entry_position'], $positions),
				$field('button_checkout_url', 'select', null, $yesNo),
				$field('auto_add', 'select', null, $yesNo),
				$field('account_link', 'select', null, $yesNo),
			),
			'orders' => array(
				$field('orders_widget', 'select', null, $yesNo),
				$field('orders_renewal', 'select', null, $yesNo),
				$field('status_paid', 'select', null, $statuses),
				$field('status_manual', 'select', null, $statuses),
				$field('status_cancelled', 'select', null, $statuses),
				$field('status_refunded', 'select', null, $statuses),
				$field('status_partially_refunded', 'select', null, $statuses),
				$field('notify_customer', 'select', null, $yesNo),
				$field('product_sync', 'select', null, $yesNo),
			),
		);
	}

	/** «Test connection» with the stored key (the form never shows it). */
	public function check() {
		$t = $this->t();
		$result = $this->model()->client()->check();
		$message = $t['text_check_' . $result];
		if ($result !== 'ok') {
			$message .= ' ' . sprintf($t['text_check_support'], Links::SUPPORT_EMAIL);
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('ok' => $result === 'ok', 'message' => $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	// ---- events -------------------------------------------------------------------------------

	/** admin/model/catalog/product/addProduct/after: the new id is the output */
	public function eventProductAdd($route, $args, $output = null) {
		$this->saved((int)$output);
	}

	/** admin/model/catalog/product/editProduct/after: editProduct($product_id, $data) */
	public function eventProductEdit($route, $args, $output = null) {
		$this->saved(isset($args[0]) ? (int)$args[0] : 0);
	}

	private function saved($product_id) {
		if (!$product_id) {
			return;
		}
		$post = $this->request->post;
		if (!empty($post['neocarts_subscription_present']) && $this->user->hasPermission('modify', self::ROUTE)) {
			// a new product's form posts its switches under id 0: they belong to the new id
			$on = array();
			foreach (isset($post['neocarts_subscription']) && is_array($post['neocarts_subscription']) ? array_keys($post['neocarts_subscription']) : array() as $key) {
				$on[preg_replace('/^\d+/', (string)$product_id, (string)$key)] = true;
			}
			$all = array((string)$product_id);
			foreach ($this->model()->choiceValues($product_id) as $value) {
				$all[] = $value['id'];
			}
			$this->model()->setOffIds($product_id, array_values(array_filter($all, function ($id) use ($on) {
				return !isset($on[$id]);
			})));
		}
		$s = $this->model()->settings();
		if ($s['status'] && $s['product_sync'] && $s['api_key'] !== '') {
			$this->model()->syncProduct($product_id);
		}
	}

	/** admin/view/catalog/product_form/after: the switches, inside the <form> so they post with it */
	public function eventProductForm(&$route, &$data, &$output) {
		$form = strpos($output, 'id="form-product"');
		$at = $form === false ? false : strpos($output, '>', $form);
		if ($at === false || !$this->user->hasPermission('access', self::ROUTE)) {
			return;
		}
		$t = $this->t();
		$productId = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$off = array_flip($productId ? $this->model()->offIds($productId) : array());
		$switches = array(array('id' => (string)$productId, 'label' => $t['entry_product_subscription'], 'on' => !isset($off[(string)$productId])));
		foreach ($productId ? $this->model()->choiceValues($productId) : array() as $value) {
			$switches[] = array('id' => $value['id'], 'label' => $value['label'], 'on' => !isset($off[$value['id']]));
		}
		$block = $this->load->view('extension/module/neocarts_product', array(
			't' => $t,
			'switches' => $switches,
			'admin_url' => Links::ADMIN_URL,
			'support_footer' => $this->supportFooter($t),
		));
		$output = substr($output, 0, $at + 1) . $block . substr($output, $at + 1);
	}
}
