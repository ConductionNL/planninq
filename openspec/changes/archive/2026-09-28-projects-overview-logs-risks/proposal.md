---
kind: code
---

# Project overview, project logs and a risk register

## Why

A project has no page that tells you where it stands. Opening a project lands on the board (`src/views/ProjectBoard.vue`, route `/projects/:id` in `src/manifest.json:145`). The description sits in the Details tab and the members in the Members tab of the settings sidebar (`src/components/ProjectSettingsSidebar.vue:16-127`). Nothing shows progress: `task.percentComplete` is declared on the schema and read nowhere in `src/`.

A project has no log. The only notes and activity are per task, in the task sidebar (`src/views/TaskDetail.vue:124-137`). A project manager cannot record an issue, a lesson learned or what a meeting decided, and cannot keep the actions from those in one place.

A project has no risk register. `lib/Settings/planninq_register.json` has no risk schema, and neither `src/manifest.json` nor `src/registry.js:42-51` has a risk page. ADR-001 places `risk-register-issue-tracking` under Projecten as a sub-page (`openspec/architecture/adr-001-information-architecture.md:61`).

Gemeente Sittard-Geleen asks for all three in tender 365739 (https://www.tenderned.nl/aankondigingen/overzicht/365739): logs for actions, issues, lessons learned and meetings (requirement 4012), and a risk table scored on likelihood and impact with a format that can differ per portfolio (requirements 4003, 4004, 64441). OpenProject and Kanboard ship a project overview page. The OpenProject community also asks for a risk register (https://community.openproject.org/wp/49051).

Parity rows: `prj-overview-page`, `prj-project-logs`, `prj-risk-register` in planninq's `openspec/parity/capabilities.json`.
Decision: build. The overview is half built and the missing half matters, with two competitors rated yes. Logs and the risk register answer a tender in the core projects area.

## What changes

- Every project gets an Overview tab: description, dates, people, progress and the latest risks and log entries on one screen.
- The project pages share one row of tabs: Overview, Board, Backlog, Timeline, Risks and Log.
- A project member records issues, lessons learned, meetings and decisions in the project log, and turns any of them into an action that is a normal task.
- A project member keeps a risk register: each risk scored on likelihood and impact, with an owner, a response and countermeasures, shown as a list and as a heat map.
- Projecten gets a Risks sub-page that lists the risks of every project you can see, highest score first.
- An admin sets the risk scale: how many levels, what each is called, and where low turns into medium and high.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-overview-page`, `prj-project-logs`, `prj-risk-register`.

### `prj-overview-page`: Open a project overview page showing its description, members and progress.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue:26-64 (title/icon/color header) opens src/components/ProjectSettingsSidebar.vue:16-127 (Details tab shows description; Members tab lists members). No progress indicator anywhere: task.percentComplete (schema property) is never read in src/ (grep confirmed zero matches)."
- Note: "description and members are both reachable, but only by opening the settings sidebar from the board, not on one overview screen, and there is no progress indicator anywhere in the app."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Inside a project > Overview grid of widgets, per project'; corpus: openproject/round4/M1-column.md 10.1 modules/overviews ; source read at v17.8.0: modules/overviews/config/routes.rb:6-7 project overview at the project root; modules/overviews/lib/overviews/grid_registration.rb:16 'project_description', :26 'project_status', :46 'members' default widgets; modules/overviews/app/components/overviews/overview_grid_component.rb renders them"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md project 'Overview' and 'Summary' ; source: app/Controller/ProjectOverviewController.php shows description, members, column counts and activity ; source read at v1.2.54: app/Template/project_overview/show.php:3-8 column task counts, description, activity; app/Template/project_overview/information.php:10-15 members by role, :22-26 start and end date; app/Controller/ProjectOverviewController.php:16 show"

### `prj-project-logs`: Keep logs per project for actions, issues, lessons learned and meetings.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No project-scoped log of actions, issues, lessons learned or meetings. The only logs are per task: CnObjectSidebar Comments (notes) and Activity (audit trail) tabs on src/views/TaskDetail.vue:129-137. The project settings sidebar has Details, Members and Danger zone tabs only (src/components/ProjectSettingsSidebar.vue:8-165)."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirement 4012 (logboeken voor acties, issues, leerpunten, bijeenkomsten), 64437."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

### `prj-risk-register`: Keep a risk register per project that scores each risk on likelihood and impact and records its countermeasures.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "searched src/ lib/ and lib/Settings/planninq_register.json for risk, likelihood, impact, countermeasure: none. No risk schema, no risk page in src/manifest.json pages, no risk component in src/registry.js:42-51."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirements 4003, 4004, 64441 (risk table scored on likelihood and impact, format adjustable per portfolio). Also asked for by openproject: https://community.openproject.org/wp/49051 (featureRequest)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

## Scope

### In scope

- A project overview page and the shared project tabs.
- A `projectLogEntry` schema, the Log tab, and actions as tasks.
- A `risk` schema with a calculated score, the Risks tab with a heat map, and a cross-project Risks page.
- An app-wide risk scale in Beheer.

### Out of scope

- A different risk scale per portfolio. The portfolio grouping arrives in `projects-grouping-hierarchy-fields`, which adds the per-portfolio override of the scale defined here.
- Portfolio roll-ups of risk: `portfolio-status-overview` reads the risk scores defined here.
- Widgets the user can rearrange on the overview (OpenProject's grid). The overview has a fixed layout.

## Impact

- Schema: new `projectLogEntry` and `risk` schemas in `lib/Settings/planninq_register.json`, with project-scoped authorization. `task` is unchanged; an action is a task with `issueType: 'action'`.
- Manifest and registry: pages `ProjectOverview` (`/projects/:id/overview`), `ProjectRisks` (`/projects/:id/risks`), `ProjectLog` (`/projects/:id/log`) and `RiskIndex` (`/projects/risks`).
- Components: a shared `ProjectTabs` header used by the board, backlog, timeline and the three new pages; a `RiskHeatMap`; dialogs `src/dialogs/RiskEditDialog.vue` and `src/dialogs/LogEntryEditDialog.vue`.
- Settings: `risk_scale` in the admin settings.
- Extends the flat spec `openspec/specs/projects.md`.
- Depends on: `tasks-create-edit-delete` (lane A) for creating the action task from a log entry. `projects-members-and-roles` for the role lists the new schemas' authorization copies.

## Risks

### Risk 1: two new schemas break the exact schema count

**Severity**: Medium
**Mitigation**: `openspec/specs/project-delivery/spec.md:68-72` and `testRegisterDeclaresExactlySevenSchemas` (`tests/unit/Settings/PlanninqRegisterSchemaTest.php:370`) pin the register at seven schemas. That scenario still names `timeEntry`, which is already `plannedTimeEntry`. Task 1.3 updates the test and the scenario to the new list in the same PR.

### Risk 2: the project-scoped rule this copies may not scope

**Severity**: High
**Mitigation**: `projectLogEntry` and `risk` copy the project-scoped authorization of `task`, which matches through `$lookup` (`planninq_register.json:51-143`). `projects-members-and-roles` task 1.1 checks that rule live. This change waits for that answer before it copies the rule. planninq#681 gave the answer: copy the denormalised `members` rule, `{"members": {"$contains": "$userId"}}`, never the `$lookup`.

### Risk 3: a sixth menu by the back door

**Severity**: Low
**Mitigation**: The cross-project Risks page is a sub-page of Projecten, as ADR-001 maps it, and the project pages use tabs, not menu entries.
