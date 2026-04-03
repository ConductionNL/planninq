---
id: TS-002
title: "Search filters projects in real time"
priority: medium
category: functional
personas:
- sem-de-jong
test-commands:
- /test-functional
tags:
- functional
- regression
status: active
created: 2026-04-03
spec-refs:
- openspec/specs/projects.md#scenario-search-projects
---

# TS-002: Search filters projects in real time

**Goal**: Verify that the search bar filters the project list client-side in real time with 300 ms debounce.

## Preconditions

- User is logged in and on the project list
- At least 3 projects with distinct titles are visible

## Scenario

- GIVEN the project list is rendered with at least 3 projects with distinct titles
- WHEN the user types a partial title substring into the search bar and waits up to 300 ms
- THEN only projects whose title or description contains the typed string are shown
- AND no page reload occurs
- AND clearing the search restores the full list

## Test Data

_(use seed projects: "Client Portal v2", "Infrastructure Migration", "Onboarding Automation")_

## Acceptance Criteria

- [ ] Typing a substring shows only matching projects
- [ ] Non-matching projects are hidden
- [ ] No page reload triggered
- [ ] Clearing search restores all projects

## Notes

_Converted from test-plan.md TC-2 during archive of `projects` change._
