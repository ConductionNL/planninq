---
id: TS-011
title: "OpenRegister gate renders error when OpenRegister is absent"
priority: high
category: functional
personas:
- noor-yilmaz
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-openregister-absent
---

# TS-011: OpenRegister gate renders error when OpenRegister is absent

**Goal**: Verify that Planix gracefully handles a missing OpenRegister dependency by showing a clear error state.

## Preconditions

- OpenRegister app is not installed or has been disabled
- User is logged in (both regular user and admin should be tested)

## Scenario

- GIVEN Planix is installed but OpenRegister is not enabled
- WHEN any user navigates to `/apps/planix`
- THEN the entire app renders `NcEmptyContent` with title "OpenRegister is required"
- AND the description explains that Planix requires OpenRegister
- AND no sidebar, navigation items, or project data is rendered
- AND admin users see an "Install OpenRegister" action button

## Test Data

_(disable OpenRegister via `occ app:disable openregister` before test; re-enable after)_

## Acceptance Criteria

- [ ] NcEmptyContent shown with "OpenRegister is required" title
- [ ] No sidebar or navigation rendered
- [ ] Admin sees "Install OpenRegister" action button
- [ ] Regular user does not see the install button

## Notes

_Converted from test-plan.md TC-11 during archive of `projects` change._
_Setup requires disabling OpenRegister — restore after test._
