---
id: TS-006
title: "Create project — default columns are created on success"
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
- openspec/specs/projects.md#scenario-create-project-default-columns-created
---

# TS-006: Create project — default columns are created on success

**Goal**: Verify that creating a project generates 4 default kanban columns and navigates to the new project.

## Preconditions

- User is on the project list
- `default_columns` admin setting is NOT configured (uses hardcoded fallback)

## Scenario

- GIVEN the user opens the "New project" dialog and enters a valid title
- WHEN the user clicks "Create"
- THEN the dialog closes
- AND the router navigates to `/projects/{newId}`
- AND 4 default columns exist: To Do (order 0), In Progress (order 1, WIP 3), Review (order 2, WIP 2), Done (order 3)
- AND the creating user is automatically added as a project member

## Test Data

_(use unique title to identify the created project in OpenRegister)_

## Acceptance Criteria

- [ ] Dialog closes on success
- [ ] Browser navigates to `/projects/{newId}`
- [ ] Success toast "Project aangemaakt" / "Project created" shown
- [ ] 4 columns exist in OpenRegister for the new project
- [ ] Creating user is in project members list

## Notes

_Converted from test-plan.md TC-6 during archive of `projects` change._
