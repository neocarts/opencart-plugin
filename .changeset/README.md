# Changesets

Every user-visible change adds one file here: run `npx changeset`, pick `patch` (a fix), `minor`
(a feature or a setting) or `major` (breaks settings, drops an OpenCart branch or a PHP version) and
write one line for the changelog, as a merchant would read it. Mark a change that concerns one
OpenCart branch only with `[oc2]`, `[oc3]` or `[oc4]`.

The release bot collects these files into a «Release» pull request; merging it bumps the version
and the changelog and tags the release. See `docs/ai/releasing.md`.
