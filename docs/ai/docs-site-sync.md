# Keeping neocarts-docs in sync

## Rule

Every user-visible change — a setting, a placement, a default selector, checkout behaviour, what a
webhook does to an order or a status, installation or upgrade steps, supported versions, a release —
is reflected on the public site `~/WebstormProjects/neocarts-docs` in the same task, **by a separate
agent** (the user's rule). Do not edit the site yourself from this repo's task.

## How to brief the docs agent

Spawn one agent (general-purpose) at the end of the task with:

1. what changed, in merchant terms, and the plugin files that are the source of truth (e.g.
   `src/shared/php/Settings.php`, `src/shared/lang/en-gb.php`, `docs/ai/adapter-contract.md`);
2. the pages to touch (table below);
3. the instruction: «Work in `~/WebstormProjects/neocarts-docs` by its `CLAUDE.md` and `docs/ai/`
   (open them first): both locales, labels quoted exactly as in `src/shared/lang/{uk-ua,en-gb}.php`,
   a `sources.md` row per new source, screenshots per `docs/ai/screenshots.md` taken from the Docker
   stand (http://localhost:8082/8083/8084, admin/admin) with the shop id and keys masked, and
   `npm run build` must pass. Do not invent behaviour: read the plugin code.»

## Where things live (code here → page there)

| Changed here | Page slug(s) |
| --- | --- |
| `Settings.php`, `lang/*.php` (admin texts), admin forms | `plugins/opencart/settings` |
| placement settings, `neocarts.js` | `plugins/opencart/placement`, `plugins/opencart/settings` |
| checkout flow (`order`, `clear`, interception, banner) | `plugins/opencart/overview` («Як працює»), `plugins/opencart/troubleshooting` |
| webhook sink, `OrderMapper.php`, statuses, mirror, admin subscriptions | `plugins/opencart/orders-and-webhooks` |
| installer, manifests, Docker-tested versions, PHP minimum | `plugins/opencart/installation`, `plugins/opencart/overview` |
| `README.md` (EN, the main one, links to the UK one at the top) + `README.uk.md` (UK): a short duplicate of the docs (versions, install, links) — keep both in line with `overview` / `installation` | — |
| `package.json` version, `CHANGELOG.md` (after a release PR) | `plugins/opencart/changelog`, `plugins/opencart/overview` (version table) |
