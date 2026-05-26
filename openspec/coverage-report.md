# Coverage Report — planix

Generated: 2026-05-24 08:31 UTC
Branch: feature/i18n-complete-translations
Scanner: opsx-coverage-scan v1

## Summary

| Bucket | Count | Next action |
|---|---|---|
| annotated | 0 | — (no files carry `@spec openspec/changes/...` yet) |
| plumbing | 24 | — (never tagged) |
| 1 — REQ matched | 50 | `/opsx-annotate planix` |
| 2a — existing capability, no REQ | 2 (2 clusters) | `/opsx-reverse-spec planix --extend <cap>` |
| 2b — no capability owner | 0 (0 clusters) | — |
| 3a — REQ broken (code removed) | 0 | — |
| 3b — REQ never implemented | 15 | Mark deferred or remove |
| 4 — ADR conformance | 9 findings across 2 rules | Follow-up issue |

Total REQs in inventory: **35** across **9 specs** (1 canonical capability dir + 8 flat .md files).
Total methods/components classified: **76** (PHP: ~30, Vue/JS: ~46).

## Bucket 1 — Ready to annotate (via ghost change `retrofit-2026-05-24-annotate-planix`)

### capability: register-schemas

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Service/SettingsService.php | loadConfiguration | REQ-All-5-schemas-defined | 0.80 | delegates to planix_register.json |
| lib/Service/SettingsService.php | loadConfiguration | REQ-Schema-validation-enforced-by-OpenRegister | 0.72 NEEDS-REVIEW | validation enforced inside OR, planix only registers |
| lib/Service/SettingsService.php | loadConfiguration | REQ-Seed-data-loaded-on-install | 0.95 | imports planix_register.json via OR ConfigurationService::importFromApp |
| lib/Service/SettingsService.php | loadConfiguration | REQ-Idempotent-import | 0.85 | importFromApp is the idempotency boundary |
| lib/Service/SettingsService.php | loadConfiguration | REQ-Version-based-skip-logic | 0.70 NEEDS-REVIEW | version param propagated, BUT both callers `force=true` bypass it |
| lib/Service/SettingsService.php | ensureRegisterPublicAccess | REQ-Seed-data-loaded-on-install | 0.75 NEEDS-REVIEW | post-install hardening; also flagged in Bucket 4 (direct SQL) |
| lib/Repair/InitializeSettings.php | run | REQ-Seed-data-loaded-on-install | 0.92 | install/upgrade hook calling loadConfiguration(force:true) |
| lib/Controller/SettingsController.php | load | REQ-Idempotent-import | 0.88 | admin-gated re-import endpoint |
| lib/Listener/DeepLinkRegistrationListener.php | handle | REQ-All-5-schemas-defined | 0.80 | registers deep links for exactly the 5 schemas (task/project/column/label/timeEntry), no `example` |
| src/views/settings/Settings.vue | initializeRegister | REQ-Idempotent-import | 0.88 | UI button POSTs to /api/settings/load |

### capability: admin-user-settings

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Settings/AdminSettings.php | getForm | REQ-Admin-Settings-Page | 0.95 | Nextcloud ISettings → renders settings/admin template |
| lib/Controller/SettingsController.php | index | REQ-Admin-Settings-Page | 0.85 | GET /api/settings backing the admin page |
| lib/Controller/SettingsController.php | create | REQ-Admin-Settings-Page | 0.88 | POST /api/settings, admin-only write |
| lib/Service/SettingsService.php | getSettings | REQ-Admin-Settings-Page | 0.85 | returns admin config + openregisters + isAdmin flags |
| lib/Service/SettingsService.php | updateSettings | REQ-Admin-Settings-Page | 0.85 | persists default_columns, allow_project_creation |
| lib/Service/SettingsService.php | getAdminSettings | REQ-Admin-Settings-Page | 0.90 | reads ADMIN_CONFIG_DEFAULTS |
| lib/Service/SettingsService.php | setAdminSettings | REQ-Admin-Settings-Page | 0.85 | private helper inherited from updateSettings |
| lib/Service/SettingsService.php | isCurrentUserAdmin | REQ-Admin-Settings-Page | 0.80 | admin authz guard |
| src/store/modules/settings.js | fetchSettings | REQ-Admin-Settings-Page | 0.85 | bootstrap of admin UI state |
| src/store/modules/settings.js | saveSettings | REQ-Admin-Settings-Page | 0.85 | persist from admin UI |
| src/views/settings/AdminRoot.vue | (component) | REQ-Admin-Settings-Page | 0.95 | mount point + CnVersionInfoCard |
| src/views/settings/Settings.vue | (component) | REQ-Admin-Settings-Page | 0.95 | default-columns editor + register-init + legacy register field |
| src/views/settings/Settings.vue | saveColumns | REQ-Admin-Settings-Page | 0.95 | persists default_columns JSON |
| src/views/settings/UserSettings.vue | (component) | REQ-User-Settings-Dialog | 0.75 NEEDS-REVIEW | NcAppSettingsDialog shell exists, content is placeholder |

