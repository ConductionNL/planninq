# Tasks: admin-user-settings

**Change ID:** admin-user-settings
**Status:** draft
**Created:** 2026-04-02

---

## Implementation Tasks

### Task 1: Setup and Prerequisites
- **spec_ref**: `openspec/specs/admin-user-settings.md`
- **files**: `src/store/settings.js`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN the developer inspects `@conduction/nextcloud-vue` WHEN checking exports THEN `CnVersionInfoCard` and `CnSettingsSection` are available
  - GIVEN the developer checks `@nextcloud/vue` WHEN checking exports THEN `NcAppSettingsDialog` and `NcCheckboxRadioSwitch` are available
  - GIVEN the developer verifies dependencies WHEN checking `lib/Settings/AdminSettings.php` THEN the file exists and is registered in `lib/AppInfo/Application.php`
  - GIVEN the developer verifies WHEN checking `src/views/settings/UserSettings.vue` THEN the file exists (even if empty placeholder)
- [ ] Confirm `@conduction/nextcloud-vue` exports: `CnVersionInfoCard`, `CnSettingsSection`
- [ ] Confirm `@nextcloud/vue` exports: `NcAppSettingsDialog`, `NcCheckboxRadioSwitch`
- [ ] Confirm `lib/Settings/AdminSettings.php` is registered in `Application.php`
- [ ] Create directory `src/components/settings/` if not present
- [ ] Create `src/store/settings.js` stub with empty state and no-op actions

---

### Task 2: SettingsController — Admin Endpoints
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-settings-persistence-and-backend`
- **files**: `lib/Controller/SettingsController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN an admin calls `GET /planix/settings/admin` WHEN the controller handles the request THEN a JSON response with `default_columns`, `register_initialized`, `app_version`, `update_available`, `update_version` is returned
  - GIVEN an admin calls `PUT /planix/settings/admin` with `{ default_columns: ["A","B"] }` WHEN the controller handles the request THEN `IAppConfig::setValueString('planix', 'default_columns', '["A","B"]')` is called and HTTP 200 is returned
  - GIVEN a non-admin user calls any `/planix/settings/admin` endpoint WHEN Nextcloud processes the request THEN HTTP 403 is returned (enforced by `@AdminRequired` annotation)
- [ ] Add `adminIndex(): JSONResponse` action to `SettingsController` (or create it if missing)
  - Read `default_columns` from `IAppConfig` (default: `["To Do","In Progress","Review","Done"]`)
  - Read `app_version` via `\OCP\App::getAppVersion('planix')`
  - Read `register_initialized` from `ConfigurationService::isInitialized()`
  - Read `update_available` and `update_version` from `IAppManager` / app info (cached)
  - Annotate with `@AdminRequired`
- [ ] Add `adminUpdate(array $settings): JSONResponse` action
  - Accept `default_columns` (JSON-decode, validate is array of strings)
  - Store via `IAppConfig::setValueString`
  - Annotate with `@AdminRequired`
- [ ] Add routes to `appinfo/routes.php`:
  - `['name' => 'settings#adminIndex', 'url' => '/settings/admin', 'verb' => 'GET']`
  - `['name' => 'settings#adminUpdate', 'url' => '/settings/admin', 'verb' => 'PUT']`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 3: SettingsController — User Endpoints
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-settings-persistence-and-backend`
- **files**: `lib/Controller/SettingsController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN any authenticated user calls `GET /planix/settings/user` WHEN the controller handles the request THEN a JSON response with all user setting keys and their values is returned for the calling user only
  - GIVEN a user calls `PUT /planix/settings/user` with `{ notify_assigned: false }` WHEN the controller handles the request THEN `IConfig::setUserValue($uid, 'planix', 'notify_assigned', 'no')` is called and HTTP 200 is returned
  - GIVEN user A calls `PUT /planix/settings/user` WHEN another user B reads their settings THEN user B's settings are unaffected
