# Adapter contract — what every branch (oc2 / oc3 / oc4) implements

The three adapters are thin and **behave identically**; everything that can be shared lives in
`src/shared/` (PHP core, `lang/`, `js/neocarts.js`). An adapter only speaks its OpenCart branch.
When you change behaviour, change it in all three in the same task.

## Where files go

| | 2.3 (`src/oc2/upload/`) | 3.0 (`src/oc3/upload/`) | 4.1 (`src/oc4/neocarts/`) |
|---|---|---|---|
| admin controller | `admin/controller/extension/module/neocarts.php` `ControllerExtensionModuleNeocarts` | same path/class | `admin/controller/module/neocarts.php` `Opencart\Admin\Controller\Extension\Neocarts\Module\Neocarts` |
| admin model (install SQL, events, product switches) | `admin/model/extension/module/neocarts.php` | same | `admin/model/module/neocarts.php` |
| admin views | `admin/view/template/extension/module/neocarts*.tpl` | `….twig` | `admin/view/template/module/neocarts*.twig` |
| catalog controller | `catalog/controller/extension/module/neocarts.php` | same | `catalog/controller/module/neocarts.php` |
| catalog model (cart lines, OC order, webhook store/sink) | `catalog/model/extension/module/neocarts.php` | same | `catalog/model/module/neocarts.php` |
| language stub (heading only) | `{admin,catalog}/language/en-gb/extension/module/neocarts.php` | same | `{admin,catalog}/language/en-gb/module/neocarts.php` |
| shared core (copied by build, linked in Docker) | `system/library/neocarts/` | same | `system/library/neocarts/` (inside the extension) |
| storefront JS | `catalog/view/javascript/neocarts/neocarts.js` | same | `catalog/view/javascript/neocarts/neocarts.js` (inside the extension) |
| manifest | `install.xml` (name/version/code only, **no** `<file>` edits) | same | `install.json` |

Load the core with `require_once` of `…/neocarts/php/autoload.php`. All texts come from
`\Neocarts\Lang::load(<language code>)` — the OpenCart language files are stubs with
`heading_title` only (needed by the extension list). Never write a text in a controller or template.

Settings: `\Neocarts\Settings::resolve($branch, $stored)` over the `setting` table, code
`neocarts` (2.3, keys `neocarts_*`) or `module_neocarts` (3.0/4.x, keys `module_neocarts_*`).
The API key and the webhook secret are write-only in the form (empty input keeps the stored value).

Posted values are HTML-escaped by OpenCart's Request: decode them (`html_entity_decode`) before
saving selectors, regexes and CSS.

## Scope: the minimal admin (the user's rule)

The plugin does in OpenCart only what cannot be done in the Neocarts panel
(https://shop.neocarts.com/ — subscriptions, «Change subscription price», payments, customers, the
webhook delivery log with «Resend», team notifications). So the admin has exactly:

1. **One settings page**, tabs Connection · Placement · Checkout · Orders (every key of
   `Settings::defaults()`), plus «Test connection», the webhook URL, the version, the maintenance
   warning and the support footer.
2. **A block on the product form**: «Subscription available» switches (the product + each value of
   every choice option) and a link to the Neocarts panel for subscriptions and prices.

No subscriptions page, menu item, dashboard tile, order-page line, reprice button, «Sync all»,
log tab, update check or failed-charge email. Do not add them back without the user.

## Routes (all JSON)

`<base>` = `extension/module/neocarts` (2.3/3.0) or `extension/neocarts/module/neocarts` (4.x;
method separator `.` since 4.0.2.0, `|` in 4.0.0–4.0.1; the route helper picks it by `VERSION`).

