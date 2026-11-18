<?php
namespace Neocarts;

/**
 * Every plugin setting with its default — the one list all three admin forms, the storefront config
 * and the docs page `plugins/opencart/settings` follow. Deliberately small: what can be done in the
 * Neocarts panel (subscriptions, prices, payments, webhook log, team notifications) is not here —
 * docs/ai/decisions.md «Minimal admin». Keys are stored with the branch prefix
 * (`neocarts_` in 2.3, `module_neocarts_` in 3.0/4.x).
 */
final class Settings
{
    /** OpenCart's default order status ids (the same in 2.3, 3.0 and 4.x sample data). */
    const STATUS_PROCESSING = 2;
    const STATUS_CANCELED = 7;
    const STATUS_REFUNDED = 11;

    /** Pages whose product cards can carry a subscribe button: setting key => OpenCart route. */
    const CARD_PAGES = [
        'card_on_home' => 'common/home',
        'card_on_category' => 'product/category',
        'card_on_search' => 'product/search',
        'card_on_special' => 'product/special',
        'card_on_manufacturer' => 'product/manufacturer',
        'card_on_product' => 'product/product',
        'card_on_other' => '*',
    ];

    const POSITIONS = ['before', 'after', 'prepend', 'append'];

    /** @param int $branch 2, 3 or 4 — the default theme markup differs */
    public static function defaults(int $branch): array
    {
        return [
            // Connection
            'status' => 0,
            'shop_id' => '',
            'api_key' => '',
            'webhook_secret' => '',
            'api_url' => Client::DEFAULT_URL,

            // Placement: the product page
            'product_status' => 1,
            'product_anchor' => '#button-cart',
            'product_position' => 'after',
            'product_class' => '',
            // inline CSS of the box around the button (class above goes on that box too)
            'product_style' => 'margin: 10px 0;',

            // Placement: product cards (listings and modules)
            'card_selector' => '.product-thumb',
            'card_id_regex' => '(?:cart\.add\(\'|addToCart\(\'|data-product-id="|name="product_id" value=")(\d+)',
            // inside the card, under the price: after the button row it falls outside the card's border
            // (2.3/3.0) or under the absolutely placed buttons (4.x) — checked visually
            'card_anchor' => $branch >= 4 ? '.description' : '.caption',
            'card_position' => 'append',
            'card_class' => '',
            'card_style' => 'margin: 10px 0;',
            'card_on_home' => 1,
            'card_on_category' => 1,
            'card_on_search' => 1,
            'card_on_special' => 1,
            'card_on_manufacturer' => 1,
            'card_on_product' => 1,
            'card_on_other' => 1,

            // Placement: cart lines
            'cart_status' => 1,
            'cart_row_selector' => $branch >= 4 ? '#shopping-cart tbody tr' : '#content form tbody tr',
            'cart_key_regex' => '(?:quantity\[|name="key" value=")(\d+)',
            'cart_anchor' => 'td:nth-child(2)',
            'cart_position' => 'append',
            'cart_class' => '',
            'cart_style' => 'margin-top: 8px;',

            // Placement: quick-view windows (follow product_*), the mini-cart refreshed after auto add,
            // one-click order buttons hidden while a subscription is chosen
            'quickview_anchor' => '',
            'minicart_selector' => '#cart || #header-cart',
            'minicart_url' => '',
            'oneclick_selector' => '',

            // Look: added after the plugin's base styles, for theme tweaks
            'custom_css' => '',

            // Checkout
            'checkout_intercept' => 1,
            // Simple and one-page checkouts are common in CIS shops (docs/ai/themes.md)
            'checkout_link_selector' => 'a[href*="checkout/checkout"], a[href*="checkout/simplecheckout"], a[href*="checkout/nicocheckout"]',
            'checkout_routes' => 'checkout/checkout,checkout/simplecheckout,checkout/nicocheckout',
            'checkout_banner_status' => 1,
            'checkout_banner_anchor' => '#content > h1 || #content h1 || #accordion || h1',
            'checkout_banner_position' => 'after',
            'button_checkout_url' => 1,
            'auto_add' => 1,
            'account_link' => 1,

            // Orders from webhooks + product cards
            'orders_widget' => 1,
            'orders_renewal' => 1,
            'status_paid' => self::STATUS_PROCESSING,
            'status_manual' => self::STATUS_PROCESSING,
            'status_cancelled' => self::STATUS_CANCELED,
            'status_refunded' => self::STATUS_REFUNDED,
            'status_partially_refunded' => 0,
            'notify_customer' => 0,
            'product_sync' => 1,

            // Diagnostics: webhook and Shop API errors to the store's error log
            'debug' => 0,
        ];
    }