### capability: projects

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| src/App.vue | (component) | REQ-OpenRegister-Gate | 0.92 | exact match for OR-gate scenario |
| lib/Service/SettingsService.php | isOpenRegisterAvailable | REQ-OpenRegister-Gate | 0.85 | backend half of the gate |
| lib/Controller/DashboardController.php | page | REQ-Project-List-UI | 0.70 NEEDS-REVIEW | SPA entry point — coarse, real UI is the Vue components |
| lib/Controller/DashboardController.php | catchAll | REQ-Project-List-UI | 0.70 NEEDS-REVIEW | delegates to page() for deep links |
| src/store/projects.js | fetchProjects | REQ-Project-Lifecycle | 0.88 | filters by current user UID |
| src/store/projects.js | fetchProject | REQ-Project-Lifecycle | 0.85 | handles 403→forbidden state |
| src/store/projects.js | createProject | REQ-Project-Lifecycle | 0.95 | default status='active', adds UID, triggers createDefaultColumns |
| src/store/projects.js | updateProject | REQ-Project-Settings-Sidebar | 0.88 | saves sidebar form |
| src/store/projects.js | createDefaultColumns | REQ-Default-Column-Creation | 0.95 | uses loadState('default_columns') with hardcoded fallback matching spec |
| src/store/projects.js | archiveProject | REQ-Project-Lifecycle | 0.92 | status='archived' |
| src/store/projects.js | deleteProject | REQ-Project-Deletion | 0.95 | cascade order exactly matches spec: timeEntries → tasks → columns → project |
| src/store/projects.js | addMember | REQ-Member-Management | 0.95 | guards duplicates |
| src/store/projects.js | removeMember | REQ-Member-Management | 0.92 | returns assigned-task count for warning |
| src/store/projects.js | leaveProject | REQ-Member-Management | 0.85 | last-member detection |
| src/store/projects.js | getTaskCount | REQ-Project-Deletion | 0.75 NEEDS-REVIEW | used by delete dialog |
| src/views/ProjectList.vue | (component) | REQ-Project-List-UI | 0.95 | NcChip status filters, useListView search, NcEmptyContent states |
| src/views/ProjectList.vue | navigateToProject | REQ-Project-List-UI | 0.85 | row click routes to ProjectBoard |
| src/views/ProjectList.vue | setStatusFilter | REQ-Project-List-UI | 0.85 | chip filter |
| src/views/ProjectList.vue | onProjectCreated | REQ-Project-Creation-Dialog | 0.82 | post-create navigation |
| src/views/ProjectBoard.vue | (component) | REQ-Project-Settings-Sidebar | 0.78 NEEDS-REVIEW | header + sidebar trigger; board content is placeholder |
| src/views/ProjectBoard.vue | openSettings | REQ-Project-Settings-Sidebar | 0.90 | injects sidebar via App.vue outlet |
| src/components/dialogs/ProjectCreationDialog.vue | (component) | REQ-Project-Creation-Dialog | 0.95 | NcDialog, required-title validation, submit calls createProject |
| src/components/dialogs/ProjectCreationDialog.vue | submit | REQ-Project-Creation-Dialog | 0.95 | shows success/error toast, preserves form on error |
| src/components/dialogs/ProjectDeleteDialog.vue | (component) | REQ-Project-Deletion | 0.92 | shows task-count warning |
| src/components/dialogs/ProjectLeaveDialog.vue | (component) | REQ-Member-Management | 0.85 | last-member warning |
| src/components/ProjectSettingsSidebar.vue | (component) | REQ-Project-Settings-Sidebar | 0.95 | NcAppSidebar with Details/Members/Danger tabs |
| src/components/ProjectSettingsSidebar.vue | saveDetails | REQ-Project-Settings-Sidebar | 0.92 | persists via updateProject |
| src/components/ProjectSettingsSidebar.vue | confirmRemoveMember | REQ-Member-Management | 0.85 | assigned-task warning |
| src/components/ProjectSettingsSidebar.vue | doArchive | REQ-Project-Lifecycle | 0.85 | danger-zone archive action |
| src/components/MemberSearch.vue | searchUsers | REQ-Member-Management | 0.92 | OCS /cloud/users search w/ 300ms debounce |
| src/components/MemberSearch.vue | selectUser | REQ-Member-Management | 0.90 | calls addMember on selection |

