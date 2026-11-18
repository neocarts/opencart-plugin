<?php
// Neocarts plugin texts, English. Every key here must exist in every other language file
// (tests/php/LangTest.php). Storefront texts (`sf_*`) go to the page through the inline config.
return [
    'heading_title' => 'Neocarts — subscriptions',
    'text_extension' => 'Extensions',
    'text_success' => 'Settings saved.',
    'text_enabled' => 'Enabled',
    'text_disabled' => 'Disabled',
    'text_yes' => 'Yes',
    'text_no' => 'No',
    'text_none' => '— do not change —',
    'button_save' => 'Save',
    'button_back' => 'Back',
    'error_permission' => 'You do not have permission to modify Neocarts.',

    // tabs
    'tab_connection' => 'Connection',
    'tab_placement' => 'Placement',
    'tab_checkout' => 'Checkout',
    'tab_orders' => 'Orders',

    // connection
    'entry_status' => 'Status',
    'entry_shop_id' => 'Shop ID',
    'help_shop_id' => 'Neocarts panel → Settings → Integration → «Shop ID».',
    'entry_api_key' => 'API key',
    'help_api_key' => 'nc_live_… from the same tab. Shown once there; stored here and never displayed again. Leave empty to keep the current key.',
    'text_api_key_set' => 'A key is saved',
    'entry_webhook_secret' => 'Webhook signing secret',
    'help_webhook_secret' => 'whsec_… from «Webhooks» on the Integration tab. Leave empty to keep the current secret.',
    'entry_api_url' => 'Shop API address',
    'help_api_url' => 'Change only for staging, as given by Neocarts support.',
    'entry_webhook_url' => 'Webhook URL',
    'help_webhook_url' => 'Paste it into the Neocarts panel: Settings → Integration → «Webhook URL». Production accepts https:// only.',
    'button_check' => 'Test connection',
    'text_check_ok' => 'The key works.',
    'text_check_unauthorized' => 'The key is wrong or revoked.',
    'text_check_unreachable' => 'The Shop API cannot be reached from this server.',
    'text_check_unexpected' => 'Unexpected answer from the Shop API — see the log.',
    'text_version' => 'Plugin version',
    'button_neocarts_admin' => 'Open the Neocarts panel',
    'warning_maintenance' => 'The store is in maintenance mode: Neocarts webhooks cannot reach it until it is switched off.',

    // placement
    'legend_product' => 'Product page',
    'legend_cards' => 'Product cards (listings and modules)',
    'legend_cart' => 'Cart lines',
    'entry_anchor' => 'Anchor (CSS selector)',
    'entry_position' => 'Position',
    'entry_class' => 'Extra CSS class',
    'entry_card_selector' => 'Card (CSS selector)',
    'entry_card_id_regex' => 'Product id in the card (regex, group 1)',
    'entry_cart_row_selector' => 'Cart line (CSS selector)',
    'entry_cart_key_regex' => 'Cart line key in the row (regex, group 1)',
    'entry_card_pages' => 'Show on pages',
    'text_position_before' => 'Before the anchor',
    'text_position_after' => 'After the anchor',
    'text_position_prepend' => 'Inside, at the start',
    'text_position_append' => 'Inside, at the end',
    'text_page_home' => 'Home',
    'text_page_category' => 'Category',
    'text_page_search' => 'Search',
    'text_page_special' => 'Specials',
    'text_page_manufacturer' => 'Brand',
    'text_page_product' => 'Product page (related products)',
    'text_page_other' => 'Other pages',
    'help_placement' => 'Defaults fit the default theme. For another theme point the selectors at its markup.',
    'help_options' => 'Products with required options get a button only in the cart, where the chosen options are known.',

    // checkout
    'entry_checkout_intercept' => 'Send carts with a subscription to Neocarts checkout',
    'help_checkout_intercept' => 'Clicking «Checkout» opens Neocarts checkout when the cart has at least one subscription. Carts without subscriptions use the store\'s checkout.',
    'entry_checkout_link_selector' => 'Checkout links (CSS selector)',
    'entry_checkout_banner' => 'Banner on the checkout page',
    'entry_button_checkout_url' => '«Go to checkout» in the subscribe window',
    'entry_auto_add' => 'Add a product to the cart when the shopper subscribes',

    // orders
    'entry_orders_widget' => 'Create an order for a paid Neocarts checkout',
    'entry_orders_renewal' => 'Create an order for every subscription renewal',
    'entry_status_paid' => 'Status: paid',
    'entry_status_manual' => 'Status: payment confirmed by hand',
    'entry_status_cancelled' => 'Status: cancelled',
    'entry_status_refunded' => 'Status: refunded',
    'entry_status_partially_refunded' => 'Status: partially refunded',
    'entry_notify_customer' => 'Email the customer on status changes',

    // subscriptions

    // products
    'entry_product_sync' => 'Send the product card to Neocarts when a product is saved',
    'help_product_sync' => 'Title, variant, image and link. The price is never sent automatically.',
    'heading_product_panel' => 'Neocarts subscriptions',

    // log
    'entry_debug' => 'Debug log',

    // orders created from webhooks
    'order_comment' => 'Neocarts №%1$s · %2$s · %3$s',
    'order_source_widget' => 'subscription checkout',
    'order_source_renewal' => 'subscription renewal',
    'order_confirmed_manually' => 'Payment confirmed by hand in the Neocarts panel.',
    'order_refunded' => 'Neocarts refund: %s.',
    'order_cancelled' => 'Cancelled in Neocarts.',
    'order_unknown_product' => 'Product «%s» (%s) was not found in the store; the line was added by name.',
    'payment_method' => 'Neocarts',
    'delivery_nova_poshta' => 'Nova Poshta',
    'delivery_ukr_poshta' => 'Ukrposhta',
    'delivery_type_branch' => 'branch',
    'delivery_type_parcel_locker' => 'parcel locker',
    'delivery_type_address' => 'courier',
    'address_apartment' => 'apt. %s',
    'address_floor' => 'floor %s',
    'account_subscriptions' => 'My subscriptions',

    // storefront (inline config)
    'sf_checkout_banner' => 'Your cart has a subscription: it is checked out through Neocarts.',
    'sf_checkout_button' => 'Check out the subscription',
    'sf_busy' => 'Placing the order…',
    'sf_error' => 'Could not place the order. Please try again.',
    'sf_not_ready' => 'Subscriptions are temporarily unavailable. Please try again later.',
    'sf_order_missing' => 'The order is no longer available — please check out again.',

    // errors from building the order (Items::build)
    'error_cart_empty' => 'The cart is empty.',
    'error_too_many_lines' => 'A subscription order can hold at most 50 lines.',
    'error_quantity' => '«%s»: at most 9 units per line in a subscription order.',
    'error_price' => '«%s»: the price is out of range.',
    'error_interval' => '«%s»: invalid subscription interval.',
    'error_no_subscription' => 'The cart has no subscription.',

    // admin menu, order totals, list filters (added by the 2.3 adapter, used by all)
    'order_total_discount' => 'Subscriber discount',
    'button_cancel' => 'Cancel',
    'text_auto' => 'Automatic',

    // support (Neocarts\Links)
    'text_support' => 'Questions or problems? Write to Neocarts support: %s',
    'text_support_short' => 'Neocarts support',
    'text_docs' => 'Plugin documentation',
    'text_check_support' => 'If it does not help, write to %s and attach the log.',

    // per-product switch, availability, reprice price, custom CSS
    'entry_product_subscription' => 'Subscription available for this product',
    'help_product_subscription' => 'On by default for every product. Off: no subscribe button, and the product is always sold one-time. Products out of stock get no button either.',
    'entry_custom_css' => 'Custom CSS',
    'help_custom_css' => 'Added after the plugin\'s styles on every page, e.g. neocarts-button.neocarts-oc-button { margin-top: 16px }',

    // the product block
    'text_neocarts_panel_link' => 'Subscriptions and their prices are managed in the Neocarts panel',
    'entry_account_link' => '«My subscriptions» link in the customer account',

    // themes: presets, fallbacks, quick view, mini-cart, one-click, manual slot
    'text_theme_preset' => 'Store theme «%s»: its selectors are used wherever you kept the default values.',
    'text_theme_no_preset' => 'Store theme «%s»: no preset, the default-theme selectors are used. Adjust them below if a button is missing or misplaced.',
    'help_selector_fallback' => 'Several selectors separated by «||» are tried in order; the first that matches is used.',
    'entry_quickview_anchor' => 'Quick view window: anchor (CSS selector)',
    'help_quickview_anchor' => 'If the theme opens products in a pop-up window. Empty — no button there.',
    'entry_minicart_selector' => 'Mini-cart (CSS selector)',
    'entry_minicart_url' => 'Mini-cart refresh address',
    'help_minicart_url' => 'Empty — the store\'s standard one.',
    'entry_oneclick_selector' => '«Buy in one click» buttons (CSS selector)',
    'help_oneclick_selector' => 'Hidden while the shopper has a subscription chosen: such orders skip the cart and could not be a subscription.',
    'entry_checkout_routes' => 'Checkout pages (routes, comma-separated)',
    'help_slot' => 'Any place of a theme, an HTML module or a page builder: <div data-neocarts-slot data-product-id="42"></div> gets the button of product 42.',

    // the box around each button
    'entry_style' => 'Box style (inline CSS)',
    'help_style' => 'CSS of the box around the button, e.g. margin: 10px 0; — the class above goes on the same box.',
];
