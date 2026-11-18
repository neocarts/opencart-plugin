<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\Lang;
use Neocarts\Links;
use Neocarts\Settings;

/** Admin half of the 3.0 adapter: settings form, subscriptions page, admin events. docs/ai/adapter-contract.md */
class ControllerExtensionModuleNeocarts extends Controller
{
    const ROUTE = 'extension/module/neocarts';
    const CODE = 'module_neocarts';

    private $error = [];

    // ---- install ----------------------------------------------------------------------------------

    public function install()
    {
        $this->load->model(self::ROUTE);
        $this->model_extension_module_neocarts->install();
    }

    public function uninstall()
    {
        $this->load->model(self::ROUTE);
        $this->model_extension_module_neocarts->uninstall();
    }

    // ---- settings form ----------------------------------------------------------------------------

    public function index()
    {
        $this->load->model(self::ROUTE);
        $model = $this->model_extension_module_neocarts;
        $model->migrate();
        $model->registerEvents();
        $t = $this->t();

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->save($model->stored());
            $this->session->data['neocarts_success'] = $t['text_success'];
            $this->response->redirect($this->link(self::ROUTE));
        }

        $s = $model->settings();
        $this->document->setTitle($t['heading_title']);

        $this->load->model('localisation/order_status');
        $data = [
            't' => $t,
            'tabs' => $this->tabs($s, $t),
            'theme_notice' => Settings::preset($model->theme())
                ? sprintf($t['text_theme_preset'], $model->theme())
                : sprintf($t['text_theme_no_preset'], $model->theme()),
            'key_set' => $s['api_key'] !== '',
            'secret_set' => $s['webhook_secret'] !== '',
            'webhook_url' => HTTPS_CATALOG . 'index.php?route=' . self::ROUTE . '/webhook',
            'version' => NEOCARTS_VERSION,
            'maintenance' => (bool) $this->config->get('config_maintenance'),
            'admin_url' => Links::ADMIN_URL,
            'support' => $this->support(),
            'success' => isset($this->session->data['neocarts_success']) ? $this->session->data['neocarts_success'] : '',
            'error_warning' => isset($this->error['warning']) ? $this->error['warning'] : '',
            'action' => $this->link(self::ROUTE),
            'cancel' => $this->link('marketplace/extension', '&type=module'),
            'check_url' => $this->link(self::ROUTE . '/check', '', false),
            'breadcrumbs' => [
                ['text' => $t['text_extension'], 'href' => $this->link('marketplace/extension', '&type=module')],
                ['text' => $t['heading_title'], 'href' => $this->link(self::ROUTE)],
            ],
        ];
        unset($this->session->data['neocarts_success']);

