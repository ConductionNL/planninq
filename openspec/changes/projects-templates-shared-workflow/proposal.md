---
kind: code
---

# Project templates, project copy and a shared workflow

## Why

Every project starts empty. `ProjectCreationDialog.vue` asks for a title, description, colour and icon, and `createProject` (`src/store/projects.js:328-363`) then adds the admin's default columns (`:469-494`). You cannot start from a template that already holds the columns, phases and tasks a kind of project always needs, and you cannot copy a project you ran last quarter. Nothing in `src/` or `lib/` copies a project.

Every project shares one fixed workflow whether you want that or not. The board draws one lane per task status from a hard-coded list (`src/utils/taskHelpers.js:252`, `src/views/ProjectBoard.vue:283-291`), so an organisation cannot define its own set of stages once and use it on many projects. There is no estimate scale: a task has both `storyPoints` and `estimatedDuration`, and nothing says which one a project uses. Labels are already shared: they are one app-wide list by a recorded decision (`docs/ARCHITECTURE.md:226`), and this change keeps them that way.

OpenProject and Kanboard create a project from a template. Nextcloud Deck, OpenProject, Kanboard and Zermelo copy a project. OpenProject and Jira Data Center share one scheme of statuses and fields across many projects, so one edit changes them all. Plane users ask for the same (https://github.com/makeplane/plane/issues/4706).

Parity rows: `prj-templates`, `prj-copy`, `prj-shared-workflow` in planninq's `openspec/parity/capabilities.json`.
Decision: build. Templates and copy are core project features with two and four competitors rated yes. The shared workflow is half built and a feature request plus two competitors ask for the missing half.

## What changes

- A project manager marks a project as a template. Templates are listed apart from working projects.
- "New project" offers a template picker. The new project gets the template's columns, phases and tasks, with their labels and with dates shifted to its own start date.
- A project manager copies any project and chooses what comes along: columns, tasks, phases, dependencies and people.
- An admin defines named workflows in Beheer: an ordered set of columns and an estimate scale.
- A project follows a workflow. Changing the workflow changes the columns of every project that follows it.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-templates`, `prj-copy`, `prj-shared-workflow`.

### `prj-templates`: Create a new project from a template that copies its columns, labels and tasks.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "grep across src/ and lib/ for template/duplicate/clone/copy-project finds nothing that creates a project pre-populated from an existing one's columns/labels/tasks. The manifest's 'Store' page (src/manifest.json 'Store' entry) installs shared configsets/flows from other organisations, which is a different mechanism (marketplace install), not "new project from a template"."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md Administration 'Projects > Project templates'; corpus: openproject/round4/M1-column.md 3.10 'applying a project template (app/models/project.rb:110)'; docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-templates (copies work packages, boards, wiki, members); no labels object exists (categories copy) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/projects/template_select_component.html.erb:39 template picker on new project; app/controllers/projects_controller.rb:106-115 new/create from template; app/controllers/projects/templated_controller.rb:35-41 mark a project as ... (shortened; full text in the matrix row)"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source: app/Template/project_creation/create.php:23 'Create from another project' with components chosen, app/Model/ProjectDuplicationModel.php:43-55 swimlanes, board columns, categories, roles, actions, tags, filters, tasks ; corpus: kanboard/round4/menu-tree.md 'Duplicate' ; source read at v1.2.54: app/Template/project_creation/create.php:23-40 'Create from another project' with Categories, Tags, Actions, Custom filters, Tasks checkboxes; app/Model/ProjectDuplicationModel.php:43-57 swimlanes and board columns always copied, :102-118 loop"

### `prj-copy`: Copy an existing project with its structure.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no copy/duplicate-project action anywhere in src/ or lib/ (same grep as prj-templates)."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 2.9 'card#clone and board#clone (appinfo/routes.php:31)' ; source: src/components/navigation/BoardCloneModal.vue options with cards, assignments, labels, due dates ; source read at v1.19.0: src/components/navigation/AppNavigationBoard.vue:52 'Clone board' opens src/components/navigation/BoardCloneModal.vue:9-32 'Clone cards', 'Clone assignments', 'Clone labels', 'Clone due dates', 'Move all cards to the first list'; lib/Service/BoardService.php:607 clone() and :767 cloneCards; appinfo/routes.php:31 board#clone"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Project settings > Copy project'; corpus: openproject/round4/M1-column.md 2.9 copy service carries watchers, attachments ; source read at v17.8.0: app/components/projects/row_actions_component.rb:165-166 'Copy' row action needs copy_projects; config/initializers/permissions.rb:261-263 copy_projects permission; app/controllers/projects_controller.rb:131-140 copy enqueues Projects::EnqueueCopyService with chosen dependencies; app/services/projects/copy/work_packages_dependent_service.rb:118-127 relations are copied too"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'Duplicate' ; source: app/Model/ProjectDuplicationModel.php:90 duplicate() ; source read at v1.2.54: app/Template/project/sidebar.php:52 'Duplicate'; app/Template/project_view/duplicate.php:26 Duplicate button; app/Controller/ProjectViewController.php:150 doDuplication; app/Model/ProjectDuplicationModel.php:90 duplicate"
  - Zermelo (Desktop, Portal and WebApp) (https://support.zermelo.nl/guides/roostermaker/nieuw-portal-project): "docs read 2026-09-27: https://support.zermelo.nl/guides/roostermaker/nieuw-portal-project 'Wanneer u een nieuw project aanmaakt in het portal, kunt u dat doen op basis van een bestaand project. Het portal neemt dan een aantal zaken mee'"

### `prj-shared-workflow`: Share one set of statuses, labels and estimates across many projects.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "Statuses: src/utils/taskHelpers.js:252 -> src/views/ProjectBoard.vue:283-291 on every board. Labels: src/views/settings/Settings.vue:152-198 -> src/store/labels.js:78-105 createLabel -> OpenRegister planninq/label objects; boards read them at src/views/ProjectBoard.vue:424-429 -> src/store/projects.js:806-833 fetchLabels. Shared by construction, not by choice: the statuses cannot be edited and labels cannot be scoped to some projects."
- Gap: "one global set only, statuses not configurable, no estimate scale"
- Note: "Demand row mined from plane (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/makeplane/plane/issues/4706
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/initializers/menus.rb:409-410 instance wide Statuses admin; app/models/status.rb:31-34 statuses and workflows are global; modules/backlogs/lib/open_project/backlogs/engine.rb:251 story points on every type; types and custom fields are shared across projects"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/defining-a-project-938847066.html): "https://confluence.atlassian.com/adminjiraserver/defining-a-project-938847066.html 'Sometimes you may wish to share schemes among your projects, so that editing one scheme changes that scheme in several projects at once. You can select Create with shared configuration' (read 2026-09-26)"

## Scope

### In scope

- Templates as flagged projects, the template picker, and copy with options.
- A `workflow` schema that defines a column set and an estimate scale, a `workflow` reference on the project, and a server-side sync that keeps each following project's own columns in step.

### Out of scope

- Per-project columns: `boards-configurable-columns` (lane A) makes the board render `column` objects. This change builds on it.
- Publishing templates to other organisations through the Store. The Store page already exchanges configsets (`src/manifest.json:61-71`); whether a template travels that way is an open question in design.md.
- Custom fields in templates: `projects-grouping-hierarchy-fields`.
- Labels offered per project or per workflow. Label scope is app-wide by a recorded decision (`docs/ARCHITECTURE.md:226`).

## Impact

- Schema: `project` gains `isTemplate` and `workflow`; a new `workflow` schema; `column` gains `workflowKey`. `column.project` stays required and `label` is unchanged, following the recorded decisions in `docs/ARCHITECTURE.md:148` and `:226`.
- Service and controller: a new `ProjectCopyService` behind `POST /apps/planninq/api/projects/{id}/copy`, which checks the creation policy and remaps every reference.
- Views and dialogs: `ProjectCreationDialog.vue` (template picker), a new `src/dialogs/ProjectCopyDialog.vue`, `ProjectList.vue` (Templates chip), a workflows section on the admin page.
- Listener: `WorkflowColumnSyncListener` writes the columns of every project that follows a workflow when the workflow or the project's `workflow` changes.
- Extends the flat specs `openspec/specs/projects.md` and `openspec/specs/kanban-board.md`.
- Depends on: `boards-configurable-columns` (lane A) for boards that render `column` objects. `projects-lifecycle-policy` for the creation policy the copy endpoint enforces.

## Risks

### Risk 1: a copy half done

**Severity**: High
**Mitigation**: The copy runs on the server in one request. It creates the new project with `metadata.copyState: 'copying'`, which the project list hides, writes columns, phases, tasks and dependencies, then clears the marker. A failure deletes what was written and answers 500 with the step that failed.

### Risk 2: a synced column edited by hand

**Severity**: Medium
**Mitigation**: The board hides column editing on a project that follows a workflow and says which workflow it follows. A column changed through the API anyway is put back on the next sync, and the sync logs it.

### Risk 3: a new schema changes the schema count

**Severity**: Low
**Mitigation**: Task 1.4 updates `testRegisterDeclaresExactlySevenSchemas` and the scenario in `openspec/specs/project-delivery/spec.md:68-72`, in step with any other change of this pass that adds a schema.
