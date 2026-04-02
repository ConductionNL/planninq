# Design: admin-user-settings

**Change ID:** admin-user-settings
**Status:** draft
**Created:** 2026-04-02

---

## Context

Planix is a thin-client Nextcloud app backed by OpenRegister. It has no custom database tables. Admin settings (`IAppConfig`) and user settings (`IConfig`) are stored in Nextcloud's native configuration storage, which both PHP and frontend can read/write through the `SettingsController`.

The existing codebase has two stub entry points:
- `lib/Settings/AdminSettings.php` — registered with Nextcloud's settings infrastructure; renders a template that is currently empty.
- `src/views/settings/UserSettings.vue` — imported in the Vue app but renders nothing.

This change implements both stubs fully, adds a `SettingsController` that exposes read/write endpoints for both admin and user settings, and integrates the user settings keys into `NotificationService`.

---

## Goals

- Admin settings page: `CnVersionInfoCard`, editable default columns list, OpenRegister register initialization button.
- User settings dialog: `NcAppSettingsDialog`, notification toggles, default view selector.
- Pinia settings store: single source of truth for both admin and user settings in the frontend.
- `ColumnListEditor` component: ordered, drag-to-reorder list for column name strings.
- Backend endpoints: `GET /settings/admin`, `GET /settings/user`, `PUT /settings/user`, `POST /settings/admin/register-init`.
- NotificationService: align `SUBJECT_SETTING_MAP` keys with user settings keys.
- Full i18n coverage (en + nl).

## Non-Goals

- Procest bridge settings (`procest_bridge_enabled`, `procest_base_url`) — V1 feature; section placeholder may be rendered but fields are non-functional.
- `allow_project_creation` access control setting — V1.
- `notify_overdue`, `notify_commented`, `notify_status_changed`, `items_per_page` user settings — V1; keys may be declared in `SUBJECT_SETTING_MAP` but toggles are not shown in MVP dialog.
- OAuth/SAML authentication settings — outside scope.
- Custom PHP admin settings UI using Nextcloud's legacy `IAdmin` interface — using Vue SPA rendered in the template instead.

---

## Decisions

### Decision 1: Admin settings use a Vue component rendered inside the Nextcloud admin template

**Options considered:**
1. Pure PHP template with HTML form elements (legacy Nextcloud pattern).
2. Vue SPA rendered inside the PHP template via a mount point `<div id="planix-admin-settings"></div>` (chosen).

**Rationale:** `CnVersionInfoCard` and `CnSettingsSection` are Vue components from `@conduction/nextcloud-vue`. Rendering them requires a Vue mount point. The PHP `AdminSettings.php` provides the initial data (version, update status, current settings values) as JSON on the page's `data-*` attributes so the Vue component can hydrate without an extra network request.

The PHP template registers a dedicated admin settings entry script (`admin-settings.js`) that mounts `AdminSettings.vue` onto the `#planix-admin-settings` div.

### Decision 2: User settings use NcAppSettingsDialog, not NcDialog

**Options considered:**
1. `NcDialog` — generic modal.
2. `NcAppSettingsDialog` — Nextcloud's purpose-built settings dialog with sectioned navigation (chosen).

**Rationale:** `NcAppSettingsDialog` provides a two-pane layout with a sidebar section list and content area. This matches the established Nextcloud UX pattern for per-app user settings (Deck, Talk, Calendar all use this). It also renders consistently across desktop and mobile viewports. Using `NcDialog` would require reimplementing the section navigation.

The dialog is opened from a gear icon `NcAppNavigationItem` at the bottom of the Planix navigation sidebar (existing slot in `MainMenu.vue`).

### Decision 3: Settings store handles both admin and user settings in one Pinia store

**Options considered:**
1. Two separate stores: `useAdminSettingsStore` and `useUserSettingsStore`.
2. One store `useSettingsStore` with namespaced getters/actions (chosen).

