# User Settings Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [admin-user-settings](../../) — implements user settings dialog with notification toggles and display preferences

## Purpose

Defines the in-app user settings dialog accessible from the Planix navigation bar. The dialog uses `NcAppSettingsDialog` (not `NcDialog`) and exposes personal preferences (notification opt-in/out, default view) stored via `OCP\IConfig`. Settings are per-user and persist across browser sessions.

## ADDED Requirements

### REQ-USR-001: User Settings Dialog via NcAppSettingsDialog [MVP]

The system MUST provide a user settings dialog opened as a modal overlay from the Planix navigation bar. The dialog MUST use `NcAppSettingsDialog` — never `NcDialog` or a routed page.

#### Scenario USR-001-A: Open settings dialog from navigation gear
- GIVEN a user is using Planix
- WHEN the user clicks the settings gear icon in the `NcAppNavigationSettings` footer
- THEN the system MUST open an `NcAppSettingsDialog` overlay
- AND the dialog MUST be visible over the current Planix view without navigation change

#### Scenario USR-001-B: Dialog does not use a route
- GIVEN the user opens the settings dialog
- WHEN the dialog is open
- THEN the browser URL MUST NOT change to a `/settings` path
- AND pressing browser Back MUST NOT close the dialog — only the dialog's close control does

#### Scenario USR-001-C: Dialog contains required sections
- GIVEN the user settings dialog is open
- THEN the dialog MUST contain at minimum:
  - A "Notification preferences" section with toggles for `notify_assigned` and `notify_due_reminder`
  - A "Display preferences" section with the `default_view` selector

### REQ-USR-002: Notification Preferences [MVP]

The user settings dialog MUST provide toggle controls for notification preferences stored via `OCP\IConfig`.

#### Scenario USR-002-A: Toggle task assignment notification off
- GIVEN the user settings dialog is open
- AND `notify_assigned` is currently `true`
- WHEN the user toggles "Notify me when a task is assigned to me" to off
- THEN the system MUST immediately save `notify_assigned = false` via `OCP\IConfig`
- AND subsequent task assignment notifications MUST NOT be sent to this user

#### Scenario USR-002-B: Toggle task assignment notification back on
- GIVEN `notify_assigned` is currently `false` for the user
- WHEN the user toggles "Notify me when a task is assigned to me" to on
- THEN the system MUST save `notify_assigned = true` via `OCP\IConfig`
- AND subsequent task assignment notifications MUST resume for this user

#### Scenario USR-002-C: Toggle due date reminder notification off
- GIVEN the user settings dialog is open
- AND `notify_due_reminder` is currently `true`
- WHEN the user toggles "Notify me 1 day before a task is due" to off
- THEN the system MUST save `notify_due_reminder = false` via `OCP\IConfig`
- AND the due date reminder notification MUST NOT be sent to this user

#### Scenario USR-002-D: Default notification values
- GIVEN a new user has never opened the user settings dialog
- WHEN the user opens the dialog for the first time
- THEN both `notify_assigned` and `notify_due_reminder` toggles MUST be shown as ON (default `true`)

### REQ-USR-003: Display Preferences [MVP]

The user settings dialog MUST provide a `default_view` selector controlling which view opens when a user enters a project.

#### Scenario USR-003-A: Change default view to Kanban
- GIVEN the user settings dialog is open
- WHEN the user selects "Kanban" as their default view
- THEN the system MUST save `default_view = kanban` via `OCP\IConfig`
- AND the next time the user opens a project, the kanban board view MUST be shown by default

#### Scenario USR-003-B: Change default view to Backlog
- GIVEN the user settings dialog is open
- WHEN the user selects "Backlog" as their default view
- THEN the system MUST save `default_view = backlog` via `OCP\IConfig`
- AND the next time the user opens a project, the backlog list view MUST be shown by default

#### Scenario USR-003-C: Default view defaults to My Work
- GIVEN a new user has never changed the `default_view` setting
- WHEN the user opens a project
- THEN the system MUST open the "My Work" view (value `my-work`)

#### Scenario USR-003-D: NcSelect uses inputLabel prop
- GIVEN the default view selector is rendered
- THEN the `NcSelect` component MUST use the built-in `inputLabel` prop for its label
- AND there MUST be no manual `<label>` element wrapping or pointing to the select

### REQ-USR-004: Settings Persistence [MVP]

All user settings MUST be persisted via `OCP\IConfig` and MUST survive browser restarts and new sessions.

#### Scenario USR-004-A: Settings persist across sessions
- GIVEN a user has set `notify_assigned = false` and `default_view = kanban`
- WHEN the user closes Planix and returns in a new browser session
- THEN the notification toggle MUST still be shown as OFF
- AND the default view setting MUST still be "Kanban"
- AND opening a project MUST open the kanban board by default