        $statuses = [];
        foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
            $statuses[$status['order_status_id']] = $status['name'];
        }
        $data['statuses'] = $statuses;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view(self::ROUTE, $data));
    }

    /** Every key of Settings::defaults() gets a field; the tab order is the contract's. */
    private function tabs(array $s, array $t)
    {
        $layout = [
            'connection' => ['status' => 'status', 'shop_id' => 'text', 'api_key' => 'secret', 'webhook_secret' => 'secret', 'api_url' => 'text', 'debug' => 'bool'],
            'placement' => [
                '#legend_product' => 'legend', 'product_status' => 'status', 'product_anchor' => 'text', 'product_position' => 'position', 'product_class' => 'text', 'product_style' => 'text',
                '#legend_cards' => 'legend', 'card_selector' => 'text', 'card_id_regex' => 'text', 'card_anchor' => 'text', 'card_position' => 'position', 'card_class' => 'text', 'card_style' => 'text',
                'card_on_home' => 'bool', 'card_on_category' => 'bool', 'card_on_search' => 'bool', 'card_on_special' => 'bool',
                'card_on_manufacturer' => 'bool', 'card_on_product' => 'bool', 'card_on_other' => 'bool',
                '#legend_cart' => 'legend', 'cart_status' => 'status', 'cart_row_selector' => 'text', 'cart_key_regex' => 'text', 'cart_anchor' => 'text', 'cart_position' => 'position', 'cart_class' => 'text', 'cart_style' => 'text',
                'quickview_anchor' => 'text', 'minicart_selector' => 'text', 'minicart_url' => 'text', 'oneclick_selector' => 'text',
                'custom_css' => 'textarea',
            ],
            'checkout' => [
                'checkout_intercept' => 'bool', 'checkout_link_selector' => 'text', 'checkout_banner_status' => 'status',
                'checkout_banner_anchor' => 'text', 'checkout_banner_position' => 'position', 'button_checkout_url' => 'bool', 'auto_add' => 'bool',
                'account_link' => 'bool', 'checkout_routes' => 'text',
            ],
            'orders' => [
                'orders_widget' => 'bool', 'orders_renewal' => 'bool', 'status_paid' => 'order_status', 'status_manual' => 'order_status',
                'status_cancelled' => 'order_status', 'status_refunded' => 'order_status', 'status_partially_refunded' => 'order_status',
                'notify_customer' => 'bool', 'product_sync' => 'bool',
            ],
        ];
        // a key added to Settings later still gets a field
        $placed = [];
        foreach ($layout as $fields) {
            $placed += $fields;
        }
        foreach (array_diff_key(Settings::defaults(3), $placed) as $key => $default) {
            $layout['orders'][$key] = is_int($default) ? 'bool' : 'text';
        }

        $tabs = [];
        foreach ($layout as $id => $fields) {
            $list = [];
            foreach ($fields as $key => $type) {
                if ($type === 'legend') {
                    $list[] = ['type' => 'legend', 'label' => $t[substr($key, 1)]];
                    continue;
                }
                $list[] = [
                    'key' => $key,
                    'type' => $type,
                    'name' => self::CODE . '_' . $key,
                    'value' => is_array($s[$key]) ? '' : (string) $s[$key],
                    'label' => $this->label($key, $t),
                    'help' => isset($t['help_' . $key]) ? $t['help_' . $key] : '',
                ];
            }
            $tabs[] = ['id' => $id, 'title' => $t['tab_' . $id], 'fields' => $list];
        }

        return $tabs;
    }

    private function label($key, array $t)
    {
        if (isset($t['entry_' . $key])) {
            return $t['entry_' . $key];
        }
        if (strpos($key, 'card_on_') === 0) {
            return $t['entry_card_pages'] . ': ' . $t['text_page_' . substr($key, 8)];
        }
        if (substr($key, -7) === '_status') {
            $base = substr($key, 0, -7);

            return isset($t['entry_' . $base]) ? $t['entry_' . $base] : $t['entry_status'];
        }
        foreach (['anchor', 'position', 'class', 'style'] as $suffix) {
            if (substr($key, -strlen($suffix) - 1) === '_' . $suffix) {
                return $t['entry_' . $suffix];
            }
        }

        return $key;
    }

    private function save(array $stored)
    {
        $post = self::decode($this->request->post);
        $save = [];
        foreach (Settings::defaults(3) as $key => $default) {
            $name = self::CODE . '_' . $key;
            if (isset($post[$name])) {
                $save[$name] = trim((string) $post[$name]);
            } elseif (isset($stored[$key])) {
                $save[$name] = $stored[$key];
            }
        }
        // write-only: an empty input keeps the stored key / secret
        foreach (['api_key', 'webhook_secret'] as $key) {
            if ($save[self::CODE . '_' . $key] === '') {
                $save[self::CODE . '_' . $key] = isset($stored[$key]) ? $stored[$key] : '';
            }
        }
        $save[self::CODE . '_schema_version'] = isset($stored['schema_version']) ? $stored['schema_version'] : 0;

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(self::CODE, $save);
    }

    /** OpenCart html-escapes every request value; selectors and regexes must be stored as typed. */
    private static function decode($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'decode'], $value);
        }

        return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    }

    // ---- AJAX -------------------------------------------------------------------------------------

    public function check()
    {
        $t = $this->t();
        $this->load->model(self::ROUTE);
        $s = $this->model_extension_module_neocarts->settings();
        $result = $s['api_key'] === '' ? 'unauthorized' : $this->model_extension_module_neocarts->client($s)->check();
        $this->json([
            'status' => $result,
            'message' => $t['text_check_' . $result],
            'support' => $result === 'ok' ? '' : sprintf(htmlspecialchars($t['text_check_support'], ENT_QUOTES, 'UTF-8'), $this->mailto()),
        ]);
    }

    // ---- events -----------------------------------------------------------------------------------

    /** admin/model/catalog/product/{add,edit}Product/after: the card, never the price. */
    public function eventProductSave(&$route, &$args, &$output)
    {
        $productId = strpos($route, 'addProduct') !== false ? (int) $output : (int) $args[0];
        $this->load->model(self::ROUTE);
        $model = $this->model_extension_module_neocarts;
        // only a save from the product form carries our switches (an unchecked box is absent)
        if ($productId && !empty($this->request->post['neocarts_subscription_present'])) {
            $on = [];
            $posted = isset($this->request->post['neocarts_subscription']) && is_array($this->request->post['neocarts_subscription']) ? $this->request->post['neocarts_subscription'] : [];
            foreach (array_keys($posted) as $id) {
                // a new product's form names its switch `0`
                $on[preg_replace('/^0(?=-|$)/', (string) $productId, (string) $id)] = true;
            }
            $all = array_merge([(string) $productId], array_keys($model->choiceValues($productId)));
            $model->setOffIds($productId, array_values(array_filter($all, function ($id) use ($on) {
                return !isset($on[$id]);
            })));
        }
        $s = $model->settings();
        if (!$s['status'] || !$s['product_sync'] || $s['api_key'] === '' || !$productId) {
            return;
        }
        try {
            $card = $model->card($productId);
            if ($card) {
                $model->client($s)->putProduct((string) $productId, $card);
            }
        } catch (\Throwable $e) {
            $this->log->write('Neocarts: product ' . $productId . ' sync failed: ' . $e->getMessage());
        }
    }

    /** admin/view/catalog/product_form/after */
    public function eventProductForm(&$route, &$data, &$output)
    {
        $productId = isset($this->request->get['product_id']) ? (int) $this->request->get['product_id'] : 0;
        $this->load->model(self::ROUTE);
        $model = $this->model_extension_module_neocarts;
        if (strpos($output, '</form>') === false) {
            return;
        }
        $block = $this->load->view(self::ROUTE . '_product', [
            't' => $this->t(),
            'switches' => $this->switches($model, $productId),
            'admin_url' => Links::ADMIN_URL,
            'support' => $this->support(),
        ]);
        // inside the form: the checkbox posts with the product
        $at = strrpos($output, '</form>');
        $output = substr($output, 0, $at) . $block . substr($output, $at);
    }

    /** [{name, label, on}] — the product, then every choice option value */
    private function switches($model, $productId)
    {
        $t = $this->t();
        $off = array_flip($model->offIds($productId));
        $switches = [['id' => (string) $productId, 'label' => $t['entry_product_subscription']]];
        foreach ($model->choiceValues($productId) as $id => $label) {
            $switches[] = ['id' => $id, 'label' => $label];
        }
        foreach ($switches as $i => $switch) {
            $switches[$i]['on'] = !isset($off[$switch['id']]);
        }

        return $switches;
    }

    /** The support footer of every plugin admin page (contract: «Support and links»). */
    private function support()
    {
        $t = $this->t();

        return $this->load->view(self::ROUTE . '_support', [
            'text' => sprintf(htmlspecialchars($t['text_support'], ENT_QUOTES, 'UTF-8'), $this->mailto()),
            'docs_text' => $t['text_docs'],
            'docs_url' => Links::docs((string) $this->config->get('config_admin_language')),
        ]);
    }

    private function mailto()
    {
        return '<a href="mailto:' . Links::SUPPORT_EMAIL . '">' . Links::SUPPORT_EMAIL . '</a>';
    }

    private function t()
    {
        return Lang::load((string) $this->config->get('config_admin_language'));
    }

    private function link($route, $args = '', $html = true)
    {
        $url = $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args, true);

        return $html ? $url : str_replace('&amp;', '&', $url);
    }

    private function json(array $body)
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->error['warning'] = $this->t()['error_permission'];
        }

        return !$this->error;
    }
}
