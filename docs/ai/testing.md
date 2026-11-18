# Testing

## `make test` — src/shared

`tests/php/run.php` is a dependency-free runner (PHPUnit cannot cover PHP 7.1 and 8.5 with one
version): every `tests/php/*Test.php` defines `test_*` functions, `eq()` / `throws()` assert. It runs
on PHP 7.1 and 8.3. Fixtures are in `tests/fixtures/` (webhook bodies, shared with e2e).

## `make e2e` — the shops

Playwright, one suite, projects `oc2` / `oc3` / `oc4` (baseURL :8082/:8083/:8084), `workers: 1`
(shared databases). Needs `make up`. `make e2e P=oc3` runs one shop: it sets `E2E_SHOPS=oc3`, so the global setup touches only that shop (CI runs one shop per job the same way).

- `global-setup.js` installs the extension in every shop (`shops/<branch>.js` → `install()`),
  saves each shop's plugin settings to `test-results/settings-backup-<shop>.json` and points it at the
  mock core with fake credentials (`E2E`); `global-teardown.js` puts the demo settings back.
- `fixtures.js` (import `test`/`expect` from there): the shop's own mock core (host port `879<branch>`) is reset and the `E2E` settings
  rewritten before every test; the CDN widget is replaced by a stub of exactly what `neocarts.js`
  calls, recorded in `window.__nc` (`open`, `removed`); `__nc.select(id, sel)` is the shopper picking
  a frequency, `__nc.emit(event, detail)` a widget event, `__nc.openError` makes the next `open()`
  reject. `seedSelection()`, `addToCart()` (the shop's own cart route, in the page's session),
  `webhook()` (a signed fixture), `sql()`.
- `shops/<branch>.js` is the only branch-specific test code: install, admin login, routes, product
  form save. Specs never branch on the version.
- Demo data used by specs: product 40 (iPhone, no options), 42 (Apple Cinema 30", required options,
  option values 1 select / 5 radio / 8 checkbox), category 20.
- After e2e the teardown restores the backup **and then** the real demo credentials from `.env`
  (`tests/e2e/demo-config.js`, also `make demo-config`). Without that, a backup taken while a shop
  still held the fake e2e settings left the stand with shop id `00000000-…` (the widget answered 422).
