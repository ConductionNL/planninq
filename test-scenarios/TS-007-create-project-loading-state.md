---
id: TS-007
title: "Create project — loading state during creation"
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
- openspec/specs/projects.md#scenario-create-project-loading-state
---

# TS-007: Create project — loading state during creation

**Goal**: Verify that the creation dialog shows a loading state while the API request is in-flight.

## Preconditions

- Creation dialog is open with a valid title
- Network conditions allow observation of the loading state (may require throttling)

## Scenario

- GIVEN the user has entered a valid title in the creation dialog
- WHEN the user clicks "Create" and the API request is in-flight
- THEN the submit button shows a loading spinner and is disabled
- AND the dialog cannot be closed (X button and ESC are disabled while saving)

## Test Data

_(throttle network to 3G to make loading state observable)_

## Acceptance Criteria

- [ ] Submit button shows loading indicator during API call
- [ ] Submit button is disabled during API call
- [ ] Dialog cannot be dismissed during API call

## Notes

_Converted from test-plan.md TC-7 during archive of `projects` change._
_Note: loading state may be difficult to observe in fast local environments — use network throttling._
