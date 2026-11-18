<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Client;
use Neocarts\Lang;
use Neocarts\Locale;
use Neocarts\Money;
use Neocarts\OrderMapper;
use Neocarts\Settings;

/**
 * Settings, and the webhook store + sink on OpenCart's own models: orders from Neocarts are ordinary
 * OpenCart orders (checkout/order addOrder + addOrderHistory). docs/ai/adapter-contract.md
 */
class ModelExtensionModuleNeocarts extends Model implements \Neocarts\WebhookStore, \Neocarts\WebhookSink
{
    const CODE = 'module_neocarts';
    const LOG_ROWS = 1000;

    private $settings;

    public function settings()
    {
        if ($this->settings === null) {
            $stored = [];
            foreach (array_keys(Settings::defaults(3)) as $key) {
                $value = $this->config->get(self::CODE . '_' . $key);
                if ($value !== null) {
                    $stored[$key] = $value;
                }
            }
            // the theme's selector preset fills what the merchant left at the default
            $theme = (string) $this->config->get('theme_' . $this->config->get('config_theme') . '_directory');
            $this->settings = Settings::resolve(3, $stored, $theme);
        }

        return $this->settings;
    }

    public function client(array $s)
    {
        return new Client($s['api_url'], $s['api_key'], Locale::resolve((string) $this->config->get('config_language')), 'opencart ' . VERSION);
    }

