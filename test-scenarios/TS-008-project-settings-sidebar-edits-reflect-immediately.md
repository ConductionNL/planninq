---
id: TS-008
title: "Project settings sidebar opens and edits reflect immediately"
priority: high
category: functional
personas:
- janwillem-van-der-berg
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-edit-project-metadata-immediate-reflection
---

# TS-008: Project settings sidebar opens and edits reflect immediately

**Goal**: Verify that the settings sidebar opens correctly and that title/metadata changes are reflected immediately in the page header without a full reload.

## Preconditions

- User is a project creator or admin
- User is on `/projects/:id` for a project they own

## Scenario

- GIVEN the user is on a project board page
- WHEN the user clicks the gear icon to open the settings sidebar
- THEN `ProjectSettingsSidebar` opens as a sidebar panel
- AND WHEN the user edits the project title and clicks Save
- THEN the updated title is reflected immediately in the page header and breadcrumb
- AND no full page reload occurs
- AND the members list is preserved after the save

## Test Data

_(use any existing seed project where admin is a member)_

## Acceptance Criteria

- [ ] Gear icon opens settings sidebar
- [ ] Details, Members, and Danger Zone tabs are visible
- [ ] Saving title change updates page header immediately
- [ ] Breadcrumb reflects new title
- [ ] Members list unchanged after save
- [ ] Success toast shown after save

## Notes

_Converted from test-plan.md TC-8 during archive of `projects` change._
_Critical: PATCH request must include members field to prevent data loss (fixed in this change)._
