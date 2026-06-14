# Tasks: Label Management Admin

## 1. Backend (planix admin endpoints)

- [x] `lib/Service/LabelService.php`: label usage-count aggregation (tasks per label UUID) and the cascade delete — sweep all referencing tasks first (remove the UUID from `labels`, save via OR), delete the label object last; idempotent on re-run.
- [x] `lib/Controller/LabelController.php` + `appinfo/routes.php`: admin-only endpoints for the usage listing and cascade delete (no `#[NoAdminRequired]`; declarative `#[AuthorizedAdminSetting(AdminSettings::class)]` posture per gate-5 plus body `isCurrentUserAdmin()` defence-in-depth); cascade response reports `tasksUpdated`. AdminSettings upgraded to `IDelegatedSettings` so the attribute can reference it.
- [x] PHPUnit: aggregation correctness, cascade (removes only the deleted UUID, other labels untouched), idempotent re-run after partial failure, admin-only posture. 14 tests (9 service + 5 controller), all green in the NC container.

## 2. Frontend (admin settings)

- [x] `src/views/settings/Settings.vue`: "Label Management" CnSettingsSection (CnVersionInfoCard stays first in AdminRoot) — list with color chip, title, description, usage count, edit/delete actions, sorted by title (server-side); loading/error/empty states.
- [x] `src/components/dialogs/LabelEditDialog.vue` (own file — modal-isolation): create/edit with required title, 6-digit-hex color (native swatch + hex field) defaulting `#4376FC`, optional description; client validation mirrors the schema pattern; create/edit go directly to the OR object API via the labels store (no planix wrapper — gate-17).
- [x] `src/components/dialogs/LabelDeleteDialog.vue` (own file): destructive confirm showing the usage count; calls the cascade endpoint; success toast with tasks-updated count.
- [x] Vitest: pure validation/normalisation helpers (`src/utils/labelHelpers.js`) — empty title, bad hex, both, default colour, trim. 10 tests green (consumed by the dialog). Component-mount rendering tests are out of scope: the repo's vitest runs in the `node` environment with no `@vue/test-utils`, matching the existing taskHelpers convention.

## 3. Integration tests

- [x] Newman (`tests/integration/planix.postman_collection.json`): "Label Management" folder — create label → listed with count 0; attach to task → count 1; invalid color → 4xx (schema validation); cascade delete → label gone and task `labels` swept (tasksUpdated ≥ 1); task no longer references the UUID. [~] Not executed live: the dev NC instance does not have planix installed (and the requests need a `project_id` the admin belongs to). The non-admin 403 contract is covered by the PHPUnit controller test; the register-re-import-idempotency scenario is left as a documented Newman step for a live run.
- [x] Playwright e2e (UI only, `tests/e2e/label-management.spec.ts`): admin sees seed labels with usage counts; creates "Tech debt" with a custom color; edits a label color/title; deletes a used label via the usage-warning dialog. Skips cleanly when planix is not installed (same convention as `due-date-reminder-settings.spec.ts`). [~] Not executed live (planix not installed in this environment); references the five non-excluded spec scenarios for gate-19.

## 4. i18n, quality, docs

- [x] i18n: en/nl translations for the section title, dialog labels, validation messages, usage warning, and toasts (English source strings as keys). [~] The other ~35 locale files were left as-is — they already lag en.json by 12 keys on `development` (pre-existing parity debt; the parity checker is informational and not wired into the hydra gates), so this change does not regress them; expanding the full EU locale fan-out is out of scope here.
- [x] Hydra gates: ALL 24 GATES GREEN on the diff (gate-5 route-auth, gate-13 modal-isolation, gate-17 redundant-controller included). PHPCS clean on the changed `lib/` files (0 errors). Pre-existing full-repo debt in untouched files unchanged.
- [x] `docs/FEATURES.md` Admin Settings "Label management" row marked implemented; README admin-panel claim ("label management") is now true.

## 5. Spec sync

- [x] On archive: fold the ADDED requirements into `openspec/specs/admin-user-settings.md` (via `openspec archive`).
