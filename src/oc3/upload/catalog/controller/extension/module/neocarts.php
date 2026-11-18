<?php
require_once DIR_SYSTEM . 'library/neocarts/php/autoload.php';

use Neocarts\ApiError;
use Neocarts\Items;
use Neocarts\Lang;
use Neocarts\Links;
use Neocarts\Locale;
use Neocarts\Money;
use Neocarts\Settings;
use Neocarts\Webhook;

/** Storefront half of the 3.0 adapter: header/account events and the JSON routes. docs/ai/adapter-contract.md */
class ControllerExtensionModuleNeocarts extends Controller
{
    const ROUTE = 'extension/module/neocarts';

    // ---- events -----------------------------------------------------------------------------------

    /** catalog/view/common/header/after: config + widget + neocarts.js before </head> */
    public function eventHeader(&$route, &$data, &$output)
    {
        $s = $this->model()->settings();
        if (!$s['status'] || $s['shop_id'] === '' || $s['api_key'] === '' || stripos($output, '</head>') === false) {
            return;
        }
        $language = (string) $this->config->get('config_language');
        $texts = [];
        foreach (Lang::load($language) as $key => $text) {
            if (strpos($key, 'sf_') === 0) {
                $texts[$key] = $text;
            }
        }
        $page = isset($this->request->get['route']) ? (string) $this->request->get['route'] : 'common/home';
        $config = [
            'route' => $page,
            'locale' => Locale::resolve($language),
            'cardsOn' => Settings::cardsOn($s, $page),
            'settings' => Settings::storefront($s),
            'texts' => $texts,
            'urls' => [
                'products' => $this->link(self::ROUTE . '/products'),
                'cart' => $this->link(self::ROUTE . '/cart'),
                'order' => $this->link(self::ROUTE . '/order'),
                'clear' => $this->link(self::ROUTE . '/clear'),
                'cartAdd' => $this->link('checkout/cart/add'),
                'miniCart' => $this->link('common/cart/info'),
                'cartPage' => $this->link('checkout/cart'),
                'success' => $this->link('checkout/success'),
                'checkout' => $this->link('checkout/checkout'),
            ],
        ];
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html = '<script>window.NeocartsOpencart = ' . $json . ';</script>' . "\n"
            . '<script src="https://cdn.neocarts.com/bundle.js" data-shop-id="' . htmlspecialchars($s['shop_id'], ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n"
            . '<script src="' . ((!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? HTTPS_SERVER : HTTP_SERVER) . 'catalog/view/javascript/neocarts/neocarts.js?v=' . rawurlencode(NEOCARTS_VERSION . '-' . (int) @filemtime(DIR_APPLICATION . 'view/javascript/neocarts/neocarts.js')) . '" defer></script>' . "\n";
        $at = stripos($output, '</head>');
        $output = substr($output, 0, $at) . $html . substr($output, $at);
    }

    /** catalog/view/account/account/after: «My subscriptions» */
    public function eventAccount(&$route, &$data, &$output)
    {
        $s = $this->model()->settings();
        $marker = '<ul class="list-unstyled">';
        if (!$s['status'] || !$s['account_link'] || strpos($output, $marker) === false) {
            return;
        }
        $t = Lang::load((string) $this->config->get('config_language'));
        $li = '<li><a href="' . Links::CUSTOMER_URL . '" target="_blank" rel="noopener">'
            . htmlspecialchars($t['account_subscriptions'], ENT_QUOTES, 'UTF-8') . '</a></li>';
        $at = strpos($output, $marker) + strlen($marker);
        $output = substr($output, 0, $at) . $li . substr($output, $at);
    }

    // ---- JSON routes ------------------------------------------------------------------------------

    public function products()
    {
        $ids = isset($this->request->get['ids']) ? array_slice(array_unique(array_filter(array_map('intval', explode(',', (string) $this->request->get['ids'])))), 0, 100) : [];
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $products = [];
        $off = $this->model()->offIds($ids);
        $stock = $this->model()->stock($ids);
        foreach ($ids as $id) {
            $p = $this->model_catalog_product->getProduct($id);
            if (!$p) {
                continue;
            }
            $price = (float) $p['special'] ?: (float) $p['price'];
            $products[(string) $id] = [
                'external_id' => (string) $id,
                'title' => html_entity_decode($p['name'], ENT_QUOTES, 'UTF-8'),
                'variant' => null,
                'image_url' => $this->image($p['image']),
                'product_url' => $this->link('product/product', 'product_id=' . $id),
                'price' => Money::minor($this->tax->calculate($price, $p['tax_class_id'], $this->config->get('config_tax'))),
                'has_options' => $this->model()->hasRequiredOptions($id),
                'subscribable' => !Items::disabled((string) $id, $off) && isset($stock[$id]) && (int) $stock[$id] > 0,
            ];
        }
        $this->json(200, ['products' => (object) $products]);
    }

    public function cart()
    {
        $lines = [];
        foreach ($this->lines() as $line) {
            $lines[] = [
                'key' => (string) $line['key'],
                'external_id' => Items::externalId((int) $line['product_id'], $line['option']),
                'title' => html_entity_decode($line['name'], ENT_QUOTES, 'UTF-8'),
                'variant' => Items::variant($line['option']),
                'image_url' => $line['image_url'],
                'product_url' => $line['product_url'],
                'price' => Money::minor($line['unit_price']),
                'quantity' => (int) $line['quantity'],
                'subscribable' => $line['subscribable'],
            ];
        }
        $this->json(200, ['lines' => $lines]);
    }

    public function order()
    {
        $t = Lang::load((string) $this->config->get('config_language'));
        $input = json_decode((string) file_get_contents('php://input'), true);
        $selection = isset($input['selection']) && is_array($input['selection']) ? $input['selection'] : [];
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            return $this->json(405, ['message' => $t['sf_error']]);
        }
        $model = $this->model();
        $s = $model->settings();
        try {
            $items = Items::build($this->lines(), $selection);
            $client = $model->client($s);
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
                $redirect = $this->link('checkout/cart', 'neocarts=checkout');
                $order = $client->createOrder($items, $redirect);
            }
            $this->session->data['neocarts_order_id'] = (string) $order['id'];
            $this->json(200, ['orderId' => (string) $order['id']]);
        } catch (ApiError $e) {
            if (isset($t[$e->getMessage()])) {
                return $this->json(422, ['message' => sprintf($t[$e->getMessage()], isset($e->errors['name']) ? html_entity_decode($e->errors['name'], ENT_QUOTES, 'UTF-8') : '')]);
            }
            $this->log->write('Neocarts: order failed: ' . $e->status . ' ' . $e->getMessage());
            $this->json(502, ['message' => $e->status === 0 ? $t['sf_error'] : $e->getMessage()]);
        }
    }

