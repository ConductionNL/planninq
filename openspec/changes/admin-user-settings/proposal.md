# Change Proposal: admin-user-settings

**Change ID:** admin-user-settings
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

Planix already has stub entry points for both admin and user settings (`lib/Settings/AdminSettings.php` exists; `src/views/settings/UserSettings.vue` is an empty placeholder), but neither is functional. Administrators have no way to configure app-level defaults (such as the default column set for new projects), verify the OpenRegister initialization status, or see the current app version from the Nextcloud administration interface. End users have no way to personalise their notification preferences or choose a default project view — meaning all notification emails are sent unconditionally and every project always opens in the system-default view regardless of user preference.

The `tasks` change introduces `NotificationService` with a `SUBJECT_SETTING_MAP` pattern that is designed to check user settings before sending a notification. Without this change, those setting keys always resolve to the default (`true`) because no user can toggle them off. The `admin-user-settings` change closes both gaps: it makes the admin settings page genuinely useful and gives users a settings dialog that actually persists and influences app behaviour.

---

## What Changes

Implement the admin settings page and user settings dialog for Planix:

1. **Admin settings page** — enhance the existing `AdminSettings.php` template to render `CnVersionInfoCard` (app name, current version, update-available indicator), a "Default Project Configuration" section with an editable ordered column list (`default_columns`), and a "Register Setup" section showing OpenRegister initialization status with an "Initialize register" button. Settings stored via `IAppConfig`.
2. **User settings dialog** — implement the currently empty `UserSettings.vue` as an `NcAppSettingsDialog` (not `NcDialog`) opened from the navigation gear icon. Dialog contains a Notification section (toggles for `notify_assigned`, `notify_due_reminder`) and a Display section (`default_view` selector). Settings stored via `OCP\IConfig`.
3. **SettingsController enhancements** — add user settings read/write endpoints (`GET /settings/user`, `PUT /settings/user`) alongside the existing admin endpoints.
4. **NotificationService integration** — align `SUBJECT_SETTING_MAP` keys in `NotificationService` with the user settings keys defined here (`notify_assigned`, `notify_due_reminder`, etc.) so toggling a preference immediately controls notification delivery.
5. **Column list editor** — new `ColumnListEditor.vue` component for the admin settings page; supports add, remove, and drag-to-reorder operations on a list of column name strings.
6. **Register initialization flow** — "Register Setup" section calls `ConfigurationService::importFromApp()` via a new admin-only endpoint; shows spinner during import and success/error feedback.
7. **i18n** — all new strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`admin-user-settings`** — implementing the full admin and user settings layer defined in `openspec/specs/admin-user-settings.md`. This change brings the capability from stub/placeholder state to fully functional: admin settings page with version card, column configuration, and register initialization; user settings dialog with notification toggles and view preference; backend endpoints; NotificationService integration.

No new capabilities are introduced. The `admin-user-settings` capability was declared in the spec; this change completes the implementation.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `lib/Settings/AdminSettings.php` | Modified — add template data: version, update status, default_columns, register init status |
| `templates/admin-settings.php` | Modified — render `CnVersionInfoCard`, column list editor, register setup section |
| `lib/Controller/SettingsController.php` | Modified — add `userIndex`, `userUpdate` actions; add `adminRegisterInit` action |
| `appinfo/routes.php` | Modified — add user settings routes; add register init route |
| `src/views/settings/UserSettings.vue` | Modified — implement full `NcAppSettingsDialog` with notification and display sections |
| `src/views/settings/AdminSettings.vue` | New — Vue component rendering `CnVersionInfoCard` + `CnSettingsSection` groups |
| `src/components/settings/ColumnListEditor.vue` | New — drag-to-reorder ordered column name list editor |
| `src/store/settings.js` | New — Pinia store for user and admin settings (read/write via SettingsController) |
| `src/navigation/MainMenu.vue` | Modified — wire gear icon to open `UserSettings.vue` dialog |
| `lib/Service/NotificationService.php` | Modified — align `SUBJECT_SETTING_MAP` keys with user settings keys |
| `l10n/en.json` | Modified — add all settings-related translation strings |
| `l10n/nl.json` | Modified — add Dutch translations for all settings strings |

### Risk

Low-to-medium. Admin settings template modifications are additive. User settings dialog is a net-new implementation of an empty placeholder. The `SettingsController` enhancement adds actions without removing existing ones. The highest-risk step is aligning `NotificationService::SUBJECT_SETTING_MAP` keys with the user settings keys — if the key names diverge from what `tasks` change established, notifications will silently fail or fire unconditionally.

The `ColumnListEditor` component uses drag-and-drop (Vue Draggable or similar). If `@conduction/nextcloud-vue` does not export a list-reorder primitive, a local component using the HTML5 Drag-and-Drop API must be used instead.

### Dependencies

- `register-schemas` must be applied first (OpenRegister register and schemas must exist for the init status check).
- `tasks` change should be applied first or in parallel (to align `NotificationService::SUBJECT_SETTING_MAP` keys). If applied before `tasks`, the map is declared here and the `tasks` change imports it.
- `@conduction/nextcloud-vue` must export `CnVersionInfoCard`, `CnSettingsSection`, `NcAppSettingsDialog` (or this change wraps `NcAppSettingsDialog` directly from `@nextcloud/vue`).
- OpenRegister `ConfigurationService` must expose `importFromApp()` (confirmed in `register-schemas` spec).
