# Design: Admin & User Settings

## Summary

Implement Planix settings infrastructure: a Nextcloud admin settings page (Administration → Planix) and an in-app user settings dialog. Admin settings persist via `IAppConfig`; user settings persist via `OCP\IConfig`. Both surfaces integrate into Nextcloud's existing settings framework without custom UI infrastructure.

## Motivation

Planix ships without any settings surfaces. Admins cannot configure default column templates or initialize the OpenRegister register post-install; users cannot opt out of notifications or choose a default view. Both gaps block MVP readiness.

## Approach

### Backend

#### SettingsController

`lib/Controller/SettingsController.php` — thin controller (< 10 lines per method), strict 3-layer pattern.

| Method | Annotation | Route | Description |
|--------|-----------|-------|-------------|
| `getAdminSettings()` | `#[AuthorizedAdminSetting]` | `GET /api/settings/admin` | Returns all `IAppConfig` values for Planix |
| `saveAdminSettings()` | `#[AuthorizedAdminSetting]` | `POST /api/settings/admin` | Persists validated admin config via `IAppConfig` |
| `getUserSettings()` | `#[NoAdminRequired]` | `GET /api/settings/user` | Returns all `IConfig` values for the current user |
| `saveUserSettings()` | `#[NoAdminRequired]` | `POST /api/settings/user` | Persists user config via `IConfig` for the session user |

Admin endpoints use `#[AuthorizedAdminSetting(Application::APP_ID)]` — framework-enforced, no `isAdmin()` call needed in the body. User endpoints derive the UID from `IUserSession`; they operate on the caller's own settings (no per-object IDOR risk).

**Response shape — admin settings:**
```json
{
  "default_columns": ["To Do", "In Progress", "Review", "Done"],
  "allow_project_creation": "all",
  "register_initialized": true
}
```

**Response shape — user settings:**
```json
{
  "notify_assigned": true,
  "notify_due_reminder": true,
  "default_view": "my-work"
}
```

All boolean values are stored as the string `"true"` / `"false"` by `IAppConfig`/`IConfig` and cast to booleans in the JSON response. The `default_columns` value is stored as a JSON string and decoded before returning.

#### AdminSettings Registration

`lib/Settings/AdminSettings.php` implements `OCP\Settings\ISettings`:

- `getSection()` → `'additional'`
- `getPriority()` → `50`
- `getForm()` → returns `TemplateResponse('planix', 'admin-settings')` rendering the Vue mount point `<div id="planix-admin-settings"></div>`

Registered in `lib/AppInfo/Application.php` via `$context->registerCapability(AdminSettings::class)` (or equivalent `ISettings` registration).

#### NotificationService — SUBJECT_SETTING_MAP

`lib/Service/NotificationService.php` gains a `SUBJECT_SETTING_MAP` constant:

```php
private const SUBJECT_SETTING_MAP = [
    'task_assigned'    => 'notify_assigned',
    'task_due_reminder' => 'notify_due_reminder',
];
```

Before dispatching any notification, the service reads `IConfig->getUserValue($userId, 'planix', $settingKey, 'true')`. If the value is `'false'`, dispatch is skipped. This pattern is checked per-recipient when sending bulk notifications.

### Frontend

#### Build Entry Points

`webpack.config.js` gains a second entry point `settings`:

```js
entry: {
  main: path.join(__dirname, 'src', 'main.js'),
  settings: path.join(__dirname, 'src', 'settings.js'),
}
```

The shared vendor and nc-vue chunks (defined in the webpack `splitChunks` config) apply to both entry points. The PHP `AdminSettings::getForm()` loads the chunks in order: `planix-shared-vendor` → `planix-shared-nc-vue` → `planix-settings`.

#### AdminRoot.vue

`src/views/AdminRoot.vue` — mounted by `settings.js` into `#planix-admin-settings`.

Layout (top to bottom):
1. **`CnVersionInfoCard`** (FIRST, required by Conduction convention) — displays app name, installed version (from `appVersion` define), and "Update available" indicator when a newer version is in the App Store.
2. **`CnSettingsSection` "Default Project Configuration"** — shows `default_columns` as a draggable ordered list of text inputs with add/remove controls. Save triggers `POST /api/settings/admin`.
3. **`CnSettingsSection` "Register Setup"** — shows initialization status (loaded via `GET /api/settings/admin` → `register_initialized`). If `false`, shows an "Initialize register" button that calls `POST /api/settings/admin` with action `initialize_register`, which triggers `ConfigurationService::importFromApp()` on the backend.

All strings are translated via `this.t('planix', '...')`. No hardcoded Dutch or English labels.

