---
status: proposed
---

# Five-Menu Navigation (ADR-001 Implementation)

## Purpose

Builds the 5-menu navigation structure (Mijn werk, Borden, Projecten,
Portfolio, Beheer) that `openspec/architecture/adr-001-information-
architecture.md` accepted 2026-05-23 but which was never implemented in
code. This is the first code delta against ADR-001's mapping table.

**Cross-references**: ADR-001 (this app), ADR-044 (menu architecture,
prerequisite), ADR-032 (spec sizing/chaining — scopes out BBV/raadsbesluit/
risk-register as follow-ups).

---

## ADDED Requirements

### Requirement: Five Top-Level Menus, No Functionality Loss

Planninq SHALL present exactly five top-level navigation menus — Mijn werk,
Borden, Projecten, Portfolio, Beheer — and every route reachable before this
change SHALL remain reachable after it (ADR-044 hard rule).

#### Scenario: All five menus present

- **GIVEN** a logged-in project member
- **WHEN** the app shell renders
- **THEN** the sidebar MUST show Mijn werk, Borden, Projecten, Portfolio (and
  Beheer only if the role check in the next requirement allows it)
- @e2e planninq/tests/e2e/navigation-ia.spec.ts

#### Scenario: No route is lost in the relabel

- **GIVEN** the pre-existing routes `/`, `/projects`, `/projects/:id`,
  `/projects/:id/backlog`, `/projects/:id/tasks/:taskId`
- **WHEN** the five-menu navigation is live
- **THEN** every one of those URLs MUST still resolve to its original view
- @e2e planninq/tests/e2e/navigation-ia.spec.ts

### Requirement: Beheer Visibility Is Role-Graded

Beheer SHALL be hidden for regular members, visible read-only (project
templates + register-schemas info) for project leads, and fully editable for
Nextcloud admins.

#### Scenario: Regular member does not see Beheer

- **GIVEN** a user who is a project member but not an NC admin and not a
  project lead
- **WHEN** the sidebar renders
- **THEN** the Beheer menu item MUST NOT be shown
- @e2e planninq/tests/e2e/navigation-ia.spec.ts

#### Scenario: Admin sees an editable Beheer

- **GIVEN** an NC admin (per `SettingsService::isCurrentUserAdmin()`)
- **WHEN** the admin opens Beheer
- **THEN** the settings fields MUST be editable, matching the existing admin
  settings behaviour
- @e2e planninq/tests/e2e/navigation-ia.spec.ts

### Requirement: Borden Lists Only the User's Projects

The Borden index SHALL show one card per project the current user is a
member of, linking to that project's existing kanban board — no parallel
board component.

#### Scenario: Borden index scoped to membership

- **GIVEN** a user who is a member of 2 of the 5 projects on the instance
- **WHEN** the user opens Borden
- **THEN** exactly 2 cards MUST render, each linking to `/projects/:id`
- @e2e planninq/tests/e2e/boards-index.spec.ts

### Requirement: Portfolio Landing Shows Capacity MVP

The Portfolio landing page SHALL show, for each project the user is a member
of, the member count and the open/overdue task counts — sourced from
existing `project`/`task` OR objects, with no bespoke aggregation service.

#### Scenario: Capacity MVP renders per-project counts

- **GIVEN** a project with 3 members, 10 open tasks, 2 overdue
- **WHEN** the user opens Portfolio
- **THEN** the project's card MUST show member count 3, open 10, overdue 2
- @e2e planninq/tests/e2e/portfolio-mvp.spec.ts