- [ ] Add `userIndex(): JSONResponse` action to `SettingsController`
  - Read all user settings from `IConfig::getUserValue($uid, 'planix', $key, $default)`
  - Return boolean values as PHP booleans (not strings) in JSON
  - No admin annotation required
- [ ] Add `userUpdate(array $settings): JSONResponse` action
  - Accept any subset of user setting keys; ignore unknown keys
  - Store booleans as `'yes'`/`'no'` strings via `IConfig::setUserValue`
  - Store `default_view` as string value directly
- [ ] Add routes to `appinfo/routes.php`:
  - `['name' => 'settings#userIndex', 'url' => '/settings/user', 'verb' => 'GET']`
  - `['name' => 'settings#userUpdate', 'url' => '/settings/user', 'verb' => 'PUT']`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 4: SettingsController — Register Init Endpoint
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-register-initialization-flow`
- **files**: `lib/Controller/SettingsController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN an admin calls `POST /planix/settings/admin/register-init` WHEN the controller handles the request THEN `ConfigurationService::importFromApp()` is called and HTTP 200 with `{ success: true }` is returned
  - GIVEN `importFromApp()` throws an exception WHEN the controller handles the error THEN HTTP 500 with `{ success: false, message: "..." }` is returned
  - GIVEN a non-admin user calls this endpoint WHEN Nextcloud processes the request THEN HTTP 403 is returned
- [ ] Add `adminRegisterInit(): JSONResponse` action to `SettingsController`
  - Inject `ConfigurationService` via constructor
  - Call `$this->configurationService->importFromApp()` in try/catch
  - Return `JSONResponse(['success' => true])` on success
  - Return `JSONResponse(['success' => false, 'message' => $e->getMessage()], 500)` on failure
  - Annotate with `@AdminRequired`
- [ ] Add route: `['name' => 'settings#adminRegisterInit', 'url' => '/settings/admin/register-init', 'verb' => 'POST']`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 5: Pinia Settings Store
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-loading-and-error-states`
- **files**: `src/store/settings.js`
- **acceptance_criteria**:
  - GIVEN `useSettingsStore()` is called WHEN the store is initialized THEN it exposes: `adminSettings`, `userSettings`, `adminLoading`, `userLoading`, `error`
  - GIVEN `fetchUserSettings()` is called WHEN the API responds THEN `userSettings` is populated with the server values
  - GIVEN `updateUserSetting('notify_assigned', false)` is called WHEN the API call is in progress THEN `userSettings.notify_assigned` immediately reflects `false` (optimistic update)
  - GIVEN `updateUserSetting` is called and the API call fails WHEN the store catches the error THEN the key reverts to its previous value
  - GIVEN `initRegister()` is called WHEN the API call succeeds THEN `adminSettings.register_initialized` is set to `true`
- [ ] Implement `fetchAdminSettings()` — GET `/planix/settings/admin`, set `adminSettings`, handle loading/error states
- [ ] Implement `updateAdminSettings(data)` — PUT `/planix/settings/admin`; update `adminSettings` on success
- [ ] Implement `initRegister()` — POST `/planix/settings/admin/register-init`; set `adminSettings.register_initialized = true` on success
- [ ] Implement `fetchUserSettings()` — GET `/planix/settings/user`, set `userSettings`, handle loading/error
- [ ] Implement `updateUserSetting(key, value)` — optimistic update + PUT `/planix/settings/user`; revert on failure
- [ ] Test

---

### Task 6: AdminSettings.php — Page Data Injection
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-cnversioninfocard-integration`
- **files**: `lib/Settings/AdminSettings.php`, `templates/admin-settings.php`
- **acceptance_criteria**:
  - GIVEN an admin navigates to Administration → Planix WHEN the PHP template renders THEN the HTML contains `<div id="planix-admin-settings" data-app-version="..." data-update-available="..." data-register-initialized="..."></div>`
  - GIVEN a newer version exists in the app store WHEN `AdminSettings.php` builds the template data THEN `update_available` is `true` and `update_version` is the new version string (served from ICache, TTL 1 hour)
  - GIVEN the app store check fails WHEN the template renders THEN `update_available` defaults to `false` gracefully
