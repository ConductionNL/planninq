# Design: Blocked Task Reason and Blocked Filter (issue #640)

## Context

The board already has a Blocked column (`BOARD_STATUSES` in
`src/utils/taskHelpers.js`) and the card already renders a "Blocked" status chip
for `status === 'blocked'` (`statusLabel` / `statusVariant` in
`src/components/TaskCard.vue`). What is missing is the *reason* and the *filter*.
The repo also carries a second, unrelated blocked concept: the dependency-derived
state (`isBlocked` / `deriveBlockedTaskIds` in `src/utils/taskHelpers.js`,
`src/components/BlockedBadge.vue`, `src/store/dependencies.js`), which is built
and unit-tested but never mounted on a card.

## Decision: which "blocked" this is

This change is about the **status enum** `blocked`, plus a new free-text
`blockedReason` on the task. It is not the dependency-derived state.

The request's reason is free text a person types ("waiting on design"), not a
reference to a blocking task. The `dependency` schema is exactly two UUID fields
(`blocker`, `blocked`) with server-side cycle validation in
`lib/Service/DependencyService.php`; putting a reason there would force a fake
blocker task or widen a schema whose whole contract is a directed edge, and it
would make "unblock" mean "delete an edge" rather than "clear a flag".

`src/components/BlockedBadge.vue` is therefore **left alone** in this change. It
stays unwired. The card's blocked mark is the existing status chip; the new
information on the card is the reason line. This avoids two "Blocked" signals on
one card. Reconciling the dependency-derived badge with the status chip is a
separate change and is listed as out of scope in the proposal.

## Data model

One additive property on the `task` schema in
`lib/Settings/planninq_register.json`:

| Property | Type | Required | Default |
|----------|------|----------|---------|
| `blockedReason` | string \| null | No | null |

The register `version` is bumped so `SettingsService` re-imports. `slug`,
`tablePrefix` and `folder` are not touched — the file header warns that the
register slug rename is coupled to `MigrateRegisterSlug`.

The `status` enum is not changed. It is frozen in
`openspec/specs/register-schemas/spec.md` and asserted by a static parity test,
and `src/manifest.json`'s `TaskStatusReport` dashboard filters
`{ "status": "blocked" }`.

## Where the code goes

- **`src/utils/taskHelpers.js`** — `isTaskBlocked(task)`, the reason truncation
  helper, and the pure blocked-filter helper. This file is the established home
  for board-derived state (`BOARD_STATUSES`, `groupTasksByStatus`, `isBlocked`,
  `dueDateStatus`).
- **`src/components/TaskCard.vue`** — the reason line and its tooltip. The
  status chip is unchanged.
- **`src/views/ProjectBoard.vue`** — the "Blocked" filter chip and its fold into
  `visibleTasks`, copying the `labelFilterChips` / `setLabelFilter` /
  `aria-pressed` / `data-testid="label-filter-chip"` idiom.
- **`src/views/TaskDetail.vue`** — the blocked toggle and reason field. This is
  the task's single detail surface and already writes to the task (the estimate
  save path). A full task create/edit dialog is out of scope.
- **`src/store/projects.js`** — the patch action carrying `status` and
  `blockedReason`, through the shared object store (ADR-022). No new controller:
  a controller whose body is a pass-through to `ObjectService` is dead code the
  moment the frontend uses the store.
- **`l10n/en.json` + `l10n/nl.json`** — new strings, kept in parity.

## Truncation rule

The reason line is capped at a fixed character count (the exact number is set in
the helper and asserted by its unit test). A reason at or under the cap renders
in full. A longer reason renders as the capped prefix with an ellipsis, and the
full text is available from the card as a `title` tooltip. The helper is pure so
the rule is testable without a browser.

## Filter composition

The blocked filter composes with the label filter: `visibleTasks` applies both,
so selecting "Blocked" and a label narrows to blocked tasks carrying that label.
The filter row must render even when the instance has no labels — today it is
guarded by `v-if="labels.length"`, and leaving that guard would make the blocked
filter disappear on a label-less instance. Filter state is not written to the URL
hash; the existing label filter does not do this and this change does not add it.

## Risks

- **Register re-import.** The version bump triggers a re-import via
  `SettingsService` / `lib/Repair/InitializeSettings.php`. The new property is
  nullable and additive, so existing tasks are unaffected.
- **Other readers of the task schema.** `src/views/Portfolio.vue`,
  `src/views/TaskDetail.vue` and the `TaskStatusReport` dashboard read task
  fields. Adding a nullable property is safe; renaming or repurposing `status`
  is not, and is not done here.
- **e2e selectors.** `tests/e2e/kanban-board.spec.ts` drives
  `[data-cy="kanban-board"]`, `.task-card` and `.task-card__due-date-badge`. New
  markup must not disturb those hooks.
- **Two blocked signals.** Mitigated by leaving `BlockedBadge.vue` unwired and
  documenting the dependency-derived state as out of scope.

## Open questions carried into implementation

1. The exact truncation length is chosen in the helper and pinned by its unit
   test; the request leaves it open.
2. Whether the blocked filter should later be reflected in the URL hash is left
   to the change that adds hash plumbing for the label filter.
