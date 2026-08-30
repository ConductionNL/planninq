---
id: TS-009
title: "Danger Zone — archive and delete require confirmation"
priority: high
category: functional
personas:
- janwillem-van-der-berg
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-sidebar-danger-zone
---

# TS-009: Danger Zone — archive and delete require confirmation

**Goal**: Verify that destructive project actions (archive and delete) require an explicit confirmation step before executing.

## Preconditions

- User is a project creator or admin
- Settings sidebar is open on the Danger Zone tab

## Scenario

- GIVEN the user has opened the settings sidebar and navigated to the Danger Zone section
- WHEN the user clicks "Archive project"
- THEN a confirmation prompt is shown before archiving executes
- AND WHEN the user cancels, the project remains intact
- AND WHEN the user clicks "Delete project"
- THEN a confirmation dialog is shown with the task count
- AND cancelling leaves the project intact

## Test Data

_(use a project with at least 1 task to verify task count in delete dialog)_

## Acceptance Criteria

- [ ] "Archive project" button is visible in Danger Zone
- [ ] "Delete project" button is visible in Danger Zone
- [ ] Archive action shows confirmation before executing
- [ ] Delete action shows confirmation dialog with task count
- [ ] Cancelling either action leaves the project intact

## Notes

_Converted from test-plan.md TC-9 during archive of `projects` change._
