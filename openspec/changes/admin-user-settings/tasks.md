# Tasks: Admin & User Settings

## Deduplication Check

- [ ] Search `openspec/specs/` and existing `lib/Controller/` for any existing `SettingsController` — document findings before creating new controller (ADR-001 deduplication rule)
- [ ] Verify `ConfigurationService::importFromApp()` exists in OpenRegister and confirm its signature matches the design (`importFromApp(appId, data, version, force)`)
- [ ] Verify `CnVersionInfoCard` and `CnSettingsSection` are available in the project's version of `@conduction/nextcloud-vue`

## Backend — PHP

- [ ] **[MVP]** Create `lib/Settings/AdminSettings.php` implementing `OCP\Settings\ISettings`:
  - `getSection()` → `'additional'`
  - `getPriority()` → `50`
  - `getForm()` → `TemplateResponse('planix', 'admin-settings')` with mount point `<div id="planix-admin-settings"></div>`
  - SPDX header: `// SPDX-License-Identifier: EUPL-1.2`
  - `@spec openspec/changes/admin-user-settings/tasks.md` PHPDoc tag

- [ ] **[MVP]** Create `lib/Controller/SettingsController.php` with four methods (< 10 lines each):
  - `getAdminSettings()` — `#[AuthorizedAdminSetting(Application::APP_ID)]` — reads `IAppConfig` and returns JSON with `default_columns` (array), `allow_project_creation` (string), `register_initialized` (bool)
  - `saveAdminSettings(Request $request)` — `#[AuthorizedAdminSetting(Application::APP_ID)]` — validates and persists via `IAppConfig`; validates `default_columns` as array of strings, `default_view` against allowlist
  - `getUserSettings()` — `#[NoAdminRequired]` — reads `IConfig` for `IUserSession->getUID()`; returns `notify_assigned`, `notify_due_reminder`, `default_view` as typed values
  - `saveUserSettings(Request $request)` — `#[NoAdminRequired]` — derives UID from `IUserSession`; validates `default_view` against `['my-work','kanban','backlog']`; persists via `IConfig`
  - All methods: static generic error messages in catch blocks, real error logged via `$this->logger->error()`
  - SPDX header + `@spec` PHPDoc tags on class and each public method

- [ ] **[MVP]** Register `AdminSettings` in `lib/AppInfo/Application.php` via `$context->registerSettings(AdminSettings::class)` (or `ISettings` registration equivalent)

- [ ] **[MVP]** Add four routes to `appinfo/routes.php`:
  ```php
  ['name' => 'settings#getAdminSettings',  'url' => '/api/settings/admin', 'verb' => 'GET'],
  ['name' => 'settings#saveAdminSettings', 'url' => '/api/settings/admin', 'verb' => 'POST'],
  ['name' => 'settings#getUserSettings',   'url' => '/api/settings/user',  'verb' => 'GET'],
  ['name' => 'settings#saveUserSettings',  'url' => '/api/settings/user',  'verb' => 'POST'],
  ```
  Verify these are placed BEFORE any wildcard `{slug}` routes

- [ ] **[MVP]** Update `lib/Service/NotificationService.php`:
  - Add `SUBJECT_SETTING_MAP` constant: `['task_assigned' => 'notify_assigned', 'task_due_reminder' => 'notify_due_reminder']`
  - Add `IConfig` constructor injection
  - Before dispatching any notification subject present in `SUBJECT_SETTING_MAP`, read `IConfig->getUserValue($userId, 'planix', $settingKey, 'true')` and skip if `=== 'false'`
  - SPDX header preserved; add `@spec` PHPDoc tag

- [ ] **[MVP]** Provide initial state in the admin settings PHP template or controller: `IInitialState::provideInitialState('settings', $adminSettingsArray)` so `settings.js` can call `loadState('planix', 'settings', {})` instead of a DOM attribute read

## Frontend — Admin Settings

- [ ] **[MVP]** Add `settings` entry point to `webpack.config.js`:
  ```js
  entry: { main: '...', settings: path.join(__dirname, 'src', 'settings.js') }
  ```
  Verify `appName` and `appVersion` `DefinePlugin` blocks are present after the plugins array is modified (ADR-004 webpack rule)

- [ ] **[MVP]** Create `src/settings.js` — mounts `AdminRoot.vue` into `#planix-admin-settings`

- [ ] **[MVP]** Create `src/views/AdminRoot.vue`:
  - SPDX header: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
  - Import `CnVersionInfoCard`, `CnSettingsSection` from `@conduction/nextcloud-vue`; register in `components: {}`
  - Layout: `CnVersionInfoCard` (FIRST) → `CnSettingsSection` "Default Project Configuration" → `CnSettingsSection` "Register Setup"
  - `created()`: call `fetchAdminSettings()` from settings store; wrap in `try/catch` with `showError()`
  - Default columns: rendered as an editable ordered list (add / remove / reorder); save button calls `saveAdminSettings()`
  - Register Setup: show status badge; show "Initialize register" `NcButton` when `register_initialized === false`; on click POST to backend and refresh status
  - All strings via `this.t('planix', '...')` — no hardcoded labels
  - DO NOT add to vue-router (ADR-004)

