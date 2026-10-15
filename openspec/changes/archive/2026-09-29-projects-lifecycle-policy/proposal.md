---
kind: code
---

# Restore archived projects, creation by group, and reviewed project requests

## Why

A project manager can archive a project but cannot bring it back. The Danger zone tab has an "Archive project" button (`src/components/ProjectSettingsSidebar.vue:139-156`) that calls `archiveProject` (`src/store/projects.js:506-517`), which PATCHes `status: 'archived'`. Nothing in `src/` sets a project back to `active`: no button, no store action. An archived project stays archived unless someone edits the object through the OpenRegister API.

An admin can let everyone create projects or only admins. `SettingsService::canCurrentUserCreateProject` (`lib/Service/SettingsService.php:158-171`) knows the values `all` and `admins`, and the admin page offers exactly those two (`src/views/settings/Settings.vue:76-86`). FEATURES.md section 3.1 already names a third value, `groups`, and `openspec/specs/admin-user-settings.md:24` lists it too. It was never built.

There is no way to ask for a project. `ProjectCreationDialog.vue` posts straight to `POST /apps/planninq/api/projects`, and `ProjectController::create` (`lib/Controller/ProjectController.php:221-278`) stores the project at once. When creation is restricted, everyone else sees "Ask an administrator to create one" (`src/views/ProjectList.vue:64`) and has to do that outside the app.

Nextcloud Deck, OpenProject, Plane, Kanboard and Jira Data Center archive and restore projects. Deck, OpenProject and Kanboard limit creation to chosen groups or a global role. The project request row was mined from the OpenProject 17.1 release notes.

Parity rows: `prj-archive`, `prj-create-policy`, `prj-request-approval` in planninq's `openspec/parity/capabilities.json`.
Decision: build. Archive and the creation policy are half built and the missing halves matter, with five and three competitors rated yes. Project requests sit in the core projects area.

## What changes

