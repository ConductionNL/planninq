---
id: TS-004
title: "Empty state when user has no projects"
priority: medium
category: functional
personas:
- fatima-el-amrani
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-empty-project-list
---

# TS-004: Empty state when user has no projects

**Goal**: Verify that a user with no project memberships sees the correct empty state.

## Preconditions

- User is logged in as a newly created Nextcloud user with zero project memberships

## Scenario

- GIVEN the user is authenticated but is not a member of any project
- WHEN the user navigates to `/apps/planix/projects`
- THEN `NcEmptyContent` is shown with title "No projects yet"
- AND an action button "Create your first project" is visible
- AND no project items are rendered

## Test Data

_(create a fresh test user with no project memberships)_

## Acceptance Criteria

- [ ] NcEmptyContent renders with "No projects yet" title
- [ ] "Create your first project" action button is visible and functional
- [ ] No project list items are shown

## Notes

_Converted from test-plan.md TC-4 during archive of `projects` change._
