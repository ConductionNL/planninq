# Test Plan: admin-user-settings

## Test Cases

### TC-1: Version card renders in admin settings page
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-version-card-renders-current-version`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: Planix is installed; admin navigates to Administration → Planix
- **steps**: Open the Nextcloud Administration panel; navigate to Planix settings
- **expected result**: `CnVersionInfoCard` is the first visible element; it displays "Planix" and the current installed version matching `\OCP\App::getAppVersion('planix')`; an "Up to date" indicator is shown when no update is available
- **test command**: /test-functional

### TC-2: Version card shows update available indicator
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-version-card-update-available`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: A mock newer version is reported via the update check (ICache-backed); admin is on the Planix admin settings page
- **steps**: Load the admin settings page with a mocked newer version available
- **expected result**: `CnVersionInfoCard` shows "Update available" indicator with a link to the Nextcloud App Store entry for Planix; `updateVersion` shows the newer version number
- **test command**: /test-functional

### TC-3: Column list editor — add, edit, and remove columns
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-add-a-column`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: Admin is on the Planix admin settings page; `ColumnListEditor` is visible with the default 4 columns loaded
- **steps**: (a) Click "Add column" — observe new row; (b) Edit an existing column name; (c) Click the remove button on a column
- **expected result**: (a) New empty text input row appended; focus moves to the new input; (b) Name updates in the list; (c) Row is removed; remove button is disabled when only 1 column remains
- **test command**: /test-functional

### TC-4: Column list editor — drag-to-reorder
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-reorder-via-drag-and-drop`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: `ColumnListEditor` is visible with at least 3 columns
- **steps**: Drag the first column row to the third position
- **expected result**: List order updates immediately; `update:modelValue` is emitted with the reordered array
- **test command**: /test-functional

### TC-5: Column list editor — keyboard reorder (WCAG AA)
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-reorder-via-keyboard-wcag-aa`
- **type**: accessibility
- **persona**: keyboard-only administrator
- **preconditions**: `ColumnListEditor` is visible with at least 2 columns
- **steps**: Tab to a row; click "Move down" button using keyboard; verify focus follows the row
- **expected result**: Row moves one position down; `update:modelValue` emitted with new order; focus follows the moved row
- **test command**: /test-accessibility

### TC-6: Column list editor — empty name validation blocks save
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-empty-column-name-validation`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: `ColumnListEditor` is visible; admin clears one column name input
- **steps**: Clear a column name; click "Save changes" in the parent settings form
- **expected result**: Inline error "Column name cannot be empty" appears; save request is NOT sent
- **test command**: /test-functional

### TC-7: Register initialization — triggered and succeeds
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-trigger-initialization`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: OpenRegister is NOT initialized for Planix; admin is on the Planix admin settings page
- **steps**: Click "Initialize register" button
- **expected result**: Button immediately becomes disabled with spinner and label "Initializing…"; POST is sent to `/planix/settings/admin/register-init`; on success the section status updates to "Register initialized"; toast "Register initialized successfully" shown
- **test command**: /test-functional

### TC-8: Register initialization — failure re-enables button
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-initialization-failure`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: OpenRegister init endpoint is set to return an error
- **steps**: Click "Initialize register" button
- **expected result**: Button re-enables with original label on failure; error toast "Failed to initialize register" shown; section status indicator remains "not initialized"
- **test command**: /test-functional

### TC-9: Register already initialized — shows status, hides init button
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-register-already-initialized`
- **type**: functional
- **persona**: Nextcloud administrator
- **preconditions**: OpenRegister is already initialized for Planix
- **steps**: Navigate to Planix admin settings
- **expected result**: "Register Setup" section shows green checkmark and "Register initialized" text; "Initialize register" button is NOT shown (or shown as disabled "Already initialized")
- **test command**: /test-functional

### TC-10: User settings dialog opens from gear icon with correct sections
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-open-user-settings-dialog-from-gear-icon`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Planix is open; gear icon is visible in the navigation sidebar
- **steps**: Click the gear icon in the Planix navigation sidebar
- **expected result**: `UserSettings.vue` opens as `NcAppSettingsDialog`; dialog sidebar shows "Notifications" and "Display" sections; "Notifications" is selected by default
- **test command**: /test-functional