`AdminRoot.vue` is **not** added to the vue-router (ADR-004; `hydra-gate-admin-router` enforces this).

#### UserSettings.vue

`src/dialogs/UserSettings.vue` — wraps `NcAppSettingsDialog` (from `@conduction/nextcloud-vue`).

Structure:
- **Section: Notification preferences**
  - `NcCheckboxRadioSwitch` toggle for `notify_assigned` ("Notify me when a task is assigned to me", default `true`)
  - `NcCheckboxRadioSwitch` toggle for `notify_due_reminder` ("Notify me 1 day before a task is due", default `true`)
- **Section: Display preferences**
  - `NcSelect` with `inputLabel` prop (never a manual `<label>`) for `default_view` with options: My Work, Kanban, Backlog

Each toggle / select change triggers an immediate `POST /api/settings/user` (debounced 300 ms) in a `try/catch` block with `showError()` feedback on failure. Settings are read on dialog open via `GET /api/settings/user`.

`UserSettings.vue` lives in `src/dialogs/` (not `src/modals/`) because it wraps `NcAppSettingsDialog` (not `NcModal`).

#### App.vue Integration

`App.vue` owns the `open-settings` boolean state:

```js
data() {
  return { settingsOpen: false }
},
```

Template binding:
```html
<MainMenu @open-settings="settingsOpen = true" />
<UserSettings :open="settingsOpen" @update:open="settingsOpen = $event" />
```

`MainMenu.vue` emits `open-settings` from the `NcAppNavigationSettings` gear click handler. There is NO `/settings` route in the router.

#### Settings Store

`src/store/modules/settings.js` — Pinia `defineStore`:

```js
state: () => ({ adminSettings: {}, userSettings: {}, loading: false }),
actions: {
  async fetchSettings() { ... },  // GET /api/settings/user (+ admin if isAdmin)
  async saveUserSettings(patch) { ... },  // POST /api/settings/user
  async saveAdminSettings(patch) { ... }, // POST /api/settings/admin
}
```

All `await axios.*` calls are wrapped in `try/finally` for loading state and `try/catch` for error feedback. `isAdmin` is loaded from backend (never `OC.isAdmin`).

Initial state is provided via `IInitialState::provideInitialState('settings', $settings)` on the PHP side and read in Vue via `loadState('planix', 'settings', {})` — not from DOM attributes.

## Reuse Analysis

Per ADR-001, the following existing services and components are leveraged:

| Platform capability | Used as |
|---|---|
| `IAppConfig` (Nextcloud OCP) | Admin settings persistence — no custom DB table |
| `OCP\IConfig` (Nextcloud OCP) | User settings persistence — no custom DB table |
| `IUserSession` (Nextcloud OCP) | Derive UID for user settings — never trust frontend-sent ID |
| `ConfigurationService::importFromApp()` (OpenRegister) | OpenRegister register initialization from admin page |
| `CnVersionInfoCard` (@conduction/nextcloud-vue) | App version display + update indicator |
| `CnSettingsSection` (@conduction/nextcloud-vue) | Logical settings group with action slot + states |
| `NcAppSettingsDialog` (via @conduction/nextcloud-vue) | In-app user settings modal — NOT NcDialog |
| `IInitialState` / `loadState` (Nextcloud OCP / @nextcloud/initial-state) | Server → Vue state bootstrap |

No new OpenRegister schemas are introduced. No custom database tables. No custom import/export handlers.

## Seed Data

No seed data required. This change introduces no OpenRegister schemas (per ADR-001: "Changes that only modify frontend components or non-schema backend logic (e.g., settings, permissions) do not require seed data").

## Accessibility & Theming

- All CSS uses Nextcloud CSS variables (`var(--color-primary-element)`, etc.). No hardcoded colors.
- `NcSelect` for `default_view` uses the built-in `inputLabel` prop — no manual `<label>` elements.
- All toggle labels and section headings are translated strings.
- Admin settings page meets WCAG AA: keyboard-navigable, associated labels, color not sole indicator.
- NL Design System double-fallback CSS pattern applies to any custom spacing/sizing.

## Security

- Admin endpoints: `#[AuthorizedAdminSetting(Application::APP_ID)]` — Nextcloud middleware rejects non-admins with 403 before the controller method is invoked.
- User endpoints: `#[NoAdminRequired]` — no per-object IDOR risk because each user can only read/write their own `IConfig` values (the UID is derived from `IUserSession`, never from the request body).
- No stack traces, SQL, or internal paths in error responses. Static messages only. Real errors logged server-side.
- Input validation: `default_columns` is validated as a JSON array of strings before persisting. `default_view` is validated against the allowlist `['my-work', 'kanban', 'backlog']`.
