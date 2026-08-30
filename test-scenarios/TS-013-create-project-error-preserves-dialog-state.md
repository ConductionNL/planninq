---
id: TS-013
title: "Create project error preserves dialog state"
priority: medium
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
- openspec/specs/projects.md#scenario-error-state-on-project-create
---

# TS-013: Create project error preserves dialog state

**Goal**: Verify that a project creation API error shows a toast notification while keeping the dialog open with user input preserved.

## Preconditions

- User is on the project list with the creation dialog open
- OpenRegister is configured to return an error for project creation

## Scenario

- GIVEN the user has entered a valid title in the creation dialog
- WHEN the user submits and the OpenRegister API returns an error
- THEN a `NcToast` error notification is shown
- AND the creation dialog remains open
- AND the user's entered title is preserved in the field

## Test Data

_(requires API interception to simulate creation failure)_

## Acceptance Criteria

- [ ] Error toast shown on API failure
- [ ] Dialog remains open (not closed on error)
- [ ] User's title input is preserved
- [ ] User can retry or cancel

## Notes

_Converted from test-plan.md TC-13 during archive of `projects` change._