### capability: procest-integration

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| src/components/ProjectSettingsSidebar.vue | (case-reference display) | REQ-Case-Reference-on-Project | 0.85 | read-only caseReference field in Details tab — MVP scope |

## Bucket 2a — Existing capability, no REQ (reverse-spec --extend)

### cluster: register-schemas (1 entry — informational)
- `lib/Settings/planix_register.json` — the canonical schema/seed manifest. Not a code unit, so the scanner cannot bucket it as a method, but the register-schemas REQs ultimately read from it. Cite in the ghost-change tasks.md when annotating.

### cluster: projects (1 method)
- `src/views/ProjectBacklog.vue` — breadcrumb shell + 'Backlog view coming soon' NcEmptyContent placeholder. No Backlog REQ exists in `projects.md` or `tasks.md`. Either retire the route or add a REQ; flagged so /opsx-reverse-spec can be invoked with `--extend projects` if/when the backlog UI is built.

## Bucket 2b — No capability owner (reverse-spec --cluster)

(empty — every code unit maps to a capability)

## Bucket 3 — Surfaced for human triage

### 3a — possibly broken
(empty — removed-lines cache had no meaningful matches; planix has thin git history)

### 3b — never implemented

Kanban / tasks / time-tracking / dashboard / procest-bridge are visible in specs but have zero implementation in the codebase. All 15 REQs:

- `kanban-board#REQ-Kanban-Board-View` — `src/views/ProjectBoard.vue` ships an explicit "Board view coming soon" empty state.
- `kanban-board#REQ-View-Toggle-Kanban-and-List` — never started.
- `tasks#REQ-Task-CRUD` — no task store, no task controllers; no `task.js` file, no task schema accessed in the projects store.
- `tasks#REQ-Task-Search` — never started.
- `tasks#REQ-Bulk-Task-Operations` — never started.
- `time-tracking#REQ-Time-Estimate` — never started.
- `time-tracking#REQ-Log-Time` — never started.
- `time-tracking#REQ-Personal-Timesheet` — never started.
- `dashboard-my-work#REQ-Personal-Dashboard` — `src/views/Dashboard.vue` ships hardcoded sample KPIs only.
- `dashboard-my-work#REQ-My-Work-View` — never started.
- `dashboard-my-work#REQ-Dashboard-Empty-State` — never started.
- `procest-integration#REQ-Task-Case-Link` — Task entity does not exist yet, so `zaakUuid` field has no carrier.
- `procest-integration#REQ-Procest-Bridge-Create-Project-from-Case` — V1 scope, not started.
- `projects#REQ-i18n-Coverage` — effectively satisfied across `src/` (every string uses `t('planix', ...)`), but no single method implements it. Consider retiring this REQ in favour of an ADR-007 conformance gate.
- `projects#REQ-Loading-and-Error-States` — cross-cutting; loading/error UI exists in every view but no single annotation target. Consider splitting per-view or retiring.

