# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Migrated the frontend from Vue 2.7 to Vue 3.5**, with `@nextcloud/vue` 8 → 9, `vue-router` 3 → 4, `@nextcloud/dialogs` 6 → 7, `vue-loader` 15 → 17, `@nextcloud/webpack-vue-config` 6 → 7 and `@conduction/nextcloud-vue` pinned at `2.1.0-vue3.13`
- SPA host element renamed `#content` → `#planix-app`: Vue 3's `mount()` renders *inside* the matched element where Vue 2's `$mount()` replaced it, so the old id would have nested the app inside Nextcloud's own `#content` wrapper
- `output.publicPath` is now `'auto'` instead of a hardcoded `/custom_apps/planix/js/`, so lazy chunks resolve under any Nextcloud apps path
- ESLint now layers `@conduction/nextcloud-vue`'s `conductionVue3Fixes` on top of the `@nextcloud` base; the Vue-2 preset armed **zero** `vue/no-deprecated-*` rules

### Fixed
- Four `beforeDestroy` hooks renamed to `beforeUnmount`. Vue 3 never calls `beforeDestroy`, so the live-update subscriptions in `ProjectList`/`TaskDetail`, the sidebar teardown in `ProjectBoard` and the debounce timer + `AbortController` in `MemberSearch` would have leaked with no console output
- `NcCheckboxRadioSwitch` in the user-settings dialog used the removed `checked` / `update:checked` API — the due-date reminder switch would have rendered permanently off and never saved
- `NcSelect` in the timesheet listened for `@input`, which `@nextcloud/vue` 9 never emits — the date-range preset would have stopped applying
- Every `NcButton` / `NcChip` `type` prop renamed to `variant` (v9 repurposed `type` as the *native* button type), and the four `native-type="submit"` buttons in admin settings renamed to `type="submit"`, restoring form submission
- `NcChip` variants that were set to the non-existent value `'default'` now use `'secondary'`, NcChip's real default
- `main.js` / `settings.js` now mount even when `l10n/<locale>.json` 404s. `loadTranslations` only calls its callback on success, so mounting inside it rendered a permanently blank app/admin panel for any locale without a bundle
- Removed the committed `openspec/schemas/conduction` symlink. It dangled in every clone, and because its target climbs seven levels — above the copy root — `docker cp` refused the whole tree with `invalid symlink`, so the app could not be deployed to a container at all
- e2e: `seedFixtures` now sends HTTP Basic credentials with `send: 'always'`. Playwright withholds them until a `WWW-Authenticate` challenge, which Nextcloud's app routes never send, so the first fixture write returned 401 and the seeder bailed — leaving all 15 regression tests asserting against an empty instance
- e2e: the target Nextcloud is resolved once in `tests/e2e/base-url.ts`, honours `PLAYWRIGHT_BASE_URL`, and no longer silently defaults to `http://localhost:8080` (the shared development container)

### Added
- Spec coverage: project-display capability spec (REQ-PXD-001..004) retroactively documenting project-list status label mapping, status chip type mapping, status filter chips, and member count display

## [0.2.1] - 2026-04-03

### Added
- Project list view with search (debounced 300 ms) and status filter chips (Active / Archived / Completed)
- Project list item shows color swatch, icon, title, member count badge, and status badge
- Empty state with "No projects yet" prompt and separate "No results" state for filtered lists
- Project creation modal dialog with title, description, color, and icon fields
- Inline form validation: submit disabled until title is provided; "Title is required" message on blur
- Loading spinner on submit button during project creation; dialog locked while saving
- Automatic creation of 4 default kanban columns on new project (configurable via admin settings)
- Project board shell view at `/projects/:id` with header, gear icon, and "View Backlog" link
- Project backlog placeholder view at `/projects/:id/backlog` with breadcrumb navigation
- Project settings sidebar with three sections: Details, Members, and Danger Zone
- Immediate metadata reflection: title/color/icon changes appear in page header without reload
- Member management: add members via Nextcloud user search; remove with assigned-task warning
- Leave project flow with last-member protection dialog
- Archive project action with inline confirmation in Danger Zone
- Project deletion cascade: removes columns, tasks, and time entries in dependency order
- Delete confirmation dialog showing task count before destructive action
- Access control: project list filtered to current user's memberships; non-member direct URL shows access-denied state
- Projects navigation entry in sidebar (NcAppNavigationItem, positioned first)
- Routes for `/projects`, `/projects/:id`, and `/projects/:id/backlog`
- Full Dutch (nl) translations for all project-related strings

### Fixed
- Webpack `output.publicPath` overridden to `/apps-extra/planix/js/` — chunks were loading from wrong path (`/apps/planix/js/`) causing 404 on all code-split bundles
- Settings sidebar save now includes `members` field in PATCH request, preventing member list from being cleared on title/description updates
- `NcChip` import fixed to use component path directly (`@nextcloud/vue/dist/Components/NcChip.js`) — not exported from main index in v8.16.0
- OpenRegister register updated to `publicWrite: true` / `publicRead: true` on app upgrade via repair step

## [0.2.0] - 2026-04-03

### Added
- Define task schema with all properties (title, description, status, priority, project, etc.)
- Define project schema with all properties (title, description, status, color, icon, members, etc.)
- Define column schema for kanban boards (title, project, order, wipLimit, color, type)
- Define timeEntry schema for time tracking (task, user, duration, date, description)
- Define label schema for categorization (title, color, description)
- Add seed data: 5 labels (Bug, Feature, Docs, Design, Infrastructure)
- Add seed data: 3 projects (Client Portal v2, Infrastructure Migration, Onboarding Automation)
- Add seed data: 12 columns (4 per project: To Do, In Progress, Review, Done)
- Add seed data: 5 tasks with realistic assignments and priorities
- Add seed data: 3 time entries referencing task seeds
- Register repair step for automatic schema import on app install/upgrade
- Bump register version to 0.2.0

### Changed
- Remove placeholder example schema from planix_register.json
- Remove example schema references from DeepLinkRegistrationListener
