<?php
namespace Opencart\Catalog\Model\Extension\Neocarts\Module;

require_once DIR_EXTENSION . 'neocarts/system/library/neocarts/php/autoload.php';

use Neocarts\Items;
use Neocarts\Lang;
use Neocarts\Money;
use Neocarts\OrderMapper;
use Neocarts\Settings;

/**
 * Cart lines, product data and the webhook store/sink on OpenCart's own models.
 * The controller instantiates this class directly for Webhook::handle (a loader proxy is not a
 * WebhookStore). docs/ai/adapter-contract.md
 */
class Neocarts extends \Opencart\System\Engine\Model implements \Neocarts\WebhookStore, \Neocarts\WebhookSink {
	public const PREFIX = 'module_neocarts_';
	private const LOG_KEEP = 1000;
	private array $off = [];

	public function settings(): array {
		$stored = [];
		foreach (array_keys(Settings::defaults(4)) as $key) {
			if ($this->config->has(self::PREFIX . $key)) {
				$stored[$key] = $this->config->get(self::PREFIX . $key);
			}
		}

		return Settings::resolve(4, $stored, (string)$this->config->get('config_theme'));
	}

	public function texts(): array {
		return Lang::load((string)$this->config->get('config_language'));
	}

	/** The price the store shows for the current customer group: special applied, tax per config_tax. */
	public function shownPrice(float $price, int $taxClassId): float {
		return $this->tax->calculate($price, $taxClassId, (bool)$this->config->get('config_tax'));
	}

	public function imageUrl(string $image): string {
		$this->load->model('tool/image');
		$image = is_file(DIR_IMAGE . $image) ? $image : 'placeholder.png';

		return $this->model_tool_image->resize($image, (int)$this->config->get('config_image_product_width'), (int)$this->config->get('config_image_product_height'));
	}

	public function productUrl(int $productId): string {
		return $this->url->link('product/product', 'product_id=' . $productId, true);
	}

	public function products(array $ids): array {
		$this->load->model('catalog/product');
		$out = [];
		foreach ($ids as $id) {
			$p = $this->model_catalog_product->getProduct($id);
			if (!$p) {
				continue;
			}
			$required = false;
			foreach ($this->model_catalog_product->getOptions($id) as $option) {
				$required = $required || !empty($option['required']);
			}
			$price = (float)$p['special'] ?: (float)$p['price'];
			$out[(string)$id] = [
				'external_id' => (string)$id,
				'title' => html_entity_decode($p['name'], ENT_QUOTES, 'UTF-8'),
				'variant' => null,
				'image_url' => $this->imageUrl((string)$p['image']),
				'product_url' => $this->productUrl($id),
				'price' => Money::minor($this->shownPrice($price, (int)$p['tax_class_id'])),
				'has_options' => $required,
				'subscribable' => $this->subscribable($id),
			];
		}

		return $out;
	}

	/**
	 * Not switched off in the admin (Items::disabled over neocarts_product) and available: status 1,
	 * quantity > 0, every chosen option value that subtracts stock has quantity > 0.
	 */
	public function subscribable(int $productId, array $optionValueIds = []): bool {
		$row = $this->db->query("SELECT `status`, `quantity` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $productId . "'")->row;
		if (!$row || !$row['status'] || (int)$row['quantity'] <= 0) {
			return false;
		}
		$externalId = $productId . ($optionValueIds ? '-' . implode('.', $optionValueIds) : '');
		if (Items::disabled($externalId, $this->offIds($productId))) {
			return false;
		}
		if ($optionValueIds) {
			$out = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "product_option_value` WHERE `product_id` = '" . $productId . "' AND `subtract` = '1' AND `quantity` <= 0 AND `product_option_value_id` IN (" . implode(',', array_map('intval', $optionValueIds)) . ")")->row;
			if ((int)$out['total'] > 0) {
				return false;
			}
		}

		return true;
	}

