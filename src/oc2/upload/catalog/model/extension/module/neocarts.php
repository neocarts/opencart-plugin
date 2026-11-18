<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Items;
use Neocarts\Lang;
use Neocarts\Money;
use Neocarts\OrderMapper;
use Neocarts\Settings;

/**
 * Cart lines, products, and the webhook store + sink on OpenCart 2.3's own models.
 * The model object itself is the WebhookStore and the WebhookSink (docs/ai/adapter-contract.md).
 */
class ModelExtensionModuleNeocarts extends Model implements \Neocarts\WebhookStore, \Neocarts\WebhookSink {
	const LOG_ROWS = 1000;

	private $settings;

	/** The store theme directory (2.3: `<config_theme>_directory`), for Settings::resolve() presets. */
	public function theme() {
		$code = (string)$this->config->get('config_theme');

		return (string)$this->config->get(($code ?: 'theme_default') . '_directory');
	}

	public function settings() {
		if ($this->settings === null) {
			$stored = array();
			$query = $this->db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = 'neocarts'");
			foreach ($query->rows as $row) {
				$stored[substr($row['key'], strlen('neocarts_'))] = $row['value'];
			}
			$this->settings = Settings::resolve(2, $stored, $this->theme());
		}

		return $this->settings;
	}

	/** The store's own setting (store 0), not the per-request value the startup may have changed. */
	private function storeSetting($key) {
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `key` = '" . $this->db->escape($key) . "'");

		return $query->num_rows ? $query->row['value'] : '';
	}

	/** OpenCart language code of the shopper (en-gb, uk-ua, …). */
	public function languageCode() {
		return isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');
	}

	private function image($file) {
		$this->load->model('tool/image');
		$theme = $this->config->get('config_theme');
		$width = (int)$this->config->get($theme . '_image_product_width') ?: 228;
		$height = (int)$this->config->get($theme . '_image_product_height') ?: 228;
		$file = ($file && is_file(DIR_IMAGE . $file)) ? $file : 'placeholder.png';

		return html_entity_decode((string)$this->model_tool_image->resize($file, $width, $height), ENT_QUOTES, 'UTF-8');
	}

	public function link($route, $args = '') {
		return html_entity_decode($this->url->link($route, $args, $this->request->server['HTTPS']), ENT_QUOTES, 'UTF-8');
	}

	private $off = array();

	/** The merchant's switches for one product (`42`, `42-17`), read once per request. */
	private function offIds($product_id) {
		$product_id = (int)$product_id;
		if (!isset($this->off[$product_id])) {
			$query = $this->db->query("SELECT external_id FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id = '" . $product_id . "'");
			$this->off[$product_id] = array_column($query->rows, 'external_id');
		}

		return $this->off[$product_id];
	}

	/** Not switched off for this product/variant, enabled and in stock. */
	private function available($product_id, $externalId) {
		if (Items::disabled($externalId, $this->offIds($product_id))) {
			return false;
		}
		$query = $this->db->query("SELECT status, quantity FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$product_id . "'");

		return $query->num_rows && (int)$query->row['status'] === 1 && (int)$query->row['quantity'] > 0;
	}

	/** GET products: the card the storefront needs; price as the store shows it to this customer. */
	public function product($product_id) {
		$this->load->model('catalog/product');
		$product = $this->model_catalog_product->getProduct($product_id);
		if (!$product) {
			return null;
		}
		$price = (float)$product['special'] ? $product['special'] : $product['price'];
		$required = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "product_option` WHERE product_id = '" . (int)$product_id . "' AND required = '1' LIMIT 1");

		return array(
			'external_id' => (string)(int)$product_id,
			'title' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
			'variant' => null,
			'image_url' => $this->image($product['image']),
			'product_url' => $this->link('product/product', 'product_id=' . (int)$product_id),
			'price' => Money::minor($this->tax->calculate($price, $product['tax_class_id'], $this->config->get('config_tax'))),
			'has_options' => (bool)$required->num_rows,
			'subscribable' => $this->available($product_id, (string)(int)$product_id),
		);
	}

