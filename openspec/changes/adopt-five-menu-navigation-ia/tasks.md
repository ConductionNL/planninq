# Tasks: Adopt the Five-Menu Navigation IA (ADR-001)

> APPLY STATUS (this session): PARTIAL. The manifest `menu-layout.json` /
> five-menu restructure (tasks 1.x, 4.x) depends on
> `adopt-cnapproot-manifest-shell` (the manifest `menu[]` + `buildManifest`
> pipeline), which is DEFERRED (see that change) — so the manifest-menu work is
> deferred with it. What IS delivered here is the buildable, verifiable
> user-facing content that does NOT require the manifest shell: the two
> previously-missing specs, and the `Boards` (Borden) + `Portfolio`
> (capacity MVP) views wired as routes + nav entries on the current shell.

## 0. Prerequisite check

- [~] 0.1 Confirm `adopt-cnapproot-manifest-shell` has merged — NOT merged
      (deferred). The manifest-menu tasks below (1.x, 4.x) are therefore
      deferred; the additive views/specs are delivered on the current shell.

## 1. Menu layout

- [~] 1.1 Create `src/menu-layout.json` with the 5 ADR-001 groups — DEFERRED
      (requires the manifest shell). Interim: the new views are reachable via
      the current `MainMenu.vue` + `router/index.js`.
- [~] 1.2 Relabel "Dashboard" → "Mijn werk" — DEFERRED (manifest menu). No URL
      change is involved; the `/` Dashboard route is unchanged.
- [x] 1.3 Add "Borden" pointing at the new `Boards.vue` index — added as a
      `Boards` route (`/boards`) + `MainMenu` entry.
- [x] 1.4 Keep "Projecten" pointing at the existing `ProjectList.vue` — unchanged.
- [x] 1.5 Add "Portfolio" pointing at the new `Portfolio.vue` — added as a
      `Portfolio` route (`/portfolio`) + `MainMenu` entry.
- [~] 1.6 Add role-graded "Beheer" — DEFERRED (manifest menu + role-grading;
      admin settings remain reachable via the existing NC admin panel + the
      in-app Settings dialog, so no access is lost).
- [x] 1.7 No pre-existing route lost its entry or URL — all existing routes
      (`/`, `/projects`, `/projects/:id`, backlog, task detail) and their nav
      entries are unchanged; the new entries are purely additive (ADR-044
      no-functionality-loss preserved).

## 2. Borden (kanban boards index)

- [x] 2.1 Create `src/views/Boards.vue`: one card per member project linking to
      `/projects/:id` (existing `ProjectBoard.vue`, unchanged).
- [~] 2.2 Add the route to the manifest `pages[]` — DEFERRED (manifest shell);
      added as a `vue-router` route in `src/router/index.js` in the interim.

## 3. Portfolio (capacity-planning MVP)

- [x] 3.1 Write `openspec/specs/capacity-planning-resource.md` (MVP scope).
- [x] 3.2 Write `openspec/specs/portfolio-dashboard-pmo.md` (landing + Borden scope).
- [x] 3.3 Create `src/views/Portfolio.vue` — per-project member/open/overdue
      counts + a CSS bar of open work, client-side reduce over the
      `project`/`task` schemas (ADR-022). Counting logic in the pure helper
      `src/utils/portfolioHelpers.js` (unit-tested, `tests/vitest/portfolio.spec.js`).
- [~] 3.4 File tracked follow-up issues for `bbv-programma-tree`,
      `raadsbesluit-deliverable-chain`, `risk-register-issue-tracking` —
      NOT filed from this worktree (issue creation is a process/PR task, out of
      apply scope); they are named as out-of-scope follow-ups in the two specs.

## 4. Beheer

- [~] 4.1 Surface admin settings + register-schemas as a role-graded "Beheer"
      menu item — DEFERRED (manifest menu + role-grading; depends on the shell
      change). Admin settings remain reachable via the NC admin panel today.

## 5. Verification

- [~] 5.1 e2e: every pre-existing route reachable under its new label —
      NEEDS LIVE INSTANCE. Static proof: no route/nav entry removed; build green.
- [~] 5.2 e2e: Beheer role-grading — DEFERRED (Beheer deferred).
- [~] 5.3 e2e: Borden lists only member projects — NEEDS LIVE INSTANCE. Code
      proof: `Boards.vue` renders `projectsStore.projects`, which
      `fetchProjects` filters to the current user's member projects.

## 6. Quality gates

- [x] 6.1 `npm run lint` (eslint src) — 0 errors on `Boards.vue`,
      `Portfolio.vue`, `portfolioHelpers.js`, `MainMenu.vue`, `router/index.js`.
- [~] 6.2 18 hydra gates incl. gate-manifest-validates + gate-19 — DEFERRED:
      gate runner not invoked in this worktree; the new views are plain routed
      pages (no manifest single-widget-dashboard anti-pattern); e2e-coverage
      for the new Scenarios is the live-instance gap in 5.x.
