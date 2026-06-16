# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