	/** The merchant's switches for one product, read once per request. */
	private function offIds(int $productId): array {
		if (!isset($this->off[$productId])) {
			$this->off[$productId] = array_column($this->db->query("SELECT `external_id` FROM `" . DB_PREFIX . "neocarts_product` WHERE `product_id` = '" . $productId . "'")->rows, 'external_id');
		}

		return $this->off[$productId];
	}

	/** Cart lines normalised for Items::build (see its docblock), plus the cart key. */
	public function cartLines(): array {
		$lines = [];
		foreach ($this->cart->getProducts() as $p) {
			$lines[] = [
				'key' => (string)$p['cart_id'],
				'product_id' => (int)$p['product_id'],
				'name' => html_entity_decode($p['name'], ENT_QUOTES, 'UTF-8'),
				'quantity' => (int)$p['quantity'],
				'unit_price' => $this->shownPrice((float)$p['price'], (int)$p['tax_class_id']),
				'image_url' => $this->imageUrl((string)$p['image']),
				'product_url' => $this->productUrl((int)$p['product_id']),
				'option' => $p['option'],
				'subscribable' => $this->subscribable((int)$p['product_id'], array_filter(array_map('intval', array_column($p['option'], 'product_option_value_id')))),
			];
		}

		return $lines;
	}

	// ---- WebhookStore ---------------------------------------------------------------------------