- [ ] Modify `lib/Settings/AdminSettings.php`:
  - Inject `IAppManager`, `ICacheFactory`, `ConfigurationService` via constructor
  - Read `appVersion` via `\OCP\App::getAppVersion('planix')`
  - Check update status via `IAppManager::getAppInfo()` or app store endpoint; cache result for 1 hour
  - Read `registerInitialized` via `ConfigurationService::isInitialized()`
  - Pass all values to `TemplateResponse`
- [ ] Modify `templates/admin-settings.php`:
  - Output `<div id="planix-admin-settings" data-app-version="<?php echo $_['appVersion']; ?>" ...></div>`
  - Include `admin-settings.js` script via `\OCP\Util::addScript('planix', 'admin-settings')`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 7: AdminSettings.vue — Vue Component
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-admin-settings-vue-mount`
- **files**: `src/views/settings/AdminSettings.vue`, webpack config / `appinfo/assets.php`
- **acceptance_criteria**:
  - GIVEN `AdminSettings.vue` mounts onto `#planix-admin-settings` WHEN the component initializes THEN it reads `data-app-version`, `data-update-available`, `data-register-initialized` from the mount div and passes them to sub-components
  - GIVEN `fetchAdminSettings()` is loading WHEN the component renders the column editor section THEN a skeleton loading state is shown
  - GIVEN settings are loaded WHEN the admin changes a column and clicks "Save changes" THEN `updateAdminSettings` is called; a "Saving…" state appears on the button; on success the button reverts to "Save changes"
- [ ] Create `src/views/settings/AdminSettings.vue`
  - On `onMounted`: read `data-*` from mount div; call `settingsStore.fetchAdminSettings()`
  - Render `CnVersionInfoCard` with version and update props
  - Render `CnSettingsSection` "Default Project Configuration" containing `ColumnListEditor`
  - Render `CnSettingsSection` "Register Setup" with init status and button
  - "Save changes" button calls `updateAdminSettings({ default_columns: columns.value })`
- [ ] Add webpack entry `admin-settings.js` that imports and mounts `AdminSettings.vue`
- [ ] Register the entry point so `\OCP\Util::addScript('planix', 'admin-settings')` resolves correctly
- [ ] Test

---

### Task 8: ColumnListEditor Component
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-column-list-editor-component`
- **files**: `src/components/settings/ColumnListEditor.vue`
- **acceptance_criteria**:
  - GIVEN `modelValue: ["To Do", "In Progress", "Done"]` WHEN `ColumnListEditor` renders THEN 3 rows appear with correct labels and a drag handle, editable text input, and remove button on each
  - GIVEN the admin drags row 2 above row 1 WHEN the drag completes THEN `update:modelValue` is emitted with the reordered array
  - GIVEN the admin clicks "Move up" on row 2 WHEN the action completes THEN `update:modelValue` is emitted with row 2 in position 1; focus follows the moved row
  - GIVEN only 1 row remains WHEN the component renders THEN the remove button is disabled
  - GIVEN an empty column name exists WHEN the parent validates before saving THEN the parent can check `modelValue.some(v => v.trim() === '')` and show an error
- [ ] Create `src/components/settings/ColumnListEditor.vue`
  - Props: `{ modelValue: Array, disabled: Boolean }`
  - Emit: `['update:modelValue']`
  - Use `vue-draggable-plus` (SortableJS wrapper) if available; otherwise HTML5 DnD API
  - Each row: drag handle (`IconDragVertical`), `<input type="text">`, Move Up button, Move Down button, Remove button
  - "Add column" button appends empty string and focuses the new input
  - Remove button disabled when `modelValue.length <= 1`
  - Move Up disabled for first row; Move Down disabled for last row
- [ ] Keyboard accessibility: Move Up/Down buttons must be focusable and operable via Enter/Space
- [ ] Use CSS variables for colors; no hardcoded color values
- [ ] Test

---

### Task 9: UserSettings.vue — NcAppSettingsDialog
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-ncappsettingsdialog-layout`
- **files**: `src/views/settings/UserSettings.vue`
- **acceptance_criteria**:
  - GIVEN the gear icon is clicked in Planix navigation WHEN `UserSettings.vue` is opened THEN it renders as an `NcAppSettingsDialog` (NOT `NcDialog`)
  - GIVEN the dialog opens WHEN `onMounted` runs THEN `settingsStore.fetchUserSettings()` is called; toggles show loading state until resolved
  - GIVEN settings are loaded WHEN the dialog renders THEN the "Notifications" section shows two toggles with correct initial values; "Display" section shows the default view selector with correct initial value
  - GIVEN the user switches to the "Display" section WHEN the section nav item is clicked THEN the content area transitions to the Display content
