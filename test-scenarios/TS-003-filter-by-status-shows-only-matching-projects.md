---
id: TS-003
title: "Filter by status shows only matching projects"
priority: medium
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
- openspec/specs/projects.md#scenario-filter-by-status
---

# TS-003: Filter by status shows only matching projects

**Goal**: Verify that status filter chips correctly filter the project list to show only projects with the selected status.

## Preconditions

- User is logged in and on the project list
- Projects with statuses Active, Archived, and Completed are all present

## Scenario

- GIVEN the project list has projects with statuses Active, Archived, and Completed
- WHEN the user clicks the "Archived" status filter chip
- THEN only archived projects are shown
- AND active and completed projects are hidden
- AND the chip is in active/selected visual state

## Test Data

_(create or seed projects with all three statuses before test)_

## Acceptance Criteria

- [ ] "Archived" chip click shows only archived projects
- [ ] Active and completed projects are hidden
- [ ] Filter chip shows selected state
- [ ] Clearing filter restores all projects

## Notes

_Converted from test-plan.md TC-3 during archive of `projects` change._
