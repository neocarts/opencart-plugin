# Third-party themes: selectors and gaps

Research from 2026-11-15. It checks how free OpenCart themes differ from the default markup that
`Settings::defaults()` and `src/shared/js/neocarts.js` assume, gives a selector preset per theme
mapped to our setting keys, and lists what the plugin is missing. The findings come from reading
templates and JS only. Nothing was installed or run, so check each preset on the demo stand before
shipping it.

## What could be downloaded

The archives are in the session scratchpad (`…/scratchpad/themes/<name>/`) and are not committed.
Each one is a GitHub snapshot pinned to a commit. Codeload zips are usually byte-stable, but GitHub
does not guarantee it, so where a theme has a release asset, its URL and sha256 are given as well.
Prefer the release asset.

Not downloadable without a login or payment, so not analysed:

- **Unishop2 free**: the best known free CIS theme for 3.0. liveopencart.ru needs a login, and
  shop.opencart-russia.ru could not be resolved from here.
- **DC Minimal BS5** (3.0) and **DC Minimal 4**: the most visible free themes on opencart.com. The
  marketplace download redirects to `account/login`.
- **Moneymaker 2** is paid. Moneymaker 1 is free only for Ukrainian shops, on request to the author.
- **Revolution** and **Journal** are paid.
- **Nextdef**: no free download found.

If someone downloads Unishop2 free or DC Minimal 4 by hand, analyse them the same way. They are
more representative than most of the GitHub themes below.

Excluded after download:

- **2Default** (d1ms/2default_opencart, 3.0, GPL-3): the repo holds only a rendered demo (no twig
  files). From the demo: the cards are `.product-thumb-card` (our `.product-thumb` misses them),
  ids come from `cart.add('N')`, and the mini-cart is a `#cart` drawer.
- **IP-CAM "Free Theme 2.3.0.2"**: a stock 2.3 install with the default theme only.

## Default selector failures

✓ means our default works, ✗ means it fails, ~ means it matches but lands in a bad spot.

| Setting (default) | Materialize 2.3 | Xezpro | First Line | Materialize 3.0 | Mintleaf | Frame free | DC Luxury | BeFree | DC Toys 4 | PN Black | Fashion 4 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| product_anchor `#button-cart` after | ✓ | ✓ | ✓ | ✓ | ~ inline with the wishlist button | ~ inside a narrow `.col` | ✓ | ~ inside the flex `.input-group` | ~ inside the flex `.product-cart-row` | ~ inside the flex `.pn-add-row` | ✓ |
| `input[name=product_id]` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (the first match is the wishlist form, same id) | ✓ | ✓ (the first match is the wishlist form) | ✓ |
| card_selector `.product-thumb` | ✗ `.card` | ✗ `.product` | ✗ `.product` | ✗ `.card` | ✓ | ✗ `.product-item` | ~ listings only, modules use `.product-item` | ✓ | ✗ `.product-card` | ✗ `.product-card` | ✓ |
| card_id_regex | ✓ | ✗ `addToCart('N')`, `data-product-id` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| card_anchor (`.button-group` / 4.x `form`) | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ (has `.btn-group`) | ✗ | ✓ | ~ the first form is the wishlist form on the image | ~ the first form is the wishlist form on the image | ✗ the form is the card's parent |
| cart_row_selector | ✗ no `#content`, no table | ✓ | ✓ | ✗ no `#content`, no table | ✓ | ✓ | ✓ | ✓ stock | ✓ stock | ✓ stock | ✓ |
| cart_key_regex | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| cart_anchor `td:nth-child(2)` | ✗ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| checkout_link_selector | ✓ | ✗ with nicocheckout on (`checkout/nicocheckout`, `#buynow`) | ✓ | ✓ | ✓ | ✗ when Simple is installed (`checkout/simplecheckout`) | ✓ | ✓ | ✓ | ✓ | ✓ |
| mini-cart `#cart` refresh | ✗ `#cart` is only the button, the list is `#modal-cart` | ~ `#cart` is the `<a>`, the list is the sibling `.cart-info` | ✓ (`#cart` is a modal) | ✗ as Materialize 2.3 | ✓ | ✗ `#ff_cart`, own route | ✓ | ✓ | ✓ (`#cart` in a drawer) | ✓ (`#cart` in a drawer) | ✗ `#header-cart` |
| checkout_banner_anchor `#content > h1` | ✗ no `#content` | ✓ | ✓ stock | ✗ no `#content` | ✗ `h3` | ✓ (✗ under Simple) | ✗ `h1` sits in `.page-head` outside `#content` | ✓ stock | ✓ stock | ✓ stock | ✓ |
| product form `#form-product, #product` | ✓ `#product` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (returns `#product`, the div around the form) | ✓ | ✓ | ✓ |