- [ ] Implement `src/views/settings/UserSettings.vue` (replaces empty placeholder)
  - Use `NcAppSettingsDialog` with `sections` prop: `[{ id: 'notifications', name: t('planix', 'Notifications') }, { id: 'display', name: t('planix', 'Display') }]`
  - On `onMounted`: call `settingsStore.fetchUserSettings()`
  - Render Notifications section: two `NcCheckboxRadioSwitch` (type="switch") for `notify_assigned` and `notify_due_reminder`
  - Render Display section: label + `NcSelect` or `NcCheckboxRadioSwitch` (type="radio") for `default_view`
  - Each control binds to `settingsStore.userSettings[key]` and calls `settingsStore.updateUserSetting(key, value)` on change
- [ ] Wire gear icon in `MainMenu.vue` to open the dialog (v-model or direct `open` call)
- [ ] Test

---

### Task 10: Wire Gear Icon in MainMenu.vue
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-ncappsettingsdialog-layout`
- **files**: `src/navigation/MainMenu.vue`
- **acceptance_criteria**:
  - GIVEN the user is in Planix WHEN they look at the bottom of the navigation sidebar THEN a gear icon (`NcAppNavigationItem` or `NcButton` with gear icon) is visible
  - GIVEN the user clicks the gear icon WHEN the click handler fires THEN `showUserSettings.value = true` causes `UserSettings.vue` to render and open
- [ ] Add a gear icon navigation item at the bottom of `MainMenu.vue` using the `#footer` slot or equivalent
- [ ] Add `const showUserSettings = ref(false)` and toggle on gear icon click
- [ ] Include `<UserSettings v-if="showUserSettings" @close="showUserSettings = false" />`
- [ ] Test

---

### Task 11: NotificationService — Align SUBJECT_SETTING_MAP Keys
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-notification-toggles`
- **files**: `lib/Service/NotificationService.php`
- **acceptance_criteria**:
  - GIVEN `NotificationService::SUBJECT_SETTING_MAP` is inspected WHEN the map is read THEN `'task_assigned'` maps to `'notify_assigned'` (not `'notify_task_assigned'`)
  - GIVEN `NotificationService::SUBJECT_SETTING_MAP` is inspected WHEN the map is read THEN `'task_due_soon'` maps to `'notify_due_reminder'`
  - GIVEN user B has `notify_assigned = 'no'` in `IConfig` WHEN `notify('task_assigned', ..., userBUid)` is called THEN no notification is sent
  - GIVEN user B has `notify_due_reminder = 'no'` WHEN `notify('task_due_soon', ..., userBUid)` is called THEN no notification is sent
- [ ] Update `SUBJECT_SETTING_MAP` in `lib/Service/NotificationService.php`:
  ```php
  private const SUBJECT_SETTING_MAP = [
      'task_assigned'       => 'notify_assigned',
      'task_due_soon'       => 'notify_due_reminder',
      // V1 — declared but not triggered in MVP:
      'task_overdue'        => 'notify_overdue',
      'task_commented'      => 'notify_commented',
      'task_status_changed' => 'notify_status_changed',
  ];
  ```
- [ ] Verify default value logic: `IConfig::getUserValue($uid, 'planix', 'notify_assigned', 'yes')` returns `'yes'` for users who have never toggled the setting (correct default-on behaviour)
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 12: i18n — English Strings
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-i18n-coverage`
- **files**: `l10n/en.json`
- **acceptance_criteria**:
  - GIVEN the `l10n/en.json` file WHEN inspected THEN all strings listed in the i18n inventory in `design.md` are present as keys
  - GIVEN any Vue template or PHP file in this change WHEN all user-visible strings are checked THEN each uses `t('planix', '...')` / `$this->l10n->t('...')` and the key exists in `en.json`
