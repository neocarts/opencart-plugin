# Neocarts for OpenCart

**English** · [Українська](README.uk.md)

> 🇺🇦 Документація українською: [README.uk.md](README.uk.md) · [docs.neocarts.com](https://docs.neocarts.com/plugins/opencart/overview/)

This plugin adds [Neocarts](https://neocarts.com) product subscriptions to an OpenCart store. A
shopper can subscribe to pet food, coffee or cosmetics and get it delivered automatically, every
week, every month or at whatever interval suits them. Neocarts takes care of payment, recurring
charges and emails to shoppers. Every paid order, the first one and each renewal, shows up in your
OpenCart admin as a regular order.

Full documentation: [docs.neocarts.com](https://docs.neocarts.com/en/plugins/opencart/overview/).
Questions: support@neocarts.com.

## OpenCart versions

| OpenCart | File to download | PHP |
| --- | --- | --- |
| 2.3 | `neocarts-oc2-X.Y.Z.ocmod.zip` | 7.1+ |
| 3.0 | `neocarts-oc3-X.Y.Z.ocmod.zip` | 7.1+ |
| 4.x | `neocarts.ocmod.zip` — don't rename it | 8.0+ |

The files are under [Releases](https://github.com/neocarts/opencart-plugin/releases/latest). We test
the plugin on 2.3.0.2, 3.0.5.1 and 4.1.0.4.

## Installation

1. **Upload the file in the admin.** On OpenCart 3.0 and 4.x use the Extension Installer (on 4.x,
   also click «Install» once it's uploaded). The OpenCart 2.3 installer only works over FTP, so
   either enable FTP in the store settings or just copy the archive's `upload/` folder into the
   site root.
2. **Turn the module on.** Extensions → Modules → Neocarts → «Install», then «Edit».
3. **Connect your store.** In the Neocarts panel, open «Settings» → «Integration». Copy the «Shop
   ID», «API key» and «Signing secret» into the plugin's «Connection» tab. Click «Test connection»;
   you should see «The key works.»
4. **Set up webhooks.** The same tab shows a «Webhook URL». Paste it into the «Webhook URL» field
   in the Neocarts panel. Without it, paid orders won't reach OpenCart.

To update, upload the new version over the old one. There's no need to uninstall first; your
settings are kept.

## What it does

- **A subscribe button** on the product page, on catalog cards and in the cart. You can turn each
  spot off, move it or restyle it. Several popular themes are already set up.
- **Checkout.** If the cart has at least one subscription, the order is placed in the Neocarts
  window. Other carts check out as before.
- **Orders in OpenCart.** Every paid order and every subscription renewal becomes a regular order,
  with the shopper's and the delivery details. Cancellations and refunds update its status.
- **Which products.** Subscriptions are available for every product out of the box. You can turn
  them off for a single product or a single variant, such as one size. Out-of-stock products don't
  get the button.
- **Subscription prices** are changed only in the Neocarts panel. Changing a product's price in
  OpenCart doesn't change what existing subscribers pay.
- **Languages:** English and Ukrainian.

## For developers

```sh
cp .env.example .env   # demo store credentials; .env stays out of git
make up                # OpenCart 2.3 / 3.0 / 4.1 on :8082 / :8083 / :8084, login admin/admin
make test              # unit tests on PHP 7.1 and 8.3
make e2e               # browser tests on all three stores
make build             # the three archives in dist/
npx changeset          # describe your change for the next release
```

How the project is built and the rules we follow: [`CLAUDE.md`](CLAUDE.md) and
[`docs/ai/`](docs/ai/README.md). How to ship a new version:
[`docs/ai/releasing.md`](docs/ai/releasing.md). What changed: [`CHANGELOG.md`](CHANGELOG.md).

## License

Copyright © 2026 Neocarts. Released under the [GNU General Public License v3.0](LICENSE), the same
license as OpenCart. The Neocarts name and logo are not covered by it.
