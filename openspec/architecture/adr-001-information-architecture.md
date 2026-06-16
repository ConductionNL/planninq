# ADR-001: Information Architecture — Top-Level Navigation & Spec Placement

**Status:** accepted
**Date:** 2026-05-23

## Context

planix is the operational planning surface — kanban boards, tasks, time-tracking,
projects, capacity planning, risk register, and PMO-rollup — used by both overheid
and MKB teams. It receives raadsbesluiten from decidesk via the deliverable-chain,
feeds progress to launchpad, and consumes the BBV-programma-tree from financeq so that
projects ladder up to programma's, doelen, and indicatoren.

The app currently inherits ~13 spec slugs of mixed scope: dashboards, boards,
projects, tasks, time-tracking, risk-register, capacity-planning, portfolio
rollup, BBV-tree consumption, raadsbesluit-chain, procest-integration,
register-schemas, and admin-user-settings. Without a shared placement convention,
each new spec risks growing into a sibling top-level menu — which would push the
sidebar past the 5–7 item cognitive ceiling, fracture the medewerker home page
(currently "Mijn werk"), and re-surface plumbing concerns (chain hops, adapters,
register schemas) that the user should not have to think about.

The fleet-wide Information Architecture design pass (`/tmp/ia-small5.md`,
2026-05-22) bounded planix at **five** top-level menus and produced a mapping
table for every existing spec. This ADR lifts the planix-only design rules from
that document into a per-app architecture record so that future spec authors and
implementers (opsx-ff, opsx-apply, team-architect) have a single canonical
reference.

### Primary persona pairs

- **medewerker + scrum-master** → land on Mijn werk + Borden
- **projectleider + PMO** → land on Projecten + Portfolio

## Decision

planix adopts the following Information Architecture.

### Top-level navigation (5 menus)