    public function hasRequiredOptions($productId)
    {
        return (bool) $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "product_option` WHERE product_id = '" . (int) $productId . "' AND required = '1' LIMIT 1")->num_rows;
    }

    /** The merchant's switches (`neocarts_product.external_id`) for these products, for Items::disabled(). */
    public function offIds(array $productIds)
    {
        $ids = array_filter(array_map('intval', $productIds));
        if (!$ids) {
            return [];
        }
        $query = $this->db->query("SELECT external_id FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id IN (" . implode(',', array_unique($ids)) . ")");

        return array_column($query->rows, 'external_id');
    }

    /** product_id => quantity of enabled products */
    public function stock(array $productIds)
    {
        $ids = array_filter(array_map('intval', $productIds));
        if (!$ids) {
            return [];
        }
        $query = $this->db->query("SELECT product_id, quantity FROM `" . DB_PREFIX . "product` WHERE status = 1 AND product_id IN (" . implode(',', $ids) . ")");

        return array_column($query->rows, 'quantity', 'product_id');
    }

    // ---- WebhookStore -----------------------------------------------------------------------------

    public function claim(string $deliveryId, string $event, string $body): bool
    {
        $table = "`" . DB_PREFIX . "neocarts_webhook`";
        $id = $this->db->escape($deliveryId);
        $this->db->query("INSERT IGNORE INTO " . $table . " SET delivery_id = '" . $id . "', event = '" . $this->db->escape($event) . "', status = 'processing', body = '" . $this->db->escape($body) . "', received_at = UTC_TIMESTAMP()");
        if ($this->db->countAffected() > 0) {
            $this->trimLog();

            return true;
        }
        // ponytail: a delivery stuck in `processing` (a crash mid-way) is not reclaimed; add a timeout if that shows up
        $this->db->query("UPDATE " . $table . " SET status = 'processing', error = NULL, received_at = UTC_TIMESTAMP() WHERE delivery_id = '" . $id . "' AND status = 'failed'");

        return $this->db->countAffected() > 0;
    }

    public function finish(string $deliveryId, string $status, ?string $error = null)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "neocarts_webhook` SET status = '" . $this->db->escape($status) . "', error = " . ($error === null ? 'NULL' : "'" . $this->db->escape($error) . "'") . " WHERE delivery_id = '" . $this->db->escape($deliveryId) . "'");
    }

    private function trimLog()
    {
        $table = "`" . DB_PREFIX . "neocarts_webhook`";
        $edge = $this->db->query("SELECT received_at FROM " . $table . " ORDER BY received_at DESC LIMIT " . self::LOG_ROWS . ",1")->row;
        if ($edge) {
            $this->db->query("DELETE FROM " . $table . " WHERE received_at <= '" . $this->db->escape($edge['received_at']) . "'");
        }
    }

    // ---- WebhookSink ------------------------------------------------------------------------------

    public function orderPaid(array $data): bool
    {
        $s = $this->settings();
        $id = $this->db->escape((string) $data['id']);
        if ($this->db->query("SELECT 1 FROM `" . DB_PREFIX . "neocarts_order` WHERE neocarts_order_id = '" . $id . "'")->num_rows) {
            return true;
        }
        $store = $this->storeDefaults();
        $t = Lang::load($store['language']);
        $m = OrderMapper::map($data, $t);
        if (!($m['source'] === 'subscription' ? $s['orders_renewal'] : $s['orders_widget'])) {
            return false;
        }

        // the primary key guards a racing retry: whoever inserts first creates the order
        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "neocarts_order` SET neocarts_order_id = '" . $id . "', oc_order_id = 0, number = '" . $this->db->escape($m['number']) . "', source = '" . $this->db->escape($m['source']) . "', subscription_ids = '" . $this->db->escape(json_encode($m['subscription_ids'])) . "', created_at = UTC_TIMESTAMP()");
        if (!$this->db->countAffected()) {
            return true;
        }

        try {
            $notes = [];
            $order = $this->orderData($m, $store, $t, $notes);
            $this->load->model('checkout/order');
            $orderId = (int) $this->model_checkout_order->addOrder($order);
            $this->db->query("UPDATE `" . DB_PREFIX . "neocarts_order` SET oc_order_id = '" . $orderId . "' WHERE neocarts_order_id = '" . $id . "'");
            $comment = $m['comment'] . ($notes ? "\n" . implode("\n", $notes) : '');
            $this->history($orderId, (int) ($m['confirmed_manually'] ? $s['status_manual'] : $s['status_paid']), $comment);
        } catch (\Throwable $e) {
            // the retry starts over; an order left without a status stays among OpenCart's «missing orders»
            $this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_order` WHERE neocarts_order_id = '" . $id . "'");
            throw $e;
        }

        return true;
    }

    public function orderCancelled(array $data): bool
    {
        $orderId = $this->ocOrderId(isset($data['id']) ? (string) $data['id'] : '');
        if (!$orderId) {
            return false;
        }
        $t = Lang::load($this->storeDefaults()['language']);
        $this->history($orderId, (int) $this->settings()['status_cancelled'], $t['order_cancelled']);

        return true;
    }

    public function paymentRefunded(array $data): bool
    {
        $orderId = $this->ocOrderId(isset($data['order_id']) ? (string) $data['order_id'] : '');
        if (!$orderId) {
            return false;
        }
        $s = $this->settings();
        $store = $this->storeDefaults();
        $t = Lang::load($store['language']);
        $amount = isset($data['refund_amount']) ? (int) $data['refund_amount'] : (isset($data['refunded_amount']) ? (int) $data['refunded_amount'] : 0);
        // ponytail: plain "2315.70 UAH", not currency->format(): the amount must stay greppable
        $comment = sprintf($t['order_refunded'], number_format(Money::major($amount), 2, '.', '') . ' ' . $store['currency']);
        $status = isset($data['status']) && $data['status'] === 'refunded' ? (int) $s['status_refunded'] : (int) $s['status_partially_refunded'];
        $this->history($orderId, $status, $comment);

        return true;
    }

    // ---- the OpenCart order -----------------------------------------------------------------------

    /** OrderMapper's neutral order → ModelCheckoutOrder::addOrder() array (3.0 field names). */
    private function orderData(array $m, array $store, array $t, array &$notes)
    {
        $customer = $m['email'] !== '' ? $this->db->query("SELECT customer_id, customer_group_id FROM `" . DB_PREFIX . "customer` WHERE LOWER(email) = '" . $this->db->escape(strtolower($m['email'])) . "' LIMIT 1")->row : [];

        $products = [];
        foreach ($m['products'] as $p) {
            $product = $p['product_id'] ? $this->db->query("SELECT model FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int) $p['product_id'] . "'")->row : [];
            $options = $product ? $this->options($p['product_id'], $p['option_value_ids'], $store['language_id']) : [];
            $name = $p['name'];
            if (count($options) < count($p['option_value_ids']) && $p['variant'] !== '') {
                $name .= ' (' . $p['variant'] . ')';
            }
            if (!$product) {
                $notes[] = sprintf($t['order_unknown_product'], $p['name'], $p['external_id']);
            }
            $products[] = [
                'product_id' => $product ? (int) $p['product_id'] : 0,
                'name' => $name,
                'model' => $product ? $product['model'] : '',
                'option' => $options,
                'download' => [],
                'quantity' => $p['quantity'],
                'subtract' => 1,
                'price' => $p['price'],
                'total' => $p['total'],
                'tax' => 0,
                'reward' => 0,
            ];
        }

        $totals = [['code' => 'sub_total', 'title' => $store['text_sub_total'], 'value' => $m['sub_total'], 'sort_order' => (int) $this->config->get('total_sub_total_sort_order')]];
        if ($m['discount'] > 0) {
            $totals[] = ['code' => 'neocarts_discount', 'title' => $t['order_total_discount'], 'value' => -$m['discount'], 'sort_order' => (int) $this->config->get('total_sub_total_sort_order') + 1];
        }
        $totals[] = ['code' => 'total', 'title' => $store['text_total'], 'value' => $m['total'], 'sort_order' => (int) $this->config->get('total_total_sort_order')];

        $address = $m['shipping'];
        $order = [
            'invoice_prefix' => (string) $this->config->get('config_invoice_prefix'),
            'store_id' => 0,
            'store_name' => (string) $this->config->get('config_name'),
            'store_url' => $store['url'],
            'customer_id' => $customer ? (int) $customer['customer_id'] : 0,
            'customer_group_id' => $customer ? (int) $customer['customer_group_id'] : (int) $this->config->get('config_customer_group_id'),
            'firstname' => $m['firstname'],
            'lastname' => $m['lastname'],
            'email' => $m['email'],
            'telephone' => $m['telephone'],
            'custom_field' => [],
            'payment_method' => $m['payment_method'],
            'payment_code' => 'neocarts',
            'shipping_method' => $m['shipping_method'],
            'shipping_code' => 'neocarts.neocarts',
            'products' => $products,
            'vouchers' => [],
            'totals' => $totals,
            'comment' => $m['comment'],
            'total' => $m['total'],
            'affiliate_id' => 0,
            'commission' => 0,
            'marketing_id' => 0,
            'tracking' => '',
            'language_id' => $store['language_id'],
            'currency_id' => $store['currency_id'],
            'currency_code' => $store['currency'],
            'currency_value' => 1,
            'ip' => '',
            'forwarded_ip' => '',
            'user_agent' => 'Neocarts',
            'accept_language' => '',
        ];
        foreach (['payment', 'shipping'] as $type) {
            $order += [
                $type . '_firstname' => $address['firstname'],
                $type . '_lastname' => $address['lastname'],
                $type . '_company' => '',
                $type . '_address_1' => $address['address_1'],
                $type . '_address_2' => $address['address_2'],
                $type . '_city' => $address['city'],
                $type . '_postcode' => $address['postcode'],
                $type . '_country' => '',
                $type . '_country_id' => 0,
                $type . '_zone' => '',
                $type . '_zone_id' => 0,
                $type . '_address_format' => '',
                $type . '_custom_field' => [],
            ];
        }

        return $order;
    }

    /** order_option rows rebuilt from the external id; a vanished value gives no row. */
    private function options($productId, array $valueIds, $languageId)
    {
        if (!$valueIds) {
            return [];
        }
        $query = $this->db->query("SELECT pov.product_option_id, pov.product_option_value_id, od.name, ovd.name AS value, o.type
            FROM `" . DB_PREFIX . "product_option_value` pov
            JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id)
            LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id)
            LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = po.option_id AND od.language_id = '" . (int) $languageId . "')
            LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . (int) $languageId . "')
            WHERE pov.product_id = '" . (int) $productId . "' AND pov.product_option_value_id IN (" . implode(',', array_map('intval', $valueIds)) . ")
            ORDER BY pov.product_option_value_id");

        return array_map(function ($row) {
            return [
                'product_option_id' => (int) $row['product_option_id'],
                'product_option_value_id' => (int) $row['product_option_value_id'],
                'name' => (string) $row['name'],
                'value' => (string) $row['value'],
                'type' => (string) $row['type'],
            ];
        }, $query->rows);
    }

    private function history($orderId, $statusId, $comment)
    {
        $this->load->model('checkout/order');
        if (!$statusId) {
            $order = $this->model_checkout_order->getOrder($orderId);
            $statusId = $order ? (int) $order['order_status_id'] : 0;
        }
        $this->model_checkout_order->addOrderHistory($orderId, $statusId, $comment, (bool) $this->settings()['notify_customer'], true);
    }

    private function ocOrderId($neocartsId)
    {
        $row = $this->db->query("SELECT oc_order_id FROM `" . DB_PREFIX . "neocarts_order` WHERE neocarts_order_id = '" . $this->db->escape($neocartsId) . "'")->row;

        return $row ? (int) $row['oc_order_id'] : 0;
    }

    /** The store's defaults, never the visitor's session (a webhook has none): language, currency, totals' titles. */
    private function storeDefaults()
    {
        $value = function ($key) {
            $row = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND `key` = '" . $this->db->escape($key) . "'")->row;

            return $row ? (string) $row['value'] : '';
        };
        $code = $value('config_language');
        $currency = $value('config_currency');
        $language = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($code) . "'")->row;
        $currencyRow = $this->db->query("SELECT currency_id FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($currency) . "'")->row;

        $oc = new Language($code);
        $oc->load($code);
        $oc->load('extension/total/sub_total');
        $oc->load('extension/total/total');

        return [
            'language' => $code,
            'language_id' => $language ? (int) $language['language_id'] : (int) $this->config->get('config_language_id'),
            'currency' => $currency,
            'currency_id' => $currencyRow ? (int) $currencyRow['currency_id'] : 0,
            'url' => HTTP_SERVER,
            'text_sub_total' => $oc->get('text_sub_total'),
            'text_total' => $oc->get('text_total'),
        ];
    }
}
