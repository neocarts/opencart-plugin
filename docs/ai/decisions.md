# Decisions made with the user

Settled 2026-11-15. Do not change them without asking.

- **Branches:** OpenCart 2.3, 3.0 and 4.x, the same features on each. The stand runs the latest
  patches (2.3.0.2, 3.0.5.1, 4.1.0.4); nothing older is promised.
- **Distribution:** one monorepo, three zips per release (`neocarts-oc{2,3,4}-X.Y.Z.ocmod.zip`) on
  GitHub Releases; the OpenCart Marketplace later, with the same zips.
- **Orders from Neocarts are ordinary OpenCart orders.** Only the payment method «Neocarts (…)»,
  the order comment and the first history entry (number, checkout / renewal, a link to
  `https://shop.neocarts.com/`) show where they came from. No custom order screens.
- **Every `order.paid` becomes an order**, the first checkout and every renewal (each switchable).
- **Minimal admin (2026-11-15, replaces the earlier subscriptions page / tile / mirror / reprice
  button).** The plugin does in OpenCart only what the Neocarts panel cannot: the settings page
  (connection, placement, checkout, orders) and, on the product form, the «Subscription available»
  switches + a link to the panel. Subscriptions, «Change subscription price», payments, customers,
  the webhook log with «Resend» and team notifications are in the Neocarts panel
  (https://shop.neocarts.com/). Webhooks the shop does not need (`subscription.*`, `payment.failed`)
  are acknowledged as `ignored`; a processing error goes back in the response body so it shows in the
  panel's webhook log.
- **Product card syncs on save, the price never does** (prices are changed in the Neocarts panel).
- **Subscribing adds to the cart.** When the shopper picks a subscription in the widget, the product
  is added to the cart (as the demo does).
- **Variants without a table:** `external_id` = `{product_id}` or `{product_id}-{povid}.{povid}` (the
  sorted `product_option_value_id`s of choice options). The webhook rebuilds the options from it;
  the human text goes to `variant`. The user explicitly doubted a mapping table.
- **Products with required options** get a button only in the cart (their variant is known there).
- **Subscription on by default, switched off per product or per option value** (`neocarts_product`
  keyed by external_id: `42`, `42-17`). Out-of-stock / disabled products get no button and always go
  one-time.
- **Languages:** en and uk from day one; a language is one file in `src/shared/lang/`.
- **Currency: convert nothing.** See `money.md`.
- **Highly configurable:** every placement can be switched off and moved by selector/position;
  every webhook use has its own switch.
- **Support email in the admin:** every plugin admin page shows support@neocarts.com (mailto) and a
  link to the plugin docs; error states repeat it. Addresses live only in `Neocarts\Links`.
