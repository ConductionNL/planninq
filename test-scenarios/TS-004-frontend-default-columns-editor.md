---
id: TS-004
title: "Frontend — Default columns editor"
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

# TS-004: Frontend — Default columns editor

**Goal**: Verify that frontend — default columns editor

## Preconditions

- App is installed and enabled in Nextcloud
- User is logged in as admin (admin/admin)
- OpenRegister is available (if required)

## Scenario

- GIVEN the app is installed and user is logged in as admin
- WHEN the user navigates to the relevant page
- THEN 
Shows current default columns as an editable ordered list- AND Admin can add, remove, and reorder column names- AND Changes are saved via `POST /api/settings` on save button click- AND Shows success/error feedback after save

## Test Data

_(use default dev environment — admin/admin on localhost:8080)_

## Acceptance Criteria

- [ ] Shows current default columns as an editable ordered list
- [ ] Admin can add, remove, and reorder column names
- [ ] Changes are saved via `POST /api/settings` on save button click
- [ ] Shows success/error feedback after save

## Notes

_Auto-generated from admin-settings-mvp/tasks.md (Task 4) during pipeline archive on 2026-04-06._