| Route | Method | What |
|---|---|---|
| `<base>/products&ids=1,2` | GET | `{products: {"42": {external_id, title, variant: null, image_url, product_url, price, has_options, subscribable}}}` — price = `Money::minor()` of the price the store shows the current customer group (special applied, tax per `config_tax`), image absolute, `has_options` = has a **required** option |
| `<base>/cart` | GET | `{lines: [{key, external_id, title, variant, image_url, product_url, price, quantity, subscribable}]}` from `$this->cart->getProducts()`; `key` = cart id; `external_id` = `Items::externalId()` |
| `<base>/order` | POST JSON `{selection}` | cart lines (with `subscribable`) → `Items::build($lines, $selection)` → session `neocarts_order_id`? `putItems` (404/409 → create) : `createOrder` with `redirect_url` = cart page + `?neocarts=checkout`. Answers `{orderId}`; errors `{message}` with 422 (texts from `Lang`, `ApiError::$errors['name']` fills `%s`) or 502 |
| `<base>/clear` | POST | empties the cart, drops `neocarts_order_id` → `{ok: true}` |
| `<base>/webhook` | POST | `Webhook::handle(raw php://input, X-Neocarts-Signature, secret, store, sink)`; answer its status with `{}` or `{"error": Webhook::$lastError}` (the merchant reads it in the Neocarts panel's webhook log); also to the error log when `debug` is on |

`subscribable` = not `Items::disabled($externalId, <the product's neocarts_product ids>)` **and**
product `status = 1`, `quantity > 0`, and for a cart line every chosen option value with
`subtract = 1` has `quantity > 0`. `neocarts.js` shows no button for `subscribable: false`;
`Items::build` sends such a line as `one_time` whatever the browser says.

Native cart add for the JS (`urls.cartAdd`): `checkout/cart/add` (2.3/3.0), `checkout/cart.add` (4.x).
Mini-cart (`urls.miniCart`): `common/cart/info` / `common/cart.info`.

## Events (registered on install, removed on uninstall; code `neocarts`)

| Trigger | Does |
|---|---|
| `catalog/view/common/header/after` (2.3: the themed path, see oc-versions.md) | inserts before `</head>`: `<script>window.NeocartsOpencart = {...}</script>`, `<script src="https://cdn.neocarts.com/bundle.js" data-shop-id="…" defer>`, `<script src="…/neocarts.js" defer>` — only when status is on and shop id + key are set |
| `catalog/view/account/account/after` | «My subscriptions» → `Links::CUSTOMER_URL` (setting `account_link`) |
| `admin/model/catalog/product/addProduct/after`, `…/editProduct/after` | store the product's switches (only when the product form posted `neocarts_subscription_present`); `putProduct` for the product (`product_sync`) — a failure is logged, never thrown |
| `admin/view/catalog/product_form/after` | the product block |

`window.NeocartsOpencart` = `{ route, locale: Locale::resolve(store language), cardsOn: Settings::cardsOn(), settings: Settings::storefront(), texts: the sf_* keys of Lang for this language, urls: {products, cart, order, clear, cartAdd, miniCart, cartPage, success} }`.

## Tables (`Schema::migrate($prefix, $stored schema_version)` on install and when the settings page opens)

- `neocarts_order` — Neocarts order id PK → OC order id; insert it **before** `addOrderHistory`
  (the PK guards a racing retry).
- `neocarts_webhook` — delivery id PK, event, status success/ignored/failed, error, body;
  idempotency only (no admin UI). Keep the last 1000 rows.
- `neocarts_product` — `external_id` PK, `product_id`, `updated_at`: the switched-off ids (`42` the
  whole product, `42-17` every variant with option value 17).

`WebhookStore::claim` = `INSERT IGNORE` (or `UPDATE … WHERE status='failed'`).

## Webhook sink (catalog model on OpenCart's own `checkout/order` model)

- `orderPaid`: skip if `neocarts_order` has the id (return true). Respect `orders_widget` /
  `orders_renewal`. `OrderMapper::map($data, Lang::load(store language))` → the branch's
  `addOrder()` array: store from config, customer by email (`customer_id`, group) or guest,
  payment address = shipping address, payment method «Neocarts (provider, card)» with code
  `neocarts`, shipping code `neocarts.neocarts`, currency = the store default (`config_currency`,
  value 1), language = the store default, products with option rows rebuilt from
  `option_value_ids` (a vanished option → no row, the line keeps `variant` in its name), totals
  `sub_total`, `neocarts_discount` (negative, only when > 0; needs an empty total model), `total`.
  Then the `neocarts_order` row, then `addOrderHistory(status_paid|status_manual, comment,
  notify_customer)`. An unknown product still becomes a line by name + a history note.
- `orderCancelled`: OC order exists → `status_cancelled` + `order_cancelled`.
- `paymentRefunded`: find by `order_id` → `status_refunded` when `status === 'refunded'`, else
  `status_partially_refunded` (0 = comment only), comment `order_refunded` with the amount.
- Every other event (subscription.*, payment.failed, order.created, new ones) → `ignored`, 200.

## Admin page

Tabs Connection · Placement · Checkout · Orders; a field per `Settings::defaults()` key, labelled
`entry_<key>` (+ `help_<key>` where it exists). The key and secret are write-only.
- **Connection:** status, shop id, key, secret, Shop API address, debug; «Test connection»
  (`Client::check()`); the webhook URL to copy; the version (`NEOCARTS_VERSION`); «Open the Neocarts
  panel»; the maintenance warning.
- **Placement:** product page, cards (selector, id regex, anchor, position, class, pages), cart lines,
  `custom_css` (textarea).
- **Checkout:** interception + link selector, banner + anchor/position, «Go to checkout» in the
  window, auto add, «My subscriptions» link.
- **Orders:** create on checkout / on renewal, the five statuses, notify the customer, product card
  sync.
- **Footer on the page and the product block:** `text_support` with a `mailto:` to
  `Links::SUPPORT_EMAIL` and `text_docs` → `Links::docs(admin language)`; a failed «Test
  connection» adds `text_check_support`. Never hard-code the addresses.

**Product block** (inside the product `<form>`, so it posts with the product): checkbox
`neocarts_subscription[<product_id>]` (the whole product) and one per value of every choice option
(select/radio/checkbox/image) `neocarts_subscription[<product_id>-<product_option_value_id>]`,
labelled «Option: value», checked = on; hidden `neocarts_subscription_present=1`. Saving replaces
the product's `neocarts_product` rows with the unchecked ids (a new product's `[0]` maps to its new
id). Under the switches: `help_product_subscription` and a link «Subscriptions and prices — in the
Neocarts panel» to `Links::ADMIN_URL`.

