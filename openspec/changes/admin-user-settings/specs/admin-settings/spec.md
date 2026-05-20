# Admin Settings Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [admin-user-settings](../../) — implements admin settings page with version info, default column config, and OpenRegister initialization

## Purpose

Defines the admin settings page rendered under Nextcloud Administration → Planix. The page exposes app-level configuration (default column templates, OpenRegister register initialization status) and integrates into Nextcloud's settings framework via `ISettings`. Admin-only access is enforced at the middleware layer.

## ADDED Requirements

### REQ-ADM-001: Admin Settings Page Registration [MVP]

The system MUST register a Nextcloud admin settings page for Planix under the "Additional settings" section.

#### Scenario ADM-001-A: Admin settings appear in Nextcloud Administration panel
- GIVEN a Nextcloud admin is logged in
- WHEN the admin navigates to Settings → Administration
- THEN the Planix admin settings page MUST appear in the navigation under "Additional settings"
- AND the link label MUST be "Planix"

#### Scenario ADM-001-B: Non-admin users are blocked
- GIVEN a regular (non-admin) Nextcloud user is logged in
- WHEN the user attempts to access the Planix admin settings URL directly
- THEN Nextcloud MUST return a 403 Forbidden response
- AND the admin settings section MUST NOT appear in the user's Settings navigation

### REQ-ADM-002: CnVersionInfoCard as First Section [MVP]

The system MUST render a `CnVersionInfoCard` as the first visible element on the admin settings page.

#### Scenario ADM-002-A: Version info card renders
- GIVEN a Nextcloud admin opens Administration → Planix
- THEN the system MUST render a `CnVersionInfoCard` as the first section
- AND the card MUST display the Planix app name and currently installed version
- AND the version value MUST be read from the `appVersion` webpack define (not a hardcoded string)

#### Scenario ADM-002-B: Update available indicator
- GIVEN a newer version of Planix is available in the Nextcloud App Store
- WHEN the admin views the Planix admin settings
- THEN `CnVersionInfoCard` MUST display an "Update available" indicator alongside the current version
- AND the indicator MUST link to the Nextcloud App Store entry for Planix

#### Scenario ADM-002-C: No update indicator when current
- GIVEN the installed version of Planix is the latest available in the App Store
- WHEN the admin views the Planix admin settings
- THEN `CnVersionInfoCard` MUST display only the current version with no "Update available" indicator

### REQ-ADM-003: Default Column Configuration [MVP]

The admin settings page MUST provide an editable ordered list allowing the admin to configure the `default_columns` value stored via `IAppConfig`.

#### Scenario ADM-003-A: View current default columns
- GIVEN a Nextcloud admin opens Administration → Planix
- WHEN the page loads
- THEN the system MUST display the current value of `default_columns` as an ordered list of column names
- AND if `default_columns` has never been set, the system MUST display the default: `["To Do","In Progress","Review","Done"]`

#### Scenario ADM-003-B: Add a column
- GIVEN the admin is in the "Default Project Configuration" section
- WHEN the admin types a new column name and clicks "Add"
- THEN the new column name MUST appear at the end of the ordered list
- AND the system MUST persist the updated list via `IAppConfig` on save

#### Scenario ADM-003-C: Remove a column
- GIVEN the admin is in the "Default Project Configuration" section
- WHEN the admin removes a column from the list
- THEN the column MUST be removed from the list
- AND the system MUST persist the updated list via `IAppConfig` on save

#### Scenario ADM-003-D: Reorder columns
- GIVEN the admin is in the "Default Project Configuration" section
- WHEN the admin reorders column names using drag-and-drop or up/down controls
- THEN the list MUST reflect the new order
- AND the system MUST persist the new order via `IAppConfig` on save

#### Scenario ADM-003-E: New projects use updated default columns
- GIVEN an admin has saved a new `default_columns` value
- WHEN a user creates a new project after the change
- THEN the new project's kanban board MUST be initialized with the updated column set

### REQ-ADM-004: OpenRegister Initialization [MVP]

The admin settings page MUST display the initialization status of the Planix OpenRegister register and provide an "Initialize register" button when not yet initialized.

#### Scenario ADM-004-A: Register already initialized
- GIVEN Planix has been previously initialized (register and schemas exist in OpenRegister)
- WHEN a Nextcloud admin opens Administration → Planix
- THEN the "Register Setup" section MUST show status "Initialized"
- AND the "Initialize register" button MUST NOT be shown