**Rationale:** Both settings types share the same controller (`SettingsController`) and the same fetch-on-mount / save-on-change pattern. A single store reduces boilerplate and makes it easy to read both in a single mount hook. State is namespaced internally (`adminSettings`, `userSettings`).

### Decision 4: Column list editor uses SortableJS via vue-draggable-plus (or HTML5 DnD fallback)

**Options considered:**
1. `vue-draggable-plus` (SortableJS wrapper, used in other Conduction apps) — chosen if already in `package.json`.
2. Plain HTML5 `draggable` attribute with `ondragstart`/`ondrop` (fallback if vue-draggable-plus is not available).

**Rationale:** Drag-to-reorder is a critical UX requirement for the column list. `vue-draggable-plus` provides a declarative Vue 3 wrapper around SortableJS with built-in accessibility. If not available, the HTML5 DnD fallback is acceptable for MVP given the admin-only audience. The component exposes a `modelValue` prop (array of strings) and emits `update:modelValue` on change, making it a standard v-model component.

The component also supports keyboard-accessible reordering (move up/down buttons) alongside drag-and-drop, satisfying WCAG AA requirement for keyboard accessibility.

### Decision 5: Register initialization is an admin-only synchronous endpoint

**Options considered:**
1. Asynchronous: endpoint triggers a background job, admin polls for status.
2. Synchronous: endpoint calls `ConfigurationService::importFromApp()` directly and returns success/error (chosen for MVP).

**Rationale:** OpenRegister register initialization is a one-time operation that completes in under 2 seconds in typical environments. A synchronous endpoint keeps the implementation simple. The frontend shows a spinner while the request is in flight. If the operation takes longer in edge cases (large schema files, slow disk), the standard Nextcloud request timeout (30 s) is sufficient.

The endpoint is protected by `OCP\AppFramework\Middleware\Security\SecurityMiddleware` admin check via the `@AdminRequired` annotation on the controller action.

### Decision 6: User settings are read once on dialog open, not reactive

**Options considered:**
1. Reactive: settings subscribe to a server-sent event or poll every N seconds.
2. Read-once: settings are fetched when the dialog opens; each toggle saves immediately via PUT (chosen).

**Rationale:** User settings do not change from another session during the dialog's open state. Read-once on open + save-on-change is simpler, faster, and consistent with how all other Nextcloud settings dialogs work (Deck, Calendar, Talk). Each toggle calls `PUT /settings/user` with the changed key/value pair; the store updates optimistically.

### Decision 7: NotificationService SUBJECT_SETTING_MAP keys use `notify_` prefix (matching user settings keys directly)

**Options considered:**
1. Map notification subjects to arbitrarily named setting keys.
2. Map notification subjects to user setting keys with `notify_` prefix, matching the `IConfig` key names exactly (chosen).

**Rationale:** This makes the relationship explicit and reduces the chance of mismatch. The `SUBJECT_SETTING_MAP` in `NotificationService` maps:

| Notification subject | IConfig user key | Default |
|---------------------|-----------------|---------|
| `task_assigned` | `notify_assigned` | `true` |
| `task_due_soon` | `notify_due_reminder` | `true` |
| `task_overdue` (V1) | `notify_overdue` | `true` |
| `task_commented` (V1) | `notify_commented` | `true` |
| `task_status_changed` (V1) | `notify_status_changed` | `false` |

Note: The `tasks` change used `notify_task_assigned` and `notify_task_due_soon` as key names in its design. This change adopts the shorter form (`notify_assigned`, `notify_due_reminder`) as defined in the base spec (`openspec/specs/admin-user-settings.md`). The `tasks` change's `NotificationService` must be updated to use these canonical key names.

---

## Component Architecture