	/** Cart lines in the shape Items expects, plus the cart_id. */
	public function lines() {
		$lines = array();
		foreach ($this->cart->getProducts() as $product) {
			$subscribable = $this->available($product['product_id'], Items::externalId((int)$product['product_id'], $product['option']));
			foreach ($product['option'] as $option) {
				// chosen values whose stock is tracked must be in stock
				if (!empty($option['subtract']) && (int)$option['quantity'] <= 0) {
					$subscribable = false;
				}
			}
			$lines[] = array(
				'subscribable' => $subscribable,
				'key' => $product['cart_id'],
				'product_id' => (int)$product['product_id'],
				'name' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
				'quantity' => (int)$product['quantity'],
				'unit_price' => $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')),
				'image_url' => $this->image($product['image']),
				'product_url' => $this->link('product/product', 'product_id=' . (int)$product['product_id']),
				'option' => $product['option'],
			);
		}

		return $lines;
	}

	public function webhook($body, $signature) {
		$settings = $this->settings();
		// the order mail of addOrderHistory may print warnings: keep them out of the answer
		ob_start();
		try {
			$status = \Neocarts\Webhook::handle($body, $signature, $settings['webhook_secret'], $this, $this);
		} finally {
			ob_end_clean();
		}
		if (\Neocarts\Webhook::$lastError !== null && $settings['debug']) {
			$this->log->write('Neocarts webhook: ' . \Neocarts\Webhook::$lastError);
		}

		return $status;
	}

	// ---- WebhookStore -------------------------------------------------------------------------

