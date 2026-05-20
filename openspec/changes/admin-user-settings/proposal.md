# Proposal: Admin & User Settings

## Summary

Add a Nextcloud admin settings page under Administration → Planix and a user settings dialog accessible from the Planix navigation bar. Admin settings control app-level defaults (default column set, OpenRegister initialization status) stored via `IAppConfig`. User settings control personal preferences (notification toggles, default view) stored via `OCP\IConfig`.

## Motivation

Planix has no settings infrastructure. There is no way for admins to configure the app (e.g., default column templates, OpenRegister initialization) or for users to manage notification preferences and display preferences. Both surfaces are required to ship the MVP: the admin page enables OpenRegister register initialization and default column configuration without CLI access; the user dialog lets users control which notifications they receive and how Planix opens by default. Without these surfaces, the app cannot be configured post-install and users cannot personalize their experience.

## Affected Projects

- [x] Project: `planix` — Backend: `SettingsController`, `AdminSettings.php` (Nextcloud settings registration). Frontend: `AdminRoot.vue`, `UserSettings.vue`, settings Pinia store, `NotificationService` integration.

## Scope

### In Scope

- `AdminSettings.php` — registers the admin settings page with Nextcloud's settings framework (`ISettings`, section `additional`, priority `50`)
- `SettingsController.php` — PHP controller with routes:
  - `GET  /api/settings/admin`  → `getAdminSettings()` — returns current admin config values
  - `POST /api/settings/admin`  → `saveAdminSettings()` — persists admin config via `IAppConfig`
  - `GET  /api/settings/user`   → `getUserSettings()` — returns current user config values
  - `POST /api/settings/user`   → `saveUserSettings()` — persists user config via `IConfig`
- Admin settings frontend (`AdminRoot.vue`, rendered by `settings.js` entry point):
  - `CnVersionInfoCard` as the first section (app name, version, update status)
  - `CnSettingsSection` "Default Project Configuration" with `default_columns` as an editable ordered list
  - `CnSettingsSection` "Register Setup" showing initialization status with an "Initialize register" button that calls `ConfigurationService::importFromApp()`
- User settings frontend (`UserSettings.vue`) wrapping `NcAppSettingsDialog`:
  - Notification preferences section: toggles for `notify_assigned` (default on) and `notify_due_reminder` (default on)
  - Display preferences section: `default_view` selector (`my-work` / `kanban` / `backlog`, default `my-work`)
- Settings Pinia store (`src/store/modules/settings.js`) with `fetchSettings()` and `saveSettings()`
- `NotificationService` wired to check user preference before dispatch via `SUBJECT_SETTING_MAP`
- `App.vue` and `MainMenu.vue` updated to open `UserSettings.vue` as a modal overlay (not a route)

### Out of Scope (V1)

- Procest bridge settings section (`procest_bridge_enabled`, `procest_base_url`) — deferred to V1
- `allow_project_creation` enforcement UI — deferred to V1
- V1 notification toggles: `notify_overdue`, `notify_commented`, `notify_status_changed`, `notify_status_changed`
- `items_per_page` backlog preference — deferred to V1

## Approach

**Backend:** `SettingsController.php` follows the thin controller pattern (< 10 lines per method). Admin endpoints use `#[AuthorizedAdminSetting(Application::APP_ID)]` for framework-level admin enforcement. User endpoints use `#[NoAdminRequired]` and derive identity from `IUserSession`; they do not require per-object authorization checks because they operate on the caller's own settings only.

`NotificationService` gets a `SUBJECT_SETTING_MAP` constant mapping subject keys (e.g. `task_assigned`) to their user setting key (e.g. `notify_assigned`). Before dispatching a notification the service reads the recipient's preference via `IConfig`; if the preference resolves to `false`, dispatch is skipped.

**Frontend (admin):** `webpack.config.js` gains a `settings` entry point. `AdminRoot.vue` is mounted into `#planix-admin-settings` by `settings.js`. Layout: `CnVersionInfoCard` → `CnSettingsSection` per feature area. Read on mount via `GET /api/settings/admin`; save via `POST /api/settings/admin` with user-facing error feedback.

**Frontend (user):** `UserSettings.vue` wraps `NcAppSettingsDialog` (NOT `NcDialog`). Opened as a modal from `App.vue` on the `@open-settings` event emitted by `MainMenu`'s `NcAppNavigationSettings` gear click. No `/settings` route is added to the router.

## New Dependencies

None — `CnVersionInfoCard`, `CnSettingsSection`, and the re-exported `NcAppSettingsDialog` are all provided by `@conduction/nextcloud-vue` (already a dependency).

## Impact

- New: `lib/Controller/SettingsController.php`
- New: `lib/Settings/AdminSettings.php`
- New: `src/views/AdminRoot.vue`
- New: `src/dialogs/UserSettings.vue`
- New: `src/store/modules/settings.js`
- Modified: `appinfo/routes.php` — add four settings routes
- Modified: `lib/AppInfo/Application.php` — register `AdminSettings` via `ISettings`
- Modified: `lib/Service/NotificationService.php` — add `SUBJECT_SETTING_MAP`, check preference before dispatch
- Modified: `webpack.config.js` — add `settings` entry point
- Modified: `src/App.vue` — bind `:open` and `@update:open` to `UserSettings`
- Modified: `src/components/MainMenu.vue` — emit `open-settings` from gear click

## Cross-Project Dependencies

None for MVP scope. The V1 Procest bridge section will be delivered in a future change and will require coordination with the `procest` app's API base URL.

## Risks

### Risk 1: Admin access bypass via direct URL
**Severity:** Medium — **Mitigation:** Admin endpoints annotated with `#[AuthorizedAdminSetting(Application::APP_ID)]`, which enforces admin-only access at the Nextcloud middleware layer before the controller method executes. Frontend-only guards are never relied upon.

### Risk 2: UserSettings.vue or AdminRoot.vue accidentally added to vue-router
**Severity:** Medium — **Mitigation:** Per ADR-004, admin settings components MUST NOT be in the router (`hydra-gate-admin-router` gate enforces this). `AdminRoot.vue` is rendered by the dedicated `settings.js` bundle. `UserSettings.vue` is a modal component — not a route, not a page.

### Risk 3: IAppConfig vs IConfig confusion
**Severity:** Low — **Mitigation:** Admin settings always use `IAppConfig` (app-scoped). User settings always use `IConfig` with the current user's UID from `IUserSession`. Both are injected via constructor DI — no static locators.

## Rollback Strategy

Revert the commit. No OpenRegister schema changes, no database migrations. Nextcloud's native `IAppConfig` and `IConfig` storage is managed by Nextcloud itself; removing or rolling back the app leaves no orphaned data requiring cleanup.