```
src/
  views/settings/
    AdminSettings.vue           # Vue component mounted in admin template
    UserSettings.vue            # NcAppSettingsDialog (replaces empty placeholder)
  components/settings/
    ColumnListEditor.vue        # Drag-to-reorder column name list editor
  store/
    settings.js                 # Pinia store — useSettingsStore

lib/
  Settings/
    AdminSettings.php           # Modified — add page data (version, update, settings)
  Controller/
    SettingsController.php      # Modified — add userIndex, userUpdate, adminRegisterInit
  Service/
    NotificationService.php     # Modified — align SUBJECT_SETTING_MAP keys

templates/
  admin-settings.php            # Modified — add mount point, load admin-settings.js

appinfo/
  routes.php                    # Modified — add user settings + register init routes
  assets.php (or webpack.config)# Modified — add admin-settings.js entry point

src/navigation/
  MainMenu.vue                  # Modified — gear icon opens UserSettings.vue
```

---

## Backend: SettingsController Endpoints

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| `GET` | `/planix/settings/admin` | Admin | Read all admin settings |
| `PUT` | `/planix/settings/admin` | Admin | Update admin settings (full or partial) |
| `POST` | `/planix/settings/admin/register-init` | Admin | Trigger `ConfigurationService::importFromApp()` |
| `GET` | `/planix/settings/user` | Any authenticated | Read current user's settings |
| `PUT` | `/planix/settings/user` | Any authenticated | Update one or more of current user's settings |

Admin settings read response schema:
```json
{
  "default_columns": ["To Do", "In Progress", "Review", "Done"],
  "allow_project_creation": "all",
  "procest_bridge_enabled": false,
  "procest_base_url": "",
  "register_initialized": true,
  "app_version": "0.1.0",
  "update_available": false,
  "update_version": null
}
```

User settings read response schema:
```json
{
  "notify_assigned": true,
  "notify_due_reminder": true,
  "notify_overdue": true,
  "notify_commented": true,
  "notify_status_changed": false,
  "default_view": "my-work",
  "items_per_page": 25
}
```

---

## Pinia Store: `useSettingsStore`

```js
// src/store/settings.js
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSettingsStore = defineStore('settings', () => {
  // State
  const adminSettings = ref({})
  const userSettings = ref({})
  const adminLoading = ref(false)
  const userLoading = ref(false)
  const error = ref(null)

  // Actions
  async function fetchAdminSettings() { /* GET /planix/settings/admin */ }
  async function updateAdminSettings(data) { /* PUT /planix/settings/admin */ }
  async function initRegister() { /* POST /planix/settings/admin/register-init */ }
  async function fetchUserSettings() { /* GET /planix/settings/user */ }
  async function updateUserSetting(key, value) {
    // Optimistic update: set locally first, then PUT; revert on failure
    const prev = userSettings.value[key]
    userSettings.value[key] = value
    try {
      await /* PUT /planix/settings/user with { [key]: value } */
    } catch (e) {
      userSettings.value[key] = prev
      throw e
    }
  }

  return {
    adminSettings, userSettings, adminLoading, userLoading, error,
    fetchAdminSettings, updateAdminSettings, initRegister,
    fetchUserSettings, updateUserSetting,
  }
})
```

---

## AdminSettings.vue Layout

```
┌─────────────────────────────────────────────────────┐
│  CnVersionInfoCard                                   │
│  App name: Planix | Version: 0.1.0 | [Update avail] │
├─────────────────────────────────────────────────────┤
│  CnSettingsSection: Default Project Configuration    │
│  ┌─────────────────────────────────────────────┐    │
│  │ ColumnListEditor                             │    │
│  │  [≡] To Do        [✕]                       │    │
│  │  [≡] In Progress  [✕]                       │    │
│  │  [≡] Review       [✕]                       │    │
│  │  [≡] Done         [✕]                       │    │
│  │  [+ Add column]                              │    │
│  └─────────────────────────────────────────────┘    │
│  [Save changes]                                      │
├─────────────────────────────────────────────────────┤
│  CnSettingsSection: Register Setup                   │
│  Status: ✓ Initialized / ✗ Not initialized          │
│  [Initialize register]   (spinner when in progress) │
└─────────────────────────────────────────────────────┘
```

---

## UserSettings.vue Layout (NcAppSettingsDialog)

