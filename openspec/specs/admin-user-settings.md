# Admin & User Settings Specification

**Status**: planned

**Standards**: Nextcloud OCP\IAppConfig (admin), OCP\IConfig (user), NcAppSettingsDialog (user), CnSettingsSection + CnVersionInfoCard (@conduction/nextcloud-vue)
**Feature tier**: MVP

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

## Purpose

Planix exposes a Nextcloud admin settings panel for app-level configuration and a user settings dialog for personal preferences and notification toggles. Admin settings control default behaviors (column templates, Procest bridge) and are stored via `IAppConfig`. User settings control personal preferences and notification opt-in/opt-out and are stored via `OCP\IConfig`. Both integrate into Nextcloud's existing settings infrastructure rather than implementing custom configuration UIs.

## Data Model

No OpenRegister entities. Settings are stored in Nextcloud's native config storage:

**Admin settings** (`IAppConfig` — `appName = 'planix'`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `default_columns` | JSON string | `["To Do","In Progress","Review","Done"]` | Default column set for new projects |
| `allow_project_creation` | string: `all`, `admins`, `groups` | `all` | Who can create projects (V1) |
| `procest_bridge_enabled` | bool string | `false` | Enable Procest case → project bridge (V1) |
| `procest_base_url` | string | `""` | Procest API base URL (V1) |

**User settings** (`IConfig` — `appName = 'planix'`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `notify_assigned` | bool string | `true` | Notify when a task is assigned to me |
| `notify_due_reminder` | bool string | `true` | Notify 1 day before a task's due date |
| `notify_overdue` | bool string | `true` | Notify when a task is overdue (V1) |
| `notify_commented` | bool string | `true` | Notify when someone comments on my task (V1) |
| `notify_status_changed` | bool string | `false` | Notify when a task's status changes (V1) |
| `default_view` | string: `my-work`, `kanban`, `backlog` | `my-work` | Landing view after opening a project |
| `items_per_page` | integer string | `25` | Items per page in backlog view (V1) |

## Requirements

### Requirement: Admin Settings Page [MVP]
The system MUST provide an admin settings page under Nextcloud Administration → Planix.

#### Scenario: Admin access only
- GIVEN a regular (non-admin) Nextcloud user
- WHEN they attempt to access the Planix admin settings URL directly
- THEN Nextcloud MUST return a 403 Forbidden response
- AND the admin settings section MUST NOT appear in the user's Settings navigation

#### Scenario: View admin settings
- GIVEN a Nextcloud admin opens Administration → Planix
- THEN the system MUST render a CnVersionInfoCard as the first section (app name, version, update status)
- AND the system MUST show a CnSettingsSection for "Default Project Configuration"
- AND the section MUST show the current `default_columns` value as an editable list

#### Scenario: CnVersionInfoCard — update available
- GIVEN a newer version of Planix is available in the Nextcloud App Store
- WHEN the admin views the Planix admin settings
- THEN CnVersionInfoCard MUST display the current version and an "Update available" indicator
- AND the indicator MUST link to the Nextcloud App Store entry for Planix

#### Scenario: Configure default columns
- GIVEN the admin is in the "Default Project Configuration" section
- WHEN the admin adds, removes, or reorders column names
- THEN the system MUST store the updated list via IAppConfig
- AND new projects created after the change MUST use the updated column set

#### Scenario: OpenRegister initialization
- GIVEN Planix is freshly installed and OpenRegister is available
- WHEN a Nextcloud admin visits the Planix admin settings
- THEN the system MUST show a "Register Setup" section indicating whether the Planix register is initialized
- AND if not initialized, the admin MUST see an "Initialize register" button that triggers ConfigurationService::importFromApp()

### Requirement: User Settings Dialog [MVP]
The system MUST provide a user settings dialog via NcAppSettingsDialog, accessible from the Planix navigation bar.

#### Scenario: Open user settings dialog
- GIVEN a user is using Planix
- WHEN the user clicks the settings gear icon in the navigation
- THEN the system MUST open an NcAppSettingsDialog (NOT NcDialog)
- AND the dialog MUST contain at minimum:
  - A notification preferences section with toggles for `notify_assigned` and `notify_due_reminder`
  - A display preferences section with the `default_view` selector

#### Scenario: Toggle task assignment notifications
- GIVEN the user settings dialog is open
- WHEN the user toggles "Notify me when a task is assigned to me" to off
- THEN the system MUST save `notify_assigned = false` via OCP\IConfig
- AND subsequent task assignment notifications MUST NOT be sent to this user

#### Scenario: Change default view
- GIVEN the user settings dialog is open
- WHEN the user selects "Kanban" as their default view
- THEN the system MUST save `default_view = kanban` via OCP\IConfig
- AND the next time the user opens a project, the board view MUST be shown by default

#### Scenario: Settings persist across sessions
- GIVEN a user has set `notify_assigned = false` and `default_view = kanban`
- WHEN the user closes Planix and returns in a new browser session
- THEN the notification toggle MUST still be off
- AND the default view MUST still be "Kanban"

## User Stories

- As an admin, I want to configure the default column set so that new projects start with our team's standard workflow
- As an admin, I want to see the app version and health status so that I know when updates are available
- As a user, I want to control which notifications I receive so that I'm not overwhelmed by alerts
- As a user, I want to choose my default view so that Planix opens in the mode I use most
- As an admin, I want to initialize the OpenRegister schemas from the settings page so that I can set up the app without CLI access
- As an admin, I want to be notified of available updates in the settings page so that I can keep the app current
- As a user, I want my preferences to survive a browser restart so that I don't have to configure Planix each time

## Acceptance Criteria

- [ ] Admin settings page appears under Nextcloud Administration with link "Planix"
- [ ] Non-admin users cannot access the admin settings page (403 response; section not shown in navigation)
- [ ] First section is CnVersionInfoCard showing app name, version, and update status
- [ ] CnVersionInfoCard shows an "Update available" indicator and App Store link when a newer version exists
- [ ] Admin can configure default columns via an editable ordered list
- [ ] Admin can trigger OpenRegister initialization from the settings page
- [ ] NcAppSettingsDialog opens from the Planix navigation — uses NcAppSettingsDialog NOT NcDialog
- [ ] Dialog contains notification toggles: task assigned (default on), due date reminder (default on)
- [ ] Dialog contains display preferences: default view selector
- [ ] All settings persist across browser sessions (stored via IAppConfig / IConfig)
- [ ] Notification settings are respected by NotificationService (SUBJECT_SETTING_MAP pattern)
- [ ] Settings page is accessible (WCAG AA) and uses NL Design System CSS variables

## Notes

- CnVersionInfoCard is the first component on every admin settings page (required by Conduction conventions).
- CnSettingsSection wraps each logical settings group with an action slot, loading/error/empty states, and a footer.
- The Procest bridge settings section (V1) adds a toggle and base URL field in a dedicated CnSettingsSection.
- The `SUBJECT_SETTING_MAP` in NotificationService maps each notification subject key (e.g., `task_assigned`) to its corresponding user setting key (e.g., `notify_assigned`). Before sending any notification, the service checks the user's preference.
- Backend: Settings are exposed via `SettingsController` (admin) and `SettingsController` (user). The frontend queries `/settings/admin` and `/settings/user` to read/write them.
