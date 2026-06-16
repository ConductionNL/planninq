# Admin & User Settings

Configure Planix at the app level (admin) or customize personal preferences (user).

## Admin Settings

Accessible to Nextcloud administrators at **Administration → Planix**.

### Sections

**App version info** — `CnVersionInfoCard` shows the installed version, connection status to OpenRegister, and an "Update available" indicator with a link to the Nextcloud App Store when a newer version exists.

**Default Project Configuration** — configure the column set that is applied when a new project is created. Columns can be added, renamed, reordered, and deleted. New projects created after saving the change will use the updated column set.

**Label Management** — create, edit, and delete app-wide labels that are available across all projects. Each label has a title and a hex color.

**OpenRegister Setup** — shows whether the Planix register and schemas are initialized in OpenRegister. An "Initialize register" button triggers the import if the register is not yet set up.

### Access Control

Only Nextcloud administrators can access the admin settings page. Non-admin users receive a 403 response if they navigate to the settings URL directly, and the section does not appear in their Settings navigation.

## User Settings

Accessible from the gear icon in the Planix navigation bar, the user settings dialog (`NcAppSettingsDialog`) lets each user configure their own preferences.

### Notification Preferences

| Setting | Default | Description |
|---------|---------|-------------|
| Notify when a task is assigned to me | On | Nextcloud notification on task assignment |
| Remind me 1 day before a task's due date | On | Due-date reminder notification |

### Display Preferences

| Setting | Default | Description |
|---------|---------|-------------|
| Default view when opening a project | My Work | Chooses whether to open a project in My Work, Kanban, or Backlog view |

All settings persist across browser sessions.

## Standards

- Nextcloud OCP\IAppConfig — admin settings storage
- Nextcloud OCP\IConfig — user settings storage
- NcAppSettingsDialog — user settings dialog component (NOT NcDialog)
- CnVersionInfoCard, CnSettingsSection — admin settings layout components

## Spec

- [admin-user-settings spec](https://codeberg.org/Conduction/planix/src/branch/development/openspec/specs/admin-user-settings.md)
