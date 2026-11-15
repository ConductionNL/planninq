---
kind: code
---

# Capacity per person across projects

## Why

The Capacity report does not show people. Its manifest page note says "one row per assignee aggregating allocation over the projects they appear in" (`src/manifest.json:151`), and the Reports card promises "How allocation is spread across the people working on projects" (`:163`). The page itself builds one row per project: `Portfolio.vue` loads the viewer's active projects, reads each project's tasks, and pushes a row with the project's member count, open tasks and overdue tasks (`src/views/Portfolio.vue:134-147`, helper `summariseProjectTasks` at `src/utils/portfolioHelpers.js:28-41`). A team lead who wants to know who is overloaded has to open every board and count cards.

Jira Data Center has a user workload report that shows how much work each person has been given, per project. Xedule shows per team what is asked, what is available and what is planned.

Parity rows: `prt-capacity` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because the report is half built, its own label promises the missing half, and two competitors ship it.

## What changes

- The Capacity report shows one row per person: open tasks, overdue tasks, remaining estimated hours, work due in the next two weeks, and the projects that work sits in.
- Each person's row opens into a breakdown per project.
- Work nobody is assigned to has its own row, so it is not lost.
- The report can be limited to one portfolio or to chosen projects.
- The existing view per project stays, one click away.
- The manifest note and the Reports card describe what the page shows.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prt-capacity`.

### `prt-capacity`: See how allocation is spread across the people working on projects.

- Area `portfolio`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/manifest.json:163 (Reports card 'Capacity') -> src/views/Portfolio.vue:134 fetchProjects({status:'active'}) + :137 fetchTasks(project.id) -> src/utils/portfolioHelpers.js:28 summariseProjectTasks(); columns Project/Members/Open/Overdue at src/views/Portfolio.vue:32-45"
- Defect: "src/manifest.json:151 page _note and :163 card description claim 'one row per assignee' / 'spread across the people', but src/views/Portfolio.vue:139-147 builds one row per project (code reading, needs a live check)"
- Note: "The page aggregates per PROJECT (member count, open and overdue task counts), not per person. Nothing groups work by assignee, so 'how allocation is spread across the people' is not what it shows."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html, https://confluence.atlassian.com/display/JIRASOFTWARESERVER/Capacity+and+velocity+in+Advanced+Roadmaps, https://confluence.atlassian.com/jirasoftwareserver/capacity-and-velocity-in-advanced-roadmaps-1044784184.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'User Workload Report: Shows how much work a user has been allocated, and how long it should take'; docs: https://confluence.atlassian.com/display/JIRASOFTWARESERVER/Capacity+and+velocity+in+Advanced+Roadmaps team capacity per sprint in hours or story points ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'User Workload Report Shows how much work a user has been allocated ... the remaining workload, on a per-project basis'; https://confluence.atlassian.com/jirasoftwareserver/capacity-and-velocity-in-advanced-roadmaps-1044784184.html team capacity per ... (shortened; full text in the matrix row)"
  - Xedule (https://support.xedule.nl/hc/nl/articles/33451846658194-Jaarplanning-Analyses-Teams-Budget): "docs read 2026-09-27: https://support.xedule.nl/hc/nl/articles/33451846658194-Jaarplanning-Analyses-Teams-Budget per team 'Vraag', 'Beschikbaar: De som van alle werktijdfactoren van medewerkers binnen het team' and 'Inzet'"

## Scope

### In scope

- A per-person view of open work across the projects the viewer can read, with a per-project breakdown.
- Filters by portfolio and project.
- Keeping the per-project view, and correcting the page's descriptions.

### Out of scope

- Availability per person (contract hours, leave). That belongs to humaniq; design.md names it as an open question.
- Booking or moving work from this page. The report is read-only, as ADR-001 rule 4 keeps project and portfolio views of people's work.
- Time actually logged per person: the Time spent report (`src/manifest.json:193-213`) covers it.

## Impact

- View: `src/views/Portfolio.vue` gains a "By person" view, the default, next to "By project".
- Helper: a pure `summariseByAssignee` next to `summariseProjectTasks` in `src/utils/portfolioHelpers.js`.
- Manifest: the `Portfolio` page `_note` and the Capacity card description.
- Extends the flat spec `openspec/specs/capacity-planning-resource.md`.
- Depends on: `tasks-assignment-priority-labels` (lane A) for `sharedWith`, the extra assignees of a task. `projects-grouping-hierarchy-fields` for the portfolio filter and portfolio readers.

## Risks

### Risk 1: hours counted twice for shared tasks

**Severity**: Medium
**Mitigation**: A task's remaining estimate counts for its primary assignee (`assignedTo`) only. People in `sharedWith` see the task in a separate "Shared" count without hours, and the column header says so.

### Risk 2: estimates are missing on most tasks

**Severity**: Medium
**Mitigation**: The row shows how many of the person's open tasks have no estimate next to the hours, so "12 h" with "9 tasks without an estimate" is not read as a light load.

### Risk 3: a slow page on many projects

**Severity**: Low
**Mitigation**: The page already reads tasks per project (`Portfolio.vue:136-137`). It keeps one read per project and groups in the browser, and the portfolio filter narrows the reads before they start.