## Themes (third-party theme support)

- **Theme detection.** Pass the store theme to `Settings::resolve($branch, $stored, $theme)` in the
  catalog and in the admin:
  - 2.3: `theme_default_directory` when `config_theme` is `theme_default`, else
    `<config_theme>_directory`;
  - 3.0: `theme_<config_theme>_directory`;
  - 4.x: the `config_theme` code.

  The preset fills only keys the merchant left at the default.
- **Settings page.** Show `text_theme_preset` (with the preset name) or `text_theme_no_preset` (with
  the theme) at the top of the Placement tab, plus `help_selector_fallback`. Show the stored value in
  each field: when a preset applies and the stored value is empty or equal to the default, show the
  preset value, so the merchant sees what is really used.
- **New keys, on the Placement tab:** `quickview_anchor` (+help), `minicart_selector`,
  `minicart_url` (+help), `oneclick_selector` (+help), and `help_slot` text, the manual-slot snippet,
  shown as `<code>`.
- **New key, on the Checkout tab:** `checkout_routes`.
- **Storefront config.** `urls.checkout` = the store's own checkout URL (`checkout/checkout` through
  `url->link`, `&amp;` decoded). The JS also intercepts links equal to it (SEO keyword URLs).

## The button box (per-place style)

`neocarts.js` puts every button into `<div class="neocarts-oc-slot neocarts-oc-slot--<place> <place>_class" style="<place>_style">`
(`place` = product | card | cart | slot). Settings `product_style`, `card_style`, `cart_style`
(inline CSS, defaults `margin: 10px 0;` / `margin-top: 8px;`) sit next to `*_class` on the
Placement tab, labelled `entry_style` (+ `help_style`). The default spacing was added after the user
saw the card button stuck to the theme's button row (3.0 default theme). The script URL carries
`?v=<version>-<filemtime>` so browsers never keep an old `neocarts.js`.