Stock OpenCart breaks two of our defaults as well:

- **Stock 4.1.0.x** puts `#button-cart` inside `.input-group` next to the quantity field, so
  `#button-cart` + `after` lands inside a flex row. Stock 4.0.2.3 has no `.input-group` there.
- **Stock 4.0.2.x** has no `#cart`. The header wraps the mini-cart in `#header-cart`, and the stock
  JS loads `common/cart.info` into `#header-cart`. 4.1 renamed it to `#cart`. So `refreshMiniCart()`
  does nothing on 4.0.2.x, and on Fashion 4 (built on 4.0.1).

## 2.3.x

| Theme | Version / commit | License | Download + sha256 |
|---|---|---|---|
| **Materialize** (trydalcoholic, 123★, material design; directory `materialize`) | master `845baaa` (`archive/opencart_2.3.x/`) | MIT | `https://codeload.github.com/trydalcoholic/opencart-materialize/zip/845baaa8bacbce36f3b95408719255173efecae3`<br>`16f53d25876f724824a09e5e4070f347414bc0ff7778890035f7109e58f1187b` |
| **Xezpro** (givanz / nicolette.ro; directory `xezpro`; claims 2.3 and 3.x, ships `.tpl`) | 1.0 | GPL-3 | release `https://github.com/givanz/xezpro/releases/download/1.0/xezpro.zip`<br>`7ef6faf81a01896df6e0e7f46c0608591bfa55d40600059096022f80d7cafec3`<br>(snapshot `c1c8926`: `7c36b9e2634587df1b151466b3f35043251d64d99eac21915e8e7698f392a500`) |
| **First Line** (IP-CAM mirror, template folder only) | `2a7298e` | none stated | `https://codeload.github.com/IP-CAM/First-Line-Theme-for-Opencart-v.2.3.x/zip/2a7298e0898044433577307b90f55cb60abbdeef`<br>`dd4a52ab21fea539b932858cd6b36f585b47ad78415938b5f9192b60d8e183f4` |

| Key | Materialize 2.3 | Xezpro | First Line |
|---|---|---|---|
| product_anchor / position | `#button-cart` after (inside `.section`) | `#button-cart` after (inline before `#buynow`) | `#button-cart` after |
| card_selector | `.card.sticky-action` | `.product` | `.product-layout > .product` |
| card_id_regex | default (`cart.add('N')`) | `(?:addToCart\('\|data-product-id=")(\d+)` | default |
| card_anchor / position | `.card-action` append | `.actions` append | `.product-buy` after |
| cart_row_selector | `form .card.horizontal` | default | default |
| cart_key_regex | default (`quantity[N]`) | default | default |
| cart_anchor / position | `.card-content` append | default | `.product-info` append |
| checkout_link_selector | default | `a[href*="checkout/checkout"], a[href*="checkout/nicocheckout"], #buynow` | default |
| checkout_banner_anchor / position | `#accordion` before | default | default (stock template) |
| mini-cart | `#modal-cart-list` (theme loads `common/cart/info` into it) | the `.cart` wrapper around `#cart` + `.cart-info` | `#cart` (a Bootstrap modal) |

Notes:

- **Materialize**: listing and module cards are both `.card.sticky-action.large.hoverable`. Each
  has a hidden `.card-reveal` panel, and the cart button is `disabled` when the product cannot be
  added. The theme's own `quickorder` module (one-click order) renders under the product buttons
  and skips checkout. jQuery comes from the theme's bundled script.
