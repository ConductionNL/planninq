---
id: TS-010
title: "Default column configuration is used when admin setting is set"
priority: medium
category: functional
personas:
- noor-yilmaz
test-commands:
- /test-functional
tags:
- functional
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-default-column-configuration
---

# TS-010: Default column configuration is used when admin setting is set

**Goal**: Verify that a custom admin-configured column set is used instead of the hardcoded fallback when creating a new project.

## Preconditions

- User is a Nextcloud admin
- Admin has configured `default_columns` in Planninq admin settings with a custom column set (e.g., Backlog, Active Sprint, Done)

## Scenario

- GIVEN the admin has configured a custom `default_columns` setting
- WHEN any user creates a new project
- THEN the project is created with the admin-configured columns
- AND the hardcoded fallback columns (To Do, In Progress, Review, Done) are NOT created

## Test Data

_(configure `default_columns` in Planninq admin settings before test)_

## Acceptance Criteria

- [ ] Admin can configure `default_columns` in admin settings
- [ ] New project uses admin-configured columns, not fallback
- [ ] Column titles, order, and WIP limits match the admin configuration

## Notes

_Converted from test-plan.md TC-10 during archive of `projects` change._