	public function claim(string $deliveryId, string $event, string $body): bool {
		$id = $this->db->escape($deliveryId);
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "neocarts_webhook` SET delivery_id = '" . $id . "', event = '" . $this->db->escape($event) . "', status = 'processing', body = '" . $this->db->escape($body) . "', received_at = UTC_TIMESTAMP()");
		if (!$this->db->countAffected()) {
			$this->db->query("UPDATE `" . DB_PREFIX . "neocarts_webhook` SET status = 'processing', error = NULL, received_at = UTC_TIMESTAMP() WHERE delivery_id = '" . $id . "' AND status = 'failed'");
			if (!$this->db->countAffected()) {
				return false;
			}
		}
		$this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_webhook` WHERE received_at < (SELECT received_at FROM (SELECT received_at FROM `" . DB_PREFIX . "neocarts_webhook` ORDER BY received_at DESC LIMIT " . (self::LOG_ROWS - 1) . ", 1) AS keep)");

		return true;
	}

	public function finish(string $deliveryId, string $status, ?string $error = null) {
		$this->db->query("UPDATE `" . DB_PREFIX . "neocarts_webhook` SET status = '" . $this->db->escape($status) . "', error = " . ($error === null ? 'NULL' : "'" . $this->db->escape($error) . "'") . " WHERE delivery_id = '" . $this->db->escape($deliveryId) . "'");
	}

	// ---- WebhookSink --------------------------------------------------------------------------

	private function ocOrderId($neocartsOrderId) {
		$query = $this->db->query("SELECT oc_order_id FROM `" . DB_PREFIX . "neocarts_order` WHERE neocarts_order_id = '" . $this->db->escape((string)$neocartsOrderId) . "'");

		return $query->num_rows ? (int)$query->row['oc_order_id'] : 0;
	}

	private function texts() {
		return Lang::load($this->storeSetting('config_language') ?: 'en-gb');
	}

	public function orderPaid(array $data): bool {
		$settings = $this->settings();
		if ($this->ocOrderId($data['id'])) {
			return true;
		}
		$renewal = isset($data['source']) && $data['source'] === 'subscription';
		if (!$settings[$renewal ? 'orders_renewal' : 'orders_widget']) {
			return false;
		}

		$t = $this->texts();
		$m = OrderMapper::map($data, $t);
		$languageCode = $this->storeSetting('config_language');
		$currencyCode = $this->storeSetting('config_currency');
		$language = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($languageCode) . "'");
		$languageId = $language->num_rows ? (int)$language->row['language_id'] : (int)$this->config->get('config_language_id');
		$currency = $this->db->query("SELECT currency_id FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($currencyCode) . "'");

		$customerId = 0;
		$customerGroupId = (int)$this->storeSetting('config_customer_group_id');
		if ($m['email'] !== '') {
			$customer = $this->db->query("SELECT customer_id, customer_group_id FROM `" . DB_PREFIX . "customer` WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($m['email'])) . "' LIMIT 1");
			if ($customer->num_rows) {
				$customerId = (int)$customer->row['customer_id'];
				$customerGroupId = (int)$customer->row['customer_group_id'];
			}
		}

		$notes = array();
		$products = array();
		foreach ($m['products'] as $line) {
			$product = $line['product_id'] ? $this->db->query("SELECT product_id, model FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$line['product_id'] . "'") : null;
			$name = $line['name'];
			$options = array();
			if ($product && $product->num_rows) {
				foreach ($line['option_value_ids'] as $valueId) {
					$option = $this->db->query("SELECT pov.product_option_id, pov.product_option_value_id, od.name, ovd.name AS value, o.type
						FROM `" . DB_PREFIX . "product_option_value` pov
						JOIN `" . DB_PREFIX . "product_option` po ON po.product_option_id = pov.product_option_id
						JOIN `" . DB_PREFIX . "option` o ON o.option_id = po.option_id
						JOIN `" . DB_PREFIX . "option_description` od ON od.option_id = o.option_id AND od.language_id = '" . $languageId . "'
						JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . $languageId . "'
						WHERE pov.product_option_value_id = '" . (int)$valueId . "' AND pov.product_id = '" . (int)$line['product_id'] . "'");
					if ($option->num_rows) {
						$options[] = $option->row;
					}
				}
				if (count($options) < count($line['option_value_ids']) && $line['variant'] !== '') {
					$name .= ' (' . $line['variant'] . ')';
				}
				$productId = (int)$line['product_id'];
				$model = $product->row['model'];
			} else {
				$notes[] = sprintf($t['order_unknown_product'], $line['name'], $line['external_id']);
				if ($line['variant'] !== '') {
					$name .= ' (' . $line['variant'] . ')';
				}
				$productId = 0;
				$model = '';
			}
			$products[] = array(
				'product_id' => $productId,
				'name' => $name,
				'model' => $model,
				'option' => $options,
				'download' => array(),
				'quantity' => $line['quantity'],
				'subtract' => 1,
				'price' => $line['price'],
				'total' => $line['total'],
				'tax' => 0,
				'reward' => 0,
			);
		}

		$ocLanguage = new Language($languageCode ?: 'en-gb');
		$ocLanguage->load('extension/total/sub_total');
		$ocLanguage->load('extension/total/total');
		$totals = array(array('code' => 'sub_total', 'title' => $ocLanguage->get('text_sub_total'), 'value' => $m['sub_total'], 'sort_order' => 1));
		if ($m['discount'] > 0) {
			$totals[] = array('code' => 'neocarts_discount', 'title' => $t['order_total_discount'], 'value' => -$m['discount'], 'sort_order' => 2);
		}
		$totals[] = array('code' => 'total', 'title' => $ocLanguage->get('text_total'), 'value' => $m['total'], 'sort_order' => 9);

		$address = $m['shipping'];
		$order = array(
			'invoice_prefix' => $this->storeSetting('config_invoice_prefix'),
			'store_id' => 0,
			'store_name' => $this->storeSetting('config_name'),
			'store_url' => HTTP_SERVER,
			'customer_id' => $customerId,
			'customer_group_id' => $customerGroupId,
			'firstname' => $m['firstname'],
			'lastname' => $m['lastname'],
			'email' => $m['email'],
			'telephone' => $m['telephone'],
			'fax' => '',
			'custom_field' => array(),
			'payment_method' => $m['payment_method'],
			'payment_code' => 'neocarts',
			'shipping_method' => $m['shipping_method'],
			'shipping_code' => 'neocarts.neocarts',
			'products' => $products,
			'vouchers' => array(),
			'totals' => $totals,
			'comment' => $m['comment'],
			'total' => $m['total'],
			'affiliate_id' => 0,
			'commission' => 0,
			'marketing_id' => 0,
			'tracking' => '',
			'language_id' => $languageId,
			'currency_id' => $currency->num_rows ? (int)$currency->row['currency_id'] : 0,
			'currency_code' => $currencyCode,
			'currency_value' => 1,
			'ip' => isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '',
			'forwarded_ip' => '',
			'user_agent' => '',
			'accept_language' => '',
		);
		foreach (array('payment', 'shipping') as $type) {
			$order[$type . '_firstname'] = $address['firstname'];
			$order[$type . '_lastname'] = $address['lastname'];
			$order[$type . '_company'] = '';
			$order[$type . '_address_1'] = $address['address_1'];
			$order[$type . '_address_2'] = $address['address_2'];
			$order[$type . '_city'] = $address['city'];
			$order[$type . '_postcode'] = $address['postcode'];
			$order[$type . '_country'] = '';
			$order[$type . '_country_id'] = 0;
			$order[$type . '_zone'] = '';
			$order[$type . '_zone_id'] = 0;
			$order[$type . '_address_format'] = '';
			$order[$type . '_custom_field'] = array();
		}

		$this->load->model('checkout/order');
		$orderId = $this->model_checkout_order->addOrder($order);

		// the primary key guards a racing retry: whoever inserts first owns the order
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "neocarts_order` SET neocarts_order_id = '" . $this->db->escape($m['neocarts_order_id']) . "', oc_order_id = '" . (int)$orderId . "', number = '" . $this->db->escape($m['number']) . "', source = '" . $this->db->escape($m['source']) . "', subscription_ids = '" . $this->db->escape(json_encode($m['subscription_ids'])) . "', created_at = UTC_TIMESTAMP()");
		if (!$this->db->countAffected()) {
			return true;
		}

