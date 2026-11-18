# AI documentation

Documentation written for AI coding agents working on this repository. It is not end-user
documentation (that is `neocarts-docs`, «Плагіни») — it records the decisions and branch quirks
that are not obvious from the code.

Read the entry that matches your task before you start.

| File | Read it when |
| --- | --- |
| [`adapter-contract.md`](adapter-contract.md) | Touching any adapter (`src/oc2`, `src/oc3`, `src/oc4`): file locations, routes, events, tables, the webhook sink, the admin page. All three follow it. |
| [`oc-versions.md`](oc-versions.md) | Something behaves differently on 2.3 / 3.0 / 4.x: routes, events, models, templates, installer, the pitfalls found so far. |
| [`decisions.md`](decisions.md) | Before changing behaviour: what the user decided (orders as ordinary orders, no automatic price sync, variants, currency, languages) and why. |
| [`themes.md`](themes.md) | Storefront placement (selectors, presets) or a third-party theme: per-theme selector sets, where our defaults fail, what placements are missing. |
| [`money.md`](money.md) | Anything with prices or currency. |
| [`docker.md`](docker.md) | The demo stand: images, install on first start, how the plugin is linked in, resetting a shop. |
| [`testing.md`](testing.md) | Writing or running tests: the PHP runner, the e2e suite, the mock core, the widget stub, settings backup. |
| [`docs-site-sync.md`](docs-site-sync.md) | At the end of every task with a user-visible change: how to brief the separate docs agent, the map from code to docs pages. |
| [`releasing.md`](releasing.md) | Changesets, the release bot, tags, major lines (`release/N.x`), migrations, what the merchant installs. |

## Adding a document here

Add a file when you learn something a future agent would otherwise have to rediscover: a
workaround, a branch difference, a decision. Keep it short and concrete, and add a row to the table
above. Structure, commands and conventions belong in the root `CLAUDE.md`, not here.
