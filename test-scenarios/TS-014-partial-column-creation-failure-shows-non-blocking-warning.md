---
id: TS-014
title: "Partial column creation failure shows non-blocking warning"
priority: low
category: functional
personas:
- sem-de-jong
test-commands:
- /test-functional
tags:
- functional
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-create-project-default-columns-created
---

# TS-014: Partial column creation failure shows non-blocking warning

**Goal**: Verify that a partial failure during default column creation results in a non-blocking warning, not a hard error, and the project remains accessible.

## Preconditions

- User is on the project list
- OpenRegister is configured to fail on the third column POST but succeed on others

## Scenario

- GIVEN the user creates a project and column creation partially fails (e.g., 3rd column POST returns error)
- WHEN the project creation process completes
- THEN a non-blocking warning toast appears listing the failed column(s)
- AND the user is still navigated to the new project board
- AND the project is accessible with the successfully created columns

## Test Data

_(requires API interception to simulate partial column creation failure)_

## Acceptance Criteria

- [ ] Warning toast shown (not error) on partial column failure
- [ ] User navigated to new project despite column failure
- [ ] Project board accessible
- [ ] Successfully created columns are shown

## Notes

_Converted from test-plan.md TC-14 during archive of `projects` change._
