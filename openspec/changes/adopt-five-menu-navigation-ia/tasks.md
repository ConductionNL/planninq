# Tasks: Adopt the Five-Menu Navigation IA (ADR-001)

## 0. Prerequisite check

- [ ] 0.1 Confirm `adopt-cnapproot-manifest-shell` has merged (manifest
      `menu[]` + `buildManifest` pipeline live) before starting task 1.

## 1. Menu layout

- [ ] 1.1 Create `src/menu-layout.json` with the 5 top-level groups per
      ADR-001: Mijn werk, Borden, Projecten, Portfolio, Beheer.
- [ ] 1.2 Relabel the existing "Dashboard" menu entry "Mijn werk" (same route
      `/`, no URL change).
- [ ] 1.3 Add "Borden" pointing at the new `Boards.vue` index.
- [ ] 1.4 Keep "Projecten" pointing at the existing `ProjectList.vue`.
- [ ] 1.5 Add "Portfolio" pointing at the new `Portfolio.vue`.
- [ ] 1.6 Add "Beheer" — role-graded visibility (hidden for members,
      read-only for project leads, editable for admins) per ADR-001 rule 7.
- [ ] 1.7 Verify no pre-existing route lost its menu entry or its URL
      (ADR-044 no-functionality-loss invariant).

## 2. Borden (kanban boards index)

- [ ] 2.1 Create `src/views/Boards.vue`: one card per project the user is a
      member of, linking to `/projects/:id` (existing `ProjectBoard.vue`,
      unchanged).
- [ ] 2.2 Add the route to the manifest `pages[]`.

## 3. Portfolio (capacity-planning MVP)

- [ ] 3.1 Write `openspec/specs/capacity-planning-resource.md` (MVP scope:
      per-project member count, open/overdue task counts — no BBV, no
      cross-app rollup).
- [ ] 3.2 Write `openspec/specs/portfolio-dashboard-pmo.md` (landing page
      scope only; full PMO rollup deferred to a follow-up spec).
- [ ] 3.3 Create `src/views/Portfolio.vue` implementing the MVP scope from
      3.1/3.2, reading directly from the `project`/`task` OR schemas
      (ADR-022 — no bespoke aggregation service; client-side reduce over
      already-fetched store data).
- [ ] 3.4 File tracked follow-up issues for `bbv-programma-tree`,
      `raadsbesluit-deliverable-chain`, `risk-register-issue-tracking`
      (ADR-032 chaining — explicitly out of scope for this change).

## 4. Beheer

- [ ] 4.1 Surface the existing admin settings + register-schemas info as an
      in-app "Beheer" menu item (in addition to the NC admin panel entry
      point), gated by `SettingsService::isCurrentUserAdmin()` for full
      access and a read-only mode for project leads.

## 5. Verification

- [ ] 5.1 e2e: every pre-existing route still reachable under its new menu
      label; no 404s introduced.
- [ ] 5.2 e2e: Beheer hidden for a regular member, read-only for a project
      lead, editable for an admin.
- [ ] 5.3 e2e: Borden index lists only projects the user is a member of.

## 6. Quality gates

- [ ] 6.1 `npm run lint` green.
- [ ] 6.2 18 hydra gates green, including gate-manifest-validates (no
      single-widget-dashboard anti-pattern on `Portfolio.vue`/`Boards.vue`)
      and gate-19 e2e-coverage for every new Scenario.
