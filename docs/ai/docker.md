# The demo stand

`docker compose` (`make up`): `db` (MariaDB 10.6, databases oc2/oc3/oc4, host port 33306),
`oc2` :8082, `oc3` :8083, `oc4` :8084, and a mock Shop API per shop: `mock-core-ocN` (host :8792/:8793/:8794) — separate so parallel e2e runs never share state. Admin: `/admin/`, admin / admin.

- One image, `docker/opencart/Dockerfile`; the PHP base and the release zip (pinned URL + sha256)
  are build args. 2.3 runs on **PHP 7.1**: its CLI installer refuses to run without `mcrypt`, which
  only 7.1 bundles. 3.0.5.1 on 8.2, 4.1.0.4 on 8.3.
- First start (`docker/opencart/entrypoint.sh`): the release is copied into the `ocN` volume, the CLI
  installer runs, `uah.sql` makes UAH the default currency and prices ×40. The seed runs with
  `--force`: tables differ between branches (4.1 has no `oc_product_special`).
- The plugin is linked live: 2.3/3.0 get a symlink per file of `src/ocN/upload/` (a **new** file needs
  `make restart S=ocN`) plus `system/library/neocarts` → `src/shared` and the JS. 4.x bind-mounts
  `src/oc4/neocarts` as `extension/neocarts`, with nested mounts for the shared core (their empty
  mountpoints in `src/oc4/neocarts` are git-ignored).
- Shop ocN reaches its mock core as `http://mock-core-ocN:8790` (set by the e2e setup, put back after).
  With the real core (`.env`), checkout opens the real Neocarts dialog of the demo shop.
- Pitfalls: Debian archive mirrors for the old 7.1 image; the MariaDB 11 client in the new images
  insists on TLS (`skip-ssl` in `/etc/mysql/conf.d`); PHP 7.1's tzdata knows `Europe/Kiev`, not
  `Europe/Kyiv`; GD configure flags differ before PHP 7.4.
- Webhooks from the real core cannot reach localhost: use `make webhook-replay` (signs a fixture with
  `NEOCARTS_WEBHOOK_SECRET` from `.env`), or a tunnel to one shop.
- `dns_opt: single-request-reopen` on the shops: without it PHP curl to `mock-core-ocN` sometimes
  stalled 5 s («Resolving timed out»), the glibc parallel A/AAAA race on Docker's DNS.

## Third-party themes

`make themes` installs the demo themes into all three shops (`S=oc3` for one). It is idempotent and
never changes the active theme. `make theme S=oc3 T=ff_frame` switches one shop, `T=default` goes
back, and with no `T` it prints the status. The switch prints the shop URL.

| Shop | `T=` | config_theme | Archive (pinned in `docker/themes/themes.php`) |
|---|---|---|---|
| oc2 | `materialize` | `materialize` | trydalcoholic/opencart-materialize @845baaa, `archive/opencart_2.3.x` |
| oc2 | `xezpro` | `xezpro` | givanz/xezpro release 1.0 |
| oc3 | `ff_frame` (or `framefree`) | `framefree` | stickpro/frame_free release 1.0.0 |
| oc3 | `luxury` | `luxury` | DesignCart/theme_luxury @ded47f8 (`theme.zip` inside) |
| oc4 | `dc_toys` | `dc_toys` | DesignCart/oc-dc-toys-4 @8157fc8 |
| oc4 | `befree` | `befree` | Intense-Finance/BeFree release v1.2.5 |

- `docker/themes/themes.php` runs inside the shop (`docker compose exec -T ocN php -- <cmd> < themes.php`),
  so no mount and no restart are needed. It downloads the archive to `/var/www/html/.neocarts-themes/_download/`
  and checks its sha256. Then it copies files only; it runs nothing from the archive. State per
  theme lives in `.neocarts-themes/<code>/`: `files.list`, `override/`, `events.list`, `ocmod.list`.
- **Install** does the following:
  - copies the files;
  - 2.3/3.0: inserts the theme's OCMOD into `oc_modification` with **status 0**;
  - 2.3/3.0: for themes with an admin page, calls OpenCart's own *Extensions > Themes > Install*
    through the admin (admin/admin), then presses *Save* on the theme form once, so its defaults
    become settings;
  - disables the events the installer added;
  - fills `<code>_*` (2.3) or `theme_<code>_*` (3.0) from `theme_default_*`: image sizes, limits,
    `_directory`, `_status`;
  - 4.x: writes `oc_extension_install`, `oc_extension_path`, `oc_extension` (theme), the theme's
    `oc_startup` row (its listener checks `config_theme` itself), admin permissions and
    `theme_<code>_status`.

  Materialize 2.3 also gets its SQL dump: new tables, plus two columns on
  `oc_product_description` and `oc_product_option_value`.
