# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Documentation for AI

`docs/ai/` holds documentation for AI agents working on this repository. Its index is imported
below, so it is always in context: **open every file listed there that is relevant to what you are
about to do** before starting; these documents record project-specific decisions that you cannot
infer from the code alone.

@docs/ai/README.md

**Keep `docs/ai/` and this file up to date — every task.** At the end of every task, record anything
non-obvious you learned that the next agent would otherwise rediscover: a difference between
OpenCart branches, a workaround, a decision the user made. Do it on your own initiative, in the same
task, without asking, and add a row to the table in `docs/ai/README.md`. When a convention or a
command here changes, change this file.

**Keep the public docs site up to date — every task, through a separate agent.** Anything a merchant
sees (a setting, a placement, what an order or a webhook does, installation, versions, the
changelog) is documented in `~/WebstormProjects/neocarts-docs` under the «Плагіни / Plugins» group.
Before you finish, hand the change to a **separate agent** with the brief described in
`docs/ai/docs-site-sync.md`; that agent works by `neocarts-docs/CLAUDE.md` and its `docs/ai/`, in
both locales, and `npm run build` there must pass. A task is not done while the site describes the
old behaviour.

## Critical functionality is always tested

No change to the checkout flow, the order the shop sends, a webhook, an OpenCart order created from
one, a setting or a price call ships without a test that would fail if it regressed: a case in
`tests/php/*Test.php` for `src/shared`, a Playwright case in `tests/e2e/*.spec.js` for behaviour in a
shop. The e2e suite runs the **same spec on all three branches** — a branch that needs a different
test is a bug in its adapter, not in the spec. Run `make test` and `make e2e` before considering a
change done.

## What this is

The Neocarts subscriptions plugin for OpenCart **2.3, 3.0 and 4.x**, the same features on every
branch, modelled on `~/WebstormProjects/neocarts-demo` (the reference integration). The contract it
implements is the public docs (`neocarts-docs`: Shop API, widget, webhooks) — never invent
behaviour of Neocarts; when the docs and the core disagree, the core
(`~/projects/localhost/neocarts-core`) wins and the docs get fixed.

## Structure

```
src/shared/php/     the core, PHP 7.1+, no OpenCart dependencies (namespace Neocarts\)
src/shared/lang/    every text, one file per language (en-gb.php, uk-ua.php)
src/shared/js/      neocarts.js — the storefront, one file for all branches
src/oc2/upload/     OpenCart 2.3 adapter (.tpl)
src/oc3/upload/     OpenCart 3.0 adapter (.twig)
src/oc4/neocarts/   OpenCart 4.x adapter (extension/neocarts)
build/              build.php → dist/ (neocarts-oc2/oc3-<version>.ocmod.zip, neocarts.ocmod.zip); webhook-replay.php
docker/             the demo stand image (one Dockerfile, branch via build args)
tests/php/          dependency-free runner + *Test.php for src/shared
tests/e2e/          Playwright: one suite, projects oc2/oc3/oc4; mock-core.mjs; shops/<branch>.js
tests/fixtures/     webhook bodies (tests and make webhook-replay)
docs/ai/            knowledge for agents
.changeset/         Changesets: one file per user-visible change; the version is in package.json
CHANGELOG.md        written by Changesets
```

## Commands

```sh
make up                 # the stand: :8082 OC 2.3.0.2 · :8083 OC 3.0.5.1 · :8084 OC 4.1.0.4 (admin/admin) + a mock core per shop :8792/:8793/:8794
make restart S=oc3      # re-link plugin files after adding a new one (2.3/3.0 link file by file)
make reset S=oc3        # wipe one shop and reinstall it
make test               # src/shared on PHP 7.1 and 8.3
make lint               # php -l on 7.1 and 8.4
make e2e [P=oc3]        # Playwright against the running stand
make build              # the three zips
make webhook-replay E=order.paid S=oc3   # sign tests/fixtures/<E>.json with .env's secret and POST it
make sandbox            # clean OC 4.1.0.4 on :8090 (uk-ua, UAH, BeFree, admin/admin) to install dist/neocarts.ocmod.zip by hand
make sandbox-reset      # wipe the sandbox and rebuild it pristine · make sandbox-down stops it
```

## Conventions

- **Events only.** The plugin hooks into OpenCart through its event system — no OCMOD file edits,
  no theme changes. Buttons are placed by `neocarts.js` with CSS selectors from the settings.
- **Shared first.** Logic that is not about one branch's API goes to `src/shared` and is tested
  there. Adapters are thin and mirror each other: a change in one branch is made in all three in
  the same task (`docs/ai/adapter-contract.md`).
- `src/shared/php` is **PHP 7.1 syntax**: no typed properties, arrow functions, `match`, union types,
  named arguments or `?->`; explicit nullable parameters (`?int $x = null`). It must run without
  deprecations up to PHP 8.5.
- **No texts in code.** Every string is a key in `src/shared/lang/*.php`; every language file has
  every key (a test checks). Adding a language = adding one file. Storefront texts reach the page
  through the inline config, never hard-coded in JS.
- **Money is never converted.** The shop's price goes out as is (`Money::minor()`), in the store's
  default currency; currency handling is Neocarts' job (`docs/ai/money.md`).
- **Prices come from the shop's server** — the browser only says which lines are subscriptions.
- **The plugin never sends a price for running subscriptions**: the merchant changes them in the
  Neocarts panel; product save syncs the card only.
- Orders from Neocarts are **ordinary OpenCart orders** created through OpenCart's own order model;
  only the payment method, the comment and the history mark the source.
- **Secrets live only in `.env`** (git-ignored). Never put a key or a secret in code, tests, docs or a
  commit; the e2e suite uses fake ones.
- Versioning: SemVer, one version for all three builds in `package.json`, managed by Changesets:
  every user-visible change adds `npx changeset` in the same task; the release bot does the
  version, the changelog and the tag (`docs/ai/releasing.md`). Never edit them by hand.
- Git: short imperative sentence-case subjects ("Add the cart line button"), no prefixes.
