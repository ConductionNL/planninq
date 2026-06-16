# Admin & User Settings Specification

**Status**: in-progress

**Standards**: Nextcloud OCP\IAppConfig (admin), OCP\IConfig (user), NcAppSettingsDialog (user), CnSettingsSection + CnVersionInfoCard (@conduction/nextcloud-vue)
**Feature tier**: MVP

**OpenSpec changes:**
- [admin-user-settings](../changes/admin-user-settings/) — implements admin settings page, user settings dialog, notification toggles, column config

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

### Requirement: Due reminder toggle write-through to OpenRegister [MVP]
The `notify_due_reminder` user setting MUST be written through to the OpenRegister notification engine's override-only per-(schema, rule) user preference for (`task`, `taskDueSoon`):

- Toggling OFF MUST store the planix `IConfig` value `false` AND write the OR override `{"enabled": false}`
- Toggling ON MUST store the planix `IConfig` value `true` AND clear the OR override (null), so the user falls through to the schema default rather than pinning a stale explicit value
- The planix `IConfig` key remains the backing value the settings dialog reads; the OR override is the single source of truth for whether the engine delivers

#### Scenario: Toggle due-date reminders off
- GIVEN the user settings dialog is open
- WHEN the user toggles "Notify me 1 day before a task's due date" to off
- THEN the system MUST save `notify_due_reminder = false` via OCP\IConfig
- AND an OpenRegister override `{"enabled": false}` MUST be stored for this user for (`task`, `taskDueSoon`)
- AND subsequent `task_due_soon` notifications MUST NOT be delivered to this user

#### Scenario: Toggle due-date reminders back on
- GIVEN a user previously toggled `notify_due_reminder` off
- WHEN the user toggles it back on
- THEN the system MUST save `notify_due_reminder = true` via OCP\IConfig
- AND the user's OpenRegister override for (`task`, `taskDueSoon`) MUST be cleared (schema default applies)

#### Scenario: Existing opt-outs reconciled on upgrade
@e2e exclude one-shot upgrade repair step, covered by PHPUnit on the repair step
- GIVEN users who set `notify_due_reminder = false` before this change shipped
- WHEN planix is upgraded and the reconciliation repair step runs
- THEN each such user MUST have an OR override `{"enabled": false}` seeded for (`task`, `taskDueSoon`)
- AND running the repair step again MUST NOT alter overrides that users changed in the meantime (idempotent, no clobber)

### Requirement: Admin lead-time setting [MVP]
The admin settings page MUST expose the due-date reminder lead time as an editable field inside an existing CnSettingsSection, backed by `IAppConfig` key `due_reminder_lead_hours` (integer string, default `24`, validated range 1–336 hours).

#### Scenario: Lead-time field shown with default
- GIVEN a Nextcloud admin opens Administration → Planix
- THEN a "Due-date reminder lead time (hours)" field MUST be visible
- AND on a fresh install it MUST show `24`

#### Scenario: Saving a new lead time
- GIVEN the admin changes the lead time to `48` and saves
- THEN `due_reminder_lead_hours = 48` MUST be stored via IAppConfig
- AND the `taskDueSoon` rule window MUST be updated accordingly (per the `task-notifications` capability)

#### Scenario: Invalid lead time rejected in the UI
- GIVEN the admin enters `0` or a non-numeric value
- WHEN the admin attempts to save
- THEN the field MUST show a validation error
- AND no value MUST be persisted

### Requirement: Label management section [MVP]
The Planix admin settings page MUST contain a "Label Management" CnSettingsSection listing every app-wide label with its color chip, title, optional description, and a usage count (number of tasks whose `labels` array contains the label's UUID). The section MUST offer create, edit, and delete actions. CnVersionInfoCard remains the first section on the page.

#### Scenario: View labels with usage counts
- GIVEN a Nextcloud admin opens Administration → Planix
- AND the seed label `Bug` is referenced by 2 tasks
- THEN the Label Management section MUST list `Bug` with its color chip and "used by 2 tasks"
- AND labels MUST be sorted by title

#### Scenario: Non-admin cannot manage labels
@e2e exclude API permission contract, covered by Newman 403 assertion
- GIVEN a regular (non-admin) Nextcloud user
- WHEN they call the label usage or cascade-delete endpoints directly
- THEN Nextcloud MUST return a 403 Forbidden response

### Requirement: Create and edit labels [MVP]
An admin MUST be able to create a label (required title, 6-digit hex color via a color picker defaulting to `#4376FC`, optional description) and edit an existing label's title, color, and description. Create and edit operate on the OpenRegister `label` schema directly (ADR-022 — no planix pass-through controller); the schema's `^#[0-9A-Fa-f]{6}$` color pattern remains the authoritative validation. Because tasks reference labels by UUID, an edit MUST propagate to every task card chip and board filter without modifying any task.

#### Scenario: Create a label
- GIVEN the admin opens the label dialog from the Label Management section
- WHEN the admin enters title "Tech debt", picks a color, and saves
- THEN the label MUST be created in the OpenRegister `label` schema
- AND it MUST appear in the list with usage count 0
- AND it MUST be selectable on tasks and in the board label filter

#### Scenario: Invalid color is rejected
- GIVEN the label dialog is open
- WHEN the admin enters a color value that is not a 6-digit hex code
- THEN the dialog MUST show a validation error and MUST NOT save
- AND a direct API write with an invalid color MUST be rejected by schema validation (HTTP 400)

#### Scenario: Rename and recolor propagate by reference
- GIVEN the label `Bug` (red) is shown as a chip on a task card
- WHEN the admin renames it to `Defect` and changes the color to orange
- THEN no task object may be modified
- AND the task card chip and the board label filter MUST show `Defect` in orange on next render

### Requirement: Delete a label with cascade [MVP]
Deleting a label MUST require a confirmation dialog stating the usage count. On confirm, the system MUST remove the label's UUID from the `labels` array of every referencing task (server-side, before the label object is deleted) and then delete the label object — no task may retain a dangling label reference. The cascade MUST be idempotent: re-running it after a partial failure completes the sweep. A register re-import MUST NOT resurrect a deleted label or reset an edited one.

#### Scenario: Delete a used label
- GIVEN the label `Bug` is referenced by 12 tasks
- WHEN the admin clicks delete and the dialog warns "It will be removed from 12 tasks" and the admin confirms
- THEN every referencing task's `labels` array MUST no longer contain the label's UUID
- AND the label object MUST be deleted
- AND the chip MUST disappear from board cards and the label filter

#### Scenario: Cascade is idempotent after partial failure
@e2e exclude failure-recovery path, covered by PHPUnit on the cascade service
- GIVEN a cascade delete failed after sweeping only part of the referencing tasks
- WHEN the admin retries the delete
- THEN the remaining tasks MUST be swept
- AND already-swept tasks MUST NOT be modified again

#### Scenario: Re-import does not resurrect deleted seed labels
@e2e exclude backend install path, covered by Newman against the OR API after re-import
- GIVEN the admin deleted the seed label `Feature`
- WHEN the register import runs again (repair step / "Initialize register")
- THEN the `Feature` label MUST NOT be recreated

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
