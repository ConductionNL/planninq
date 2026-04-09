---
id: TS-001
title: "Backend — SettingsController admin endpoints"
priority: medium
category: api
personas: []
test-commands:
- test-functional
- test-app
tags:
- api
- regression
- admin-settings-mvp
status: active
created: 2026-04-06
spec-refs:
- admin-settings-mvp/tasks.md
---

# TS-001: Backend — SettingsController admin endpoints

**Goal**: Verify that backend — settingscontroller admin endpoints

## Preconditions

- App is installed and enabled in Nextcloud
- User is logged in as admin (admin/admin)
- OpenRegister is available (if required)

## Scenario

- GIVEN the app is installed and user is logged in as admin
- WHEN the API endpoint is called
- THEN 
`GET /api/settings` returns current admin settings as JSON- AND `POST /api/settings` accepts a JSON body and stores values via IAppConfig- AND Settings include: `default_columns` (JSON string), `allow_project_creation` (string)- AND Only admin users can write settings (middleware or annotation check)- AND Returns 403 for non-admin write attempts

## Test Data

_(use default dev environment — admin/admin on localhost:8080)_

## Acceptance Criteria

- [ ] `GET /api/settings` returns current admin settings as JSON
- [ ] `POST /api/settings` accepts a JSON body and stores values via IAppConfig
- [ ] Settings include: `default_columns` (JSON string), `allow_project_creation` (string)
- [ ] Only admin users can write settings (middleware or annotation check)
- [ ] Returns 403 for non-admin write attempts

## Notes

_Auto-generated from admin-settings-mvp/tasks.md (Task 1) during pipeline archive on 2026-04-06._
