---
kind: code
---

# Plan tasks against a release and see a roadmap of releases and epics

## Why

A project member cannot say which release a task is meant for, and cannot see the project's releases and epics laid out over time. No schema in `lib/Settings/planninq_register.json` has a release or version property, and no view names one. The task schema does carry `issueType` (`lib/Settings/planninq_register.json:324-329`, free string, default `task`) and `epic` (`:376-383`, a `$ref` to the epic-level task), but nothing in `src/` or `lib/` reads or writes either, so epics exist only on imported data.

The matrix rates `bkl-roadmap` as built, and its own evidence says why that is wrong for this row: the only roadmap page, `FeaturesRoadmap` at `/features-roadmap` (`src/manifest.json:214-221`), is planninq's own product roadmap. It is rendered by the shared `CnFeaturesAndRoadmapView` from the committed `docs/features.json` and the GitHub proxy. It shows what Conduction plans to build into planninq, not the user's releases or epics. This change specs the user's roadmap and leaves the product roadmap alone.

OpenProject (versions with a project roadmap page), Plane (modules with a target date) and Jira (fix versions, version report, Advanced Roadmaps) all let a team plan work against a release; OpenProject and Jira show those releases and epics on a roadmap.

Parity rows: `bkl-releases`, `bkl-roadmap` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because three competitors plan against releases and two show a roadmap of them, while planninq's only roadmap page describes the product itself.

This change extends the flat spec `openspec/specs/projects.md` through two new capabilities, `releases` and `project-roadmap`.

## What changes

- A project member can create a release with a name, a target date and a description, and plan tasks against it.
- A project member can mark a release as released, and decides what happens to the tasks that are not done.
- Every release shows its progress: done tasks out of all its tasks.
- A project member can mark a task as an epic and link other tasks to it.
- Each project gets a roadmap page that shows its releases as dated markers and its epics as bars over time, with a list equivalent.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `bkl-releases`, `bkl-roadmap`.

### `bkl-releases`: Plan tasks against a version or release.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No release/version field or planning concept exists in the task, project or any other schema (lib/Settings/planninq_register.json has no release/version property), and no UI references one."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Project settings ... Versions'; corpus: openproject/round4/M1-column.md 9.1 and 11.10 version custom field; docs: opf/openproject HEAD 27a58131 docs/user-guide/roadmap/README.md 'Product roadmap release planning' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: config/routes.rb:478 project versions new, create; app/models/work_package_version.rb:31-35 a work package links to versions with kind target, several target versions allowed since 17.8; config/initializers/menus.rb:793 project settings 'Versions'"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §3 'Module is a scope grouping: start_date / target_date, status from ModuleStatus ... Membership is ModuleIssue'; no version object as such, a module with a target date stands in for a release ; source read at v1.4.2: apps/web/core/components/modules/modal.tsx:44 createModule; apps/api/plane/app/urls/module.py:20 modules route, apps/api/plane/app/views/module/base.py:295 create; apps/api/plane/db/models/module.py:67-74 Module with target_date and status. A module with a target date stands in for a release"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html, https://confluence.atlassian.com/jirasoftwareserver/releases-in-advanced-roadmaps-1044784195.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'Version Report', 'Release Burndown'; docs: https://confluence.atlassian.com/jirasoftwareserver/releases-in-advanced-roadmaps-1044784195.html 'a release can start Relative to previous release date' (corpus 8.8) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/releases-in-advanced-roadmaps-1044784195.html 'Releases in Advanced Roadmaps are referred to as Fix versions in Jira Software'; https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'Version Report' (read 2026-09-26)"

### `bkl-roadmap`: Show a roadmap of epics or versions over time.

- Area `backlog`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "The only 'roadmap' page in the app, FeaturesRoadmap (src/manifest.json, route /features-roadmap, type 'roadmap'), is the app's OWN development roadmap: its _note says it is 'Rendered by the shared CnFeaturesAndRoadmapView, which reads the committed docs/features.json and OpenRegister's GitHub proxy', it shows Planninq's own GitHub feature/issue roadmap, not a roadmap of the user's epics, projects or releases."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/roadmap/README.md 'Find out about the Product Roadmap and Release planning in OpenProject'; corpus: openproject/round4/menu-tree.md global 'Gantt charts' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: config/initializers/menus.rb:713-716 project 'Roadmap' menu when versions exist; config/routes.rb:484-487 /roadmap is versions#index; app/controllers/versions_controller.rb:39 index lists versions with their work packages and progress"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html, https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html, https://confluence.atlassian.com/jirasoftwareserver/releases-in-advanced-roadmaps-1044784195.html): "corpus: jira-data-center/round4/M1-column.md 8.8 Releases in Advanced Roadmaps and 3.14 'Modify a view in Advanced Roadmaps'; https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'dependent issues will have numbered badges at either end of the schedule bar' (timeline of epics and releases, Advanced Roadmaps bundled in the Jira Software DC documentation set) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/plans-1044784166.html 'a plan contains all of the work that needs to be done on your project in a timeline format'; https://confluence.atlassian.com/jirasoftwareserver/releases-in-advanced-roadmaps-1044784195.html ... (shortened; full text in the matrix row)"


## Scope

### In scope

- A `release` schema scoped to one project, with the project-membership authorization of `projectPhase`.
- A nullable `task.release` reference, and task detail fields for release, type (epic or not) and epic.
- A roadmap page per project at `/projects/:id/roadmap`, reached from the project board header.
- Release progress and the "mark as released" step.

### Out of scope

- A roadmap across projects. Portfolio views belong to `portfolio-status-overview` (lane C), which can reuse this change's helper.
- A release burndown and a version report.
- Several target releases per task (OpenProject allows several since 17.8). One release per task keeps the backlog filter a scalar equality.
- Any change to the product roadmap page `FeaturesRoadmap`.

## Impact

- Schema: new `release`; new `task.release`. `issueType` and `epic` are used as they are.
- Store: `src/store/projects.js` gains release reads and writes and epic linking.
- Views: a new `ProjectRoadmap` page (manifest page plus `src/registry.js` entry), a Roadmap button in the `ProjectBoard` header, release, type and epic fields on `TaskDetail`.
- Dialogs: `src/dialogs/ReleaseEditDialog.vue`, `src/dialogs/ReleaseShipDialog.vue`.
- Specs and tests: the schema count in `openspec/specs/project-delivery/spec.md:68-72` and `tests/unit/Settings/PlanninqRegisterSchemaTest.php:370` moves up by one.
- Depends on: `tasks-create-edit-delete` (the editable task detail the release and epic fields sit in). Coordinates with `backlog-sprints`, which also adds a schema.

## Risks

### Risk 1: two pages called roadmap
**Severity**: Medium
**Mitigation**: the footer page keeps its label "Features & roadmap" and describes the product. The new page is titled "Roadmap" inside a project, with the project name in its breadcrumb, and is only reached from that project. The design names this split so nobody "fixes" one into the other.

### Risk 2: epics without dates vanish from the roadmap
**Severity**: Medium
**Mitigation**: an epic with no dates of its own takes its span from the earliest start and latest due date of its tasks. An epic with no dated task at all is listed under "Not scheduled yet" instead of being dropped, the same rule the timeline applies (`openspec/specs/gantt-timeline-view/spec.md`).

### Risk 3: schema count assertions break
**Severity**: Low
**Mitigation**: updated in the same PR as the schema (task 1.3), and in whichever order this change and `backlog-sprints` land.