#### Scenario ADM-004-B: Register not initialized — button shown
- GIVEN Planix is freshly installed and the OpenRegister register has not been initialized
- WHEN a Nextcloud admin visits the Planix admin settings
- THEN the system MUST show a "Register Setup" section with status "Not initialized"
- AND the section MUST display an "Initialize register" button

#### Scenario ADM-004-C: Initialize register from settings
- GIVEN the "Register Setup" section shows "Not initialized"
- WHEN the admin clicks "Initialize register"
- THEN the system MUST call `ConfigurationService::importFromApp()` on the backend
- AND on success, the status MUST update to "Initialized"
- AND the "Initialize register" button MUST be hidden
- AND the system MUST show a success notification

#### Scenario ADM-004-D: Initialization failure is surfaced
- GIVEN the admin clicks "Initialize register"
- WHEN `ConfigurationService::importFromApp()` returns an error
- THEN the system MUST show a user-facing error notification
- AND the button MUST remain visible so the admin can retry

### REQ-ADM-005: Admin Settings Persistence [MVP]

All admin settings MUST be persisted via `IAppConfig` and MUST survive app upgrades and server restarts.

#### Scenario ADM-005-A: Settings survive a server restart
- GIVEN an admin has saved a custom `default_columns` value
- WHEN the Nextcloud server is restarted
- THEN the custom `default_columns` value MUST still be the active setting

#### Scenario ADM-005-B: Save failure is surfaced
- GIVEN the admin has made changes to admin settings
- WHEN the admin clicks "Save" and the backend returns an error
- THEN the system MUST display a user-facing error notification
- AND the settings MUST NOT be marked as saved

### REQ-ADM-006: Admin Settings API [MVP]

The system MUST expose `GET /api/settings/admin` and `POST /api/settings/admin` endpoints.

#### Scenario ADM-006-A: GET returns current admin settings
- GIVEN a Nextcloud admin sends `GET /api/settings/admin`
- THEN the system MUST return HTTP 200 with a JSON object containing:
  - `default_columns` as an array of strings
  - `allow_project_creation` as a string
  - `register_initialized` as a boolean

#### Scenario ADM-006-B: POST persists admin settings
- GIVEN a Nextcloud admin sends `POST /api/settings/admin` with a valid payload
- THEN the system MUST persist the values via `IAppConfig`
- AND return HTTP 200 with the updated settings object

#### Scenario ADM-006-C: Non-admin POST returns 403
- GIVEN a non-admin user sends `POST /api/settings/admin`
- THEN the system MUST return HTTP 403 Forbidden
- AND no settings MUST be modified

## Non-Functional Requirements

- **Security:** Admin endpoints annotated with `#[AuthorizedAdminSetting(Application::APP_ID)]` — enforced at middleware layer, never via body check.
- **Accessibility:** Admin settings page MUST be WCAG AA compliant. All form controls have associated labels. Color is not the sole indicator.
- **Internationalization:** All user-visible strings use `this.t('planix', '...')` (Vue) / `$this->l->t('...')` (PHP). No hardcoded Dutch or English labels in source.
- **Performance:** Admin settings page load MUST complete within 1 second on a standard Nextcloud installation.
- **Spec traceability:** `SettingsController.php` and `AdminSettings.php` MUST carry `@spec openspec/changes/admin-user-settings/tasks.md` PHPDoc tags.

## Acceptance Criteria

- [ ] Admin settings page appears under Nextcloud Administration → Additional settings → Planix
- [ ] Non-admin users receive 403 when accessing the admin settings URL; section does not appear in their navigation
- [ ] `CnVersionInfoCard` is the first rendered element, displaying app name and version
- [ ] "Update available" indicator and App Store link appear when a newer version exists
- [ ] Admin can add, remove, and reorder columns in the "Default Project Configuration" section
- [ ] Saved `default_columns` value is used by new projects created after the change
- [ ] "Register Setup" section shows correct initialization status
- [ ] "Initialize register" button triggers `ConfigurationService::importFromApp()` and reflects success/failure
- [ ] `GET /api/settings/admin` returns 200 with correct data for admin users
- [ ] `POST /api/settings/admin` returns 403 for non-admin users

## Notes

- `CnVersionInfoCard` is required as the first section on every Conduction admin settings page (convention enforced by `hydra-gate-admin-router` and design review).
- `AdminRoot.vue` MUST NOT be registered in the vue-router — it is mounted directly by the `settings.js` entry point (ADR-004, `hydra-gate-admin-router`).
- The `allow_project_creation` setting is stored in `IAppConfig` but its enforcement UI is deferred to V1.
- Related spec: `openspec/specs/admin-user-settings.md`.
