# Capacity Planning (Resource) Specification

**Status**: in-progress
**Feature tier**: MVP
**Spec refs**: ADR-001 (information architecture — Portfolio menu), ADR-022 (apps consume OR abstractions)

## Purpose

Give a project lead a single-screen read on where open work sits across the
projects they are a member of, so they can spot overloaded or at-risk projects
at a glance. MVP scope is deliberately small: it reads counts already derivable
from the `project` and `task` OpenRegister schemas (member count, open task
count, overdue task count) with a client-side reduce — no bespoke aggregation
service, no cross-app rollup, no BBV programme tree.

## Scope

**In scope (MVP):**
- Per-project member count.
- Per-project open task count (tasks whose status is not `done`/`cancelled`).
- Per-project overdue task count (open tasks whose `dueDate` is in the past).
- A simple per-project bar of open work for visual comparison.

**Out of scope (tracked follow-ups, ADR-032 chaining):**
- `bbv-programma-tree`, `raadsbesluit-deliverable-chain`,
  `risk-register-issue-tracking`.
- Cross-app / PMO rollup and forecasting.
- Per-member allocation / availability modelling.

## Requirements

### Requirement: Per-Project Capacity Summary [MVP]

The system MUST show, for every project the current user is a member of, the
project's member count, its open task count and its overdue task count,
derived client-side from the `project`/`task` schemas (ADR-022 — no server
aggregation endpoint).

#### Scenario: View capacity across my projects
- GIVEN the current user is a member of one or more projects
- WHEN the user opens the Portfolio view
- THEN the system MUST list each of those projects with its member count,
  open task count and overdue task count
- AND the system MUST render a bar per project sized by its open task count

#### Scenario: Closed tasks are excluded from open/overdue counts
- GIVEN a project has tasks in `done` or `cancelled` status
- WHEN the capacity summary is computed
- THEN those tasks MUST NOT contribute to the open or overdue counts

#### Scenario: Overdue counts only open tasks past their due date
- GIVEN an open task whose `dueDate` is strictly before today
- WHEN the capacity summary is computed
- THEN that task MUST be counted as overdue
- AND a closed task past its due date MUST NOT be counted as overdue

## Acceptance Criteria

- [x] Portfolio view lists every member project with member/open/overdue counts
- [x] Open count excludes `done`/`cancelled` tasks
- [x] Overdue count includes only open tasks with a past `dueDate`
- [x] A bar per project visualises open work (design-token bars, no charting lib)

## Notes

- The counting logic lives in the pure helper `src/utils/portfolioHelpers.js`
  (`summariseProjectTasks`), unit-tested in `tests/vitest/portfolio.spec.js`.
