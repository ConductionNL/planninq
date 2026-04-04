# Admin Settings MVP

**Status**: approved
**Spec reference**: [admin-user-settings](../../specs/admin-user-settings.md)
**Priority**: MVP

## Summary

Implement the admin settings page for Planix. This is the first MVP feature that wires up
the backend settings API with a proper frontend admin page. The user settings dialog is
deferred to a follow-up change — this change focuses on the admin side only.

## Scope

- Admin settings page under Nextcloud Administration → Planix
- CnVersionInfoCard as the first section
- Default columns configuration (editable ordered list)
- OpenRegister initialization status and trigger button
- Backend: SettingsController with read/write endpoints via IAppConfig
- Frontend: AdminRoot.vue with CnVersionInfoCard + CnSettingsSection components

## Out of scope

- User settings dialog (NcAppSettingsDialog) — separate change
- Procest bridge settings — V1, separate change
- Notification preferences — separate change

## Architecture

- Backend: `SettingsController` exposes `GET /api/settings` and `POST /api/settings`
- Settings stored via `OCP\IAppConfig` (server-side, admin-only)
- Frontend: Vue 2 + `@conduction/nextcloud-vue` components
- No new database tables or OpenRegister entities

## Risks

- CnVersionInfoCard and CnSettingsSection require `@conduction/nextcloud-vue` — verify the
  dependency is declared in package.json
- OpenRegister initialization depends on OpenRegister being installed and enabled
