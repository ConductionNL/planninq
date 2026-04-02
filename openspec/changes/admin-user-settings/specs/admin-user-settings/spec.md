# Delta Spec: admin-user-settings

**Capability:** admin-user-settings
**Change ID:** admin-user-settings
**Delta type:** implementation
**Base spec:** [openspec/specs/admin-user-settings.md](../../../../specs/admin-user-settings.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the admin settings page and user settings dialog. The base spec (`openspec/specs/admin-user-settings.md`) defines all business requirements, scenarios, user stories, and acceptance criteria. The delta below documents:

1. Vue component patterns required by the implementation architecture (admin template mount strategy, `NcAppSettingsDialog` layout).
2. `ColumnListEditor` component contract (drag-to-reorder, keyboard accessibility, v-model interface).
3. `CnVersionInfoCard` integration requirements (data injection, update-check caching).
4. Register initialization flow details (synchronous endpoint, double-click guard).
5. `NotificationService` `SUBJECT_SETTING_MAP` key alignment.
6. Pinia settings store patterns (optimistic update, error rollback).
7. Loading and error state requirements.
8. i18n requirements.

All base spec requirements are implemented as-is. No base spec requirement is modified or removed.

---

## ADDED Requirements

### Requirement: CnVersionInfoCard Integration [MVP]

The admin settings page MUST use `CnVersionInfoCard` from `@conduction/nextcloud-vue` as the first rendered section.

#### Scenario: Version card renders current version
- GIVEN a Nextcloud admin opens Administration → Planix
- WHEN `AdminSettings.vue` mounts
- THEN `CnVersionInfoCard` MUST be the first visible element
- AND it MUST display the app name ("Planix") and the current installed version (read from `data-app-version` attribute injected by `AdminSettings.php`)
- AND the version MUST match the value returned by `\OCP\App::getAppVersion('planix')`

#### Scenario: Version card — update available
- GIVEN the PHP `AdminSettings.php` detects a newer version in the app store (cached, TTL 1 hour)
- WHEN the admin page renders
- THEN `CnVersionInfoCard` MUST receive `updateAvailable: true` and `updateVersion: "{newVersion}"`
- AND the card MUST display an "Update available" indicator linking to the Nextcloud App Store entry for Planix
- AND the update check result MUST be served from the `ICache` layer to avoid a live app store HTTP call on every page load

#### Scenario: Version card — up to date
- GIVEN no newer version is available
- WHEN the admin page renders
- THEN `CnVersionInfoCard` MUST show an "Up to date" indicator (no update link)

---

### Requirement: Admin Settings Vue Mount [MVP]

The admin settings page MUST render the Vue `AdminSettings.vue` component inside the PHP template.

#### Scenario: Vue component mounts in admin template
- GIVEN a Nextcloud admin navigates to Administration → Planix
- WHEN the PHP template `templates/admin-settings.php` is rendered
- THEN the template MUST include a `<div id="planix-admin-settings" data-app-version="{version}" data-update-available="{bool}" data-update-version="{version|null}" data-register-initialized="{bool}"></div>` mount point
- AND the template MUST load the `admin-settings.js` webpack entry point
- AND `AdminSettings.vue` MUST mount onto `#planix-admin-settings` and read initial values from the `data-*` attributes (no additional network request for static values)

#### Scenario: Admin settings fetch editable values on mount
- GIVEN `AdminSettings.vue` has mounted
- WHEN the component's `onMounted` hook runs
- THEN the component MUST call `settingsStore.fetchAdminSettings()` to retrieve `default_columns` and other editable settings via `GET /planix/settings/admin`
- AND a loading skeleton MUST be shown until the fetch resolves

---

### Requirement: Column List Editor Component [MVP]

The `ColumnListEditor.vue` component MUST provide an accessible, ordered, editable list of column name strings.

#### Scenario: Render column list
- GIVEN `ColumnListEditor` receives `modelValue: ["To Do", "In Progress", "Review", "Done"]`
- WHEN the component renders
- THEN each column name MUST appear as a row with: a drag handle icon, an editable text input, and a remove button
- AND the rows MUST be rendered in the order provided by `modelValue`

#### Scenario: Add a column
- GIVEN the `ColumnListEditor` is rendered
- WHEN the admin clicks "Add column"
- THEN a new empty text input row MUST be appended to the list
- AND focus MUST move to the new input automatically
- AND `update:modelValue` MUST be emitted with the new array including the empty string

#### Scenario: Remove a column
- GIVEN the list has 2 or more columns
- WHEN the admin clicks the remove button on a row
- THEN that row MUST be removed from the list
- AND `update:modelValue` MUST be emitted with the updated array
- AND the remove button MUST be disabled (visually and functionally) when only 1 column remains

#### Scenario: Reorder via drag-and-drop
- GIVEN the list has at least 2 columns
- WHEN the admin drags a row to a new position using the drag handle
- THEN the list order MUST update immediately (optimistic, no save button needed for visual update)
- AND `update:modelValue` MUST be emitted with the reordered array

#### Scenario: Reorder via keyboard (WCAG AA)
- GIVEN the list has at least 2 columns
- WHEN the admin focuses a row and clicks the "Move up" or "Move down" button
- THEN the row MUST move one position in the indicated direction
- AND `update:modelValue` MUST be emitted with the reordered array
- AND focus MUST follow the moved row to maintain keyboard navigation context

#### Scenario: Empty column name validation
- GIVEN the admin clears a column name input (leaving it empty)
- WHEN the "Save changes" button is clicked in the parent `AdminSettings.vue`
- THEN the parent MUST validate that no column name is empty
- AND if validation fails, an inline error MUST appear: `t('planix', 'Column name cannot be empty')`
- AND the save request MUST NOT be sent

---

### Requirement: Register Initialization Flow [MVP]

#### Scenario: Register already initialized
- GIVEN OpenRegister has already been initialized for Planix (detected via `ConfigurationService::isInitialized()`)
- WHEN the admin settings page renders
- THEN the "Register Setup" section MUST show a green checkmark and the text "Register initialized"
- AND the "Initialize register" button MUST NOT be shown (or shown as disabled with label "Already initialized")

#### Scenario: Register not initialized
- GIVEN OpenRegister is NOT initialized for Planix
- WHEN the admin settings page renders
- THEN the "Register Setup" section MUST show a warning indicator and the text "Register not initialized"
- AND an "Initialize register" button MUST be visible and enabled

#### Scenario: Trigger initialization
- GIVEN the "Initialize register" button is enabled
- WHEN the admin clicks it
- THEN the button MUST immediately become disabled and show a spinner with label "Initializing…"
- AND the frontend MUST call `settingsStore.initRegister()` which POSTs to `/planix/settings/admin/register-init`
- AND the PHP endpoint MUST call `ConfigurationService::importFromApp()` synchronously
- AND on success, the section status MUST update to "Register initialized"
- AND a success toast MUST be shown: `t('planix', 'Register initialized successfully')`

#### Scenario: Initialization failure
- GIVEN the "Initialize register" request fails (API error or `importFromApp()` throws)
- WHEN the store catches the error
- THEN the button MUST re-enable with its original label
- AND an error toast MUST be shown: `t('planix', 'Failed to initialize register')`
- AND the section status indicator MUST remain in the "not initialized" state

---

### Requirement: NcAppSettingsDialog Layout [MVP]

The user settings dialog MUST use `NcAppSettingsDialog` and be opened from the Planix navigation gear icon.

#### Scenario: Open user settings dialog from gear icon
- GIVEN a user is using Planix
- WHEN the user clicks the gear icon in the Planix navigation sidebar (bottom navigation item)
- THEN `UserSettings.vue` MUST open as an `NcAppSettingsDialog`
- AND the dialog MUST show two sections in its sidebar: "Notifications" and "Display"
- AND the first section ("Notifications") MUST be selected by default

#### Scenario: Dialog navigation — switch section
- GIVEN the user settings dialog is open on the "Notifications" section
- WHEN the user clicks "Display" in the dialog's section sidebar
- THEN the content area MUST transition to show the Display section content
- AND the "Display" section item MUST be highlighted as active

#### Scenario: Load user settings on open
- GIVEN the user settings dialog is opened
- WHEN `UserSettings.vue` mounts
- THEN `settingsStore.fetchUserSettings()` MUST be called
- AND while loading, each toggle and selector MUST render in a loading/skeleton state
- AND after loading, each control MUST reflect the current saved value from `IConfig`

---

### Requirement: Notification Toggles [MVP]

#### Scenario: Display notification toggles
- GIVEN the user settings dialog is open on the "Notifications" section
- WHEN the section content renders
- THEN the following toggles MUST be present:
  - "Notify me when a task is assigned to me" (`notify_assigned`, default: on)
  - "Notify me 1 day before a task's due date" (`notify_due_reminder`, default: on)
- AND each toggle MUST be an `NcCheckboxRadioSwitch` (toggle mode) from `@nextcloud/vue`
- AND each toggle's state MUST reflect the value returned by `fetchUserSettings()`

#### Scenario: Toggle notification preference — save immediately
- GIVEN the "Notifications" section is rendered with toggles
- WHEN the user clicks a toggle to change its state
- THEN `settingsStore.updateUserSetting(key, value)` MUST be called immediately (no separate Save button for toggles)
- AND the toggle MUST update optimistically (change is visible before server confirms)
- AND on success, a brief confirmation indicator (or no feedback for toggle, per NC convention) is shown
- AND on failure, the toggle MUST revert to its previous state and an error toast MUST appear

#### Scenario: Notification service respects user preference
- GIVEN a user has toggled `notify_assigned` to off
- WHEN user B assigns a task to this user
- THEN `NotificationService::notify('task_assigned', ...)` MUST check the user's `notify_assigned` IConfig value
- AND MUST find `false` (or string `'no'`)
- AND MUST NOT create or send the notification
- AND the assignment MUST still succeed

---

### Requirement: Default View Selector [MVP]

#### Scenario: Display default view selector
- GIVEN the user settings dialog is open on the "Display" section
- WHEN the section content renders
- THEN a "Default view" label and a dropdown/radio selector MUST be present
- AND the three options MUST be: "My Work" (value: `my-work`), "Kanban" (value: `kanban`), "Backlog" (value: `backlog`)
- AND the selector MUST reflect the value returned by `fetchUserSettings()` (default: `my-work`)

#### Scenario: Change default view
- GIVEN the "Display" section is rendered
- WHEN the user selects "Kanban" from the default view selector
- THEN `settingsStore.updateUserSetting('default_view', 'kanban')` MUST be called immediately
- AND the next time the user opens a project (without a saved route), Planix MUST navigate to the Kanban view
- AND the setting MUST persist in `OCP\IConfig` across browser sessions

---

### Requirement: Settings Persistence and Backend [MVP]

#### Scenario: Admin settings persist via IAppConfig
- GIVEN the admin changes the default columns list and clicks "Save changes"
- WHEN `settingsStore.updateAdminSettings({ default_columns: [...] })` calls `PUT /planix/settings/admin`
- THEN the backend MUST call `IAppConfig::setValueArray('planix', 'default_columns', [...])` (or `setValueString` with JSON-encoded value)
- AND subsequent reads via `GET /planix/settings/admin` MUST return the updated value
- AND new projects created after the save MUST use the updated column set

#### Scenario: User settings persist via IConfig
- GIVEN a user toggles `notify_assigned` to off
- WHEN `PUT /planix/settings/user` is called with `{ notify_assigned: false }`
- THEN the backend MUST call `IConfig::setUserValue($uid, 'planix', 'notify_assigned', 'no')`
- AND subsequent calls to `GET /planix/settings/user` MUST return `notify_assigned: false`
- AND the toggle MUST still be off after the user closes and reopens the dialog

#### Scenario: User settings survive browser restart
- GIVEN a user has set `notify_assigned = false` and `default_view = kanban`
- WHEN the user closes the browser, clears session cookies, and returns to Planix
- THEN `GET /planix/settings/user` MUST still return `notify_assigned: false` and `default_view: "kanban"` (stored server-side in `IConfig`, not in browser storage)

---

### Requirement: Admin Access Control [MVP]

#### Scenario: Admin endpoint blocked for regular users
- GIVEN a regular (non-admin) Nextcloud user
- WHEN they call `GET /planix/settings/admin`, `PUT /planix/settings/admin`, or `POST /planix/settings/admin/register-init`
- THEN Nextcloud MUST return HTTP 403 Forbidden
- AND the admin settings link MUST NOT appear in the user's Nextcloud settings navigation

#### Scenario: User settings accessible to all authenticated users
- GIVEN any authenticated Nextcloud user
- WHEN they call `GET /planix/settings/user` or `PUT /planix/settings/user`
- THEN the request MUST succeed (200)
- AND the response MUST only contain settings for the calling user (uid from session)

---

### Requirement: i18n Coverage [MVP]

#### Scenario: All user-visible strings use t()
- GIVEN any Vue component or PHP file in this change
- WHEN it contains a string visible to the end user
- THEN the string MUST be wrapped in `t('planix', '...')` (Vue) or `$this->l10n->t('...')` (PHP)
- AND the key MUST be present in both `l10n/en.json` and `l10n/nl.json`
- AND NO English text MUST appear as a hardcoded string in templates or PHP output

#### Scenario: Dutch translation completeness
- GIVEN the `l10n/nl.json` file
- WHEN checked against `l10n/en.json`
- THEN every key present in `en.json` introduced by this change MUST also be present in `nl.json`
- AND all Dutch translations MUST be human-readable Dutch (no English placeholders, no machine-translation artifacts)

---

### Requirement: Loading and Error States [MVP]

#### Scenario: Admin settings loading
- GIVEN `AdminSettings.vue` is mounted
- WHEN `fetchAdminSettings()` is in progress
- THEN the `ColumnListEditor` area MUST show a skeleton loading state
- AND the "Save changes" button MUST be disabled until loading completes

#### Scenario: User settings loading
- GIVEN the `UserSettings.vue` dialog has opened
- WHEN `fetchUserSettings()` is in progress
- THEN all toggles and selectors MUST show a loading/disabled state
- AND no stale values MUST be shown (no flash of default values before server values load)

#### Scenario: Save failure — optimistic rollback
- GIVEN the user changes a setting (toggle or selector)
- WHEN `updateUserSetting()` applies the change optimistically AND the API call fails
- THEN the control MUST revert to its previous state
- AND an error toast MUST be shown: `t('planix', 'Failed to save settings')`
