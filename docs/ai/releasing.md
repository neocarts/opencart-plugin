# Releasing

Releases run on **Changesets** (`@changesets/cli` 3, `changesets/action@v2`), the same flow as the
user's other projects. Nobody edits the version, the changelog or tags by hand.

## Every change

- A user-visible change adds a changeset in the same PR: `npx changeset` → `patch` (a fix), `minor`
  (a feature or a setting), `major` (settings or tables change without a migration, an OpenCart
  branch or a PHP version is dropped) + one line for merchants. Tag a branch-only change
  `[oc2]`/`[oc3]`/`[oc4]`. `.changeset/README.md` says the same for humans.
- The version lives in `package.json` (`version`, the only source). `build/build.php` writes it into
  `NEOCARTS_VERSION`, `install.xml` (2.3/3.0), `install.json` (4.x) and the zip names.

## A release

`ci.yml` only checks (lint, unit, e2e on all three branches, a build artifact); it never tags or
publishes. `.github/workflows/release.yml` runs on `workflow_run` **after CI passed** on `main` or
`release/*`, on the exact commit CI tested:

1. Pending changesets → the bot opens or updates the PR «🚀 Release» (`changeset-release/<branch>`):
   it bumps `package.json`, writes `CHANGELOG.md` (`## X.Y.Z` / `### Minor Changes` …) and deletes
   the changeset files.
2. Merging that PR → CI runs on the merge commit → when green, the bot runs `changeset git-tag`
   (`privatePackages.tag`) and pushes `vX.Y.Z`.
3. The same job sees a tag without a GitHub Release, checks the `## X.Y.Z` changelog section, builds
   the three zips and creates the Release. «Latest» only for the highest stable version;
   `X.Y.Z-beta.N` is a pre-release (`npx changeset pre enter beta` / `pre exit`). The step is
   idempotent: a tag that already has a Release is skipped, a tag left without one (a failed run)
   gets it on the next green CI.
4. The docs agent updates `plugins/opencart/changelog` from `CHANGELOG.md`.

Nothing is tagged or released from a red commit. Never tag by hand.

**`BOT_TOKEN`** (repo secret): a token that can push and open PRs (a fine-grained PAT or a GitHub App
token with contents + pull-requests write). The bot opens the release PR with it, so CI runs on that
PR; a PR opened with the default `GITHUB_TOKEN` would not start CI.

## Major lines and fixes for an older major

- Before the first `major` changeset lands on `main`, branch off the old line:
  `git branch release/1.x v1.N.M && git push origin release/1.x`.
- A fix for 1.x: land it (with its `patch` changeset) on `main` if it applies there, then
  `git cherry-pick` it onto `release/1.x` (the changeset file comes along). The bot opens
  «🚀 Release (release/1.x)» into that branch; merging it tags `v1.N.(M+1)`; CI releases it without
  «Latest».
- `ci.yml` → `branch-guard` fails when a `release/N.x` push or PR moves `package.json` off major N.
- Each branch keeps its own `CHANGELOG.md`; the docs changelog page merges both lines.

## Migrations and upgrades

The stored setting `schema_version` is compared with `Schema::VERSION` on install and when the
module's admin page opens; numbered idempotent migrations run in order. Upgrading = uploading the
new package over the old one, no uninstall; settings and data are kept. A major may change tables,
but only through `Schema::migrate`.

## What the merchant installs

- 2.3: `neocarts-oc2-X.Y.Z.ocmod.zip`. The 2.3 Extension Installer copies `upload/` **over FTP
  only** (`installer.php` → `ftp()` refuses without `config_ftp_status`), so the docs offer both
  ways: enable FTP in the store settings, or copy `upload/` into the store root by hand; then
  Extensions → Modules → Neocarts → Install.
- 3.0: `neocarts-oc3-X.Y.Z.ocmod.zip` through Extensions → Installer (no FTP needed), then Modules →
  Install.
- 4.x: `neocarts.ocmod.zip` — **the name must stay `neocarts.ocmod.zip`**: 4.x takes the extension
  code (and so the directory `extension/neocarts` and the PHP namespace) from the file name.
