---
id: TS-003
title: "Frontend — AdminRoot with CnVersionInfoCard"
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

# TS-003: Frontend — AdminRoot with CnVersionInfoCard

**Goal**: Verify that frontend — adminroot with cnversioninfocard

## Preconditions

- App is installed and enabled in Nextcloud
- User is logged in as admin (admin/admin)
- OpenRegister is available (if required)

## Scenario

- GIVEN the app is installed and user is logged in as admin
- WHEN the user navigates to the relevant page
- THEN 
Admin settings page renders under Nextcloud Administration → Planix- AND First section is CnVersionInfoCard showing app name and version- AND Page uses CnSettingsSection for each logical group- AND Loads current settings from `GET /api/settings` on mount

## Test Data

_(use default dev environment — admin/admin on localhost:8080)_

## Acceptance Criteria

- [ ] Admin settings page renders under Nextcloud Administration → Planix
- [ ] First section is CnVersionInfoCard showing app name and version
- [ ] Page uses CnSettingsSection for each logical group
- [ ] Loads current settings from `GET /api/settings` on mount

## Notes

_Auto-generated from admin-settings-mvp/tasks.md (Task 3) during pipeline archive on 2026-04-06._