	public function claim(string $deliveryId, string $event, string $body): bool {
		$id = $this->db->escape($deliveryId);
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "neocarts_webhook` SET `delivery_id` = '" . $id . "', `event` = '" . $this->db->escape($event) . "', `status` = 'processing', `body` = '" . $this->db->escape($body) . "', `received_at` = UTC_TIMESTAMP()");
		if ($this->db->countAffected() < 1) {
			$this->db->query("UPDATE `" . DB_PREFIX . "neocarts_webhook` SET `status` = 'processing', `error` = NULL, `received_at` = UTC_TIMESTAMP() WHERE `delivery_id` = '" . $id . "' AND `status` = 'failed'");
			if ($this->db->countAffected() < 1) {
				return false;
			}
		}
		$old = $this->db->query("SELECT `received_at` FROM `" . DB_PREFIX . "neocarts_webhook` ORDER BY `received_at` DESC LIMIT " . self::LOG_KEEP . ", 1");
		if ($old->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_webhook` WHERE `received_at` <= '" . $this->db->escape($old->row['received_at']) . "'");
		}

		return true;
	}

	public function finish(string $deliveryId, string $status, ?string $error = null) {
		$this->db->query("UPDATE `" . DB_PREFIX . "neocarts_webhook` SET `status` = '" . $this->db->escape($status) . "', `error` = " . ($error === null ? 'NULL' : "'" . $this->db->escape($error) . "'") . " WHERE `delivery_id` = '" . $this->db->escape($deliveryId) . "'");
	}

	// ---- WebhookSink ----------------------------------------------------------------------------

	public function orderPaid(array $data): bool {
		if ($this->ocOrderId((string)$data['id'])) {
			return true;
		}
		$s = $this->settings();
		$renewal = isset($data['source']) && $data['source'] === 'subscription';
		if (!($renewal ? $s['orders_renewal'] : $s['orders_widget'])) {
			return false;
		}
		$t = $this->texts();
		$m = OrderMapper::map($data, $t);

		$this->load->model('checkout/order');
		$this->load->model('account/customer');
		$customer = $m['email'] !== '' ? $this->model_account_customer->getCustomerByEmail($m['email']) : [];
		$currency = $this->db->query("SELECT `currency_id` FROM `" . DB_PREFIX . "currency` WHERE `code` = '" . $this->db->escape((string)$this->config->get('config_currency')) . "'")->row;
		$languageCode = (string)$this->config->get('config_language');
		$language = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language` WHERE `code` = '" . $this->db->escape($languageCode) . "'")->row;

		$notes = [];
		$products = [];
		foreach ($m['products'] as $line) {
			$products[] = $this->orderLine($line, $t, $notes);
		}

		$totals = [['extension' => 'opencart', 'code' => 'sub_total', 'title' => $this->totalTitle('sub_total'), 'value' => $m['sub_total'], 'sort_order' => 1]];
		if ($m['discount'] > 0) {
			$totals[] = ['extension' => 'neocarts', 'code' => 'neocarts_discount', 'title' => $t['order_total_discount'], 'value' => -$m['discount'], 'sort_order' => 2];
		}
		$totals[] = ['extension' => 'opencart', 'code' => 'total', 'title' => $this->totalTitle('total'), 'value' => $m['total'], 'sort_order' => 9];

		$address = $m['shipping'];
		$order = [
			'subscription_id' => 0,
			'invoice_prefix' => (string)$this->config->get('config_invoice_prefix'),
			'store_id' => (int)$this->config->get('config_store_id'),
			'store_name' => (string)$this->config->get('config_name'),
			'store_url' => (string)$this->config->get('config_url'),
			'customer_id' => $customer ? (int)$customer['customer_id'] : 0,
			'customer_group_id' => $customer ? (int)$customer['customer_group_id'] : (int)$this->config->get('config_customer_group_id'),
			'firstname' => $m['firstname'],
			'lastname' => $m['lastname'],
			'email' => $m['email'],
			'telephone' => $m['telephone'],
			'custom_field' => [],
			'payment_method' => ['name' => $m['payment_method'], 'code' => 'neocarts'],
			'shipping_method' => ['name' => $m['shipping_method'], 'code' => 'neocarts.neocarts', 'cost' => 0, 'tax_class_id' => 0],
			'products' => $products,
			'totals' => $totals,
			'comment' => $m['comment'],
			'total' => $m['total'],
			'affiliate_id' => 0,
			'commission' => 0,
			'marketing_id' => 0,
			'tracking' => '',
			'language_id' => $language ? (int)$language['language_id'] : (int)$this->config->get('config_language_id'),
			'language_code' => $languageCode,
			'currency_id' => $currency ? (int)$currency['currency_id'] : 0,
			'currency_code' => (string)$this->config->get('config_currency'),
			'currency_value' => 1,
			'ip' => '',
			'forwarded_ip' => '',
			'user_agent' => 'Neocarts webhook',
			'accept_language' => '',
		];
		// payment address = shipping address
		foreach (['payment', 'shipping'] as $kind) {
			$order[$kind . '_address_id'] = 0;
			$order[$kind . '_company'] = '';
			$order[$kind . '_country'] = '';
			$order[$kind . '_country_id'] = 0;
			$order[$kind . '_zone'] = '';
			$order[$kind . '_zone_id'] = 0;
			$order[$kind . '_address_format'] = '';
			$order[$kind . '_custom_field'] = [];
			foreach (['firstname', 'lastname', 'address_1', 'address_2', 'city', 'postcode'] as $field) {
				$order[$kind . '_' . $field] = $address[$field];
			}
		}

		$orderId = $this->model_checkout_order->addOrder($order);
		// the primary key guards a racing retry: the second insert fails, the delivery is retried and skipped
		$this->db->query("INSERT INTO `" . DB_PREFIX . "neocarts_order` SET `neocarts_order_id` = '" . $this->db->escape($m['neocarts_order_id']) . "', `oc_order_id` = '" . (int)$orderId . "', `number` = '" . $this->db->escape($m['number']) . "', `source` = '" . $this->db->escape($m['source']) . "', `subscription_ids` = '" . $this->db->escape(json_encode($m['subscription_ids'])) . "', `created_at` = UTC_TIMESTAMP()");

		$comment = $m['comment'] . ($notes ? "\n" . implode("\n", $notes) : '');
		$status = $m['confirmed_manually'] ? $s['status_manual'] : $s['status_paid'];
		$this->model_checkout_order->addHistory($orderId, (int)$status, $comment, (bool)$s['notify_customer'], true);

		return true;
	}