## Bucket 4 — ADR conformance findings

### missing-spec-in-file-docblock (8 files, ADR-003 §Spec traceability)
Every `lib/**/*.php` file has `@license` and `@copyright` but **none** carry `@spec openspec/changes/...` — there are no `@spec` tags anywhere in the codebase (0 hits). Annotation is the entire purpose of running /opsx-annotate next.

- lib/Controller/SettingsController.php
- lib/Controller/DashboardController.php
- lib/Service/SettingsService.php
- lib/Repair/InitializeSettings.php
- lib/AppInfo/Application.php
- lib/Listener/DeepLinkRegistrationListener.php
- lib/Sections/SettingsSection.php
- lib/Settings/AdminSettings.php

### direct-sql-violates-adr-001 (1 finding)
- `lib/Service/SettingsService.php::ensureRegisterPublicAccess` runs a raw `UPDATE openregister_registers SET public_write=0, public_read=0 WHERE slug='planix'` via `OCP\IDBConnection`. Per ADR-001 apps consume OpenRegister abstractions; reaching into another app's table directly is a real smell. The method is wrapped in `try/catch \Throwable` and logs warnings on failure, so it fails open. Recommend: ask OpenRegister to expose a `Register::setPublicAccess($slug, bool $write, bool $read)` API, or use the import payload's `publicWrite`/`publicRead` fields and remove this method entirely.

## Notes for the human reviewer

- **Spec layout is mixed.** Only `register-schemas/spec.md` uses the canonical capability-dir layout. The other 8 specs are flat `openspec/specs/*.md` files. Their `### Requirement: <Name> [<tier>]` headings have no `[A-Z]{2,4}-[0-9]+` IDs — the scanner synthesised slug-style REQ IDs (e.g. `REQ-Project-Lifecycle`). Before running `/opsx-annotate`, decide whether to (a) retrofit canonical IDs into all 8 flat specs, or (b) let opsx-annotate accept slug IDs.
- **Thin git history.** `git log --all -p -- lib/ src/` produces only 163 removed lines (cache built in 0.5s). All unimplemented REQs went straight to Bucket 3b — there is no historical evidence of partial implementations for kanban/tasks/time-tracking, just a single placeholder string match on "Kanban".
- **REQ-Version-based-skip-logic mismatch.** `SettingsService::loadConfiguration` accepts and propagates a `$force` flag, but both callers (repair step + admin endpoint) pass `force=true` unconditionally. The version-skip behaviour only lives in OpenRegister's `ConfigurationService::importFromApp`. Either accept that the REQ is satisfied by delegation, or remove the dead `$force` path.
- **REQ-OpenRegister-Gate** sits in `projects.md` but implementation is in `src/App.vue` and `SettingsService::isOpenRegisterAvailable`. If more apps adopt the same gate pattern, consider moving the REQ to a shared `app-shell` capability.
- **REQ-Project-List-UI** assigned to `DashboardController::page/catchAll` at 0.70 NEEDS-REVIEW. The controllers are the SPA entry points — real UI implementation is in Vue. Acceptable but coarse.
- **REQ-User-Settings-Dialog** at 0.75 NEEDS-REVIEW. The dialog shell, mount, and open/close wiring exist (`UserSettings.vue`), but the content is an "No settings available yet" placeholder. Spec-wise the REQ is partially satisfied — flag for human decision.
- **Cross-cutting REQs** (`projects#REQ-i18n-Coverage`, `projects#REQ-Loading-and-Error-States`) are effectively satisfied in practice but have no single annotation target. Both classified as 3b so a human can decide between retiring, splitting, or annotating multiple files.
- **lib/Migration/Version20260403000000.php** intentionally skipped (per skill rule excluding `lib/Migration/`).
- **No `.opsx-ignore`** file — zero entries suppressed.
- **No `openspec/changes/`** directory exists yet — `/opsx-annotate` will need to create one with the ghost-change `retrofit-2026-05-24-annotate-planix/` and a tasks.md enumerating every Bucket 1 REQ.
