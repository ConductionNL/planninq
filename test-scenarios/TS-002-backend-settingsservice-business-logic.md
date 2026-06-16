---
id: TS-002
title: "Backend — SettingsService business logic"
priority: medium
category: functional
personas: []
test-commands:
- test-functional
- test-app
tags:
- functional
- regression
- admin-settings-mvp
status: active
created: 2026-04-06
spec-refs:
- admin-settings-mvp/tasks.md
---

# TS-002: Backend — SettingsService business logic

**Goal**: Verify that backend — settingsservice business logic

## Preconditions

- App is installed and enabled in Nextcloud
- User is logged in as admin (admin/admin)
- OpenRegister is available (if required)

## Scenario

- GIVEN the app is installed and user is logged in as admin
- WHEN the user navigates to the relevant page
- THEN 
`getAdminSettings()` reads all planix admin keys from IAppConfig with defaults- AND `setAdminSettings(array $settings)` validates and stores each key- AND Default values match the spec: `default_columns = ["To Do","In Progress","Review","Done"]`- AND Unknown keys are silently ignored (no error, no storage)

## Test Data

_(use default dev environment — admin/admin on localhost:8080)_

## Acceptance Criteria

- [ ] `getAdminSettings()` reads all planix admin keys from IAppConfig with defaults
- [ ] `setAdminSettings(array $settings)` validates and stores each key
- [ ] Default values match the spec: `default_columns = ["To Do","In Progress","Review","Done"]`
- [ ] Unknown keys are silently ignored (no error, no storage)

## Notes

_Auto-generated from admin-settings-mvp/tasks.md (Task 2) during pipeline archive on 2026-04-06._