    public function clear()
    {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            return $this->json(405, ['ok' => false]);
        }
        $this->cart->clear();
        unset($this->session->data['neocarts_order_id']);
        $this->json(200, ['ok' => true]);
    }

    public function webhook()
    {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            return $this->json(405, new \stdClass());
        }
        $model = $this->model();
        $s = $model->settings();
        $body = (string) file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_NEOCARTS_SIGNATURE']) ? (string) $_SERVER['HTTP_X_NEOCARTS_SIGNATURE'] : '';
        $status = Webhook::handle($body, $signature, $s['webhook_secret'], $model, $model);
        $error = Webhook::$lastError;
        if ($s['debug']) {
            $this->log->write('Neocarts: webhook ' . $status . ($error !== null ? ': ' . $error : '') . ': ' . substr($body, 0, 2000));
        }
        // the merchant reads the error in the Neocarts panel's webhook log
        $this->json($status, $error !== null ? ['error' => $error] : new \stdClass());
    }

    // ---- helpers ----------------------------------------------------------------------------------

    /** Cart lines normalised for Items (see Items' docblock); subscribable = not Items::disabled(), product and chosen option values in stock. */
    private function lines()
    {
        $lines = [];
        $products = $this->cart->getProducts();
        $ids = array_column($products, 'product_id');
        $off = $this->model()->offIds($ids);
        $stock = $this->model()->stock($ids);
        foreach ($products as $p) {
            $available = !Items::disabled(Items::externalId((int) $p['product_id'], $p['option']), $off) && isset($stock[$p['product_id']]) && (int) $stock[$p['product_id']] > 0;
            foreach ($p['option'] as $option) {
                if (!empty($option['subtract']) && (int) $option['quantity'] <= 0) {
                    $available = false;
                }
            }
            $lines[] = [
                'key' => $p['cart_id'],
                'product_id' => (int) $p['product_id'],
                'name' => $p['name'],
                'quantity' => (int) $p['quantity'],
                'unit_price' => $this->tax->calculate($p['price'], $p['tax_class_id'], $this->config->get('config_tax')),
                'image_url' => $this->image($p['image']),
                'product_url' => $this->link('product/product', 'product_id=' . (int) $p['product_id']),
                'option' => $p['option'],
                'subscribable' => $available,
            ];
        }

        return $lines;
    }

    /** The webhook store/sink must be the model object itself, not 3.0's loader proxy. */
    private function model()
    {
        $this->load->model(self::ROUTE);
        if (!$this->registry->has('neocarts_model')) {
            $this->registry->set('neocarts_model', new \ModelExtensionModuleNeocarts($this->registry));
        }

        return $this->registry->get('neocarts_model');
    }

    private function image($image)
    {
        $this->load->model('tool/image');
        $file = $image && is_file(DIR_IMAGE . $image) ? $image : 'placeholder.png';
        $url = $this->model_tool_image->resize($file, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width') ?: 500, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height') ?: 500);

        return html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
    }

    private function link($route, $args = '')
    {
        return html_entity_decode($this->url->link($route, $args, !empty($this->request->server['HTTPS'])), ENT_QUOTES, 'UTF-8');
    }

    private function json($status, $body)
    {
        http_response_code($status);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->setOutput(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
