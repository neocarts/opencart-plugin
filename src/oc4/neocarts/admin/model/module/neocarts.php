<?php
namespace Opencart\Admin\Model\Extension\Neocarts\Module;

require_once DIR_EXTENSION . 'neocarts/system/library/neocarts/php/autoload.php';

use Neocarts\Items;
use Neocarts\Schema;
use Neocarts\Settings;

/** Install SQL, events, settings, the product switches and product cards for the admin. docs/ai/adapter-contract.md */
class Neocarts extends \Opencart\System\Engine\Model {
	public const CODE = 'module_neocarts';
	public const PREFIX = 'module_neocarts_';
	private const BASE = 'extension/neocarts/module/neocarts';

	/** 4.0.2+ separate the method with `.`, 4.0.0–4.0.1 with `|`. */
	public static function sep(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
	}

	/** trigger => method of the catalog (catalog/…) or admin (admin/…) controller */
	private function events(): array {
		$s = self::sep();

		return [
			'catalog/view/common/header/after' => 'header',
			'catalog/view/account/account/after' => 'account',
			'admin/model/catalog/product' . $s . 'addProduct/after' => 'eventProductAdd',
			'admin/model/catalog/product' . $s . 'editProduct/after' => 'eventProductEdit',
			'admin/view/catalog/product_form/after' => 'eventProductForm',
		];
	}

	public function install(): void {
		$this->migrate();
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('neocarts');
		foreach ($this->events() as $trigger => $method) {
			$this->model_setting_event->addEvent([
				'code' => 'neocarts',
				'description' => 'Neocarts subscriptions',
				'trigger' => $trigger,
				'action' => self::BASE . self::sep() . $method,
				'status' => 1,
				'sort_order' => 0,
			]);
		}
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('neocarts');
		foreach (Schema::drop(DB_PREFIX) as $sql) {
			$this->db->query($sql);
		}
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting(self::CODE);
	}

	/** Brings the tables to Schema::VERSION; on install and whenever the settings page opens. */
	public function migrate(): void {
		$stored = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . self::PREFIX . "schema_version'");
		$from = $stored->num_rows ? (int)$stored->row['value'] : 0;
		if ($from >= Schema::VERSION) {
			return;
		}
		foreach (Schema::migrate(DB_PREFIX, $from) as $sql) {
			$this->db->query($sql);
		}
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . self::PREFIX . "schema_version'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = '" . self::CODE . "', `key` = '" . self::PREFIX . "schema_version', `value` = '" . (int)Schema::VERSION . "', `serialized` = '0'");
	}

	public function stored(): array {
		$this->load->model('setting/setting');
		$stored = [];
		foreach ($this->model_setting_setting->getSetting(self::CODE) as $key => $value) {
			$stored[substr($key, strlen(self::PREFIX))] = $value;
		}

		return $stored;
	}

	public function settings(): array {
		return Settings::resolve(4, $this->stored(), (string)$this->config->get('config_theme'));
	}

	/** Saves the form; an empty key or secret keeps the stored one. */
	public function save(array $post): void {
		// OpenCart's Request runs htmlspecialchars over every POST value: undo it, selectors and CSS need `>`
		array_walk_recursive($post, function (&$value) {
			$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		});
		$stored = $this->stored();
		$data = [self::PREFIX . 'schema_version' => $stored['schema_version'] ?? Schema::VERSION];
		foreach (Settings::defaults(4) as $key => $default) {
			$value = $post[self::PREFIX . $key] ?? (is_array($default) ? [] : (is_int($default) ? 0 : ''));
			if (in_array($key, ['api_key', 'webhook_secret'], true) && trim((string)$value) === '') {
				$value = $stored[$key] ?? '';
			}
			$data[self::PREFIX . $key] = is_array($value) ? $value : trim((string)$value);
		}
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting(self::CODE, $data);
	}

	/** The merchant's «subscription off» switches of one product (external ids). */
	public function offIds(int $productId): array {
		return array_column($this->db->query("SELECT `external_id` FROM `" . DB_PREFIX . "neocarts_product` WHERE `product_id` = '" . $productId . "'")->rows, 'external_id');
	}

	public function setOffIds(int $productId, array $externalIds): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_product` WHERE `product_id` = '" . $productId . "'");
		foreach (array_unique($externalIds) as $id) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "neocarts_product` SET `external_id` = '" . $this->db->escape((string)$id) . "', `product_id` = '" . $productId . "', `updated_at` = UTC_TIMESTAMP()");
		}
	}

	/** Values of the product's choice options (select/radio/checkbox/image): [{product_option_value_id, name, value}] */
	public function choiceValues(int $productId): array {
		$lang = (int)$this->config->get('config_language_id');

		return $this->db->query("SELECT `pov`.`product_option_value_id`, `od`.`name`, `ovd`.`name` AS `value` FROM `" . DB_PREFIX . "product_option_value` `pov` LEFT JOIN `" . DB_PREFIX . "product_option` `po` ON (`po`.`product_option_id` = `pov`.`product_option_id`) LEFT JOIN `" . DB_PREFIX . "option` `o` ON (`o`.`option_id` = `po`.`option_id`) LEFT JOIN `" . DB_PREFIX . "option_description` `od` ON (`od`.`option_id` = `o`.`option_id` AND `od`.`language_id` = '" . $lang . "') LEFT JOIN `" . DB_PREFIX . "option_value_description` `ovd` ON (`ovd`.`option_value_id` = `pov`.`option_value_id` AND `ovd`.`language_id` = '" . $lang . "') WHERE `pov`.`product_id` = '" . $productId . "' AND `o`.`type` IN ('select', 'radio', 'checkbox', 'image') ORDER BY `po`.`product_option_id`, `pov`.`product_option_value_id`")->rows;
	}

	// ---- products -------------------------------------------------------------------------------

	/** The card (never the price) for PUT /v1/shop/products/{product_id}, or null when the product is gone. */
	public function card(int $productId): ?array {
		$product = $this->db->query("SELECT `p`.`image`, `pd`.`name` FROM `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`pd`.`product_id` = `p`.`product_id` AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE `p`.`product_id` = '" . $productId . "'")->row;
		if (!$product) {
			return null;
		}
		$this->load->model('tool/image');
		$image = is_file(DIR_IMAGE . $product['image']) ? $product['image'] : 'placeholder.png';

		return Items::card(
			html_entity_decode((string)$product['name'], ENT_QUOTES, 'UTF-8'),
			null,
			$this->model_tool_image->resize($image, (int)$this->config->get('config_image_product_width'), (int)$this->config->get('config_image_product_height')),
			HTTP_CATALOG . 'index.php?route=product/product&product_id=' . $productId
		);
	}
}
