# Design: Label Management Admin

## Summary

A "Label Management" CnSettingsSection on the admin settings page, doing plain OR CRUD on the existing `label` schema for list/create/edit, plus two thin planix admin endpoints for the parts that need server logic: usage counts and the delete cascade over `task.labels`.

## Data model (unchanged)

`register-schemas` already defines everything needed:

- `label`: `title` (required), `color` (required via default, `^#[0-9A-Fa-f]{6}$`, default `#4376FC`), `description` (optional)
- `task.labels`: `array` of label UUIDs (`format: uuid`), default `[]` — **reference by UUID**, which is what makes rename/recolor propagation free and the delete cascade a pure array-element removal

No schema delta, no migration.

## Surface

- Section placement: after the existing "Default Project Configuration" / "Register Setup" sections in `AdminRoot.vue`; CnVersionInfoCard remains first (Conduction convention).
- List: one row per label — color chip, title, description, "used by N tasks", edit + delete actions. Sorted by title.
- Create/edit: a single `LabelEditDialog.vue` (own file under `src/modals/`, modal-isolation rule) with title (required), NC color picker constrained to 6-digit hex, description. Client-side validation mirrors the schema pattern; the schema remains the authoritative validator (400 on violation).
- Delete: `LabelDeleteDialog.vue` showing the usage count — "Delete label 'Bug'? It will be removed from 12 tasks." Destructive-styled confirm.

## Backend split (ADR-022 / gate-17)

| Operation | Path | Rationale |
|---|---|---|
| List labels, create, edit | OR object API directly from the frontend | pure CRUD on an existing schema — a planix wrapper would be gate-17 redundant |
| Usage counts | planix admin endpoint (`SettingsController`) | aggregation across tasks (count of tasks per label UUID) — real logic, single round-trip for the listing |
| Delete | planix admin endpoint (`SettingsController`) | server-authoritative cascade: delete the label object, then sweep every task whose `labels` contains the UUID and save it without that element. Idempotent: re-running after a partial failure completes the sweep (label already gone → only the sweep runs) |

Both endpoints are admin-only (no `#[NoAdminRequired]` — NC SecurityMiddleware default enforces admin), registered in `appinfo/routes.php` with explicit auth posture per gate-5.

## Cascade semantics

1. Resolve all tasks with the UUID in `labels` (OR filtered query).
2. Delete the label object first? **No** — sweep first, delete last: if the sweep partially fails the label still exists and the admin can retry; dangling-reference windows are avoided entirely. Re-run completes remaining tasks.
3. Each task update goes through OR's normal save (audit-trailed, RBAC-exempt because the endpoint is admin).
4. Response reports `{tasksUpdated: N}` for the success toast.

Seed-label note: the register import is idempotent on slug/uuid and must not resurrect a deleted seed label or reset an edited one — same install semantics `register-schemas` already requires for schemas ("re-import neither duplicates nor resets"), now pinned for label objects in the spec delta.

## Propagation

Because tasks store UUIDs, rename/recolor needs zero task writes: chips on cards, the board label filter, and the backlog re-render from the label objects. The spec pins this as observable behavior (edit → card chip reflects new title/color on next load) so a future refactor cannot silently switch to by-title storage.

## Testing strategy

- **PHPUnit:** usage-count aggregation shape; cascade removes the UUID from all referencing tasks and leaves other labels untouched; idempotent re-run; admin-only posture (no NoAdminRequired attribute); color pattern enforced by schema (static assertion on `planix_register.json` unchanged).
- **Newman (API):** create label via OR API → appears in listing with count 0; attach to a task → count 1; invalid color → 400; non-admin calls the cascade-delete endpoint → 403 (admin-only contract); delete → label gone AND the task's `labels` no longer contains the UUID; register re-import does not resurrect it.
- **Playwright (UI only):** admin opens settings → section lists seed labels with usage counts; create "Tech debt" with custom color → appears; edit color → chip updates on a task card; delete shows usage warning and removes the chip from the board.