1. **Mijn werk** — personal landing surface (today, taken, borden, tijd, inbox)
2. **Borden** — kanban (alle borden, bord-detail, templates, archief)
3. **Projecten** — project model (overzicht, detail, risico's, Gantt, mijlpalen)
4. **Portfolio** — PMO surface (dashboard, capaciteit, BBV-tree view, roadmap)
5. **Beheer** — admin (templates, register-schemas, integrations, rollen, logs)

Reguliere medewerkers see Mijn werk + Borden + Projecten. Beheer is hidden for
medewerkers; projectleiders see a read-only sub-set (project-templates +
register-schemas).

### Canonical spec → placement mapping

| spec_slug | placement | parent |
|---|---|---|
| `dashboard-my-work` | menu | Mijn werk |
| `tasks` | sub-page | Mijn werk > Mijn taken (+ Projecten > Taken) |
| `kanban-board` | menu | Borden |
| `projects` | menu | Projecten |
| `bbv-programma-tree` | tab | Projecten > project > BBV-koppeling (+ Portfolio view) |
| `raadsbesluit-deliverable-chain` | tab | Projecten > project > Raadsbesluit-chain (+ Portfolio widget) |
| `risk-register-issue-tracking` | sub-page | Projecten > Risico-register |
| `time-tracking` | sub-page | Mijn werk > Tijdregistratie |
| `capacity-planning-resource` | sub-page | Portfolio > Capaciteitsplanning |
| `portfolio-dashboard-pmo` | sub-page | Portfolio > PMO-dashboard |
| `procest-integration` | settings | Beheer > Procest-integration |
| `register-schemas` | settings | Beheer > Register-schemas |
| `admin-user-settings` | settings | Beheer |

### Design rules

1. **5-menu ceiling — no sixth menu.** planix's sidebar is capped at five
   top-level menus (Mijn werk, Borden, Projecten, Portfolio, Beheer). New specs
   that look like "another noun" must be placed as a sub-page, tab, or widget
   under one of these five. Promoting a feature to a sixth menu requires a
   superseding ADR with cross-app architect sign-off.

2. **Chain relations are tabs, not menus.** BBV-programma-tree and
   raadsbesluit-deliverable-chain describe *how* a project ladders up to a
   programma or besluit — they are views/relations, not separate nouns the user
   creates. Surface them as tabs on the project detail (and as read-only widgets
   in Portfolio), never as their own menu items. The same rule applies to any
   future cross-app chain (e.g. financeq budget-realisatie, launchpad bestuurder-
   view): one project, many ladder-views.

3. **One task model, two contexts.** Tasks live in a single underlying model
   surfaced in two contexts — Mijn werk for personal queues and Projecten >
   Taken for managerial/project views. A task created on a bord and a task
   assigned to a project are the *same record*; never build a parallel task
   system. This rule extends to mentions, comments, and status: editing in one
   context is immediately visible in the other.

4. **Time-tracking is personal-first.** Time entries are created and edited
   under Mijn werk > Tijdregistratie. Project- and portfolio-level views are
   read-only roll-ups ("tijd op dit project", aggregation reports in Portfolio).
   Never duplicate a time-entry UI inside a project; never let a manager edit
   another user's time entry from a project screen.

5. **Integrations and plumbing live in Beheer, results live inline.**
   procest-integration, decidesk-koppeling, launchpad-koppeling, financeq-bridge,
   register-schemas, connectors, and all other adapter configuration sit under
   Beheer. The user-facing result surfaces inline on the operational object
   ("taak auto-aangemaakt door procest", "raadsbesluit-deadline op project",
   "BBV-doel gekoppeld"). There is no top-level "Integrations" or "Chain" menu
   — the medewerker focuses on the work, not the plumbing.

6. **Mijn werk is the landing page, not a dashboard sidebar.** Medewerkers
   land on Mijn werk by default (not a portfolio dashboard, not a project
   list). Mijn werk is treated as the daily home — quick-add taak, timer, focus
   modus, vandaag-KPI — optimised for the 80% of users who are not managers.
   Portfolio-style rollups and PMO views are deliberately separated under
   Portfolio so the medewerker surface stays uncluttered.

7. **Beheer visibility is role-graded, not binary.** Beheer is hidden for
   reguliere medewerkers; visible read-only (project-templates +
   register-schemas) for projectleiders; fully editable for app-admins. New
   Beheer sub-pages must declare their minimum-role at design time so the
   sidebar never shows a menu the user cannot use.

## Consequences

### Positive

- New spec authors have a deterministic placement decision: consult the mapping
  table, or apply rules 1–7 to a novel spec. No more "should this be a menu?"
  debates per-PR.
- The medewerker surface (Mijn werk + Borden) stays small and learnable even as
  the app accumulates portfolio, chain, and integration features.
- Cross-app chain hops (decidesk → planix → procest → launchpad) stay invisible to
  the medewerker; the platform plumbing is owned by Beheer and audit, not the
  daily UI.
- One task model + one time-tracking surface eliminates two classes of bugs
  (duplicated tasks across views, time-entry edits in the wrong place).

### Negative

- Specs that originally pitched themselves as standalone menus
  (bbv-programma-tree, raadsbesluit-deliverable-chain, capacity-planning,
  portfolio-dashboard) need their placement re-stated as sub-pages / tabs in
  their spec docs and implementation tickets. One-time migration cost.
- Beheer becomes a catch-all and risks its own sub-menu sprawl; a future ADR
  may need to impose structure within Beheer (e.g. Configuratie / Connectors /
  Rollen / Logs as fixed sub-tabs).
- The 5-menu ceiling will at some point block a feature that "deserves" its
  own menu (e.g. dedicated Risico's surface for a risk-management persona);
  the superseding-ADR escape hatch must be honoured, not eroded by exceptions.

### Neutral

- Implementation phases (1: MVP boards + tasks, 2: projects, 3: portfolio,
  4: chain) align naturally with this IA — early phases populate Mijn werk +
  Borden, later phases fill Portfolio and Beheer without renaming sidebar items.

## References

- `/tmp/ia-small5.md` — fleet-wide IA design pass (2026-05-22), Section 3
- planix `openspec/specs/` — per-spec details for each slug in the mapping table
- Related app ADRs (cross-cutting IA): `openbuild/openspec/architecture/`,
  `scholiq/openspec/architecture/`
