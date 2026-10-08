# Design: restore archived projects, creation by group, and reviewed project requests

## Context

What exists at `de35541`:

- **Status.** `project.status` is an enum of `active`, `archived`, `completed` and `cancelled`, default `active` (`lib/Settings/planninq_register.json:455-466`). The schema has no `x-openregister-lifecycle` block, so any caller with update rights can write any status.
- **Archive.** The Danger zone tab (`src/components/ProjectSettingsSidebar.vue:129-165`) shows "Archive project" whatever the current status, with an inline confirm (`:139-156`). `doArchive` (`:378`) calls `archiveProject` (`src/store/projects.js:506-517`), which PATCHes `status: 'archived'` and drops the project from the local list.
- **List.** `ProjectList.vue` has status chips All, Active, Archived and Completed (`:220-227`) and filters client-side (`:238-249`). An archived project opens its board like any other. There is no restore action anywhere.
- **Creation policy.** `SettingsService::canCurrentUserCreateProject` (`lib/Service/SettingsService.php:158-171`) reads `allow_project_creation` (default `all`, `:62`) and knows only `admins`. `ProjectController::checkCreatePolicy` (`lib/Controller/ProjectController.php:191-200`) and `create` (`:221-278`) enforce it on the server. `ProjectList.vue:207-215` mirrors it in the browser. The admin page offers two options (`src/views/settings/Settings.vue:76-86`) and saves through `settingsStore.saveSettings` (`:510-522`).
- **Live schema patch.** `DueReminderWindowService` writes an admin setting into the live `task` schema (`lib/Service/DueReminderWindowService.php:104`). Only `SettingsService.php:272` calls it, on save.
- **Create dialog.** `src/dialogs/ProjectCreationDialog.vue` asks for title, description, colour and icon and posts through `createProject` (`projects.js:328-363`).

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- A schema can declare `x-openregister-lifecycle` with a `field`, an `initial` state and named `transitions`, each with `from`, `to` and optional `authorization`, `condition` and `message` (`lib/Service/Lifecycle/LifecycleAnnotationValidator.php:118-133`, `:200-330`). A transition's `authorization` is a list of group ids or `{role}` objects (`:491-520`).
- `POST /apps/openregister/api/objects/{id}/transition` applies a named transition, and `GET /apps/openregister/api/objects/{id}/available-actions` lists the ones the caller may run (`appinfo/routes.php:613-614`).
- Notification rules accept the triggers `created`, `updated`, `transition`, `scheduled`, `threshold` and `calculatedChange` (`lib/Service/Notification/NotificationAnnotationValidator.php:50`).

## Goals / non-goals

Goals:

- Restore is one click for a project manager, and OpenRegister refuses it for anyone else.
- An admin can name the groups that may create projects.
- Everyone else can ask, and someone who may create answers.

Non-goals:

- Read-only archived projects.
- A configurable request form.

## Decisions

### Decision 1: archive, restore, approve and reject are schema transitions

The `project` schema gets an `x-openregister-lifecycle` block on `status`:

| Action | From | To | Who |
|---|---|---|---|
| `archive` | `active`, `completed` | `archived` | owner and managers |
| `restore` | `archived`, `completed` | `active` | owner and managers |
| `approve` | `requested` | `active` | reviewers |
| `reject` | `requested` | `rejected` | reviewers |

The store calls the transition endpoint instead of PATCHing `status`, and the sidebar asks `available-actions` which buttons to show. This is ADR-031: the rule sits on the schema, and a direct API call gets the same answer as the interface.

Owner and manager are object-level rights, not groups, so `archive` and `restore` carry no transition `authorization` list and rely on the project's update rule. `approve` and `reject` carry the reviewer groups (Decision 3).

Alternative considered: a `restoreProject` store action that PATCHes `status: 'active'`, mirroring `archiveProject`. It is the smallest change, but any member with update rights could then jump a project to any status, including approving their own request.

### Decision 2: `allow_project_creation` gains `groups`

The setting takes `all`, `admins` or `groups`. With `groups`, a second key `project_creation_groups` holds a JSON list of group ids. `canCurrentUserCreateProject` returns true for admins and for members of any listed group, through `IGroupManager::isInGroup`. The admin page shows an `NcSelect` of groups, with `inputLabel`, when `groups` is chosen. The browser mirror in `ProjectList.vue` reads a server-computed `canCreateProject` flag from the settings payload instead of repeating the rule.

### Decision 3: a request is a project in status `requested`

