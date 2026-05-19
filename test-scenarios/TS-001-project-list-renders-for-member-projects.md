---
id: TS-001
title: "Project list renders for member projects"
priority: high
category: functional
personas:
- sem-de-jong
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#requirement-project-list-ui-mvp
---

# TS-001: Project list renders for member projects

**Goal**: Verify that the project list correctly shows only projects the current user is a member of, with all required visual elements.

## Preconditions

- User is logged in as an authenticated Nextcloud user
- At least 2 projects exist with the user as a member; each project has a color, icon, title, and member count
- At least 1 project exists where the user is NOT a member

## Scenario

- GIVEN the user is authenticated and has projects they are a member of
- WHEN the user navigates to `/apps/planix/projects`
- THEN `CnListViewLayout` renders with a search bar and status filter chips
- AND each project item shows: color swatch, icon, title, member count, and status badge
- AND projects where the user is NOT a member are not shown

## Test Data

_(use seed projects from planix_register.json — ensure admin is a member of at least one)_

## Acceptance Criteria

- [ ] Project list view renders without JavaScript errors
- [ ] Search bar is visible
- [ ] Status filter chips (Active / Archived / Completed) are visible
- [ ] Each project item shows color swatch, icon, title, member count, and status badge
- [ ] Non-member projects are not visible

## Notes

_Converted from test-plan.md TC-1 during archive of `projects` change._
