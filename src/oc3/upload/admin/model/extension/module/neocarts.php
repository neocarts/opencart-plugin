<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Client;
use Neocarts\Items;
use Neocarts\Locale;
use Neocarts\Schema;
use Neocarts\Settings;

/** Install SQL, events, settings and the product switches. docs/ai/adapter-contract.md */
class ModelExtensionModuleNeocarts extends Model
{
    const CODE = 'module_neocarts';
    const BRANCH = 3;

    /** trigger => action (code `neocarts`) */
    private static $events = [
        'catalog/view/common/header/after' => 'extension/module/neocarts/eventHeader',
        'catalog/view/account/account/after' => 'extension/module/neocarts/eventAccount',
        'admin/model/catalog/product/addProduct/after' => 'extension/module/neocarts/eventProductSave',
        'admin/model/catalog/product/editProduct/after' => 'extension/module/neocarts/eventProductSave',
        'admin/view/catalog/product_form/after' => 'extension/module/neocarts/eventProductForm',
    ];

    public function install()
    {
        $this->migrate(true);
        $this->registerEvents();
    }

    public function uninstall()
    {
        foreach (Schema::drop(DB_PREFIX) as $sql) {
            $this->db->query($sql);
        }
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('neocarts');
    }

    /** Idempotent: re-registering on every settings page open heals events after an upgrade. */
    public function registerEvents()
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('neocarts');
        foreach (self::$events as $trigger => $action) {
            $this->model_setting_event->addEvent('neocarts', $trigger, $action);
        }
    }

    public function migrate($force = false)
    {
        $stored = $this->stored();
        $from = $force || !isset($stored['schema_version']) ? 0 : (int) $stored['schema_version'];
        if ($from >= Schema::VERSION) {
            return;
        }
        foreach (Schema::migrate(DB_PREFIX, $from) as $sql) {
            $this->db->query($sql);
        }
        $this->setValue('schema_version', Schema::VERSION);
    }

    public function setValue($key, $value)
    {
        $key = $this->db->escape(self::CODE . '_' . $key);
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND `key` = '" . $key . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = '" . self::CODE . "', `key` = '" . $key . "', `value` = '" . $this->db->escape((string) $value) . "', serialized = 0");
    }

    /** Stored values straight from the table (config may be stale within this request). */
    public function stored()
    {
        $stored = [];
        $query = $this->db->query("SELECT `key`, `value`, serialized FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND `code` = '" . self::CODE . "'");
        foreach ($query->rows as $row) {
            $stored[substr($row['key'], strlen(self::CODE) + 1)] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
        }

        return $stored;
    }

    public function settings()
    {
        return Settings::resolve(self::BRANCH, $this->stored(), $this->theme());
    }

    /** The store theme's directory (`theme_<config_theme>_directory`), for its selector preset. */
    public function theme()
    {
        return (string) $this->config->get('theme_' . $this->config->get('config_theme') . '_directory');
    }

    public function client(array $s)
    {
        return new Client($s['api_url'], $s['api_key'], Locale::resolve((string) $this->config->get('config_admin_language')), 'opencart ' . VERSION);
    }

    /** The product card (never the price) for putProduct(). */
    public function card($productId)
    {
        $row = $this->db->query("SELECT p.image, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int) $this->config->get('config_language_id') . "') WHERE p.product_id = '" . (int) $productId . "'")->row;
        if (!$row) {
            return null;
        }
        $this->load->model('tool/image');
        $image = $this->model_tool_image->resize($row['image'] && is_file(DIR_IMAGE . $row['image']) ? $row['image'] : 'no_image.png', 500, 500);
        $url = (defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG) . 'index.php?route=product/product&product_id=' . (int) $productId;

        return Items::card(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8'), null, $image, $url);
    }

    // ---- product switches (neocarts_product) ----------------------------------------------------

    /** The product's switched-off external ids (`42`, `42-17`). */
    public function offIds($productId)
    {
        return array_column($this->db->query("SELECT external_id FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id = '" . (int) $productId . "'")->rows, 'external_id');
    }

    public function setOffIds($productId, array $externalIds)
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "neocarts_product` WHERE product_id = '" . (int) $productId . "'");
        foreach (array_unique($externalIds) as $id) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "neocarts_product` SET external_id = '" . $this->db->escape((string) $id) . "', product_id = '" . (int) $productId . "', updated_at = UTC_TIMESTAMP()");
        }
    }

    /** Every value of the product's choice options: [external_id => «Option: value»] */
    public function choiceValues($productId)
    {
        $lang = (int) $this->config->get('config_language_id');
        $query = $this->db->query("SELECT pov.product_option_value_id, od.name, ovd.name AS value
            FROM `" . DB_PREFIX . "product_option_value` pov
            JOIN `" . DB_PREFIX . "option` o ON (o.option_id = pov.option_id)
            LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = pov.option_id AND od.language_id = '" . $lang . "')
            LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . $lang . "')
            WHERE pov.product_id = '" . (int) $productId . "' AND o.type IN ('select', 'radio', 'checkbox', 'image')
            ORDER BY o.sort_order, pov.product_option_id, pov.product_option_value_id");
        $values = [];
        foreach ($query->rows as $row) {
            $values[(int) $productId . '-' . $row['product_option_value_id']] = $row['name'] . ': ' . $row['value'];
        }

        return $values;
    }
}