- **Xezpro**: the out-of-stock product page renders no `#button-cart`. `#buynow` links to
  `checkout/nicocheckout&product_id=N`, the theme's one-page checkout, and the cart links switch to
  `/nicocheckout` when `nico_get_config('checkout')` is on. Quick view: `span.quickview` opens
  Colorbox with the product URL + `&ajax=true`, so the full product markup (`#button-cart`,
  `input[name=product_id]`) appears inside `#colorbox` on listing pages. Our `productPage()` would
  pick it up by accident (there is no route check). The theme overrides `cart.add` in
  `js/main.js` and uses jQuery 2.1.x. The endpoint is still stock `checkout/cart/add`.
- **First Line** is a bare `template/` folder: no admin, no checkout templates, so checkout falls
  back to the default theme.

## 3.0.x

| Theme | Version / commit | License | Download + sha256 |
|---|---|---|---|
| **Frame free** (stickpro, CIS author, free edition of "Frame"; extension code `framefree`, directory `ff_frame`) | 1.0.0 | none stated | release `https://github.com/stickpro/frame_free/releases/download/1.0.0/framefree_1.0.0.ocmod.zip`<br>`8ff53db3978cfcb5a0691d0fdc5dbb1294beeae79058e7e23ed0e40db9cf4998`<br>(snapshot `a01714c`: `37f76cfe1d25e66189f40aa67f557df94bc91a8a66e3836e88b8276ccaaa0c5f`) |
| **Materialize 3.0** (same repo; directory `materialize`; `source/opencart_3.1.x` is a newer `mt_materialize`) | master `845baaa` (`source/opencart_3.0.x/`) | MIT | same zip as 2.3 |
| **Mintleaf** (settysantu, 18★; directory `mintleaf`) | branch 3.0.3.2 `f763ad0` | GPL-3 | `https://codeload.github.com/settysantu/mintleaf/zip/f763ad084d7a4c161ba258b0ae220f18a8d5056b`<br>`94e462db8f452d5e5a0c3f99355b3d7b481f0c0d06c02a5834135e5b36e4f562` |
| **DC Luxury** (Design Cart, 2018; directory `luxury`; the theme is `theme.zip` inside the repo) | `ded47f8` | none stated | `https://codeload.github.com/DesignCart/theme_luxury/zip/ded47f80c36782bb5ced2a94dbcb5ceb99068475`<br>`2d46e8139ef856e54cd0b0073c7473b6ae9dc695e804b31df055c1719d113b20` |

| Key | Frame free | Materialize 3.0 | Mintleaf | DC Luxury |
|---|---|---|---|---|
| product_anchor / position | `#product .form-row:has(#button-cart)` after (full-width row), fallback `#button-cart` after | `#button-cart` after | `#button-cart` after (or its `.form-group` after) | `#button-cart` after |
| card_selector | `.product-item` | `.card.hoverable` (listings `#productN .card`, modules `.card.horizontal.hoverable`) | `.product-thumb` | `.product-thumb, .product-item` |
| card_id_regex | default | default | default | default (`cart.add('N', 'min')`) |
| card_anchor / position | `.product-controls` append | `.card-action` append | `.module-product-btn-cart` after | `.caption` append |
| cart_row_selector | default | `form .card.horizontal` | default | default |
| cart_key_regex | default | default | default | default |
| cart_anchor / position | default | `.card-content` append | default | default |
| checkout_link_selector | `a[href*="checkout/checkout"], a[href*="checkout/simplecheckout"]` | default | default | default |
| checkout_banner_anchor / position | `#content > h1` after; under Simple `#simplecheckout_form_0` before | `#accordion` before | `#accordion` before (the heading is `h3.page-heading-1`) | `#accordion` before (or `.page-head` after) |
| mini-cart | `#ff_cart`, refreshed from `index.php?route=extension/module/framefreetheme/ff_cart/info` | `#modal-cart-list` | `#cart` | `#cart` |

Notes:

- **Frame free** has the most features of these themes, and they are typical of CIS shops:
  - Quick view: `#ff_modal_qview`, loaded by AJAX from `extension/module/framefreetheme/ff_qview`.
    It has `input[name=product_id]` and the button `[id^="qv-button-cart-"]`.
  - Fast order (one-click order) modal `ff_fastorder`, next to `#button-cart` and on every card.
    It skips checkout.
  - An "added" popup `#ff_popup_cart`.
  - A "show more" button (`showmore()`) that appends the next page by AJAX on category, search,
    special and manufacturer. The theme also bundles ocfilter, which reloads the listing by AJAX.
    The MutationObserver covers both.
  - `cart.add` posts to its own `extension/module/framefreetheme/ff_cart/add`. Our POST to the
    stock endpoint still adds the product, but the theme's counters do not update.
  - It ships Simple templates (`simplecheckout*.twig`). With Simple installed, checkout is
    `checkout/simplecheckout`, which our banner (`^checkout/checkout`) and links miss. The Simple
    cart rows sit in `#simplecheckout_cart` (`quantity[N]`).
  - The card's add button sits in a flex `.btn-group.dropup`. Do not anchor inside it.
  - jQuery 3.5.1.
