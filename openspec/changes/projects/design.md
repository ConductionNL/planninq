# Design: Projects Specification

## Summary

Implement the full project management UI for Planix: project list, creation dialog, settings sidebar, member management, lifecycle operations (archive/delete), default column initialization, and the OpenRegister gate. All state stored as OpenRegister objects via `useObjectStore`; no custom database tables.

## Motivation

Without project management UI, Planix cannot be used end-to-end. Projects are the top-level containers for tasks, boards, and member access. This design delivers the complete feature set defined in the Projects Specification, using the thin-client architecture established by the `register-schemas` change.

## Approach

Thin-client on OpenRegister: Vue 2 components with Pinia stores that call OpenRegister's REST API. All UI components from `@conduction/nextcloud-vue`; no custom backend logic beyond what OpenRegister provides.

- **Project list**: `CnListViewLayout` + `useListView` composable. Client-side search debounced at 300 ms. Status filter chips (`Active`, `Archived`, `Completed`) managed by `useListView`. Empty state via `NcEmptyContent` with "Create your first project" action.
- **Project creation**: `NcDialog` modal (`ProjectCreationDialog.vue` in `src/dialogs/`). Title required, other fields optional. On submit: POST project → POST 4 default columns. Column failures show a non-blocking warning toast; project access is not blocked.
- **Project settings**: `CnObjectSidebar` (`ProjectSettingsSidebar.vue` in `src/sidebars/`). Sections: Details (title/description/color/icon/defaultAssignee), Members, Danger Zone (archive/delete).
- **Member management**: Nextcloud user search via `NcSelect`. Remove-member warning computed from task count with that assignee. Leave-project last-member guard before confirming.
- **OpenRegister gate**: `App.vue` `created()` checks `openRegisters` flag from settings API. Renders `NcEmptyContent` (no sidebar, no navigation) when absent; shows "Install OpenRegister" button for admin users only.
- **Default columns**: Created by the Pinia project store immediately after project POST. Falls back to hardcoded set (To Do, In Progress, Review, Done) when `default_columns` admin setting is absent.

## Scope

This design covers the `planix` app only. The `project` schema is already defined by the `register-schemas` change. No new PHP controllers are needed.

---

## Reuse Analysis

| Capability | Platform service / component | Custom code needed? |
|------------|------------------------------|---------------------|
| Project CRUD | `useObjectStore` + `createObjectStore` | No — use store factory |
| Project list view | `CnListViewLayout` + `useListView` | No |
| Client-side search + filter | `useListView` (built-in debounce, filter state) | No |
| Pagination | `CnPagination` (built into `CnListViewLayout`) | No |
| Creation form | `NcDialog` + `NcTextField` / `NcColorPicker` | Minimal — title validation only |
| Settings sidebar | `CnObjectSidebar` | No — reuse as-is |
| User search (members) | `@nextcloud/vue` `NcSelect` + NC Users API | No |
| Loading / error states | Pinia store `loading`/`error` flags + `NcEmptyContent` | No |
| Delete confirmation | `NcDialog` (confirm pattern) | Minimal — task count in message |
| i18n | `t(appName, '...')` + `l10n/*.json` | No |
| Column CRUD | `useObjectStore` for `column` schema | No |
| OpenRegister gate | `openRegisters` flag from settings API | Minimal — condition in `App.vue` |

**Finding**: No custom backend logic is required beyond what OpenRegister provides. All CRUD, search, pagination, and sidebar features are delivered by the platform. Custom code is limited to: (1) default column creation sequence, (2) assigned-task count warning on member removal, (3) project-specific Vue component layout and routing.

---

## Seed Data

The following seed objects MUST be added to `lib/Settings/planix_register.json` under `components.objects[]` using the `@self` envelope (`register: "planix"`, `schema: "project"`). Values are fictional but realistic Dutch IT/government team data. Import is idempotent — re-importing skips existing objects matched by slug.

### Project 1 — Webportaal Gemeente Utrecht

```json
{
  "@self": {
    "register": "planix",
    "schema": "project",
    "slug": "project-webportaal-gemeente-utrecht"
  },
  "title": "Webportaal Gemeente Utrecht",
  "description": "Herontwerp en technische migratie van het burgerportaal naar een op NL Design System gebaseerde omgeving.",
  "status": "active",
  "color": "#1D4ED8",
  "icon": "🏛️",
  "members": ["admin", "jan.devries", "sofie.bakker"],
  "defaultAssignee": "jan.devries",
  "caseReference": null,
  "labels": ["frontend", "migratie"]
}
```

### Project 2 — API Koppeling Kadaster

```json
{
  "@self": {
    "register": "planix",
    "schema": "project",
    "slug": "project-api-koppeling-kadaster"
  },
  "title": "API Koppeling Kadaster",
  "description": "Implementatie van de BAG-bevragingsservice voor adresvalidatie in het zaaksysteem.",
  "status": "active",
  "color": "#047857",
  "icon": "🗺️",
  "members": ["admin", "remi.hoekstra"],
  "defaultAssignee": "remi.hoekstra",
  "caseReference": null,
  "labels": ["backend", "integratie"]
}
```

### Project 3 — Interne Tooling v2

```json
{
  "@self": {
    "register": "planix",
    "schema": "project",
    "slug": "project-interne-tooling-v2"
  },
  "title": "Interne Tooling v2",
  "description": "Vervanging van de verouderde Excel-dashboards door een geautomatiseerd Nextcloud-gebaseerd rapportagesysteem.",
  "status": "active",
  "color": "#7C3AED",
  "icon": "🔧",
  "members": ["admin", "lisa.vermeer", "tom.janssen"],
  "defaultAssignee": null,
  "caseReference": null,
  "labels": ["tooling"]
}
```

### Project 4 — Burgerzaken Digitalisering

```json
{
  "@self": {
    "register": "planix",
    "schema": "project",
    "slug": "project-burgerzaken-digitalisering"
  },
  "title": "Burgerzaken Digitalisering",
  "description": "Digitalisering van aanvraagprocessen voor paspoorten, rijbewijzen en uittreksels via een zelfbedieningsportaal.",
  "status": "completed",
  "color": "#B45309",
  "icon": "📋",
  "members": ["admin", "annemiek.de-vries"],
  "defaultAssignee": "annemiek.de-vries",
  "caseReference": null,
  "labels": ["burgerzaken", "digitalisering"]
}
```

### Project 5 — Security Audit 2025

```json
{
  "@self": {
    "register": "planix",
    "schema": "project",
    "slug": "project-security-audit-2025"
  },
  "title": "Security Audit 2025",
  "description": "Jaarlijkse pentest en DPIA-review van alle Nextcloud-apps in de gemeentelijke omgeving.",
  "status": "archived",
  "color": "#DC2626",
  "icon": "🔒",
  "members": ["admin"],
  "defaultAssignee": null,
  "caseReference": null,
  "labels": ["security", "audit"]
}
```

---

## Notes

- Column seed data is out of scope for this change — columns are created dynamically per project by the store at creation time.
- `caseReference` values are null in seed data; the Procest bridge sets this at runtime.
- Member UIDs reference Nextcloud users — seed data uses common demo user names (`admin`, `jan.devries`, etc.).
- Seed data is loaded via `ConfigurationService::importFromApp()` in the repair step; re-import is idempotent (matched by slug).
