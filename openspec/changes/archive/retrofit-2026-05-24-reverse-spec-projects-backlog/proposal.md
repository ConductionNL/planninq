# Retrofit — projects (Backlog placeholder route)

Describes observed behavior of 1 Vue component under the `projects` capability as 1 new REQ. Code already exists — this change retroactively specifies it.

## Affected code units
- src/views/ProjectBacklog.vue — `/projects/:id/backlog` route placeholder

## Approach
- Read the component and describe observed inputs (route param, projects store),
  outputs (rendered breadcrumb + placeholder), preconditions (project must
  resolve via the store), and failure modes (project fetch failure surfaces via
  the store, not the view itself).
- Draft 1 REQ that matches the observed behavior — a navigable shell rendering
  a placeholder, not a working backlog. The placement of the route +
  breadcrumb is the user-visible behavior worth specifying; the fact that the
  list itself is a stub is captured as a deliberate note so a future
  task-management REQ can replace this without ambiguity.
- The Notes section flags that the route is a deliberate scaffold awaiting
  tasks#REQ-Task-CRUD (Bucket 3b in the coverage report); the placeholder
  copy must stay aligned with the unimplemented task feature until that
  REQ lands.

## Why this is Bucket 2a
The `projects` capability spec exists (openspec/specs/projects.md) but does
not mention the Backlog route — yet the route, view, and breadcrumb are wired
in `src/router/index.js`. Falls into "existing capability, no REQ" — the
coverage scan flagged it for reverse-spec with `--extend projects`.

The other Bucket 2a entry (`register-schemas` → `lib/Settings/planix_register.json`)
is skipped per the coverage report's own note: the scanner enumerates
`.php`/`.vue`/`.js`/`.ts` files but not JSON, and the schema manifest is the
canonical implementation of REQs already covered by Bucket 1 annotations
(`REQ-All-5-schemas-defined`).

Source: openspec/coverage-report.md generated 2026-05-24. See [retrofit playbook](../../../../.github/docs/claude/retrofit.md).
