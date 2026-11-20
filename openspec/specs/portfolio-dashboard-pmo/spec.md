# Portfolio Dashboard (PMO) Specification

**Status**: in-progress
**Feature tier**: MVP
**Spec refs**: ADR-001 (information architecture — Portfolio + Borden menus), ADR-022 (apps consume OR abstractions)

## Purpose

Provide the "Portfolio" landing surface ADR-001 commits to, plus the "Borden"
(boards) index that lets a user jump to any project board from one place. MVP
scope is the landing page and the boards index only; the full PMO rollup
(cross-project financials, programme trees, portfolio-level forecasting) is
deferred to a follow-up spec.

## Scope

**In scope (MVP):**
- A Portfolio landing page hosting the capacity-planning MVP
  (see `capacity-planning-resource.md`).
- A Boards ("Borden") index: one card per project the user is a member of,
  linking to that project's existing kanban board.

**Out of scope (follow-up):**
- PMO financial rollup, programme/portfolio hierarchy, forecasting.
- Cross-app portfolio aggregation.

## Requirements

### Requirement: Boards Index [MVP]

The system MUST provide a top-level Boards index listing one card per project
the current user is a member of, each navigating to that project's existing
kanban board without duplicating the board component.

#### Scenario: Open a board from the index
- GIVEN the current user is a member of a project
- WHEN the user opens the Boards index and activates that project's card
- THEN the system MUST navigate to `/projects/:id` (the existing
  `ProjectBoard`)

#### Scenario: Only member projects are listed
- GIVEN the current user is a member of some projects but not others
- WHEN the Boards index loads
- THEN it MUST list only the projects the user is a member of

### Requirement: Portfolio Landing [MVP]

The system MUST provide a Portfolio landing page that hosts the
capacity-planning MVP summary.

#### Scenario: View the portfolio landing
- GIVEN the current user is a member of one or more projects
- WHEN the user opens the Portfolio view
- THEN the system MUST render the per-project capacity summary defined in
  `capacity-planning-resource.md`

## Acceptance Criteria

- [x] Boards index lists one card per member project, linking to its board
- [x] Boards index excludes projects the user is not a member of
- [x] Portfolio landing renders the capacity-planning MVP summary

## Notes

- Both views are reachable from the app navigation (`Boards`, `Portfolio`).
  Under the current hand-rolled shell they are `MainMenu` entries + routes;
  once `adopt-cnapproot-manifest-shell` + `adopt-five-menu-navigation-ia`
  land, they move under the ADR-001 five-menu manifest layout (Borden,
  Portfolio) with the same routes.