### TC-11: Notification toggles load current settings and save on toggle
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-toggle-notification-preference-save-immediately`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User settings dialog is open on "Notifications" section; user has `notify_assigned = true` saved
- **steps**: Observe the "Notify me when a task is assigned" toggle is on; click it to turn it off
- **expected result**: Toggle updates optimistically; `settingsStore.updateUserSetting('notify_assigned', false)` is called; on success, toggle remains off; on API failure, toggle reverts and error toast "Failed to save settings" shown
- **test command**: /test-functional

### TC-12: Notification preference disables assignment notifications
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-notification-service-respects-user-preference`
- **type**: functional
- **persona**: user B with `notify_assigned` disabled
- **preconditions**: User B has toggled `notify_assigned` to off; user A assigns a task to user B
- **steps**: User A assigns a task to user B
- **expected result**: No notification appears in user B's notification bell; the assignment succeeds; user B's `notify_assigned` remains false in `IConfig`
- **test command**: /test-functional

### TC-13: Default view selector saves and persists across sessions
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-change-default-view`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User settings dialog is open on "Display" section; current default is "My Work"
- **steps**: Select "Kanban" from the default view selector; close the dialog; close the browser; reopen Planix
- **expected result**: `updateUserSetting('default_view', 'kanban')` is called immediately; on next open of a project, Planix navigates to the Kanban view by default; setting persists across browser sessions (stored in `IConfig`)
- **test command**: /test-functional

### TC-14: Admin endpoints blocked for regular users (HTTP 403)
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-admin-endpoint-blocked-for-regular-users`
- **type**: security
- **persona**: regular (non-admin) Nextcloud user
- **preconditions**: A regular user account is available; user is logged in
- **steps**: Send direct HTTP requests to `GET /planix/settings/admin`, `PUT /planix/settings/admin`, and `POST /planix/settings/admin/register-init`
- **expected result**: All three endpoints return HTTP 403 Forbidden; admin settings link does NOT appear in the user's Nextcloud settings navigation
- **test command**: /test-api

### TC-15: User settings accessible to authenticated users, scoped to calling user
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-user-settings-accessible-to-all-authenticated-users`
- **type**: security
- **persona**: any authenticated Nextcloud user
- **preconditions**: Two user accounts exist (user A and user B); user A is logged in
- **steps**: Call `GET /planix/settings/user` as user A; call `PUT /planix/settings/user` with a value as user A
- **expected result**: Both requests succeed (HTTP 200); GET response only contains settings for user A; PUT only modifies user A's settings (user B's settings are unaffected)
- **test command**: /test-api

### TC-16: Settings persist across browser sessions (server-side storage)
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-user-settings-survive-browser-restart`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User has saved `notify_assigned = false` and `default_view = kanban`
- **steps**: Close the browser; clear session cookies; log back in; open Planix user settings dialog
- **expected result**: `GET /planix/settings/user` returns `notify_assigned: false` and `default_view: "kanban"`; both toggles/selectors reflect the saved values; values were not lost on session clear
- **test command**: /test-functional

### TC-17: User settings loading state — no stale flash
- **spec_ref**: `openspec/changes/admin-user-settings/specs/admin-user-settings/spec.md#scenario-user-settings-loading`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User settings dialog has a slow API response
- **steps**: Open the user settings dialog; observe the initial render before the API responds
- **expected result**: All toggles and selectors show a loading/disabled state; no stale or default values flash before server values load
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| CnVersionInfoCard Integration [MVP] | Current version, update available, up-to-date | TC-1, TC-2 |
| Admin Settings Vue Mount [MVP] | Template mount, editable values fetch | TC-1 |
| Column List Editor Component [MVP] | Add, remove, reorder (drag + keyboard), validation | TC-3, TC-4, TC-5, TC-6 |
| Register Initialization Flow [MVP] | Success, failure, already-initialized | TC-7, TC-8, TC-9 |
| NcAppSettingsDialog Layout [MVP] | Open from gear, section navigation, loading | TC-10, TC-17 |
| Notification Toggles [MVP] | Display, optimistic save, service respect | TC-11, TC-12 |
| Default View Selector [MVP] | Change and persist | TC-13 |
| Settings Persistence and Backend [MVP] | IAppConfig, IConfig, browser restart | TC-16 |
| Admin Access Control [MVP] | 403 for non-admin, user scoping | TC-14, TC-15 |
| Loading and Error States [MVP] | User settings loading, save rollback | TC-17, TC-11 |
| i18n Coverage [MVP] | Not covered in browser test (see Out of Scope) | — |

## Out of Scope

- i18n translation completeness — verified via build-time linting
- `CnVersionInfoCard` update check caching (ICache TTL) — verified via PHP unit test, not browser test
- Column list editor with Vue Draggable internals — drag-and-drop is tested in TC-4; internal SortableJS event handling is a unit test concern