- **Materialize 3.0**: cart rows are `.card.horizontal`, and the module cards are
  `.card.horizontal.hoverable`. Card selectors must keep `.hoverable`, or the cart rows on the cart
  page match too. They are filtered out later anyway, because the rows carry
  `cart.remove('N')`, not `cart.add`.
- **DC Luxury**: the module cards are Owl Carousel slides. Owl clones slides (`.owl-item.cloned`)
  and the stage has a fixed height, so a button can be clipped or duplicated.

## 4.x

| Theme | Version / commit | License | Download + sha256 |
|---|---|---|---|
| **BeFree** (Intense-Finance; extension code `befree`; 4.0 and 4.1; created Oct 2026, AI-generated per its README) | 1.2.5 | GPL-3 | release `https://github.com/Intense-Finance/BeFree/releases/download/v1.2.5/befree.ocmod.zip`<br>`9a80bc7664c56fe0fc34711570b1bb7901e5d8f5f17194286220b2fda0626b29`<br>(snapshot `46abb7b`: `1903757f6b598bf1952bdb0fe2c09ce57542772e89ee21dcb8e214e1db3dfbd7`) |
| **DC Toys 4** (Design Cart, the author of DC Minimal 4; extension code `dc_toys`) | `8157fc8` | free, with the footer credit kept (the theme reinjects it) | `https://codeload.github.com/DesignCart/oc-dc-toys-4/zip/8157fc8e21c64b265a74e441eaf761b3d326bf5b`<br>`118f270048acd848b062398f727085fe11252730740f7f173c9f0b6a2d21bab9` |
| **PN Black / NØR** (Paweł Nosko / Design Cart; extension code `pn_black`) | 1.0.0 `cae3784` | none stated | `https://codeload.github.com/pawelnosko/pn-black/zip/cae3784893e00bf72a3ddfb1ba8839c0fc4d356a`<br>`886ba187f3e61d4fee76e9a590d436d5cf223d34f68a1e5cb8eeff1a52cd76c4` |
| **Fashion 4** (erolislen; a full OpenCart **4.0.1.1** tree with `catalog/view/template` overwritten, not an extension) | `d2ec04b` | OpenCart GPL-3 | `https://codeload.github.com/erolislen/opencart-4-fashion-theme/zip/d2ec04bc4eaacd3f32e90726f9f7c6e12e7b7606`<br>`2c02a4d422d7fd60138853385aa4f1d0711d2e2cf91beca9c32da5c705243fb6` |

| Key | BeFree | DC Toys 4 | PN Black | Fashion 4 (reference only) |
|---|---|---|---|---|
| product_anchor / position | `#form-product .input-group:has(> #button-cart)` after (same as stock 4.1) | `.product-cart-row` after | `.pn-add-row` after | `#button-cart` after |
| card_selector | default `.product-thumb` | `.product-card` | `article.product-card` | default `.product-thumb` |
| card_id_regex | default | default (`name="product_id" value="N"`) | default | default |
| card_anchor / position | default `form` after | `.product-card-price-row` after | `.product-card-info` append | `.text-center` after (the `form` wraps the card) |
| cart_row_selector / cart_key_regex / cart_anchor | defaults (stock `checkout/cart_list`) | defaults (stock) | defaults (stock) | defaults |
| checkout_link_selector | default | default | default | default |
| checkout_banner_anchor | default (stock) | default (stock) | default (stock) | default |
| mini-cart | `#cart` (header wrapper; `cart.twig` has no id, so the body fallback applies) | `#cart` inside the drawer `#cart-panel .cart-panel-content` | `#cart` inside the drawer `aside#cart-panel .cart-panel-content` | `#header-cart` |

