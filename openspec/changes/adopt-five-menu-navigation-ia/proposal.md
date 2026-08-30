---
kind: code
---

# Proposal: Build the Five-Menu Navigation ADR-001 Already Committed To

## Why

`openspec/architecture/adr-001-information-architecture.md` (accepted
2026-05-23) commits planninq to a 5-menu top-level navigation — **Mijn werk,
Borden, Projecten, Portfolio, Beheer** — with an explicit spec→placement
mapping table naming `capacity-planning-resource`, `portfolio-dashboard-pmo`,
`bbv-programma-tree`, `risk-register-issue-tracking`, and
`raadsbesluit-deliverable-chain` as specs that belong under Portfolio /
Projecten tabs.

Verified against HEAD, none of this exists:

- `src/navigation/MainMenu.vue:1-73` renders exactly **3** items: Dashboard,
  Projects, Documentation (external link). No "Mijn werk", no "Borden", no
  "Portfolio", no "Beheer".
- `find openspec/specs -iname "*risk-register*" -o -iname "*capacity*" -o
  -iname "*portfolio*" -o -iname "*bbv*" -o -iname "*raadsbesluit*"` returns
  **zero files**. The specs ADR-001's own mapping table names do not exist —
  not even as drafts.
- `src/router/index.js:1-36` has 5 routes, all under `/` and `/projects/*`;
  there is no `/portfolio`, `/mijn-werk`, or `/beheer` route family.
- `src/views/Dashboard.vue` is the only "Mijn werk"-shaped surface today, and
  it is reached via a generic "Dashboard" nav item, not a "Mijn werk" menu
  with the daily-home content ADR-001 rule 6 describes (quick-add taak,
  timer, focus modus, vandaag-KPI).

ADR-001 rule 1 says "New specs that look like 'another noun' must be placed
as a sub-page, tab, or widget under one of these five [menus]" — but the five
menus themselves were never built, so there is nothing to place anything
under. The IA design pass this ADR lifted from (`/tmp/ia-small5.md`) was a
planning exercise that never reached code. Per ADR-032 (spec sizing), the
five Portfolio-domain specs named in the mapping table (capacity-planning,
BBV-tree, raadsbesluit-chain, risk-register, PMO-dashboard) are each
substantial enough to be their own chained spec — this change is scoped to
building the **navigation shell** ADR-001 mandates plus the **one
already-buildable** Portfolio content (a capacity-planning MVP using data
already on the `project`/`task` schemas: member count, task count by status,
overdue count), with the remaining Portfolio/chain specs filed as tracked
follow-ups rather than attempted in one PR (ADR-032 §chaining).

## What Changes

- Restructure the manifest `menu[]` (built by `adopt-cnapproot-manifest-shell`,
  a **dependency** of this change — see `hydra.json`) into ADR-001's 5 menus:
  **Mijn werk** (Dashboard, renamed/repositioned as the personal landing
  page), **Borden** (a new top-level "all boards" index, distinct from the
  per-project board), **Projecten** (existing Projects list + detail),
  **Portfolio** (new — landing page + capacity-planning MVP sub-page),
  **Beheer** (existing admin settings surfaced as an in-app menu item rather
  than only the NC admin panel, plus register-schemas info read-only).
- **No-functionality-loss** (ADR-044 hard rule): every current route stays
  reachable — Dashboard becomes "Mijn werk" (same route, `/`), Projects stays
  under "Projecten" (same routes), nothing is deleted without a redirect.
- Add `src/views/Portfolio.vue` (new): capacity-planning MVP — per-project
  member count, open/overdue task counts, a simple bar chart across all
  projects the user is a member of (uses NC chart primitives already in
  nc-vue per ADR-036, not a bespoke charting library).
- Add `src/views/Boards.vue` (new): "Borden" landing — a card per project the
  user is a member of, linking to that project's kanban board (`ProjectBoard`
  unchanged), implementing ADR-001's "kanban-board: menu → Borden" placement
  without duplicating the per-project board component.
- Beheer visibility: role-graded per ADR-001 rule 7 — hidden for regular
  members, read-only (project templates read + register-schemas info) for
  project leads, fully editable for NC admins. Reuses the existing
  `SettingsService::isCurrentUserAdmin()` check; no new authorization
  service (ADR-005/ADR-022).
- Write the two currently-missing specs referenced by ADR-001's mapping table
  that this change actually builds:
  `openspec/specs/capacity-planning-resource.md` (MVP scope only) and
  `openspec/specs/portfolio-dashboard-pmo.md` (landing page only, PMO
  rollup deferred). `bbv-programma-tree`, `raadsbesluit-deliverable-chain`,
  and `risk-register-issue-tracking` are filed as tracked follow-up issues,
  not attempted here (ADR-032 chaining).

## Impact

- Depends on `adopt-cnapproot-manifest-shell` (needs the manifest `menu[]` +
  `buildManifest` pipeline this change edits to exist first).
- Added: `src/views/Portfolio.vue`, `src/views/Boards.vue`, 2 new specs.
- Modified: `menu-layout.json` / manifest `menu[]`, `MainMenu` → manifest-
  driven equivalent (already replaced by the dependency change).
- No routes removed; Dashboard's URL (`/`) is unchanged, only relabeled
  "Mijn werk" in the nav.

## Dependencies

Depends on `adopt-cnapproot-manifest-shell` (see `hydra.json`). Cannot land
first — ADR-044 §6 requires the manifest fragment / `buildManifest` pipeline
as a prerequisite for any menu-layout change.