	public function orderCancelled(array $data): bool {
		$orderId = $this->ocOrderId((string)$data['id']);
		if (!$orderId) {
			return false;
		}
		$s = $this->settings();
		$this->load->model('checkout/order');
		$this->model_checkout_order->addHistory($orderId, (int)$s['status_cancelled'], $this->texts()['order_cancelled'], (bool)$s['notify_customer'], true);

		return true;
	}

	public function paymentRefunded(array $data): bool {
		$orderId = $this->ocOrderId(isset($data['order_id']) ? (string)$data['order_id'] : '');
		if (!$orderId) {
			return false;
		}
		$s = $this->settings();
		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($orderId);
		$status = isset($data['status']) && $data['status'] === 'refunded' ? (int)$s['status_refunded'] : (int)$s['status_partially_refunded'];
		$minor = isset($data['refund_amount']) ? (int)$data['refund_amount'] : (int)($data['refunded_amount'] ?? 0);
		$amount = number_format(Money::major($minor), 2, '.', '') . ' ' . $this->config->get('config_currency');
		$comment = sprintf($this->texts()['order_refunded'], $amount);
		$this->model_checkout_order->addHistory($orderId, $status ?: (int)$order['order_status_id'], $comment, (bool)$s['notify_customer'], true);

		return true;
	}

	// ---- helpers --------------------------------------------------------------------------------

	private function ocOrderId(string $neocartsOrderId): int {
		$q = $this->db->query("SELECT `oc_order_id` FROM `" . DB_PREFIX . "neocarts_order` WHERE `neocarts_order_id` = '" . $this->db->escape($neocartsOrderId) . "'");

		return $q->num_rows ? (int)$q->row['oc_order_id'] : 0;
	}

	/** One OrderMapper product → a 4.1 addProduct() line; options rebuilt from the external_id. */
	private function orderLine(array $line, array $t, array &$notes): array {
		$product = $line['product_id'] ? $this->db->query("SELECT `product_id`, `master_id`, `model` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$line['product_id'] . "'")->row : [];
		$name = $line['name'];
		$options = [];
		if ($product) {
			foreach ($line['option_value_ids'] as $povId) {
				$o = $this->db->query("SELECT `pov`.`product_option_id`, `pov`.`product_option_value_id`, `od`.`name`, `ovd`.`name` AS `value`, `o`.`type` FROM `" . DB_PREFIX . "product_option_value` `pov` LEFT JOIN `" . DB_PREFIX . "product_option` `po` ON (`po`.`product_option_id` = `pov`.`product_option_id`) LEFT JOIN `" . DB_PREFIX . "option` `o` ON (`o`.`option_id` = `po`.`option_id`) LEFT JOIN `" . DB_PREFIX . "option_description` `od` ON (`od`.`option_id` = `o`.`option_id` AND `od`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "option_value_description` `ovd` ON (`ovd`.`option_value_id` = `pov`.`option_value_id` AND `ovd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE `pov`.`product_option_value_id` = '" . (int)$povId . "' AND `pov`.`product_id` = '" . (int)$product['product_id'] . "'")->row;
				if ($o) {
					$options[] = $o;
				}
			}
			if (count($options) < count($line['option_value_ids']) && $line['variant'] !== '') {
				$name .= ' (' . $line['variant'] . ')';
			}
		} else {
			$notes[] = sprintf($t['order_unknown_product'], $line['name'], $line['external_id']);
			if ($line['variant'] !== '') {
				$name .= ' (' . $line['variant'] . ')';
			}
		}

		return [
			'product_id' => $product ? (int)$product['product_id'] : 0,
			'master_id' => $product ? (int)$product['master_id'] : 0,
			'name' => $name,
			'model' => $product ? $product['model'] : '',
			'quantity' => $line['quantity'],
			'price' => $line['price'],
			'total' => $line['total'],
			'tax' => 0,
			'reward' => 0,
			'option' => $options,
		];
	}

	private function totalTitle(string $code): string {
		$this->load->language('extension/opencart/total/' . $code, 'neocarts');

		return (string)$this->language->get('neocarts_text_' . $code);
	}
}
