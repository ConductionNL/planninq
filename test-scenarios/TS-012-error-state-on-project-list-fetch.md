---
id: TS-012
title: "Error state on project list fetch"
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
- openspec/specs/projects.md#scenario-error-state-on-list-fetch
---

# TS-012: Error state on project list fetch

**Goal**: Verify that an API failure on the project list fetch shows a user-friendly error state with a retry option.

## Preconditions

- OpenRegister API is unreachable or returns a 500 error (simulate by temporarily blocking or returning error)

## Scenario

- GIVEN the OpenRegister API returns an error when fetching projects
- WHEN the user navigates to `/apps/planix/projects`
- THEN `NcEmptyContent` is shown with an error message and a "Retry" button
- AND the error is logged to the browser console (not swallowed silently)
- AND clicking "Retry" re-triggers the fetch

## Test Data

_(requires API interception or a broken OpenRegister URL to simulate)_

## Acceptance Criteria

- [ ] Error state shown with user-friendly message
- [ ] "Retry" button is visible and triggers a new fetch
- [ ] Error is logged to browser console
- [ ] App does not crash on API failure

## Notes

_Converted from test-plan.md TC-12 during archive of `projects` change._
