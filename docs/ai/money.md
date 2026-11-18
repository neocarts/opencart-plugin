# Money

- The user's rule (2026-11-15): **nothing is converted.** No exchange rates, no `currency->convert`,
  no «the store must have UAH» check. The price goes out as the store has it, in the store's
  default currency (`config_currency`), tax applied per `config_tax`, as integer minor units.
- `Neocarts\Money::minor()` is the only place a price becomes a Shop API integer, `Money::major()`
  the only way back (webhook amounts → OpenCart order totals). Keep it that way: a future admin
  switch «send the store currency» will change exactly these two places plus a `currency` field.
- The Shop API has no currency field today (it treats amounts as kopecks). When core adds one, send
  `config_currency` as is — Neocarts will handle it.
- OpenCart orders created from webhooks use the store default currency with `currency_value` 1.
- The Docker stand makes UAH the default currency and multiplies the demo prices by 40 — only so the
  demo looks Ukrainian. The plugin must work the same with any default currency.
