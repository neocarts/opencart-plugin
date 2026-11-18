# OpenCart branch differences

What differs between 2.3, 3.0 and 4.x for this plugin, and the pitfalls met so far. Each adapter's
agent keeps its own section; facts true for all branches go to the top.

## All branches

- Language loader falls back to `en-gb` (2.3 and 3.0 load the default directory first), so the
  OpenCart language files are stubs with `heading_title`; real texts come from `Neocarts\Lang`.
- Cart lines (`$this->cart->getProducts()`) share the option shape
  `{product_option_value_id, name, value, type}`; text options have an empty value id.
- Default theme markup: product page `#button-cart` + `input[name=product_id]`; cards
  `.product-thumb` (2.3/3.0 `onclick="cart.add('42', …)"` in `.button-group`; 4.x a `<form>` with
  `input[name=product_id]`); cart page rows carry `quantity[<cart_id>]` (2.3/3.0) or
  `<input name="key" value="<cart_id>">` (4.x, reloaded by AJAX into `#shopping-cart`).
- Demo data ids are the same in all three databases (products 40/42, category 20, statuses 2/7/11).

## 2.3 (src/oc2)

- Routes: `extension/module/neocarts/<method>` with `/` as the separator, in both admin and catalog.
  Admin pages need `&token=…`. Permission is checked against `extension/module/neocarts`, which
  `extension/extension/module/install` grants, so `check` needs no grant of its own.
- **Catalog view triggers carry the theme path.** The theme event `view/*/before` rewrites the route
  to `<theme>/template/<route>` before `after` fires, so the triggers are
  `catalog/view/*/template/common/header/after` and `…/account/account/after`. Admin view routes
  stay plain (`admin/view/catalog/product_form/after`). Event matching is a prefix regex with no
  end anchor.
- Model events: the handler gets `($route, $args, $output)`. For `addProduct` the new id is
  `$output`; for `editProduct` it is `$args[0]`. A view `after` handler changes `&$output`. A
  handler must return null, because any other value replaces the output.
- **`addOrderHistory()` loads `model/extension/total/<code>` for every order total**, and the
  loader throws on a missing file. A `neocarts_discount` total therefore needs the stub
  `catalog/model/extension/total/neocarts_discount.php`. 3.0 has the same loop.
- `addOrderHistory()` sends the order mail itself. With `mail` and no sendmail in Docker it prints
  warnings, so the webhook wraps `Webhook::handle` in `ob_start()`/`ob_end_clean()`.
- The model object is the WebhookStore and WebhookSink itself (`implements`, after
  `require_once autoload.php` at the top of the model file). Pass `$this` from inside the model:
  the `Proxy` from `$this->load->model` cannot be passed.
- `$this->url->link()` returns `&amp;`; JSON urls and `redirect_url` are passed through
  `html_entity_decode`. `Request` htmlspecialchars-cleans `server`/`get`. The signature header has
  no special characters, so it survives the cleaning. The body is read raw from `php://input`.
- Catalog `config_language` is the store default, and the shopper's language is
  `session->data['language']`. The webhook reads `config_language`/`config_currency` straight from
  `oc_setting`.
- Settings are written by the plugin's own `put()` (`serialized = 0`), like the e2e
  `setSettings`. `model_setting_setting->editSetting()` would delete the e2e rows of the code.
- `Request` htmlspecialchars-cleans `$_POST` too. `save()` therefore decodes every posted value
  with `html_entity_decode`, otherwise `#content > h1` or `custom_css` would be stored as `&gt;`.
- The product block (the switches) is inserted just after the opening `<form id="form-product">`
  tag so it posts with the product. The add/edit events store the switches only when
  `neocarts_subscription_present` was posted, so a product copy or an API call does not reset
  them. On a new product the switches are posted as `[0]`/`[0-<povid>]` and are re-keyed to the
  new id.
