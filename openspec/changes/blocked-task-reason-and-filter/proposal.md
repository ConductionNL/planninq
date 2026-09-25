---
kind: code
---

# Proposal: Blocked Tasks Are Invisible on the Board (Issue #640)

## Why

On the planning board a blocked task looks exactly like a task that is not
blocked. The board already has a Blocked column and the card already renders a
"Blocked" status chip for `status === 'blocked'`, but the *reason* a task is
waiting is stored nowhere and shown nowhere, so a planner has to open every task
to find out what is holding it up. There is also no way to narrow the board down
to the tasks that are waiting, which is the one view a planner needs in a
stand-up. Issue #640 asks for both: a mark on the card that carries a short
reason, and a board filter for blocked tasks.

## What changes

- A task can carry a short free-text reason for being blocked, entered by a
  person on the task's detail surface and stored on the task object in the
  `planninq` register.
- A blocked task's card on the board shows the reason without the task being
  opened, next to the existing status chip.
- The board's filter row gains a "Blocked" filter that shows only blocked tasks
  and hides the rest, and that composes with the existing label filter rather
  than replacing it.
- Clearing the reason and moving the task off `blocked` removes the mark and the
  reason from the board, and the task drops out of the blocked filter.
- A board with no blocked tasks renders exactly as it does today.

## Out of scope

- The dependency-derived blocked state (`isBlocked` / `deriveBlockedTaskIds`,
  `src/components/BlockedBadge.vue`, `src/store/dependencies.js`). This change
  does not wire `BlockedBadge.vue` and does not change the `dependency` schema.
- A general task create/edit dialog. The reason is entered on the existing task
  detail surface; a full task editor is a separate change.
- Renaming, repurposing or extending the `status` enum. The enum is frozen in
  `openspec/specs/register-schemas/spec.md`.
- Reflecting filter state in the URL hash. The existing label filter does not do
  this and this change does not add it.
- The stale "exactly 5 schemas" wording in
  `openspec/specs/register-schemas/spec.md`, which is a pre-existing defect.

## Impact

- **App**: `planninq`.
- **Capability**: `kanban-board` (the per-project board), extending the existing
  "Blocked task indicator on cards [V1]" requirement.
- **Data**: one additive nullable string property `blockedReason` on the `task`
  schema in `lib/Settings/planninq_register.json`, with a register version bump
  so `SettingsService` re-imports. No new schema, no new register, no change to
  `slug`, `tablePrefix` or `folder`.
- **Surfaces touched**: `src/utils/taskHelpers.js`, `src/components/TaskCard.vue`,
  `src/views/ProjectBoard.vue`, `src/views/TaskDetail.vue`,
  `src/store/projects.js`, `l10n/en.json`, `l10n/nl.json`.
- **Sibling apps**: none. `openregister` owns the objects and schemas and is
  consumed, not duplicated.
- **Migration / breaking**: none. The new property is nullable and absent on
  existing tasks; the filter row must render even when the instance has no
  labels, so the blocked filter is not hidden behind `v-if="labels.length"`.
