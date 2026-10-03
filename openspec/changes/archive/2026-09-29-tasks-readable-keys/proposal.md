---
kind: code
---

# Give projects a short key and tasks a readable key like ABC-123

## Why

The register already promises readable keys. The project schema has `key`, "short unique project
key used as the prefix of task keys (e.g. PLX)" (`lib/Settings/planninq_register.json:524`), and
the task schema has `key`, "human-readable unique work-item key (e.g. PLX-123)" (`:330`). No code
produces or shows either. The New project dialog asks for title, description, colour and icon
and has no key field (`src/dialogs/ProjectCreationDialog.vue:17-60`), and a task on the card or
the task page has no key.

Teams talk about work by its key: in a stand-up, in a commit message, in an email to the
applicant. OpenProject shipped readable keys in 17.5, and Kanboard users asked for them
(https://github.com/orgs/kanboard/discussions/5544). The finance import in this pass
(`portfolio-finance`) also needs the project key as the project number to match on.

Parity rows: `tsk-readable-key`, `prj-create` in planninq's `openspec/parity/capabilities.json`.
Decision: build. `tsk-readable-key` has three competitors yes; `prj-create` is rated partial
(title and description work, the key does not) with four competitors yes on the missing half.

## What changes

- The New project dialog asks for a short key, suggested from the title and checked for
  uniqueness.
- Every new task in a project with a key gets the next number, such as VERG-42, and keeps it.
- Keys show on the card, on the task page and in the page title, and search matches them.
- Setting a key on an existing project numbers its existing tasks once, oldest first.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-readable-key`, `prj-create`.

### `tsk-readable-key`: Give each task a readable key made of the project key and a number, such as ABC-123.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Schema only: key on task and project, both described as import-preserved values. No key generation, no key on src/components/TaskCard.vue:1-68 or src/views/TaskDetail.vue:262-270, no key field on project create."
- Note: "Demand row mined from openproject (changelog) on 2026-09-26. Also asked for by kanboard: https://github.com/orgs/kanboard/discussions/5544 (featureRequest)."
- Demand (changelog, via origin): https://www.openproject.org/docs/release-notes/17-5-0/
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: app/models/work_package/semantic_identifier.rb:37-42 'PROJ-42' identifiers accepted in routes; config/constants/settings/definition.rb:1411-1418 admin setting work_packages_identifier, classic by default, semantic on request"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source read at v1.4.2: apps/web/core/components/issues/issue-detail/relation-select.tsx:136 shows identifier-sequence such as ABC-12; apps/api/plane/db/models/issue.py:199 per project sequence_id; apps/api/plane/app/urls/issue.py:282 lookup by project identifier and number"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/creating-a-project-938846813.html): "https://confluence.atlassian.com/adminjiraserver/creating-a-project-938846813.html 'The project key becomes the first part of that project's issue keys, e.g. DDT-1, DDT-2' (read 2026-09-26)"

### `prj-create`: Create a project with a name, a short key and a description.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/dialogs/ProjectCreationDialog.vue:113-118 (form: title, description, color, icon; no key field) -> src/store/projects.js:328 createProject() -> lib/Controller/ProjectController.php:221 create()"
- Defect: "src/dialogs/ProjectCreationDialog.vue:113-118 no field for the schema's `key` property"
- Note: "title and description work end to end. lib/Settings/planninq_register.json declares a project `key` property ("short unique project key") but no form anywhere exposes it, so a user cannot set a short key at create time."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Projects' global module and 'Project settings > Information'; corpus: openproject/round4/M1-column.md 11.23 project attributes; docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-settings/project-information/README.md (name, identifier, description) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/projects/new_component.html.erb:32 project create form; app/forms/projects/settings/editable_identifier_form.rb:36 'identifier' field (the short key); app/forms/projects/settings/description_form.rb:35 description field; app/controllers/projects_controller.rb:113 create; ... (shortened; full text in the matrix row)"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/journeys.md 'Create a workspace, create a project, create a work item' ; code-census.md §2 'project.py:76 identifier = models.CharField(max_length=12)', unique per workspace via ProjectIdentifier ; source: apps/web/core/components/project/card.tsx:285 renders project.description ; source read at v1.4.2: apps/web/core/components/project/create/common-attributes.tsx:89 identifier field (derived from name, max 10) and :132 description field in the create modal; apps/api/plane/app/views/project/base.py:257-258 create; apps/api/plane/db/models/project.py:70-76 name, description, identifier CharField(max_length=12)"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 1 'createProject Vergunningen with identifier VERG' ; source: app/Model/ProjectModel.php:129 getByIdentifier, project description on the Edit project form ; source read at v1.2.54: app/Template/project_creation/create.php:13-15 'Identifier' field on the new project form; app/Template/project_edit/show.php:27-28 'Description' editor; app/Controller/ProjectCreationController.php:56 save; app/Model/ProjectModel.php:123 getByIdentifier"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/creating-a-project-938846813.html): "docs: https://confluence.atlassian.com/adminjiraserver/creating-a-project-938846813.html 'you must provide: a name ... a key ... and designate a project lead', description added after creation; corpus: jira-data-center/round4/M1-column.md 2.1 'Jira issue keys ... are of the format <project key>-<issue number>' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/creating-a-project-938846813.html 'When creating a project, yo need to give it a name, a key, and add a project lead'; the description is set under project details after creation (read 2026-09-26)"

## Scope

### In scope

- Key field and validation in `ProjectCreationDialog.vue` and `ProjectController::create`.
- Key display and first-time setting in the project settings sidebar.
- Server-side task numbering on create, safe under concurrent creates.
- A one-time background numbering of existing tasks when a project first gets a key.

### Out of scope

- Renaming a key after tasks carry it. Old keys stay on their tasks; the sidebar shows the key
  read-only once numbered.
- Key-based URLs such as /tasks/VERG-42.

## Impact

- `src/dialogs/ProjectCreationDialog.vue`, `src/components/ProjectSettingsSidebar.vue`,
  `lib/Controller/ProjectController.php`, a new listener on task creation, a background job,
  `lib/Settings/planninq_register.json` (project `nextTaskNumber`), `src/components/TaskCard.vue`,
  `src/views/TaskDetail.vue`, `l10n/`.
- Depends on: `tasks-create-edit-delete` for tasks to be created at all.

## Risks

### Risk 1: Two tasks get the same number
**Severity**: High
**Mitigation**: the number is assigned in a pre-create listener that holds a per-project lock
(`OCP\Lock\ILockingProvider`) while it reads and bumps the project's counter. A PHPUnit test runs
two creates against the same counter.

### Risk 2: An imported key is overwritten
**Severity**: Medium
**Mitigation**: the listener only assigns a key when the incoming task has none.