A person who may not create a project may request one when the admin turns on `project_requests` (default off). `ProjectController::create` then stores the project with `status: 'requested'` instead of answering 403. The requester is `owner` and the only member, so they see their own request and nobody else's.

The reviewers are the people who may create projects: admins, plus the creation groups when the policy is `groups`. When the admin saves the policy, a new `ProjectPolicySchemaService` writes those groups into the live `project` schema in two places: the `authorization` list of `approve` and `reject`, and a read rule `{"group": "<id>", "match": {"status": "requested"}}` per group. It follows `DueReminderWindowService`. A repair step re-applies the patch after the register import, because the import rewrites the live schema.

On `approve`, the approving reviewer's client creates the default columns, as `createProject` does today (`projects.js:353-354`).

The request form, `src/dialogs/ProjectRequestDialog.vue`, has two steps: what the project is (title, description) and why it is needed (`requestReason`, a desired start date). The review dialog, `src/dialogs/ProjectRequestReviewDialog.vue`, shows the request and asks for a `reviewNote` on reject.

Two notification rules on `project` (trigger `transition`, actions `approve` and `reject`) tell the owner, who is the requester.

Alternative considered: a separate `projectRequest` schema that an approval copies into a new `project`. It keeps requests out of the project list, but it duplicates every project field and breaks the link between the request and the project it became.

### Decision 4: a requested project is not a working project

While `status` is `requested` or `rejected`, the board shows a banner instead of the columns: "This project is waiting for review" or "This request was not approved", with the review note. The dashboard KPIs already count `active` and `archived` by equality (`src/manifest.json:88-127`), so they do not change.

## Risks / trade-offs

- [A register import resets the live patch] -> The repair step re-applies it, and a PHPUnit test runs the import then the repair and asserts the reviewer groups are present.
- [A group listed in the policy is deleted] -> `ProjectPolicySchemaService` drops unknown groups on save, and the admin page shows a warning for a listed group that no longer exists.
- [Transition authorization and object update rules disagree] -> Task 1.2 checks live that a member without update rights gets 403 on `restore`, before the store is moved to the transition endpoint.

## Built at HEAD (29 Sep 2026, section 1)

- The lifecycle also declares `complete` (active to completed) and `cancel` (active to cancelled), and `restore` also leaves `cancelled`. OpenRegister's `LifecycleValidationListener` refuses any status write no transition allows, so without them an API client setting `completed` or `cancelled` would have been refused.
- "Owner and managers": managers do not exist yet (`projects-members-and-roles` is not built), so archive and restore follow the project's update rule: the owner and admins. The list shows Restore to them only.
- `approve` and `reject` carry `authorization: ["admin"]` in the register file; section 3 writes the reviewer groups into the live schema.
- The block passed OpenRegister's own `LifecycleAnnotationValidator` (development `4abd834`): 0 findings, control with an empty `authorization` 1.

## Built at HEAD (29 Sep 2026, section 2)

- `SettingsService` refuses an `allow_project_creation` value outside `all`, `admins`, `groups`, and on save keeps only groups that exist. For an admin the settings payload names listed groups that no longer exist (`creationGroupsMissing`), and the admin page warns about each.
- The group picker reads the groups through OCS `cloud/groups` (admin only, like the page).
- The list reads `canCreateProject` from the settings payload; an older payload without it falls back to the policy, with `groups` read as admins only.

## Built at HEAD (29 Sep 2026, section 3)

- Approving needs `update` permission on the project: OpenRegister's transition endpoint checks it before the lifecycle does, and the reviewer is not the owner. So `ProjectPolicySchemaService` writes, per reviewer group, a read AND an update rule `{"group": "<id>", "match": {"status": "requested"}}`, besides the `approve`/`reject` authorization. The requester (owner) keeps update rights on the request but cannot approve it: the transition authorization does not name them.
- The live schema is found through the planninq register's schema ids, not the slug alone, because another app may have a `project` schema.
- Reviewer groups follow the creation policy: the creation groups under `groups`, none (admins only) under `admins`. The patch runs on saving the policy and in the `ApplyProjectPolicy` repair step after the import. A settings `load` (forced re-import) does not re-run it; saving the policy once does.
- `reject` declares the input `reviewNote` (required), so the reason arrives in the transition save. `ProjectReviewListener` stamps `reviewedBy` and `reviewedAt` in the reviewing save and, after an approval, creates the default columns inside OpenRegister's system scope (the column guard keeps columns to the owner).
- `project_requests` is an admin switch (`on`/`off`, default off) shown when creation is not open to all. The list shows "Request a project" to whoever the server says may request.
- The desired start date uses the existing `startDate` property.