```
┌──────────────────────────────────────────────────────┐
│  Planix Settings                              [Close] │
├──────────┬───────────────────────────────────────────┤
│ Sections │ Content area                               │
│          │                                            │
│ Notific. │  Notifications                             │
│ Display  │  ──────────────────────────────────────   │
│          │  [toggle] Notify when a task is            │
│          │           assigned to me                   │
│          │                                            │
│          │  [toggle] Notify 1 day before a            │
│          │           task's due date                  │
└──────────┴───────────────────────────────────────────┘
```

Second section (Display):
```
│  Display
│  ──────────────────────────────────────
│  Default view:  [my-work ▼]
│    Options: My Work | Kanban | Backlog
```

---

## ColumnListEditor Component Anatomy

```
props: {
  modelValue: { type: Array, required: true },  // string[]
  disabled: { type: Boolean, default: false },
}
emits: ['update:modelValue']
```

Each item row:
- Drag handle icon (`≡`) — cursor: grab
- Text input (column name, min 1 char)
- Remove button (`✕`) — disabled if only 1 item remains
- Up/Down keyboard buttons for accessibility

Add column: appends `''` (empty) to the list; focuses the new input.
Save is triggered by the parent (`AdminSettings.vue` "Save changes" button), not per-keystroke.

---

## PHP: AdminSettings.php Data Injection

```php
class AdminSettings implements ISettings {
    public function getForm(): TemplateResponse {
        $appVersion = \OCP\App::getAppVersion('planix');
        // Check for updates via IAppManager or app store API (cached)
        $updateInfo = $this->appManager->getAppInfo('planix');
        $registerInitialized = $this->configurationService->isInitialized();

        return new TemplateResponse('planix', 'admin-settings', [
            'appVersion'         => $appVersion,
            'updateAvailable'    => $updateInfo['update_available'] ?? false,
            'updateVersion'      => $updateInfo['update_version'] ?? null,
            'registerInitialized'=> $registerInitialized,
        ]);
    }
}
```

The Vue `AdminSettings.vue` component reads these values from `data-*` attributes on the mount div and supplements with a `GET /settings/admin` call for the editable settings values.

---

## i18n String Inventory

| Key context | Example string |
|-------------|----------------|
| Admin page title | `Planix Settings` |
| Version card | `Version {version}`, `Update available: {version}`, `Up to date` |
| Default columns section | `Default Project Configuration`, `Default columns for new projects` |
| Column editor | `Add column`, `Remove column`, `Move up`, `Move down`, `Column name` |
| Column editor validation | `Column name cannot be empty` |
| Save button | `Save changes`, `Saving…`, `Changes saved` |
| Register setup section | `Register Setup`, `Register initialized`, `Register not initialized`, `Initialize register`, `Initializing…`, `Register initialized successfully`, `Failed to initialize register` |
| User dialog title | `Planix Settings` |
| Notifications section | `Notifications` |
| Notify assigned toggle | `Notify me when a task is assigned to me` |
| Notify due reminder toggle | `Notify me 1 day before a task's due date` |
| Display section | `Display` |
| Default view label | `Default view` |
| Default view options | `My Work`, `Kanban`, `Backlog` |
| Save success | `Settings saved` |
| Save error | `Failed to save settings` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| `NotificationService` key mismatch with `tasks` change | Medium | Canonical key names defined here; `tasks` change must adopt them. Document in both PRs. |
| Vue mount inside PHP admin template conflicts with NC CSP | Low | Use `TemplateResponse` (not raw HTML output); NC admin templates already support script includes. |
| `CnVersionInfoCard` update-check hits app store on every page load | Low | Cache update check result in `ICache` (TTL: 1 hour); serve cached value in `AdminSettings.php`. |
| Drag-to-reorder not keyboard accessible by default | Medium | `ColumnListEditor` must include Up/Down buttons alongside drag handles. |
| `ConfigurationService::importFromApp()` called multiple times (double-click) | Low | Disable "Initialize register" button after first click; re-enable only on error. |