Notes:

- **DC Toys 4 / PN Black**:
  - Every card has 2–3 forms: wishlist first, then a quick "+" / "Add bag" form on the image, then
    (Toys only) the main cart form.
  - The product page has `input[name=product_id]` twice, once in the wishlist form and once in
    `#form-product`.
  - Own drawer JS (`catalog/view/javascript/theme.js`): it intercepts `cart.add` form submits,
    replaces `.cart-panel-content` and opens `#cart-panel`. Quantity edits go to the theme's own
    `extension/<code>/checkout/dc_cart.edit|remove`.
  - Drawer lines carry `data-cart-id="N"` (Toys `.row-cart`, Black `.pn-cart-line`).
  - No cart or checkout templates, so the pages are stock.
- **BeFree** keeps the stock 4.x markup almost everywhere. It has no checkout templates other than
  `register.twig`.
- **Fashion 4** is pre-4.0.2 (`checkout/cart|list` routes) and is not a theme extension. Use it only
  as evidence that 4.0.x stock-derived themes target `#header-cart`, and that a `<form>` can wrap
  the whole card.
- None of the analysed themes renders products with Vue or React, uses shadow DOM, or builds
  products from JSON. The one SPA-like case found is `vuefront/theme-opencart` (a separate Vue
  storefront), which our approach cannot support. jQuery versions vary (2.1 to 3.7) but we do not
  use jQuery.

## What the plugin needs

1. **Ordered fallback anchors** (all themes). A CSS list `a, b` returns the first match in
   *document* order, not in priority order. Allow `a || b || c` (or one selector per line), tried in
   order, in every `*_anchor` and `*_selector` setting. Default examples:
   - product: `.input-group:has(> #button-cart) || #button-cart` (stock 4.1, BeFree)
   - banner: `#content > h1 || #accordion || #simplecheckout_form_0 || h1` (Materialize, Mintleaf,
     DC Luxury, Simple)
   - mini-cart: `#cart || #header-cart || #ff_cart`
2. **A missing card anchor falls back to the card itself (`append`) instead of skipping the
   card.** Needed by Fashion 4, where the form wraps the card, and by any preset that guesses wrong.
   Change the 4.x default card anchor to `form:has(> input[name="quantity"])`. It skips the
   wishlist forms (DC Toys, PN Black) and still matches stock and BeFree.
3. **A wider default card_id_regex:**
   `(?:cart\.add\('|addToCart\('|data-product-id="|name="product_id" value=")(\d+)`. It covers
   Xezpro, and it is harmless elsewhere. Also add `.product-thumb-card` (2Default) to the default
   card selector list.
4. **Theme presets, auto-detected.** The server already knows the theme:
   - 2.3: `config_theme` = `theme_default` plus `theme_default_directory`.
   - 3.0: `config_theme` = the extension code (`framefree`) plus `theme_<code>_directory`
     (`ff_frame`).
   - 4.x: `config_theme` = the extension code (`befree`, `dc_toys`, `pn_black`; `basic` is stock).

   Key the presets by directory (2.3/3.0) or code (4.x), as the tables above give them. Apply a
   preset only to the keys the merchant left at the default, and show the detected preset in the
   admin. Presets to ship first: `materialize`, `xezpro`, `ff_frame`/`framefree`, `mintleaf`,
   `luxury`, `dc_toys`, `pn_black`. Add Unishop2 free and DC Minimal once someone downloads them.
5. **Mini-cart refresh settings: `mini_cart_selector` (a fallback list) and `mini_cart_url`.**
   - Stock 4.0.2.x and Fashion 4 need `#header-cart`.
   - Frame free needs `#ff_cart` plus `extension/module/framefreetheme/ff_cart/info`.
   - Materialize needs `#modal-cart-list`.
   - Xezpro needs the `.cart` wrapper.

   A cheaper alternative that also fixes theme-specific counters and popups: for a cart add without
   options on 2.3/3.0, call the theme's global `cart.add(id)` when it exists. Frame free, Xezpro and
   Materialize all override it. On 4.x, `requestSubmit()` the card's or product's own cart form, so
   the theme's `data-oc-target` and drawer JS run. Fall back to our own fetch.
