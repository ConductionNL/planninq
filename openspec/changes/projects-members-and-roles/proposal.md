---
kind: code
---

# Project members, roles, group sharing and team ownership

## Why

A project owner who is not a Nextcloud admin cannot add anyone to a project. The member search in `src/components/MemberSearch.vue:124-131` calls the OCS provisioning endpoint `/ocs/v2.php/cloud/users`. That endpoint lists users only for admins and group sub-admins, so a regular owner types a colleague's name and reads "No users found" (`MemberSearch.vue:31-33`). Removing members still works. The matrix re-rated this row from yes to partial on 2026-09-26 (planninq#645).

Everyone on a project has the same rights. The `project` schema knows one `owner` and a flat `members` list of user ids (`lib/Settings/planninq_register.json:478-490`). The owner edits and deletes, every member reads and writes every task, and nobody can be read-only. You cannot give a project to a Nextcloud group either: people are added one by one. When the owner leaves, `lib/Controller/ProjectController.php:359-369` hands the project to the alphabetically first member. When the owner's account is deleted, nothing runs and the project keeps an owner nobody holds.

Nextcloud Deck, OpenProject, Plane, Kanboard and Jira Data Center let a project owner add people from the organisation, give them a role and share with a group. Deck and Kanboard keep team projects alive when a person leaves. The Deck changelog (nextcloud/deck#8320) is where the team ownership demand was mined.

Parity rows: `prj-members`, `prj-member-roles`, `prj-group-share`, `prj-team-owner` in planninq's `openspec/parity/capabilities.json`.
Decision: build. Member search is half built and the missing half blocks every non-admin owner, with five competitors shipping it. Roles, group sharing and team ownership are core to the projects area, with five, four and two competitors rated yes.

## What changes

- A project owner who is not an admin finds colleagues by name and adds them, within the limits the admin set for user search.
- The member list shows names, not user ids.
- Each member has a role: manager, member or viewer. A viewer reads the board and cannot change tasks.
- A project manager adds a whole Nextcloud group, with a role, in one step. Joining or leaving the group grants or removes access.
- A project owner hands the project to a group. Every member of that group then holds owner rights, so the project outlives any one account.
- Deleting a Nextcloud account or group removes it from every project, and an owned project passes to a remaining manager instead of pointing at nobody.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-members`, `prj-member-roles`, `prj-group-share`, `prj-team-owner`.

### `prj-members`: Add and remove project members from the organisation's user list.

- Area `projects`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/ProjectSettingsSidebar.vue:77-113 (Members tab) -> src/components/MemberSearch.vue:120-151 (OCS /ocs/v2.php/cloud/users search) + :160-173 selectUser -> src/store/projects.js:610 addMember() / :672 removeMember()"
- Note: "Re-rated yes to partial on 2026-09-26 (planninq#645): the yes was read from the admin path. src/components/MemberSearch.vue:123-124 searches through the OCS provisioning endpoint /ocs/v2.php/cloud/users, which answers only admins and group sub-admins, so a regular project owner gets an empty member search and cannot add anyone from the organisation's user list; removing members still works."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'sharing with users, groups, teams and remote users, each with edit, share and manage' ; journeys.md 1 ACL for jdevries ; source read at v1.19.0: src/components/board/SharingTabSidebar.vue:7 NcSelectUsers picker to add a user, group, team or remote user, :64 'Delete' removes the share; lib/Service/BoardService.php:419 addAcl and :541 deleteAcl, routes appinfo/routes.php:28-30"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Inside a project > Members'; corpus: openproject/round4/M1-column.md 11.19 'memberships in app/models/member.rb' ; source read at v17.8.0: config/initializers/permissions.rb:208-216 manage_members covers members index, create, destroy and autocomplete_for_member; app/controllers/members_controller.rb:42 create, :87 destroy_by_principal"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md 'Settings: General, Members, ...' ; code-census.md §5 'ProjectMember.role', ProjectMemberInvite (project.py:192) ; source read at v1.4.2: apps/web/core/components/project/member-list.tsx:78,110 'add_member' opens the invite modal, which lists workspace members not yet in the project, apps/web/core/components/project/send-project-invitation-modal.tsx:74; apps/api/plane/app/views/project/member.py:46-47 create and :290-291 destroy (admin only); apps/web/core/components/project/member-list-item.tsx:36 removeMemberFromProject"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 5 'addProjectUser(bob, project-viewer)' ; menu-tree.md 'Permissions' ; source read at v1.2.54: app/Template/project_permission/users.php:36-54 add user form with a user autocomplete and role select, :26 'Remove'; app/Controller/ProjectPermissionController.php:67 addUser, :94 removeUser"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/managing-project-role-membership-938847171.html): "corpus: jira-data-center/round4/M1-column.md 13.1 'Project roles are a flexible way to associate users and/or groups with particular projects' (Managing project roles, 2022-10-07); corpus: jira-data-center/round4/M1-column.md 13.4 Managing project role membership ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/managing-project-role-membership-938847171.html 'Select Add users to a role ... Search for the user or group you wish to add, and select the project role' (read 2026-09-26)"

### `prj-member-roles`: Give project members different roles, such as owner and member, with different rights.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "lib/Settings/planninq_register.json project schema has only `owner` (string, set once at creation) and `members` (flat array of UIDs, no per-member role). src/components/ProjectSettingsSidebar.vue:83-113 renders the member list with only a Leave/Remove action, no role picker."
- Note: "owner vs member is an implicit two-tier RBAC baked into schema authorization rules (update/delete require owner), not something a user assigns, and it cannot be changed except by leaving (ownership handoff in ProjectController.php:290-300). There is no UI or API to give a member elevated rights while staying a member."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/code-census.md 'Acl: participant of type user, group, remote or team, with edit, share, manage' lib/Db/Acl.php:27-35 ; journeys.md 5 edit-only collaborator refused 403 on ACL change ; source read at v1.19.0: src/components/board/SharingTabSidebar.vue:39-58 per share 'Can edit', 'Can share', 'Can manage', 'Owner' (transfer); lib/Db/Acl.php:31-34 PERMISSION_READ, EDIT, SHARE, MANAGE; lib/Service/PermissionService.php:131-132 owner or ACL grants manage and share"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 13.1 'app/models/role.rb:31 with nine subtypes ... Permissions are rows ... against a catalogue declared in code'; corpus: openproject/round4/M1-column.md 13.16 roles and permissions matrix ; source read at v17.8.0: app/models/role.rb:31-41 Role with builtin kinds; config/routes.rb:709 admin roles resources; config/initializers/permissions.rb:208 permissions are declared per role in code; app/controllers/members_controller.rb:71 update a member's roles"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §5 'ROLE_CHOICES = ((20, "Admin"), (15, "Member"), (5, "Guest"))' project.py:21 ; three fixed roles, no custom role ; source read at v1.4.2: apps/api/plane/db/models/project.py:21 ROLE_CHOICES Admin 20, Member 15, Guest 5 and :219 ProjectMember.role; apps/web/core/components/project/settings/member-columns.tsx:101 updateMemberRole role dropdown; apps/api/plane/app/views/project/member.py:206 partial_update checks workspace role (:210-214). Three fixed roles, no custom role"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/usability.md 'three project roles and custom ones with column restrictions' ; journeys.md 5 custom role Toetser ; source read at v1.2.54: app/Core/Security/Role.php:18-20 project-manager, project-member, project-viewer; app/ServiceProvider/AuthenticationProvider.php:76-78 role hierarchy; app/Template/project_role/show.php:5 'Add a new custom role' with :21-27 project, drag and drop and column restrictions; app/Controller/ProjectRoleController.php:50 save"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/managing-project-roles-938847166.html): "corpus: jira-data-center/round4/M1-column.md 13.1 global permissions and project roles; corpus: jira-data-center/round4/M1-column.md 2.4 'Assign issues Permission ... Assignable user Permission' (Managing project permissions) ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/managing-project-roles-938847166.html 'Project roles are a flexible way to associate users and/or groups with particular projects'; roles are used in 'permission schemes' (read 2026-09-26)"

### `prj-group-share`: Share a project with a whole group or team at once instead of person by person.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "projects only carry a flat `members` array of individual Nextcloud UIDs (lib/Settings/planninq_register.json); adding a member is one-by-one via src/components/MemberSearch.vue. No group-sharing code found anywhere in src/ or lib/."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/journeys.md 1 'then the group vergunningen with edit' ; code-census.md Acl participant type group or team ; source read at v1.19.0: src/components/board/SharingTabSidebar.vue:30-31 shares shown as '(Group)' and '(Team)'; lib/Db/Acl.php:37 PERMISSION_TYPE_GROUP and :39 PERMISSION_TYPE_CIRCLE; lib/Service/BoardService.php:419 addAcl(boardId, type, participant, ...)"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 13.6 'a Group can be given a membership, so group-based visibility works' ; source read at v17.8.0: app/models/member.rb:40 a member is any principal; app/models/group.rb:31 Group < Principal; app/services/members/create_service.rb:49-55 adding a Group as member creates inherited memberships for its users; config/initializers/menus.rb:752 project Members page"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 5 'createGroup Bouwtoezicht, addGroupMember carol, addProjectGroup(project-member): carol read the task' ; source read at v1.2.54: app/Template/project_permission/groups.php:38-48 add group form with group autocomplete, :29 remove; app/Controller/ProjectPermissionController.php:154-168 addGroup with a role"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/managing-project-role-membership-938847171.html): "corpus: jira-data-center/round4/M1-column.md 13.1 'Project roles are a flexible way to associate users and/or groups with particular projects' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/managing-project-role-membership-938847171.html 'Search for the user or group you wish to add, and select the project role you wish to add them to' (read 2026-09-26)"

### `prj-team-owner`: Let a team own a project so it stays when a person leaves.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Owner and members are user ids [lib/Settings/planninq_register.json:478-490]. No group, team or circle can own or join a project [prj-group-share]. When the owner leaves through the leave dialog, ownership passes to the alphabetically first remaining member (lib/Controller/ProjectController.php:288-290), but a deleted account leaves the project with an owner nobody holds."
- Note: "Demand row mined from nextcloud-deck (changelog) on 2026-09-26."
- Demand (changelog, via origin): https://github.com/nextcloud/deck/pull/8320
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/locales/en.yml:1915 'Add member' accepts groups, :3344 'Groups'; app/models/member.rb:40 a member is any principal, user or group; a project belongs to no person, so it stays when one leaves"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source read at v1.2.54: app/Template/project_permission/index.php:13 group permissions; app/Controller/ProjectPermissionController.php:154 addGroup; app/Model/UserModel.php:423-432 removing a user deletes only private projects, team projects stay"

## Scope

### In scope

- User and group search that works for every signed-in user.
- Three roles (manager, member, viewer) for users and for groups, declared as OpenRegister authorization on the project and on the project-scoped schemas.
- An owning group on the project.
- Clean-up when a Nextcloud user or group is deleted.
- The Members tab of the project settings sidebar, and the client-side access checks that read `members` today.

### Out of scope

- Nextcloud Teams (Circles) as principals. OpenRegister resolves `$user.groups` to Nextcloud groups only, so a Team would need its own resolver first. This is named in design.md as a follow-up.
- Custom roles with per-column restrictions (Kanboard). Three fixed roles match Plane and FEATURES.md.
- A notification when someone is added to a project. That is a separate row (notification toggles in FEATURES.md) and is not in this change.
- Public links for people outside the organisation: `projects-public-share`.

## Impact

- Schema: `project` gains `managers`, `viewers`, `managerGroups`, `memberGroups`, `viewerGroups` and `ownerGroup`, with property-level update rules on `owner` and `ownerGroup`. The authorization blocks of `project`, `task`, `column` and `projectPhase` read the new lists; `plannedTimeEntry` reads them on read only.
- Component: `MemberSearch.vue` (new endpoint, users and groups), `ProjectSettingsSidebar.vue` (role picker, group rows, owning group).
- Store: `src/store/projects.js` (`addMember`, `removeMember`, a new `setMemberRole`, and the member filter in `fetchProjects` and `applyLiveProjects`), plus a shared role helper used by `ProjectBoard.vue` and `ProjectList.vue`.
- Controller: `ProjectController::leaveProject` removes the caller from every role list.
- Listener: a new listener for `UserDeletedEvent` and `GroupDeletedEvent`.
- Initial state: the current user's group ids, so the client can tell a group member from an outsider.
- Extends the flat spec `openspec/specs/projects.md` (requirement "Member Management").
- Depends on: none of the other changes in this pass.

## Risks

### Risk 1: task rights may not follow the project today

**Severity**: High
**Mitigation**: The `task` authorization rules (`planninq_register.json:51-143`) match through a `$lookup` operator. I found no `$lookup` operator in OpenRegister's RBAC code at development `c53dd0685`. Task 1.1 checks live what that rule does before any role rule is copied into it, and the result decides whether the task rules move to a denormalised field.

### Risk 2: a stale client filter hides shared projects

**Severity**: Medium
**Mitigation**: `fetchProjects` and `ProjectBoard.vue` filter on `members.includes(uid)`. A group or viewer grant would be allowed by the server and hidden by the client. One role helper replaces all three checks, with a vitest spec per role.

### Risk 3: a manager edits away the owner

**Severity**: Medium
**Mitigation**: Property-level update rules on `owner` and `ownerGroup` restrict them to the owner and the owning group, so a manager's PATCH cannot rewrite them.