- The cart option rows of `$this->cart->getProducts()` carry `quantity` and `subtract` for each
  chosen value, which is what `subscribable` checks.
- Theme for presets: `config_theme` is an extension code (`theme_default`); the directory is
  `config->get(<config_theme> . '_directory')`, e.g. `theme_default_directory` = `default`.
  Store-0 settings are in `$this->config` in the admin too.
- The left admin column starts collapsed (`localStorage 'column-left'`), so its texts are hidden.
  Do not target them in specs.
- Files deleted from `src/oc2/upload` leave dangling symlinks in the shop: remove them with
  `find /var/www/html -xtype l -path '*neocarts*' -delete` inside the container.
- Docker DNS sometimes takes 5 s to resolve `mock-core-oc2` from PHP curl (glibc parallel A/AAAA).
  `dns_opt: [single-request-reopen]` in compose fixes it.

## 3.0 (src/oc3)

- Routes: `index.php?route=extension/module/neocarts/<method>` (method separator `/`); admin URLs need
  `&user_token=`. Admin: the settings page `extension/module/neocarts` and AJAX `…/check`. Install = Extensions → Modules →
  `extension/extension/module/install&extension=neocarts` (adds the permissions, then calls our
  `install()`); it inserts a duplicate `oc_extension` row if called twice, so `shops/oc3.js` checks
  the table first.
- Events: action `extension/module/neocarts/eventX`; handler signatures `(&$route, &$data, &$output)`
  for `view/*/after`, `(&$route, &$args, &$output)` for
  `model/*/after` (`addProduct` → id in `$output`, `editProduct` → id in `$args[0]`). The admin
  settings page deletes and re-adds the `neocarts` events on every open (heals upgrades) and runs
  `Schema::migrate` from `module_neocarts_schema_version`.
- **The loader returns a proxy, not the model**: `$this->model_x` does not implement our interfaces,
  so the catalog controller instantiates `new ModelExtensionModuleNeocarts($registry)` itself for
  `Webhook::handle()` (store + sink).
- `addOrderHistory()` loads `model/extension/total/<code>` for every total of the order when it moves
  to a processing/complete status → `catalog/model/extension/total/neocarts_discount.php` is an
  empty model class, otherwise `order.paid` dies with «Could not load model».
- `editSetting('module_neocarts', …)` deletes every row of the code: the form save writes
  `schema_version` back. `$this->request->post` is html-escaped: selectors/regexes are decoded with
  `html_entity_decode` before saving.
- Twig runs with **autoescape off**: every value in our templates is `|e`. Admin `url->link()` gives
  `&amp;` — decode it for URLs used in JS/JSON.
- Webhook orders: language/currency are read from store-0 settings (`config_language`,
  `config_currency`), not `$this->config` (the catalog picks the language from Accept-Language).
  Sub-Total/Total titles come from OpenCart's own `extension/total/*` language files.
- The demo product 42 is related to 40 and 41: its product page shows two card buttons in «Related
  products» (`card_on_product`).
