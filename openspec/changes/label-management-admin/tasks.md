# Tasks: Label Management Admin

## 1. Backend (planix admin endpoints)

- [ ] `lib/Service/SettingsService.php`: label usage-count aggregation (tasks per label UUID) and the cascade delete — sweep all referencing tasks first (remove the UUID from `labels`, save via OR), delete the label object last; idempotent on re-run.
- [ ] `lib/Controller/SettingsController.php` + `appinfo/routes.php`: admin-only endpoints for the usage listing and cascade delete (no `#[NoAdminRequired]`; explicit auth posture per gate-5); cascade response reports `tasksUpdated`.
- [ ] PHPUnit: aggregation correctness, cascade (removes only the deleted UUID, other labels untouched), idempotent re-run after partial failure, admin-only posture.

## 2. Frontend (admin settings)

- [ ] `src/views/settings/AdminRoot.vue`: "Label Management" CnSettingsSection (CnVersionInfoCard stays first) — list with color chip, title, description, usage count, edit/delete actions, sorted by title; loading/error/empty states per CnSettingsSection conventions.
- [ ] `src/modals/LabelEditDialog.vue` (own file — modal-isolation): create/edit with required title, 6-digit-hex color picker defaulting `#4376FC`, optional description; client validation mirrors the schema pattern; create/edit go directly to the OR object API (no planix wrapper — gate-17).
- [ ] `src/modals/LabelDeleteDialog.vue` (own file): destructive confirm showing the usage count; calls the cascade endpoint; success toast with tasks-updated count.
- [ ] Vitest: list rendering with counts, dialog validation (empty title, bad hex), delete dialog wires the cascade call.

## 3. Integration tests

- [ ] Newman (`tests/integration/*.postman_collection.json`): create label → listed with count 0; attach to task → count 1; invalid color → 400 (schema validation); non-admin cascade-delete call → 403; cascade delete → label gone and task `labels` swept; register re-import does not resurrect the deleted label.
- [ ] Playwright e2e (UI only): admin sees seed labels with usage counts; creates "Tech debt" with a custom color; edits a label color and the chip on a task card reflects it; deletes a used label via the usage-warning dialog and the chip disappears from the board. Reference the unexcluded scenarios from the spec delta (gate-19).

## 4. i18n, quality, docs

- [ ] i18n: nl translations for the section title, dialog labels, validation messages, usage warning, and toasts (English source strings as keys).
- [ ] Run `composer check:strict` + hydra gates (gate-5 route-auth, gate-17 redundant-controller, modal-isolation). Fix any pre-existing quality issues encountered.
- [ ] Update `docs/FEATURES.md` Admin Settings "Label management" row status; README admin-panel claim now true.

## 5. Spec sync

- [ ] On archive: fold the ADDED requirements into `openspec/specs/admin-user-settings.md`; cross-link from `register-schemas` (label schema) and `tasks.md` (label chips/filter) notes.