- [ ] Add all admin and user settings strings to `l10n/en.json` (see i18n inventory in `design.md`)
- [ ] Verify no hardcoded English strings remain in any new or modified component or PHP file
- [ ] Test

---

### Task 13: i18n — Dutch Translations
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-i18n-coverage`
- **files**: `l10n/nl.json`
- **acceptance_criteria**:
  - GIVEN the `l10n/nl.json` file WHEN compared to `l10n/en.json` THEN every key added by this change in `en.json` also exists in `nl.json`
  - GIVEN the Dutch translations WHEN reviewed THEN they are natural Dutch (not literal translations or English placeholders)
- [ ] Add Dutch translations for all settings strings to `l10n/nl.json`
- [ ] Key translations:
  - `Planix Settings` → `Planix instellingen`
  - `Default Project Configuration` → `Standaard projectconfiguratie`
  - `Default columns for new projects` → `Standaard kolommen voor nieuwe projecten`
  - `Add column` → `Kolom toevoegen`
  - `Remove column` → `Kolom verwijderen`
  - `Move up` → `Omhoog verplaatsen`
  - `Move down` → `Omlaag verplaatsen`
  - `Save changes` → `Wijzigingen opslaan`
  - `Register Setup` → `Register instellen`
  - `Register initialized` → `Register geïnitialiseerd`
  - `Register not initialized` → `Register niet geïnitialiseerd`
  - `Initialize register` → `Register initialiseren`
  - `Notifications` → `Meldingen`
  - `Notify me when a task is assigned to me` → `Stuur een melding wanneer een taak aan mij is toegewezen`
  - `Notify me 1 day before a task's due date` → `Stuur een melding 1 dag voor de vervaldatum van een taak`
  - `Display` → `Weergave`
  - `Default view` → `Standaardweergave`
  - `My Work` → `Mijn werk`
  - `Settings saved` → `Instellingen opgeslagen`
  - `Failed to save settings` → `Instellingen konden niet worden opgeslagen`
- [ ] Test

---

### Task 14: BUG — Fix /api/health and /api/metrics Endpoints (from test-app 2026-04-04)
- **spec_ref**: `openspec/specs/admin-user-settings.md`
- **files**: `lib/Controller/SettingsController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN an authenticated user calls `GET /index.php/apps/planix/api/health` WHEN the controller handles the request THEN HTTP 200 is returned with a JSON health status (not 500)
  - GIVEN an authenticated user calls `GET /index.php/apps/planix/api/metrics` WHEN the controller handles the request THEN HTTP 200 is returned with a JSON metrics object (not 500)
- **bug_details**: API test agent found both `/api/health` and `/api/metrics` return HTTP 500 Internal Server Error. Either the routes are not defined, the controller actions throw unhandled exceptions, or required dependencies are not injected.
- **severity**: MEDIUM
- [ ] Check if `/api/health` and `/api/metrics` routes exist in `appinfo/routes.php`
- [ ] If routes exist: check the controller action for unhandled exceptions or missing dependencies
- [ ] If routes don't exist: either add them or remove them from the app (if not part of the spec)
- [ ] Test

---

### Task 15: BUG — Admin Settings Route Returns 404 (from test-app 2026-04-04)
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-admin-settings-vue-mount`
- **files**: `lib/Settings/AdminSettings.php`, `lib/AppInfo/Application.php`
- **acceptance_criteria**:
  - GIVEN an admin navigates to `/settings/admin/planix` WHEN the page loads THEN the Planix admin settings panel renders (not a 404)
  - GIVEN `AdminSettings.php` WHEN registered in `Application.php` THEN it is registered via `ISettingsManager::registerSettings()`
