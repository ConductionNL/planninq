---
id: TS-005
title: "Frontend — OpenRegister initialization section"
priority: medium
category: ux
personas: []
test-commands:
- test-functional
- test-app
tags:
- ux
- regression
- admin-settings-mvp
status: active
created: 2026-04-06
spec-refs:
- admin-settings-mvp/tasks.md
---

# TS-005: Frontend — OpenRegister initialization section

**Goal**: Verify that frontend — openregister initialization section

## Preconditions

- App is installed and enabled in Nextcloud
- User is logged in as admin (admin/admin)
- OpenRegister is available (if required)

## Scenario

- GIVEN the app is installed and user is logged in as admin
- WHEN the user navigates to the relevant page
- THEN 
Shows whether the Planninq register is initialized (green check / warning)- AND If not initialized, shows "Initialize register" button- AND Button triggers register initialization (calls backend endpoint)- AND Shows loading state during initialization- AND Shows success or error result after completion

## Test Data

_(use default dev environment — admin/admin on localhost:8080)_

## Acceptance Criteria

- [ ] Shows whether the Planninq register is initialized (green check / warning)
- [ ] If not initialized, shows "Initialize register" button
- [ ] Button triggers register initialization (calls backend endpoint)
- [ ] Shows loading state during initialization
- [ ] Shows success or error result after completion

## Notes

_Auto-generated from admin-settings-mvp/tasks.md (Task 5) during pipeline archive on 2026-04-06._
