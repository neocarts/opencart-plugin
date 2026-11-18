<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\ApiError;
use Neocarts\Items;
use Neocarts\Lang;
use Neocarts\Locale;
use Neocarts\Settings;

/** Storefront events and the JSON routes of docs/ai/adapter-contract.md, OpenCart 2.3. */
class ControllerExtensionModuleNeocarts extends Controller {
	const BASE = 'extension/module/neocarts';

	/** Not a layout module: nothing to render. */
	public function index() {
		return '';
	}

	private function model() {
		$this->load->model('extension/module/neocarts');

		return $this->model_extension_module_neocarts;
	}

	private function json($status, $body) {
		http_response_code($status);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function texts() {
		return Lang::load($this->model()->languageCode());
	}

	// ---- events -------------------------------------------------------------------------------

	/** catalog/view/*\/template/common/header/after */
	public function eventHeader(&$route, &$data, &$output) {
		$model = $this->model();
		$s = $model->settings();
		if (!$s['status'] || $s['shop_id'] === '' || $s['api_key'] === '' || strpos($output, '</head>') === false) {
			return;
		}
		$code = $model->languageCode();
		$texts = array();
		foreach (Lang::load($code) as $key => $text) {
			if (strpos($key, 'sf_') === 0) {
				$texts[$key] = $text;
			}
		}
		$page = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
		$config = array(
			'route' => $page,
			'locale' => Locale::resolve($code),
			'cardsOn' => Settings::cardsOn($s, $page),
			'settings' => Settings::storefront($s),
			'texts' => $texts,
			'urls' => array(
				'products' => $model->link(self::BASE . '/products'),
				'cart' => $model->link(self::BASE . '/cart'),
				'order' => $model->link(self::BASE . '/order'),
				'clear' => $model->link(self::BASE . '/clear'),
				'cartAdd' => $model->link('checkout/cart/add'),
				'miniCart' => $model->link('common/cart/info'),
				'cartPage' => $model->link('checkout/cart'),
				'checkout' => $model->link('checkout/checkout'),
				'success' => $model->link('checkout/success'),
			),
		);
		$html = '<script>window.NeocartsOpencart = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";</script>\n"
			. '<script src="https://cdn.neocarts.com/bundle.js" data-shop-id="' . htmlspecialchars($s['shop_id'], ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n"
			. '<script src="' . ((!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? HTTPS_SERVER : HTTP_SERVER) . 'catalog/view/javascript/neocarts/neocarts.js?v=' . rawurlencode(NEOCARTS_VERSION . '-' . (int) @filemtime(DIR_APPLICATION . 'view/javascript/neocarts/neocarts.js')) . '" defer></script>' . "\n";
		$at = strpos($output, '</head>');
		$output = substr($output, 0, $at) . $html . substr($output, $at);
	}

	/** catalog/view/*\/template/account/account/after: «My subscriptions» */
	public function eventAccount(&$route, &$data, &$output) {
		$s = $this->model()->settings();
		$marker = '<ul class="list-unstyled">';
		if (!$s['status'] || !$s['account_link'] || ($at = strpos($output, $marker)) === false) {
			return;
		}
		$at += strlen($marker);
		$link = '<li><a href="' . \Neocarts\Links::CUSTOMER_URL . '" target="_blank" rel="noopener">' . htmlspecialchars($this->texts()['account_subscriptions'], ENT_QUOTES, 'UTF-8') . '</a></li>';
		$output = substr($output, 0, $at) . $link . substr($output, $at);
	}

	// ---- JSON routes --------------------------------------------------------------------------

	public function products() {
		$ids = isset($this->request->get['ids']) ? explode(',', (string)$this->request->get['ids']) : array();
		$ids = array_slice(array_unique(array_filter(array_map('intval', $ids))), 0, Items::MAX_LINES);
		$products = array();
		foreach ($ids as $id) {
			$product = $this->model()->product($id);
			if ($product) {
				$products[(string)$id] = $product;
			}
		}
		$this->json(200, array('products' => (object)$products));
	}

	public function cart() {
		$lines = array();
		foreach ($this->model()->lines() as $line) {
			$lines[] = array(
				'key' => $line['key'],
				'external_id' => Items::externalId($line['product_id'], $line['option']),
				'title' => $line['name'],
				'variant' => Items::variant($line['option']),
				'image_url' => $line['image_url'],
				'product_url' => $line['product_url'],
				'price' => \Neocarts\Money::minor($line['unit_price']),
				'quantity' => $line['quantity'],
				'subscribable' => $line['subscribable'],
			);
		}
		$this->json(200, array('lines' => $lines));
	}

	public function order() {
		$model = $this->model();
		$s = $model->settings();
		$texts = $this->texts();
		$input = json_decode(file_get_contents('php://input'), true);
		$selection = isset($input['selection']) && is_array($input['selection']) ? $input['selection'] : array();
		try {
			$items = Items::build($model->lines(), $selection);
			$client = new \Neocarts\Client($s['api_url'], $s['api_key'], Locale::resolve($model->languageCode()), 'opencart ' . VERSION);
			$order = null;
			if (!empty($this->session->data['neocarts_order_id'])) {
				try {
					$order = $client->putItems($this->session->data['neocarts_order_id'], $items);
				} catch (ApiError $e) {
					if ($e->status !== 404 && $e->status !== 409) {
						throw $e;
					}
				}
			}
			if (!$order) {
				$order = $client->createOrder($items, $model->link('checkout/cart', 'neocarts=checkout'));
			}
			$this->session->data['neocarts_order_id'] = (string)$order['id'];
			$this->json(200, array('orderId' => (string)$order['id']));
		} catch (ApiError $e) {
			if (isset($texts[$e->getMessage()])) {
				// Items::build: a Lang key, the line name fills %s
				$name = isset($e->errors['name']) ? $e->errors['name'] : '';
				$this->json(422, array('message' => sprintf($texts[$e->getMessage()], $name)));
			} else {
				$this->log->write('Neocarts order: ' . $e->status . ' ' . $e->getMessage());
				$this->json(502, array('message' => $e->getMessage()));
			}
		}
	}

	public function clear() {
		$this->cart->clear();
		unset($this->session->data['neocarts_order_id']);
		$this->json(200, array('ok' => true));
	}

	public function webhook() {
		$signature = isset($this->request->server['HTTP_X_NEOCARTS_SIGNATURE']) ? (string)$this->request->server['HTTP_X_NEOCARTS_SIGNATURE'] : '';
		$status = $this->model()->webhook((string)file_get_contents('php://input'), $signature);
		// the merchant reads the reason in the Neocarts panel's webhook log
		$error = \Neocarts\Webhook::$lastError;
		$this->json($status, $error !== null ? array('error' => $error) : new \stdClass());
	}
}
