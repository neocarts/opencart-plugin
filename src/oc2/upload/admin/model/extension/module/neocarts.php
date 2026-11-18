<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Client;
use Neocarts\Items;
use Neocarts\Lang;
use Neocarts\Locale;
use Neocarts\Schema;
use Neocarts\Settings;

/** Install SQL, events, settings and the product switches of the admin, OpenCart 2.3. */
class ModelExtensionModuleNeocarts extends Model {
	const CODE = 'neocarts';
	const PREFIX = 'neocarts_';
	const ROUTE = 'extension/module/neocarts';

	/** trigger => method of the admin or catalog controller extension/module/neocarts */
	private static $events = array(
		// 2.3's theme event rewrites the catalog view route to <theme>/template/<route> before `after` runs
		'catalog/view/*/template/common/header/after' => 'eventHeader',
		'catalog/view/*/template/account/account/after' => 'eventAccount',
		'admin/model/catalog/product/addProduct/after' => 'eventProductAdd',
		'admin/model/catalog/product/editProduct/after' => 'eventProductEdit',
		'admin/view/catalog/product_form/after' => 'eventProductForm',
	);

	private $settings;

	// ---- install ------------------------------------------------------------------------------

	public function install() {
		$this->migrate();
		$this->load->model('extension/event');
		$this->model_extension_event->deleteEvent(self::CODE);
		foreach (self::$events as $trigger => $method) {
			$this->model_extension_event->addEvent(self::CODE, $trigger, self::ROUTE . '/' . $method);
		}
	}

	public function uninstall() {
		foreach (Schema::drop(DB_PREFIX) as $sql) {
			$this->db->query($sql);
		}
		$this->load->model('extension/event');
		$this->model_extension_event->deleteEvent(self::CODE);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . self::CODE . "'");
	}

	/** Brings the tables to Schema::VERSION; run on install and whenever the settings page opens. */
	public function migrate() {
		$stored = $this->raw();
		$from = isset($stored['schema_version']) ? (int)$stored['schema_version'] : 0;
		if ($from >= Schema::VERSION) {
			return;
		}
		foreach (Schema::migrate(DB_PREFIX, $from) as $sql) {
			$this->db->query($sql);
		}
		$this->put('schema_version', Schema::VERSION);
	}

	// ---- settings -----------------------------------------------------------------------------

	/** Stored values without the prefix, as strings. */
	private function raw() {
		$stored = array();
		$query = $this->db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = '" . self::CODE . "'");
		foreach ($query->rows as $row) {
			$stored[substr($row['key'], strlen(self::PREFIX))] = $row['value'];
		}

		return $stored;
	}

	private function put($key, $value) {
		$key = $this->db->escape(self::PREFIX . $key);
		$value = (string)$value;
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `key` = '" . $key . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = '" . self::CODE . "', `key` = '" . $key . "', `value` = '" . $this->db->escape($value) . "', serialized = '0'");
	}

	/** The store theme directory (2.3: `<config_theme>_directory`), for Settings::resolve() presets. */
	public function theme() {
		$code = (string)$this->config->get('config_theme');

		return (string)$this->config->get(($code ?: 'theme_default') . '_directory');
	}

	public function settings() {
		if ($this->settings === null) {
			$this->settings = Settings::resolve(2, $this->raw(), $this->theme());
		}

		return $this->settings;
	}

	/** The form's POST: every key of Settings::defaults(); an empty key or secret keeps the stored one. */
	public function save(array $post) {
		$stored = $this->settings();
		foreach (Settings::defaults(2) as $key => $default) {
			$value = isset($post[self::PREFIX . $key]) ? (string)$post[self::PREFIX . $key] : (string)$default;
			if (($key === 'api_key' || $key === 'webhook_secret') && trim($value) === '') {
				$value = $stored[$key];
			}
			// OpenCart's Request HTML-escapes $_POST: selectors, regexes and CSS must be stored as typed
			$this->put($key, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
		}
		$this->settings = null;
	}

	public function client() {
		$s = $this->settings();

		return new Client($s['api_url'], $s['api_key'], Locale::resolve((string)$this->config->get('config_admin_language')), 'opencart ' . VERSION);
	}

	// ---- products -----------------------------------------------------------------------------

	private function product($product_id) {
		$query = $this->db->query("SELECT p.product_id, p.image, p.price, p.tax_class_id, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' WHERE p.product_id = '" . (int)$product_id . "'");

		return $query->num_rows ? $query->row : null;
	}

	/** The card for PUT /products/{external_id} — never a price. */
	private function card(array $product, $variant) {
		$this->load->model('tool/image');
		$image = $product['image'] && is_file(DIR_IMAGE . $product['image']) ? $product['image'] : 'placeholder.png';
		$catalog = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

		return Items::card(
			(string)$product['name'],
			$variant !== null && $variant !== '' ? $variant : null,
			html_entity_decode((string)$this->model_tool_image->resize($image, 228, 228), ENT_QUOTES, 'UTF-8'),
			$catalog . 'index.php?route=product/product&product_id=' . (int)$product['product_id']
		);
	}

	/** The product card (variants get theirs from the checkout); a failure is logged, never thrown. */
	public function syncProduct($product_id) {
		$product = $this->product($product_id);
		if (!$product) {
			return;
		}
		try {
			$this->client()->putProduct((string)(int)$product_id, $this->card($product, null));
		} catch (\Exception $e) {
			$this->log->write('Neocarts putProduct ' . (int)$product_id . ': ' . $e->getMessage());
		}
	}

	/** The switched-off ids of a product: `42` and/or `42-<product_option_value_id>`. */
	public function offIds($product_id) {
		return array_column($this->db->query("SELECT external_id FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id = '" . (int)$product_id . "'")->rows, 'external_id');
	}

	public function setOffIds($product_id, array $ids) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id = '" . (int)$product_id . "'");
		foreach ($ids as $id) {
			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "neocarts_product` SET external_id = '" . $this->db->escape((string)$id) . "', product_id = '" . (int)$product_id . "', updated_at = UTC_TIMESTAMP()");
		}
	}

	/** Every value of the product's choice options: [{id: "42-5", label: "Radio: Red"}]. */
	public function choiceValues($product_id) {
		$language = (int)$this->config->get('config_language_id');
		$rows = $this->db->query("SELECT pov.product_option_value_id, od.name AS option_name, ovd.name AS value_name
			FROM `" . DB_PREFIX . "product_option_value` pov
			JOIN `" . DB_PREFIX . "product_option` po ON po.product_option_id = pov.product_option_id
			JOIN `" . DB_PREFIX . "option` o ON o.option_id = po.option_id
			LEFT JOIN `" . DB_PREFIX . "option_description` od ON od.option_id = o.option_id AND od.language_id = '" . $language . "'
			LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . $language . "'
			WHERE pov.product_id = '" . (int)$product_id . "' AND o.type IN ('select', 'radio', 'checkbox', 'image')
			ORDER BY o.sort_order, po.product_option_id, pov.product_option_value_id")->rows;
		$values = array();
		foreach ($rows as $row) {
			$values[] = array(
				'id' => (int)$product_id . '-' . (int)$row['product_option_value_id'],
				'label' => html_entity_decode($row['option_name'] . ': ' . $row['value_name'], ENT_QUOTES, 'UTF-8'),
			);
		}

		return $values;
	}

	public function texts() {
		return Lang::load((string)$this->config->get('config_admin_language'));
	}
}
