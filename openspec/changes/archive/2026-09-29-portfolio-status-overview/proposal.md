---
kind: code
---

# Portfolio overview, status per aspect and a multi-project timeline

## Why

A portfolio office cannot see how its projects are doing. The only cross-project page is Portfolio (`src/views/Portfolio.vue`), reached from a Reports card (`src/manifest.json:163`). It loads the active projects the viewer is a member of (`Portfolio.vue:134` through `fetchProjects`, with the member filter at `src/store/projects.js:195-197`) and shows members, open tasks and overdue tasks per project (`Portfolio.vue:29-69`). It shows no lifecycle status, progress, dates, money or health, and it cannot be limited to one portfolio.

A project leader cannot report status the way Dutch municipalities do. There is no per-aspect status on the project (`lib/Settings/planninq_register.json:445-580`) and no report history. Gemeente Sittard-Geleen asks in tender 365739 (https://www.tenderned.nl/aankondigingen/overzicht/365739) for a status per project on money, organisation, time, information, quality and risk, the aspects of the GROTIK method, rolled up to the portfolio (requirements 4020, 4129, 24322, 64437).

Nobody can see several projects on one timeline. `ProjectTimeline.vue` is scoped to one `:id`, and its endpoint `GET /apps/planninq/api/projects/{projectId}/timeline` (`lib/Controller/TimelineController.php:123`) reads one project.

OpenProject and Jira Data Center show many projects in one status list and on one timeline.

Parity rows: `prt-portfolio`, `prt-status-grotik`, `pln-multi-project-timeline` in planninq's `openspec/parity/capabilities.json`.
Decision: build. The portfolio view is half built and the missing half matters, with two competitors rated yes. The per-aspect status answers the tender. The multi-project timeline has two competitors rated yes.

## What changes

- A portfolio overview lists every project of a chosen portfolio the viewer can read, with lifecycle status, progress, dates, money, the six aspect statuses and the date of the last report.
- A project leader writes a status report: on track, at risk or off track for money, organisation, time, information, quality and risk, each with a short note. Planninq suggests a status for money, time and risk from the project's own data.
- The portfolio overview rolls the six aspects up: how many projects are in each state per aspect, and the portfolio's worst state per aspect.
- A multi-project timeline shows the projects of a portfolio on one time axis, each project a summary bar that opens into its phases and tasks.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prt-portfolio`, `prt-status-grotik`, `pln-multi-project-timeline`.

### `prt-portfolio`: See the state of all projects in one portfolio view.

- Area `portfolio`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/manifest.json:163 -> src/views/Portfolio.vue:134 fetchProjects({status:'active'}) -> src/store/projects.js:170 fetchProjects() (client-side member filter at :195) -> OpenRegister /api/objects/planninq/project"
- Note: "One table over projects, but only ACTIVE projects the viewer is a member of, and only members/open/overdue counts: no status, progress, dates, budget or health per project. Portfolio is not in the main menu, only a Reports card."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-lists/README.md:44 'create a multi-project status dashboard if you include your own project attributes or project life cycle phases' and project status filters (:129); portfolio and program workspaces are Enterprise gated (corpus: openproject/round4/open-core.md portfolio_management) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/models/queries/projects/filters/project_status_filter.rb:35 project status on the free projects list with custom project attribute columns; app/components/projects/index_page_header_component.rb:64-66 open all as one Gantt; ... (shortened; full text in the matrix row)"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html issue sources 'determine which projects, boards, and filters you want to import', a plan holds up to 100 projects (Advanced Roadmaps, bundled) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html 'Issue sources - Choose which projects, boards, and filters you want to import'; 'For a program: Connected plans - choose the plans you want to c[onnect]' (read 2026-09-26)"

### `prt-status-grotik`: Report each project's status on money, organisation, time, information, quality and risk, and roll it up to the portfolio.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "The only per-project roll-up is src/views/Portfolio.vue:29-69 (members, open, overdue, open-work bar) fed by src/store/projects.js:170 fetchProjects. No per-aspect status property on the project schema (lib/Settings/planninq_register.json:450-580) and no portfolio roll-up of such statuses."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirements 4020, 4129, 24322, 64437 (GROTIK status per project, aggregated per portfolio)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

### `pln-multi-project-timeline`: See several projects on one timeline.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectTimeline.vue is scoped to one :id (route /projects/:id/timeline); src/views/Portfolio.vue (route /portfolio) is a per-project capacity bar chart of open-task counts, not a shared Gantt/timeline"
- Note: "Portfolio is the closest cross-project view and it is a bar chart, not a timeline."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md global module 'Gantt charts' across projects; docs: opf/openproject HEAD 27a58131 docs/user-guide/gantt-chart OpenProject-overarching-project-planning.png (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/gantt/config/routes.rb:16-24 global Gantt outside any project; modules/gantt/lib/open_project/gantt/engine.rb:60 global menu item; app/components/projects/index_page_header_component.rb:64-66 'Open as Gantt' from the project list via app/services/projects/gantt_query_generator_service.rb"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html, https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html 'A board displays issues from one or more projects'; Advanced Roadmaps plans take several projects and boards as sources (corpus: jira-data-center/round4/M1-column.md 8.8 plan releases across a plan) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html 'Issue sources - Choose which projects, boards, and filters you want to import'; 'A single plan can load up to 5,000 issues and can't contain more than 100 projects' (read 2026-09-26)"

## Scope

### In scope

- A `projectStatusReport` schema, the report form, report history, and the latest statuses copied onto the project.
- The portfolio status overview and its roll-up.
- A read-only multi-project timeline.

### Out of scope

- Editing dates on the timeline: `planning-timeline-editing` (lane B).
- The capacity page: `portfolio-people-capacity`.
- Money figures themselves: `portfolio-finance`, which this change reads.
- Sprints of any kind. Planninq is kanban-only by a recorded decision (`docs/ARCHITECTURE.md:149`).

## Impact

- Schema: new `projectStatusReport`; `project` gains `health` fields for the latest report (`healthMoney`, `healthOrganisation`, `healthTime`, `healthInformation`, `healthQuality`, `healthRisk`, `healthOverall`, `healthDate`).
- Listener: copies the newest report onto its project.
- Controller: `TimelineController` gains a read for several projects at once, RBAC-scoped like the single read.
- Views: a Status tab on the project (`/projects/:id/status`), a portfolio overview (`/portfolio/status`) and a portfolio timeline (`/portfolio/timeline`).
- Extends the flat spec `openspec/specs/portfolio-dashboard-pmo.md` and the capability folder `gantt-timeline-view`.
- Depends on: `projects-grouping-hierarchy-fields` (portfolios and portfolio readers), `projects-overview-logs-risks` (risk scores and `ProjectTabs`), `portfolio-finance` (budget and actual cost for the money suggestion).

## Risks

### Risk 1: a copied status drifts from its report

**Severity**: Medium
**Mitigation**: Only the listener writes the `health` fields, from the newest report by `reportDate`. A property rule lets no user write them, and deleting the newest report copies the one before it.

### Risk 2: a suggestion read as a judgement

**Severity**: Low
**Mitigation**: A suggestion is shown next to the choice with its reason ("3 tasks are past their due date"), and the project leader always picks the status. A report never saves a suggestion the leader did not choose.

### Risk 3: another schema changes the schema count

**Severity**: Low
**Mitigation**: Task 1.4 updates `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72`.
