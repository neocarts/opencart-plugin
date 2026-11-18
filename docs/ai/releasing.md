# Releasing

- **One SemVer version** for all three builds, in `VERSION`. MAJOR: settings or tables change
  without a migration, or a branch is dropped. MINOR: a feature or a setting. PATCH: a fix.
- `CHANGELOG.md` (Keep a Changelog): every user-visible change gets a line under `## [Unreleased]`
  in the same task; tag `[oc2]`/`[oc3]`/`[oc4]` when it is not for every branch.
- `build/build.php` writes the version into `NEOCARTS_VERSION` (`src/shared/php/autoload.php`),
  `install.xml` (2.3/3.0), `install.json` (4.x) and the zip names.
- **Migrations:** the stored setting `schema_version` is compared with the code on install and when
  the module's admin page opens; numbered idempotent migrations run in order. Upgrading = uploading
  the new zip over the old one, no uninstall; settings and data are kept.
- The version is sent to the Shop API in `User-Agent: neocarts-opencart/X.Y.Z (oc …; php …)` and shown
  in the admin, which checks the latest GitHub Release once a day (switchable).
- Release: move `[Unreleased]` to `[X.Y.Z] — date`, bump `VERSION`, tag `vX.Y.Z`; CI checks
  tag = `VERSION` and a changelog entry, builds, and attaches the three zips. Then the docs agent
  updates `plugins/opencart/changelog`.

## What the merchant installs

- 2.3: `neocarts-oc2-X.Y.Z.ocmod.zip`. The 2.3 Extension Installer copies `upload/` **over FTP
  only** (`installer.php` → `ftp()` refuses without `config_ftp_status`), so the docs offer both
  ways: enable FTP in the store settings, or copy `upload/` into the store root by hand; then
  Extensions → Modules → Neocarts → Install.
- 3.0: `neocarts-oc3-X.Y.Z.ocmod.zip` through Extensions → Installer (no FTP needed), then Modules →
  Install.
- 4.x: `neocarts.ocmod.zip` — **the name must stay `neocarts.ocmod.zip`**: 4.x takes the extension
  code (and so the directory `extension/neocarts` and the PHP namespace) from the file name.

## Branches and major versions

- `main` is the next release. A patch or minor is cut from it: bump `VERSION`, move
  `[Unreleased]` to `[X.Y.Z] — date`, commit, `git tag vX.Y.Z`, push the commit and the tag.
- **A major release (2.0.0) that breaks something** (settings without a migration, a dropped
  OpenCart branch or PHP version) first gets a maintenance branch for the old line:
  `git branch release/1.x v1.N.M && git push origin release/1.x`. Then `main` goes to 2.0.0.
- **A fix for the old line:** commit it on `main` first (if it applies there), then
  `git switch release/1.x && git cherry-pick <sha>`. Bump `VERSION` to 1.N.(M+1), add a changelog
  entry, tag `v1.N.(M+1)` **on that branch** and push. CI builds the tag the same way.
- **«Latest» on GitHub:** CI marks a release «Latest» only when its tag is the highest stable
  version, so `releases/latest` never jumps back to 1.x. `X.Y.Z-beta.N` is a pre-release (never
  latest). `build.php` accepts both forms.
- **CHANGELOG on the maintenance branch** lists only that line's releases. When 1.x gets a fix,
  copy its entry into `main`'s CHANGELOG as well, so the docs changelog page stays complete.
- **Merchants and DB schema:** a major may change tables, but only through `Schema::migrate`. An
  upgrade from any 1.x to 2.0 must still work by uploading the new package over the old one.
