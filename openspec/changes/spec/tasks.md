# Tasks — Admin Settings MVP

## Task 1: Backend — SettingsController admin endpoints
**spec_ref**: admin-user-settings.md → Requirement: Admin Settings Page
**files_likely_affected**: lib/Controller/SettingsController.php, appinfo/routes.php
**acceptance_criteria**:
- [x] `GET /api/settings` returns current admin settings as JSON
- [x] `POST /api/settings` accepts a JSON body and stores values via IAppConfig
- [x] Settings include: `default_columns` (JSON string), `allow_project_creation` (string)
- [x] Only admin users can write settings (middleware or annotation check)
- [x] Returns 403 for non-admin write attempts

## Task 2: Backend — SettingsService business logic
**spec_ref**: admin-user-settings.md → Data Model
**files_likely_affected**: lib/Service/SettingsService.php
**acceptance_criteria**:
- [x] `getAdminSettings()` reads all planix admin keys from IAppConfig with defaults
- [x] `setAdminSettings(array $settings)` validates and stores each key
- [x] Default values match the spec: `default_columns = ["To Do","In Progress","Review","Done"]`
- [x] Unknown keys are silently ignored (no error, no storage)

## Task 3: Frontend — AdminRoot with CnVersionInfoCard
**spec_ref**: admin-user-settings.md → Scenario: View admin settings
**files_likely_affected**: src/views/settings/AdminRoot.vue, src/views/settings/Settings.vue
**acceptance_criteria**:
- [x] Admin settings page renders under Nextcloud Administration → Planix
- [x] First section is CnVersionInfoCard showing app name and version
- [x] Page uses CnSettingsSection for each logical group
- [x] Loads current settings from `GET /api/settings` on mount

## Task 4: Frontend — Default columns editor
**spec_ref**: admin-user-settings.md → Scenario: Configure default columns
**files_likely_affected**: src/views/settings/Settings.vue or new component
**acceptance_criteria**:
- [x] Shows current default columns as an editable ordered list
- [x] Admin can add, remove, and reorder column names
- [x] Changes are saved via `POST /api/settings` on save button click
- [x] Shows success/error feedback after save

## Task 5: Frontend — OpenRegister initialization section
**spec_ref**: admin-user-settings.md → Scenario: OpenRegister initialization
**files_likely_affected**: src/views/settings/Settings.vue or new component
**acceptance_criteria**:
- [x] Shows whether the Planix register is initialized (green check / warning)
- [x] If not initialized, shows "Initialize register" button
- [x] Button triggers register initialization (calls backend endpoint)
- [x] Shows loading state during initialization
- [x] Shows success or error result after completion