- **bug_details**: All test agents found that `/settings/admin/planix` returns 404. The app has an internal settings page at `/index.php/apps/planix/settings`, but the standard Nextcloud admin settings integration is missing.
- **severity**: MEDIUM
- [ ] Verify `AdminSettings.php` implements `ISettings` and is registered in `Application.php` via `$context->registerSettings()`
- [ ] Check that the section ID matches `planix` and the priority is set correctly
- [ ] If AdminSettings.php doesn't exist yet: this is expected and will be implemented in Task 6-7 of this change
- [ ] Test

---

### Task 16: BUG — Settings Form Labels Use div Instead of label Elements (from test-app 2026-04-04)
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#requirement-ncappsettingsdialog-layout`
- **files**: `src/views/settings/AdminSettings.vue`, `src/views/settings/UserSettings.vue`
- **acceptance_criteria**:
  - GIVEN any form field in admin or user settings WHEN inspected in the DOM THEN it has a proper `<label>` element associated via `for` attribute or wrapping the input
  - GIVEN a screen reader user navigates the settings form WHEN they tab to an input THEN the label is announced (WCAG 3.3.2)
- **bug_details**: Accessibility test agent found that configuration labels in the settings views use `<div>` elements instead of proper `<label>` elements. This is a WCAG 3.3.2 violation.
- **severity**: MEDIUM
- [ ] Audit all form inputs in `AdminSettings.vue` and `UserSettings.vue` for proper `<label>` elements
- [ ] Replace any `<div>` acting as a label with a `<label for="...">` element
- [ ] Ensure NcCheckboxRadioSwitch components have proper label text (they handle this internally — verify usage is correct)
- [ ] Test with keyboard navigation

---

## Verification
- [ ] All tasks checked off
- [ ] Manual testing against acceptance criteria
- [ ] Admin settings page visible under Nextcloud Administration → Planix (admin account only)
- [ ] User settings dialog opens from gear icon in Planix navigation
- [ ] Default columns change persists after page reload
- [ ] Register initialization button works on a clean install
- [ ] Notification toggles save and are respected by NotificationService

## Tests (company-wide ADR-009)
- [ ] PHPUnit unit tests for `SettingsController::adminIndex` (returns all expected keys, 403 for non-admin)
- [ ] PHPUnit unit tests for `SettingsController::userIndex` and `userUpdate` (returns user-scoped values, boolean conversion)
- [ ] PHPUnit unit tests for `SettingsController::adminRegisterInit` (success + exception handling, 403 for non-admin)
- [ ] PHPUnit unit tests for `NotificationService` with updated `SUBJECT_SETTING_MAP` key names (preference check, self-notification guard)
- [ ] Browser tests (Playwright MCP) for admin settings page: version card renders, column list editable, save persists
- [ ] Browser tests (Playwright MCP) for user settings dialog: opens from gear icon, toggle saves, default view selector saves
- [ ] Browser tests (Playwright MCP) for register init: button shows spinner, success state updates after init
- [ ] Browser tests (Playwright MCP) for settings persistence: reload and verify saved values are still shown
- [ ] All tests pass

## Documentation (company-wide ADR-010)
- [ ] Feature documentation updated (admin settings and user settings sections in `docs/`)
- [ ] Screenshot captured: admin settings page with version card and column editor; user settings dialog open on Notifications section

## i18n (company-wide ADR-005)
- [ ] Dutch and English translation strings added (Tasks 12 and 13)