6. **Look up the product id from the anchor's scope**, not the first `input[name=product_id]` on
   the page: `anchor.closest('form, #product, .modal, #colorbox')`. In 4.x every card carries that
   input, and the wishlist form comes first. A quick view opened on a listing page would otherwise
   take the wrong product.
7. **Quick view placement**: `quickview_anchor` (fallback list) plus the id from the scope in
   item 6. Frame free: `#ff_modal_qview [id^="qv-button-cart-"]`. Xezpro: `#colorbox #button-cart`.
   The observer already re-scans when the modal content arrives.
8. **Checkout links**:
   - Add `a[href*="checkout/simplecheckout"]` (Simple, bundled with Frame free and very common in
     CIS) and `a[href*="checkout/nicocheckout"], #buynow` (Xezpro) to the default.
   - Also pass the server-rendered checkout URL (`$this->url->link('checkout/checkout')`) in
     `cfg.urls`, and match `a[href="<it>"]`. With SEO keyword URLs (ocStore's SEO Pro) the href
     may not contain `checkout/checkout` at all. Verify this on ocStore.
9. **Banner on one-page checkouts**: make the banner route list configurable (default
   `checkout/checkout`, `checkout/simplecheckout`, `checkout/nicocheckout`), with anchors from
   item 1.
10. **One-click / fast order buttons skip checkout.** This covers Frame free `ff_fastorder`,
    Materialize `quickorder` and Xezpro `#buynow`. Add a setting `oneclick_selector` whose elements
    the plugin hides while the product (or any cart line) is in subscription mode, so a
    subscription cannot be ordered as a one-time product.
11. **Manual slot `<div data-neocarts-slot data-product-id="N">`** (and `data-cart-key` for cart
    lines). The plugin fills any slot it finds, whatever the selectors say. This is the escape
    hatch for themes we have no preset for, like Fashion 4 or heavily customised shops, and for
    HTML modules or page builders.
12. **Optional: mini-cart line placement** (`minicart_row_selector`, `minicart_key_regex`):
    - stock 2.3/3.0: `#cart .dropdown-menu table tr` with `cart\.remove\('(\d+)`
    - stock 4.x: `name="key" value="(\d+)"`
    - DC Toys / PN Black: `[data-cart-id]` with `data-cart-id="(\d+)"`

    Lower priority. The cart page covers the same need, but drawer carts (the DC themes, 2Default)
    are where shoppers spend their time.
13. **Carousels**: when a card sits in `.owl-item.cloned`, place the button on the original only,
    or accept duplicates. Watch for clipping in fixed-height stages (DC Luxury modules).

## Demo stand recommendations

Installed by `make themes`, switched by `make theme S=ocN T=<name>` (docs/ai/docker.md
«Third-party themes»):

| Branch | Installed (`T=`) | Why |
|---|---|---|
| 2.3 | **Materialize** `materialize` (MIT) + **Xezpro** `xezpro` (GPL-3) | Materialize: the best-known free theme on GitHub; it looks nothing like stock, and its cart has no table and no `#content`. Xezpro: custom cards (`addToCart`), quick view, a one-page checkout and buy-now. |
| 3.0 | **Frame free** `ff_frame` + **DC Luxury** `luxury` | Frame free has the CIS stack: quick view, fast order, show more, its own mini-cart, Simple templates. DC Luxury: plain templates, `.product-item` module cards, `h1` outside `#content`. It replaces Materialize 3.0, whose OCMOD breaks PHP on 3.0.5.1 (parse errors in the patched product model). Add Unishop2 free if someone downloads it. |
| 4.x | **DC Toys 4** `dc_toys` + **BeFree** `befree` | DC Toys: drawer cart, multi-form cards, flex product row, from the author of the popular DC Minimal 4. BeFree: GPL, stock-like markup, works on 4.0 and 4.1. Swap DC Toys for DC Minimal 4 if a marketplace login is available. |

Checked once each, while active, on OpenCart 2.3.0.2 / 3.0.5.1 / 4.1.0.4. Home, category 20 and
product 40 return 200 with the theme's markup and `neocarts.js`, and no PHP error text. Materialize
2.3 and Frame free get there only by hiding error display (their warnings are logged); see
docker.md.

Also keep a stock **4.0.2.3** container, or test it once: `#header-cart` and the button
placement differ from 4.1, and our docker stand only runs 4.1.