    /**
     * Selector sets for third-party themes, keyed by the theme directory (2.3/3.0) or the theme
     * extension code (4.x). Researched in docs/ai/themes.md; each one checked on the demo stand.
     * Only the keys a theme needs; `a || b` = try a, then b.
     */
    const PRESETS = [
        'materialize' => [
            'card_selector' => '.card.sticky-action, .card.hoverable',
            'card_anchor' => '.card-action',
            'card_position' => 'append',
            'cart_row_selector' => 'form .card.horizontal',
            'cart_anchor' => '.card-content',
            'cart_position' => 'append',
            'checkout_banner_anchor' => '#accordion',
            'checkout_banner_position' => 'before',
            'minicart_selector' => '#modal-cart-list || #cart',
        ],
        'xezpro' => [
            'card_selector' => '.product',
            'card_anchor' => '.actions',
            'card_position' => 'append',
            'quickview_anchor' => '#colorbox #button-cart',
            'checkout_link_selector' => 'a[href*="checkout/checkout"], a[href*="checkout/nicocheckout"], #buynow',
            'minicart_selector' => '.cart || #cart',
        ],
        'ff_frame' => [
            'product_anchor' => '#product .form-row:has(#button-cart) || #button-cart',
            'card_selector' => '.product-item',
            'card_anchor' => '.product-controls',
            'card_position' => 'append',
            'quickview_anchor' => '#ff_modal_qview [id^="qv-button-cart-"]',
            'checkout_banner_anchor' => '#content > h1 || #simplecheckout_form_0',
            'minicart_selector' => '#ff_cart',
            'minicart_url' => 'index.php?route=extension/module/framefreetheme/ff_cart/info',
        ],
        'mintleaf' => [
            'card_anchor' => '.module-product-btn-cart',
            'card_position' => 'after',
            'checkout_banner_anchor' => '#accordion',
            'checkout_banner_position' => 'before',
        ],
        'luxury' => [
            'card_selector' => '.product-thumb, .product-item',
            'checkout_banner_anchor' => '#accordion || .page-head',
            'checkout_banner_position' => 'before',
        ],
        'befree' => [
            'product_anchor' => '#form-product .input-group:has(> #button-cart) || #button-cart',
        ],
        'dc_toys' => [
            'product_anchor' => '.product-cart-row || #button-cart',
            'card_selector' => '.product-card',
            'card_anchor' => '.product-card-price-row',
            'card_position' => 'after',
        ],
        'pn_black' => [
            'product_anchor' => '.pn-add-row || #button-cart',
            'card_selector' => 'article.product-card',
            'card_anchor' => '.product-card-info',
            'card_position' => 'append',
        ],
    ];

    /** Theme directory/code aliases → the preset key. */
    const PRESET_ALIASES = ['framefree' => 'ff_frame', 'mt_materialize' => 'materialize'];

    /** The preset key for a store theme, or null (the default theme and unknown themes). */
    public static function preset(string $theme)
    {
        $theme = strtolower(trim($theme));
        $theme = isset(self::PRESET_ALIASES[$theme]) ? self::PRESET_ALIASES[$theme] : $theme;

        return isset(self::PRESETS[$theme]) ? $theme : null;
    }

    /**
     * Stored values over defaults; unknown keys dropped, JSON-ish values decoded.
     * With a known $theme its preset replaces every default the merchant left as it was
     * (missing or saved equal to the plain default), so the merchant's own choices always win.
     */
    public static function resolve(int $branch, array $stored, string $theme = ''): array
    {
        $settings = self::defaults($branch);
        $preset = self::preset($theme);
        if ($preset) {
            foreach (self::PRESETS[$preset] as $key => $value) {
                if (!array_key_exists($key, $stored) || trim((string) $stored[$key]) === (string) $settings[$key]) {
                    $stored[$key] = $value;
                }
            }
        }
        foreach ($settings as $key => $default) {
            if (!array_key_exists($key, $stored)) {
                continue;
            }
            $value = $stored[$key];
            if (is_array($default)) {
                $value = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
            } elseif (is_int($default)) {
                $value = (int) $value;
            } else {
                $value = trim((string) $value);
            }
            if (substr($key, -9) === '_position' && !in_array($value, self::POSITIONS, true)) {
                $value = $default;
            }
            $settings[$key] = $value;
        }

        return $settings;
    }

    /** Which card toggle governs a page route. */
    public static function cardsOn(array $settings, string $route): bool
    {
        $route = str_replace(['|', '.'], '/', $route); // 4.0 uses `|`, 4.1 `.` for methods
        foreach (self::CARD_PAGES as $key => $page) {
            if ($page !== '*' && strpos($route, $page) === 0) {
                return (bool) $settings[$key];
            }
        }

        return (bool) $settings['card_on_other'];
    }

    /** The storefront part only: never the key or the secret. */
    public static function storefront(array $settings): array
    {
        $keys = [
            'product_status', 'product_anchor', 'product_position', 'product_class', 'product_style',
            'card_selector', 'card_id_regex', 'card_anchor', 'card_position', 'card_class', 'card_style',
            'cart_status', 'cart_row_selector', 'cart_key_regex', 'cart_anchor', 'cart_position', 'cart_class', 'cart_style',
            'checkout_intercept', 'checkout_link_selector', 'checkout_banner_status', 'checkout_banner_anchor',
            'checkout_banner_position', 'button_checkout_url', 'auto_add', 'custom_css', 'checkout_routes',
            'quickview_anchor', 'minicart_selector', 'minicart_url', 'oneclick_selector',
        ];

        return array_intersect_key($settings, array_flip($keys));
    }
}