		$comment = $m['comment'] . ($notes ? "\n" . implode("\n", $notes) : '');
		$status = $m['confirmed_manually'] ? $settings['status_manual'] : $settings['status_paid'];
		$this->model_checkout_order->addOrderHistory($orderId, $status, $comment, (bool)$settings['notify_customer']);

		return true;
	}

	public function orderCancelled(array $data): bool {
		$orderId = isset($data['id']) ? $this->ocOrderId($data['id']) : 0;
		if (!$orderId) {
			return false;
		}
		$settings = $this->settings();
		$this->load->model('checkout/order');
		$this->model_checkout_order->addOrderHistory($orderId, $settings['status_cancelled'], $this->texts()['order_cancelled'], (bool)$settings['notify_customer']);

		return true;
	}

	public function paymentRefunded(array $data): bool {
		$orderId = isset($data['order_id']) ? $this->ocOrderId($data['order_id']) : 0;
		if (!$orderId) {
			return false;
		}
		$settings = $this->settings();
		$this->load->model('checkout/order');
		$status = isset($data['status']) && $data['status'] === 'refunded' ? $settings['status_refunded'] : $settings['status_partially_refunded'];
		if (!$status) {
			// 0 = comment only: keep the current status
			$status = (int)$this->model_checkout_order->getOrder($orderId)['order_status_id'];
		}
		$minor = isset($data['refund_amount']) ? (int)$data['refund_amount'] : (isset($data['refunded_amount']) ? (int)$data['refunded_amount'] : 0);
		$amount = number_format(Money::major($minor), 2, '.', '') . ' ' . $this->storeSetting('config_currency');
		$this->model_checkout_order->addOrderHistory($orderId, $status, sprintf($this->texts()['order_refunded'], $amount), (bool)$settings['notify_customer']);

		return true;
	}
}