- A project manager restores an archived project from the project settings sidebar or from the Archived filter of the project list.
- Archive and restore become declared transitions on the `project` schema, so OpenRegister refuses them for anyone without the right.
- An admin limits project creation to one or more Nextcloud groups.
- When creation is limited, everyone else sees "Request a project" and fills in a short guided form.
- People who may create projects review requests, approve them into active projects or reject them with a reason, and the requester is told either way.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-archive`, `prj-create-policy`, `prj-request-approval`.

### `prj-archive`: Archive a finished project and restore it later.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/ProjectSettingsSidebar.vue:149 (Archive project button, Danger zone tab) -> src/store/projects.js:506 archiveProject() (PATCH status='archived')"
- Defect: "src/components/ProjectSettingsSidebar.vue: Danger zone tab has no restore/unarchive action for an already-archived project"
- Note: "archiving works and removes the project from the active list. There is no restore/unarchive action anywhere in src/ (no button, no store action), an archived project can only be reopened by filtering the list to Archived and there is nothing there to bring it back to active from the UI."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'All boards ... (with Archived boards ...)' ; source: lib/Controller/BoardController.php:54 update(..., bool archived) ; source read at v1.19.0: src/components/navigation/AppNavigationBoard.vue:62-68 'Archive board' and :54-60 'Unarchive board' actions calling archiveBoard/unarchiveBoard (:364-370); lib/Controller/BoardController.php:54 update(id, title, color, archived)"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-settings/project-information/README.md:42 'More actions menu ... make it public, set it as a template, archive it, or delete it'; project-lists README:127 'Archived projects - returns all projects that are not active' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/projects/row_actions_component.rb:141-148 'Archive' item needs archive_project; :153-161 'Unarchive' item (admin only); app/controllers/projects/archive_controller.rb:38-43 create archives, destroy unarchives; config/routes.rb:447"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md workspace 'Archives' ; source: apps/api/plane/db/models/project.py:114 archived_at, apps/api/plane/app/urls/project.py:123-125 ProjectArchiveUnarchiveEndpoint (archive and unarchive) ; source read at v1.4.2: apps/web/core/components/project/archive-restore-modal.tsx:32,44,67 archiveProject and restoreProject; apps/api/plane/app/urls/project.py:123-125 archive route; apps/api/plane/app/views/project/base.py:427-437 ProjectArchiveUnarchiveEndpoint post and delete; apps/api/plane/db/models/project.py:114 archived_at"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'Close this project' ; source: app/Template/project_list/project_icons.php:20 is_active 0 shown as closed, reopen from the same menu ; source read at v1.2.54: app/Template/project/sidebar.php:59 'Close this project', :62 'Open this project'; app/Controller/ProjectStatusController.php:28 enable, :57 disable; app/Model/ProjectModel.php:539 enable, :555 disable; app/Template/project_list/project_icons.php:20 closed marker"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/archiving-a-project-938847621.html): "corpus: jira-data-center/round4/open-core.md 'archiving of issues and projects, with restore' citing Archiving a project (2025-12-11); Data Center only ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/archiving-a-project-938847621.html 'You can archive any project, and restore it later, if needed'; needs 'the Jira Administrator or Jira System Administrator global permission' (read 2026-09-26)"

### `prj-create-policy`: Restrict who may create projects to admins or chosen groups.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectList.vue:207-215 canCreateProject() -> lib/Service/SettingsService.php:158 canCurrentUserCreateProject() reads app config 'allow_project_creation' (default 'all', line 62) -> lib/Controller/ProjectController.php:191 checkCreatePolicy()"
- Note: "only two policy values exist: 'all' or 'admins' (lib/Service/SettingsService.php:158-169). There is no group-based restriction anywhere in the code, so "admins or chosen groups" is only half true."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'Deck settings ... group limit for board creation (admin)' ; source: lib/Service/PermissionService.php:345-363 canCreate reads groupLimit ; source read at v1.19.0: src/components/DeckAppSettings.vue:26-37 admin section 'Limit board creation to some groups' (multi-select of groups); lib/Service/ConfigService.php:209 stores groupLimit; lib/Service/PermissionService.php:368-383 canCreate allows only members of the listed groups"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md Administration 'Roles and permissions' with global roles; corpus: openproject/round4/M1-column.md 13.1 'nine subtypes ... spanning project roles, global roles (app/models/global_role.rb)'; add_project is a global permission (corpus: openproject/round4/M1-column.md 13.8 'the select_project_custom_fields and add_project permissions') ; source read at v17.8.0: config/initializers/permissions.rb:34-39 add_project is a global permission; app/contracts/projects/create_contract.rb:67 create checks user.allowed_globally?(:add_project); app/models/global_role.rb:31 global roles; app/controllers/groups_controller.rb:184 a group has a 'global_roles' tab, so a ... (shortened; full text in the matrix row)"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'New project · New personal project' ; source: app/ServiceProvider/AuthenticationProvider.php:168 ProjectCreationController create needs Role::APP_MANAGER; app/Controller/ProjectCreationController.php:145 disable_private_project switch ; source read at v1.2.54: app/ServiceProvider/AuthenticationProvider.php:168 ProjectCreationController create needs Role::APP_MANAGER; app/Template/config/project.php:21 'Disable personal projects' admin setting; app/Controller/ProjectCreationController.php:145 refuses personal projects when disabled; restriction is by application role, not by chosen group"

### `prj-request-approval`: Request a new project through a guided form that someone reviews before it starts.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Create path: src/dialogs/ProjectCreationDialog.vue:65-75 submit -> src/store/projects.js:328 createProject -> POST /apps/planninq/api/projects -> lib/Controller/ProjectController.php create, which stores the project immediately. No request status, no reviewer and no guided intake form. The creation policy [prj-create-policy] restricts WHO may create, it does not route a request to someone."
- Note: "Demand row mined from openproject (changelog) on 2026-09-26."
- Demand (changelog, via origin): https://www.openproject.org/docs/release-notes/17-1-0/
- Competitors rated yes: none.

## Scope

### In scope

- Restore for archived projects, and archive and restore as schema transitions.
- The `groups` value for `allow_project_creation` and the group picker on the admin page.
- A request status on the project, a guided request dialog, a review list, approve and reject.

### Out of scope

- Making an archived project read-only for its members. Archiving hides it from the active list today, and this change keeps that behaviour.
- Capping the number of projects per user (`max_projects_per_user`, Enterprise in FEATURES.md).
- A configurable intake form. The request form has fixed fields in this change.
- Templates offered in the request form: `projects-templates-shared-workflow`.

## Impact

- Schema: `project` gains the statuses `requested` and `rejected`, the fields `requestReason`, `reviewedBy`, `reviewedAt` and `reviewNote`, an `x-openregister-lifecycle` block, and notification rules for approve and reject.
- Service: `SettingsService::canCurrentUserCreateProject` learns `groups`; a new `ProjectPolicySchemaService` writes the reviewer groups into the live schema, following `DueReminderWindowService`.
- Controller: `ProjectController::create` stores a request instead of refusing when the caller may only request.
- Views and dialogs: `ProjectSettingsSidebar.vue`, `ProjectList.vue`, `Settings.vue`, a new `src/dialogs/ProjectRequestDialog.vue` and `src/dialogs/ProjectRequestReviewDialog.vue`.
- Store: `src/store/projects.js` gains `restoreProject`, `requestProject`, `approveRequest` and `rejectRequest`.
- Extends the flat specs `openspec/specs/projects.md` (requirement "Project Lifecycle") and `openspec/specs/admin-user-settings.md`.
- Depends on: `projects-members-and-roles` for the manager role that may archive and restore. Until it lands, the owner holds that right.

## Risks

### Risk 1: reviewer rights come from an admin setting

**Severity**: Medium
**Mitigation**: The transition `authorization` list and the read rule for requested projects are written into the live schema when the admin saves the policy, the same way `DueReminderWindowService` writes the reminder window. A register import rewrites the live schema, and today the reminder window is patched only on save (`lib/Service/SettingsService.php:272`). This change re-applies its own patch in a repair step that runs after the import, and a PHPUnit test asserts both paths.

### Risk 2: new statuses surprise existing readers

**Severity**: Medium
**Mitigation**: The dashboard KPIs filter on `status` equality (`src/manifest.json:88-127`), so `requested` and `rejected` do not count as active or archived. `fetchProjects` shows requested projects only to their requester and to reviewers, and the list gets a "Requested" chip.

### Risk 3: the register schema count

**Severity**: Low
**Mitigation**: This change adds no schema. It changes `project` only, so `testRegisterDeclaresExactlySevenSchemas` is unaffected.
