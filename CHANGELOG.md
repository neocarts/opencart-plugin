# neocarts-opencart-plugin

## 1.0.0

The first release (2026-11-18).

### Major Changes
- Subscribe buttons on the product page, product cards and cart lines; every placement can be
  switched off and moved by selector.
- Neocarts checkout for carts with a subscription; carts without one keep the store's checkout.
- Webhooks: paid Neocarts orders and subscription renewals become ordinary OpenCart orders;
  cancellations and refunds update their status.
- Subscription is available for every product by default; it can be switched off per product or per
  option value on the product form. Products out of stock get no subscribe button.
- Product cards sync to Neocarts on save (never the price; prices are changed in the Neocarts panel).
- Every button sits in its own box with a per-place class and inline style (product page, cards,
  cart lines; default spacing 10px), plus custom CSS for theme tweaks.
- Presets for popular themes, fallback selectors (`a || b`), quick view, a manual slot
  `<div data-neocarts-slot data-product-id="…">`, hiding one-click order buttons.
- The support email and a docs link on the plugin's admin pages.
- English and Ukrainian.
