# Tasks — Admin Settings MVP

## Task 1: Backend — SettingsController admin endpoints
**spec_ref**: admin-user-settings.md → Requirement: Admin Settings Page
**files_likely_affected**: lib/Controller/SettingsController.php, appinfo/routes.php
**acceptance_criteria**:
- [ ] `GET /api/settings` returns current admin settings as JSON
- [ ] `POST /api/settings` accepts a JSON body and stores values via IAppConfig
- [ ] Settings include: `default_columns` (JSON string), `allow_project_creation` (string)
- [ ] Only admin users can write settings (middleware or annotation check)
- [ ] Returns 403 for non-admin write attempts

## Task 2: Backend — SettingsService business logic
**spec_ref**: admin-user-settings.md → Data Model
**files_likely_affected**: lib/Service/SettingsService.php
**acceptance_criteria**:
- [ ] `getAdminSettings()` reads all planix admin keys from IAppConfig with defaults
- [ ] `setAdminSettings(array $settings)` validates and stores each key
- [ ] Default values match the spec: `default_columns = ["To Do","In Progress","Review","Done"]`
- [ ] Unknown keys are silently ignored (no error, no storage)

## Task 3: Frontend — AdminRoot with CnVersionInfoCard
**spec_ref**: admin-user-settings.md → Scenario: View admin settings
**files_likely_affected**: src/views/settings/AdminRoot.vue, src/views/settings/Settings.vue
**acceptance_criteria**:
- [ ] Admin settings page renders under Nextcloud Administration → Planix
- [ ] First section is CnVersionInfoCard showing app name and version
- [ ] Page uses CnSettingsSection for each logical group
- [ ] Loads current settings from `GET /api/settings` on mount

## Task 4: Frontend — Default columns editor
**spec_ref**: admin-user-settings.md → Scenario: Configure default columns
**files_likely_affected**: src/views/settings/Settings.vue or new component
**acceptance_criteria**:
- [ ] Shows current default columns as an editable ordered list
- [ ] Admin can add, remove, and reorder column names
- [ ] Changes are saved via `POST /api/settings` on save button click
- [ ] Shows success/error feedback after save

## Task 5: Frontend — OpenRegister initialization section
**spec_ref**: admin-user-settings.md → Scenario: OpenRegister initialization
**files_likely_affected**: src/views/settings/Settings.vue or new component
**acceptance_criteria**:
- [ ] Shows whether the Planix register is initialized (green check / warning)
- [ ] If not initialized, shows "Initialize register" button
- [ ] Button triggers register initialization (calls backend endpoint)
- [ ] Shows loading state during initialization
- [ ] Shows success or error result after completion