#### Scenario USR-004-B: Save failure is surfaced
- GIVEN the user changes a setting
- WHEN the `POST /api/settings/user` request fails
- THEN the system MUST display a user-facing error notification via `showError()`
- AND the toggle or selector MUST revert to the previously saved value

### REQ-USR-005: NotificationService Respects User Preferences [MVP]

`NotificationService` MUST check the recipient's preference before dispatching any notification subject that has a corresponding user setting.

#### Scenario USR-005-A: Suppressed notification is not sent
- GIVEN user A has `notify_assigned = false`
- WHEN a task is assigned to user A
- THEN `NotificationService` MUST NOT create a Nextcloud notification for user A

#### Scenario USR-005-B: Enabled notification is sent
- GIVEN user A has `notify_assigned = true` (or has never changed the setting)
- WHEN a task is assigned to user A
- THEN `NotificationService` MUST create a Nextcloud notification for user A

#### Scenario USR-005-C: SUBJECT_SETTING_MAP is the single source of truth
- GIVEN `NotificationService` has a `SUBJECT_SETTING_MAP` constant mapping subject keys to user setting keys
- WHEN a new notification subject is added in the future
- THEN it MUST be added to `SUBJECT_SETTING_MAP` — not checked inline in individual dispatch methods

### REQ-USR-006: User Settings API [MVP]

The system MUST expose `GET /api/settings/user` and `POST /api/settings/user` endpoints.

#### Scenario USR-006-A: GET returns current user settings
- GIVEN an authenticated user sends `GET /api/settings/user`
- THEN the system MUST return HTTP 200 with a JSON object containing:
  - `notify_assigned` as a boolean
  - `notify_due_reminder` as a boolean
  - `default_view` as a string

#### Scenario USR-006-B: POST persists user settings
- GIVEN an authenticated user sends `POST /api/settings/user` with a valid payload
- THEN the system MUST persist the values via `OCP\IConfig` for the session user
- AND return HTTP 200 with the updated settings object

#### Scenario USR-006-C: User identity is derived from session
- GIVEN the user settings endpoints process a request
- THEN the UID used for `IConfig` read/write MUST be derived from `IUserSession`
- AND MUST NOT be read from the request body or query string

#### Scenario USR-006-D: Unauthenticated request returns 401
- GIVEN an unauthenticated request is sent to `GET /api/settings/user` or `POST /api/settings/user`
- THEN the system MUST return HTTP 401 Unauthorized

## Non-Functional Requirements

- **Security:** User endpoints use `#[NoAdminRequired]`. The UID is always derived from `IUserSession` — never from a request parameter. No per-object IDOR risk (users can only read/write their own settings).
- **Accessibility:** `NcAppSettingsDialog` provides built-in keyboard navigation and focus management. Toggle controls MUST have associated labels. `NcSelect` MUST use `inputLabel` prop (WCAG 1.3.1).
- **Internationalization:** All user-visible strings use `this.t('planix', '...')`. Dutch translations go in `l10n/nl.json`.
- **Persistence:** `IConfig` values survive server restarts. Frontend reads on dialog open — no stale state from earlier sessions.
- **Spec traceability:** `SettingsController` user methods MUST carry `@spec openspec/changes/admin-user-settings/tasks.md` PHPDoc tags.

## Acceptance Criteria

- [ ] Settings gear in navigation opens `NcAppSettingsDialog` (not `NcDialog`, not a page route)
- [ ] Dialog contains "Notification preferences" section with `notify_assigned` and `notify_due_reminder` toggles
- [ ] Dialog contains "Display preferences" section with `default_view` selector using `inputLabel` prop
- [ ] Both notification toggles default to ON for new users
- [ ] `default_view` defaults to `my-work` for new users
- [ ] Toggle changes saved immediately via `POST /api/settings/user`; errors show `showError()` feedback
- [ ] Settings persist across browser sessions (read fresh from backend on dialog open)
- [ ] `NotificationService` checks `SUBJECT_SETTING_MAP` before dispatching `task_assigned` and `task_due_reminder` notifications
- [ ] `GET /api/settings/user` returns correct data for the session user
- [ ] `POST /api/settings/user` with a tampered UID in the body still saves to the session user's config

## Notes

- `NcAppSettingsDialog` is the required component — NOT `NcDialog` (ADR-004; `CnSettingsSection` wraps sections within it).
- `UserSettings.vue` lives in `src/dialogs/` (not `src/modals/`) per the ADR-004 modal isolation rule.
- V1 notification settings (`notify_overdue`, `notify_commented`, `notify_status_changed`) and `items_per_page` are stored in `IConfig` but not exposed in the UI until a follow-up V1 change.
- Related spec: `openspec/specs/admin-user-settings.md`.
