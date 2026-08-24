# Proposal: Label Management Admin

## Summary

Add the promised app-wide label management to the Planix admin settings: a "Label Management" CnSettingsSection where a Nextcloud admin can create, edit, and delete the shared labels that tasks reference. The `label` schema, seed labels, and the `task.labels` UUID array all exist (`register-schemas` spec) — but no surface manages them, leaving "shared vocabulary" labels effectively frozen at the seed set. Closes row 2 of the 2026-06-11 feature re-evaluation (`FEATURE-REEVALUATION-2026-06-11/planix.md`).

## Motivation

README promises "Admin Settings — Configurable admin panel for default columns, **label management**, and OpenRegister initialization"; FEATURES.md lists "Label management (create, edit, delete app-wide labels)" as **MVP** with justification "Shared vocabulary". The board already filters by label and task cards show label chips (`tasks.md` acceptance criteria), and `register-schemas` seeds `Bug`/`Feature`/etc. — yet `admin-user-settings.md` contains zero label requirements. Without CRUD, teams cannot adapt the vocabulary to their workflow, which undercuts the cross-project categorization story that labels exist for.

## Affected Projects

- [x] Project: `planix` — admin settings "Label Management" section, label CRUD against the OR `label` schema, server-side delete cascade over `task.labels`
- [ ] Project: `openregister` — none (plain object CRUD on an existing schema)

## Scope

### In Scope

- "Label Management" CnSettingsSection on the Planix admin settings page: list all labels (color chip, title, description, usage count), create, edit (title/color/description), delete
- Color picker honoring the schema contract (`^#[0-9A-Fa-f]{6}$`, default `#4376FC`); invalid colors rejected client- and schema-side
- Usage count per label (number of tasks whose `labels` array contains the label's UUID)
- Delete with a usage-aware confirmation, and a **server-side cascade** that removes the deleted label's UUID from every task's `labels` array — no dangling references
- Rename/recolor propagation: tasks reference labels by UUID, so edits reflect everywhere (cards, filters) without touching tasks

### Out of Scope

- Per-project (non-app-wide) labels — not promised; labels are deliberately a shared vocabulary
- Assigning labels to tasks and filtering the board by label — already covered by `tasks.md` / `kanban-board.md`
- Label merge, label archiving, label-based reporting ("Label/category distribution chart" is FEATURES.md V1)
- Allowing non-admins to manage labels (a "groups may manage labels" knob would be a V1 access-control follow-up alongside `allow_project_creation`)

## Approach

The list/create/edit parts are plain OR object CRUD on the existing `label` schema and run from the admin settings frontend via the OR API (ADR-022 — no planix pass-through controller for them). Two pieces need planix backend logic, both in `SettingsService`/`SettingsController` (admin-gated like the rest of the settings endpoints):

1. **Usage counts** — one aggregated query over tasks per listing (count tasks per label UUID), returned alongside the labels so the UI shows "used by N tasks" and the delete dialog can warn.
2. **Delete cascade** — deleting a label removes the label object AND sweeps every task whose `labels` contains its UUID, saving each task without that entry (server-authoritative; a frontend-driven sweep would race and could partially fail silently). The endpoint is admin-only and idempotent (re-running after a partial failure completes the sweep).

Both add real logic beyond pass-through, so gate-17 is satisfied.

## New Dependencies

None (no new composer/npm packages).

## Cross-Project Dependencies

None. The `label` schema, seed data, and OR object CRUD all exist.

## Impact

- `src/views/settings/AdminRoot.vue` (admin settings) — new "Label Management" CnSettingsSection (after the existing sections, CnVersionInfoCard stays first per conventions)
- `src/modals/` — label create/edit dialog and delete-confirmation dialog as separate files (modal-isolation rule)
- `lib/Service/SettingsService.php` + `lib/Controller/SettingsController.php` — label listing with usage counts; admin-only cascade delete
- `appinfo/routes.php` — routes for the two admin label endpoints (usage listing, cascade delete) with explicit auth posture
- `openspec/specs/admin-user-settings.md` — gains the label-management requirements (via this delta)
- i18n — en/nl strings for the section, dialogs, and validation messages

## Risks

### Risk 1: Cascade delete over many tasks is slow or partial
**Severity:** Medium — sweeping `task.labels` arrays touches every referencing task. **Mitigation:** server-side batch with idempotent re-run semantics (spec requires re-running to complete); usage counts in the dialog set expectations; planix-scale task counts (thousands, not millions) keep this in one request.

### Risk 2: Concurrent label edits from two admin sessions
**Severity:** Low — last-write-wins on the label object is acceptable; the cascade keys on UUID so a rename during delete cannot strand references.

### Risk 3: Seed re-import resurrects deleted seed labels
**Severity:** Low — the register import is idempotent by slug/uuid; the spec pins that re-running the import MUST NOT recreate labels an admin deleted (matching the existing "re-import neither duplicates nor resets" install semantics).

## Rollback Strategy

The section, dialogs, and the two endpoints are additive — reverting the commit restores the previous admin page. Labels created/edited through the UI are ordinary OR objects and remain valid; no schema change and no migration are involved.