- **A theme file that would overwrite an existing shop file is not copied.** It is kept in
  `override/`, with the original in `_core/`, and swapped in only while the theme is active. Xezpro
  has 42 such files, including `catalog/controller/event/theme.php` and `system/library/image.php`.
- **Switch** does the following:
  - puts back every other theme's overridden files and disables their OCMOD and events, then turns
    on the target's;
  - sets `config_theme`;
  - refreshes modifications through the admin when the OCMOD set changed. On 3.0 this flips
    `config_maintenance` on for well under a second, so avoid switching during e2e. The first
    refresh also writes OpenCart's own `system/modification.xml` patches into `storage/modification`;
  - clears the data cache.

  Back on `default`, the shop's tree is back to the original. The tables and columns themes added
  remain, as do their new files, which nothing loads.
- `quiet` themes (Materialize 2.3, Frame free) turn `config_error_display` off while they are
  active. The original value is kept in `.neocarts-themes/error_display` and restored on `default`.
  Their own PHP 8 warnings, and the `getimagesize(http://localhost:8082/…)` that the container
  cannot reach, go to the error log instead of the page.
- OpenCart 3.0.5.1 ships Twig 3. `{% for x in y if c %}` (Frame free) is rewritten on copy to
  `{% for %}{% if %}…{% endif %}{% endfor %}`.
- Not installed: Materialize 3.0. Its OCMOD (written for 3.0.2) generates PHP parse errors in
  `catalog/model/catalog/product.php` and `module/slideshow.php` on 3.0.5.1. DC Luxury replaces it.

## Sandbox for manual testing

`make sandbox` builds a clean OpenCart **4.1.0.4** (PHP 8.3) on **http://localhost:8090**, admin at
`/admin/` (admin / admin), for installing `dist/neocarts.ocmod.zip` by hand through
*Extensions → Installer*. The plugin is not installed or mounted and there is no mock core.
`make sandbox-reset` wipes it (db + files) and builds it again pristine; `make sandbox-down` stops it.

- Separate from the stand: `docker/sandbox/compose.yml`, project `neocarts-sandbox`, its own MariaDB
  (no host port) and volumes `neocarts-sandbox_db`/`_web`. Stand commands never touch it, and back.
- The image is the stand's (`docker/opencart`, same release zip + sha256 as oc4), referenced as
  `additional_contexts: base: service:base` (`base` has `scale: 0`: built, never run). On top,
  `docker/sandbox/Dockerfile` adds the Ukrainian files. The first start is the stand's entrypoint:
  CLI install + `uah.sql` (UAH default, prices ×40).
- **Ukrainian**: opencartbot/Ukrainian-Language-OpenCart-4 release v1.0.7 («compatible with
  4.1.0.0»), `https://github.com/opencartbot/Ukrainian-Language-OpenCart-4/releases/download/v1.0.7/ukrainian.ocmod.zip`,
  sha256 `5438f169191aeea9ba57c7860f2715fa4492157b0ae4303e2259eb92a696e662`. Only
  `admin|catalog/language/uk-ua/` is taken from it. Against 4.1.0.4's en-gb it has ~92 % of the admin
  keys and ~83 % of the storefront keys; the files 4.1 added are missing (authorize/2FA, subscription
  mails, `product/related`, `error/exception`). OpenCart 4 has no fallback language and would print
  the key, so each uk-ua file is built as the en-gb file followed by the translation: gaps are
  English. Its one syntax slip (`'…'.;` in `information/gdpr_success.php`) is fixed on the way.
- `make sandbox` then runs once (marker `.sandbox-ready` in the web root):
  `docker/sandbox/setup.php` adds uk-ua through the admin's own language form (it copies every
  description, so product names stay the English sample data), copies the store meta title, and sets
  `config_language_catalog` + `config_language_admin` = `uk-ua`; English stays installed. Then
  `docker/themes/themes.php install befree` + `activate befree`, the same script as the stand.
- The installer works as is: `upload_max_filesize`/`post_max_size` 32M (`docker/opencart/php.ini`),
  `system/storage/marketplace` writable by www-data. Checked by uploading and installing
  `dist/neocarts.ocmod.zip` over HTTP, then resetting.
- 4.1's admin login needs a `login_token` from the login page and a User-Agent (a null one breaks
  `user/user.php`): scripts that log in over HTTP must send one.