- [ ] **[MVP]** Verify `AdminRoot.vue` is NOT registered in `src/router/index.js` (hydra-gate-admin-router)

## Frontend — User Settings

- [ ] **[MVP]** Create `src/dialogs/UserSettings.vue` (in `src/dialogs/`, not `src/modals/`):
  - SPDX header: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
  - Wraps `NcAppSettingsDialog` — import from `@conduction/nextcloud-vue`; registered in `components: {}`
  - Props: `:open` (boolean), emits `update:open`
  - Section "Notification preferences": `NcCheckboxRadioSwitch` for `notify_assigned` and `notify_due_reminder`
  - Section "Display preferences": `NcSelect` with `inputLabel` prop for `default_view` (options: My Work, Kanban, Backlog) — NO manual `<label>` element
  - On toggle/select change: call `saveUserSettings(patch)` (debounced 300 ms); wrap in `try/catch`; on error call `showError()` and revert the control
  - `created()`: call `fetchUserSettings()`; wrap in `try/catch`
  - All strings via `this.t('planix', '...')`

- [ ] **[MVP]** Update `src/App.vue`:
  - Add `settingsOpen: false` to `data()`
  - Register and render `<UserSettings :open="settingsOpen" @update:open="settingsOpen = $event" />`
  - Handle `@open-settings` event from `<MainMenu>`: `settingsOpen = true`

- [ ] **[MVP]** Update `src/components/MainMenu.vue`:
  - `NcAppNavigationSettings` gear click handler MUST emit `open-settings` (not route to `/settings`)
  - Verify no `/settings` route is referenced anywhere in this component

## Frontend — Settings Store

- [ ] **[MVP]** Create `src/store/modules/settings.js` — Pinia `defineStore`:
  - State: `{ adminSettings: {}, userSettings: {}, loading: false }`
  - Actions:
    - `fetchAdminSettings()` — `GET /api/settings/admin` via `@nextcloud/axios`; sets `adminSettings`; wrapped in `try/finally` for loading, `try/catch` for error
    - `fetchUserSettings()` — `GET /api/settings/user`; sets `userSettings`
    - `saveAdminSettings(patch)` — `POST /api/settings/admin`; updates `adminSettings` on success
    - `saveUserSettings(patch)` — `POST /api/settings/user`; updates `userSettings` on success
  - `isAdmin` populated from backend response — NEVER from `OC.isAdmin`
  - Use `axios` from `@nextcloud/axios` for all calls — never raw `fetch()`

- [ ] **[MVP]** Register `settings` store in `src/store/store.js` and call `fetchUserSettings()` from `initializeStores()`

## Pre-Commit Verification

- [ ] Run `grep -rL 'SPDX-License-Identifier' lib/Controller/SettingsController.php lib/Settings/AdminSettings.php lib/Service/NotificationService.php src/views/AdminRoot.vue src/dialogs/UserSettings.vue src/store/modules/settings.js` — must return no files
- [ ] Run `grep -rn "from '@nextcloud/vue'" src/` — must return zero matches (use `@conduction/nextcloud-vue`)
- [ ] Verify every `<NcFoo>` and `<CnFoo>` in `AdminRoot.vue` and `UserSettings.vue` templates has a matching import and `components: {}` registration
- [ ] Run `grep -rn 'await.*[Ss]ettings' src/ --include='*.vue'` — verify every settings store call is wrapped in `try/catch`
- [ ] Run `grep -rn "AdminRoot" src/router/` — must return zero matches (admin component must NOT be in router)
- [ ] Verify `POST /api/settings/admin` with a non-admin session token returns 403
- [ ] Verify `GET /api/settings/user` with user A's session returns user A's settings (not user B's)
- [ ] Verify `NcSelect` in `UserSettings.vue` uses `inputLabel` prop — no manual `<label>` elements
- [ ] Verify `UserSettings.vue` is in `src/dialogs/` directory (not `src/modals/`)
- [ ] Verify `default_view` value is validated against `['my-work', 'kanban', 'backlog']` in `saveUserSettings()` backend method
- [ ] Run `grep -rn 'OC\.isAdmin' src/` — must return zero matches
- [ ] Verify `SUBJECT_SETTING_MAP` constant exists in `NotificationService.php` and is used consistently

## Testing

- [ ] **[MVP]** Write PHPUnit test for `SettingsController::getAdminSettings()` — mock `IAppConfig`, assert response shape
- [ ] **[MVP]** Write PHPUnit test for `SettingsController::saveUserSettings()` — verify UID comes from `IUserSession`, not request body
- [ ] **[MVP]** Write PHPUnit test for `NotificationService` preference check — mock `IConfig` returning `'false'` for `notify_assigned` and assert notification is NOT dispatched
- [ ] **[MVP]** Write Vue unit test for `UserSettings.vue` — assert default values for `notify_assigned` and `notify_due_reminder` toggles and `default_view` selector