- Product switches: the Neocarts block goes **before the last `</form>`** of `product_form` (after
  the tab content, outside the tabs) so `neocarts_subscription[…]` posts with the product; the
  add/editProduct event rewrites the product's `neocarts_product` rows only when
  `neocarts_subscription_present` is posted (a product saved by anything else keeps its switches).
  `editProduct` re-inserts `product_option_value` rows with their old ids, so `42-<povid>` switches
  survive a save; choice values are read from the DB after the save (a new product's `[0]` → its id).
- Theme for `Settings::resolve()`: `theme_<config_theme>_directory` (`config_theme` is the
  extension code, e.g. `default`; the directory setting holds the template folder). Both admin and
  catalog configs carry it (store-0 settings).
- Availability: `catalog/product` `getProduct()` already filters `status = 1`; cart options carry
  `subtract` / `quantity` per chosen value (`system/library/cart/cart.php`), used for `subscribable`.

## 4.x (src/oc4)

- **Routes:** `extension/neocarts/module/neocarts.<method>` (catalog JSON + `.webhook`; admin `.save`,
  `.check`). The method separator is
  `.` since **4.0.2.0** and `|` in 4.0.0–4.0.1 (checked in `system/engine/action.php` of both tags);
  `sep()` in the admin model / catalog controller picks it by `VERSION` and is used for routes *and*
  model event triggers (`admin/model/catalog/product.editProduct/after`).
- **Admin namespace needs `oc_extension_install`.** Admin `startup/extension` registers autoloaders,
  templates and languages only for rows of `oc_extension_install` (the Installer writes one when
  `neocarts.ocmod.zip` is uploaded — the code comes from the zip's file name). Extensions → Modules
  lists any `extension/*/admin/controller/module/*.php` by glob, so a bind-mounted extension shows up
  but its settings page 500s until that row exists: `tests/e2e/shops/oc4.js install()` inserts it, then
  calls `extension/module.install`. The catalog registers extensions from `oc_extension` instead.
- `$this->load->model()` gives a `Proxy`, not the model instance: type controller helpers as
  `\Opencart\System\Engine\Proxy`. `Webhook::handle` needs a real `WebhookStore`, so the catalog
  controller does `new \Opencart\Catalog\Model\Extension\Neocarts\Module\Neocarts($this->registry)`.
- `$this->config->get()` returns `''` (not null) for a missing key — use `config->has()` before
  passing stored settings to `Settings::resolve`, or every default is wiped.
- Twig `autoescape` is **off** in 4.x: escape user data with `|e` in templates.
- `addOrder()` (4.1): `payment_method`/`shipping_method` are arrays `{name, code}`; there is **no
  `payment_code` column** in `oc_order` (the code lives in the `payment_method` JSON). The admin order
  page also reads `shipping_method.cost` and `.tax_class_id` (warnings without them). Every field of
  the docblock must be present (`subscription_id`, `*_address_id`, `*_custom_field`, …).
- `addHistory()` loads `extension/<extension>/total/<code>` for **every** order total, so the
  `neocarts_discount` total needs a model: `catalog/model/total/neocarts_discount.php` (empty class).
  Titles of `sub_total`/`total` come from OpenCart's own `extension/opencart/total/*` language files.
  Pass `override = true` to skip the anti-fraud check.
- No `product_special`: specials are `product_discount` rows with `special = 1`; the catalog model's
  `getProduct()` returns them as `special`.
- Variants of 4.1 (`master_id` products) are separate products with their own id.
- Events: view after = `(&$route, &$data, &$output)`, model after = `(&$route, &$args, &$output)` —
  `addProduct` returns the id in `$output`, `editProduct` has it in `$args[0]`. The product form saves
  over AJAX (`catalog/product.save`, the form serialized), the events still fire. An unchecked box is
  not posted, hence the hidden `neocarts_subscription_present`; the product block is inserted before
  the `</form>` of `#form-product` so it is posted with the product. Option value ids survive an edit
  (`addOptionValue` reinserts the posted `product_option_value_id`), so `42-5` switches stay valid.
- `Request` runs `htmlspecialchars` over every POST value: the settings save decodes them (selectors
  with `>`, CSS).
- Admin JSON: return `{success}` / `{error}`; `common.js` handles `data-oc-toggle="ajax"` forms and
  puts alerts into `#alert`. The settings form writes the whole code with `editSetting`, so
  `schema_version` is carried over in the posted data.
- Response status: `http_response_code()` works; `Response::output()` only adds headers.
- Theme for `Settings::resolve()`: the `config_theme` code (`basic` on the stand; 4.x themes are
  extensions, the code is what `PRESETS` is keyed by). `url->link(route, '', true)` already returns
  `&` unescaped; `urls.checkout` is decoded anyway in case an SEO rewriter re-escapes it.
