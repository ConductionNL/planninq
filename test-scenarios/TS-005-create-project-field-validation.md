---
id: TS-005
title: "Create project — field validation prevents submit without title"
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
- openspec/specs/projects.md#scenario-create-project-field-validation
---

# TS-005: Create project — field validation prevents submit without title

**Goal**: Verify that the project creation dialog enforces title as a required field with inline validation.

## Preconditions

- User is on the project list
- "New project" button is visible and clickable

## Scenario

- GIVEN the user opens the "New project" creation dialog
- WHEN the user focuses the title field, then blurs it without entering a value
- THEN an inline validation error "Title is required" is displayed below the field
- AND the submit button remains disabled

## Test Data

_(no specific test data — use empty title field)_

## Acceptance Criteria

- [ ] Submit button is disabled when title is empty
- [ ] Inline "Title is required" message appears after field blur
- [ ] Submit button becomes enabled when a valid title is entered
- [ ] Dialog can still be cancelled without submitting

## Notes

_Converted from test-plan.md TC-5 during archive of `projects` change._
_Implementation note: uses `@focusout.native` on NcTextField (not `@blur`) to detect field blur._
