# Design: project members, roles, group sharing and team ownership

## Context

What exists at `de35541`:

- **Schema.** `project` has `owner` (`lib/Settings/planninq_register.json:478`) and `members`, an array of user ids (`:483-490`). Its authorization (`:404-443`) grants read to anyone whose uid is in `members`, create to `admin` only, and update and delete to `owner = $userId` or `admin`. `task` (`:51-143`), `projectPhase` (`:596`) and `column` (`:767`) grant every verb through a rule that looks the project up by `members`. `plannedTimeEntry` (`:918`) grants read the same way and keeps every write with the entry's own `user`. `dependency` (`:1118`) is written only through `DependencyController`.
- **Create.** `ProjectController::create` (`lib/Controller/ProjectController.php:221-278`) sets `owner` to the caller and puts the caller in `members` (`:249-250`), then saves with `_rbac: false`.
- **Search.** `MemberSearch.vue:120-151` fetches `generateUrl('/ocs/v2.php/cloud/users')` with the search term. For a non-admin, non-sub-admin caller that endpoint returns no users, and the component shows "No users found" (`:31-33`). `selectUser` (`:160-173`) calls the store.
- **Store.** `addMember` (`src/store/projects.js:610-623`) and `removeMember` (`:672-687`) read the project and PATCH `members` through OpenRegister (`patchProject`, `:424-456`). `leaveProject` (`:708-739`) posts to `/api/projects/{id}/leave`.
- **Leave.** `ProjectController::leaveProject` (`:308-396`) checks the caller is in `members`, refuses to remove the last member, and hands ownership to the alphabetically first remaining member when the owner leaves (`:359-369`).
- **Sidebar.** The Members tab (`src/components/ProjectSettingsSidebar.vue:67-127`) mounts `MemberSearch` and lists each uid with a Leave or Remove button. It prints the raw uid (`:92`). There is no role picker.
- **Client access checks.** `fetchProjects` keeps only projects whose `members` include the caller (`projects.js:194-197`), and so does `applyLiveProjects` (`:222-229`). `ProjectBoard.vue:356-366` shows "You do not have access to this project" when `members` does not include the caller.
- **Account deletion.** Nothing in `lib/Listener/` listens for a deleted user or group.

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- `$contains` with an array operand matches when any one value is in the column (`lib/Db/MagicMapper/MagicRbacHandler.php:2196-2240`). `$user.groups` resolves to the caller's group ids (`:951-962`). So `{"memberGroups": {"$contains": "$user.groups"}}` is a valid rule.
- `ConditionMatcher::resolveDynamicValue` recurses into operator arrays (`lib/Service/ConditionMatcher.php:297-306`), so `{"ownerGroup": {"$in": "$user.groups"}}` resolves too.
- A property can carry its own `authorization.update` rules (`lib/Service/PropertyRbacHandler.php:122`, `canUpdateProperty`).
- I found no `$lookup` operator in `MagicRbacHandler.php`, `ConditionMatcher.php` or `OperatorEvaluator.php` at that sha.

## Goals / non-goals

Goals:

- Every signed-in owner can find and add colleagues and groups.
- Three roles with different rights, enforced by OpenRegister, not by the browser.
- A project can belong to a group and survive any one person.
- No project points at a deleted account.

Non-goals:

- Nextcloud Teams (Circles) as principals.
- Custom roles.
- Changing what "owner" means for existing projects that never set a group.

## Decisions

### Decision 1: search users and groups through the core autocomplete endpoint

`MemberSearch` calls `GET /ocs/v2.php/core/autocomplete/get` through `generateOcsUrl`, with `shareTypes[]=0` (user) and `shareTypes[]=1` (group). This endpoint answers every signed-in user and applies the admin's sharing settings: when the admin limits autocomplete to the user's own groups, a project owner sees only those people. The picker is an `NcSelect` with `inputLabel` (hydra gate for `NcSelect` labels), showing avatar and display name, and a group icon for groups.

Alternatives considered: the files sharing `sharees` endpoint, which Deck uses. It needs the files sharing app and an item type that does not fit a project. A planninq search controller over `IUserManager::search` was rejected because it would ignore the admin's enumeration limits and would be a thin wrapper.

### Decision 2: one list per role, for users and for groups

The project keeps `members` with its current meaning: people who read and write tasks. It gains `managers` and `viewers` for users, and `managerGroups`, `memberGroups` and `viewerGroups` for Nextcloud group ids. The owner is always a manager. When a person appears in more than one list, the highest role wins.

| Role | Project settings and members | Tasks, columns, phases | Read |
|---|---|---|---|
| Owner | edit, delete | write | yes |
| Manager | edit, manage members, archive | write | yes |
| Member | none | write | yes |
| Viewer | none | none | yes |

Keeping `members` means every existing reader keeps working: the dashboard KPI "Projects I am in", the procest bridge and the demo data.

Alternative considered: one `sharedWith` list of `{type, id, role}` entries with derived scalar lists, the pattern OpenRegister uses for credentials (`lib/Service/Sharing/SharePrincipalDeriver.php:47`). The derivation runs only inside OpenRegister's credential controller, not on a generic object write, so planninq would need its own write path to keep the derived lists in step. The deriver's own docblock calls a stale derived list an access-control bug. Separate lists have nothing to derive.

### Decision 3: rights live on the schemas, not in the browser

The `project` read rule gains one entry per list: `managers`, `members` and `viewers` contain `$userId`, and the three group lists contain `$user.groups`. Update adds `managers` and `managerGroups`. Delete stays with the owner and adds the owning group. `task`, `projectPhase` and `column` get the same split: read for all six lists, write for managers and members only. `plannedTimeEntry` gains the six lists on read only; writing time stays with the person who booked it, as ADR-001 rule 4 requires. The membership guard in `DependencyService` (`lib/Service/DependencyService.php:365`) reads `members` only; it moves to a PHP role helper with the same rules as the schemas.

`owner` and `ownerGroup` carry property-level update rules that admit only the owner, the owning group and admins. A manager can change the member lists but cannot rewrite who owns the project.

The browser uses one helper, `projectRole(project, uid, groupIds)`, in `fetchProjects`, `applyLiveProjects`, `ProjectBoard.vue` and `ProjectList.vue`. It decides what to show (a read-only board for a viewer, no Members tab for a member). The server still decides what is allowed. The caller's group ids come from `IInitialState::provideInitialState`, read with `loadState`, following the hydra initial-state rule.

### Decision 4: a group can own a project

`ownerGroup` is a nullable Nextcloud group id. Every member of that group holds owner rights through `{"ownerGroup": {"$in": "$user.groups"}}`. The `owner` field stays as the person who created the project. Only the owner or the owning group sets `ownerGroup`, from the Members tab.

Alternative considered: letting a manager group double as owner. That would give every manager group the delete right, which is more than a manager should hold.

### Decision 5: deleted users and groups are cleaned up by a listener

A new `ProjectPrincipalCleanupListener` handles `OCP\User\Events\UserDeletedEvent` and `OCP\Group\Events\GroupDeletedEvent`. It finds the projects that list the uid or group id and removes it from every list. When the deleted user was the owner and the project has no `ownerGroup`, ownership passes to the first manager, else to the first member in alphabetical order, the same rule `leaveProject` uses (`ProjectController.php:359-364`). It writes with `_rbac: false`, as `leaveProject` does, because no user session exists at that moment.

`leaveProject` removes the caller from every user list, not only `members`, and keeps its last-member guard.

## Risks / trade-offs

- [The `$lookup` rule on `task` may not evaluate as intended] -> Task 1.1 checks live, as a non-admin member, whether task reads and writes are scoped to the project. If `$lookup` is not evaluated, the project-scoped schemas move to a denormalised list on each task, kept in step by the same listener, and design.md is updated before any other task starts.
- [Six read rules per project-scoped schema cost query time] -> Each rule is an indexed JSON containment. Task 2.4 measures a board with 200 tasks before and after.
- [A group grant widens access when the admin adds someone to the group] -> This is the expected behaviour and matches Deck and OpenProject. The Members tab shows the group as a group, with its own icon and the words "everyone in this group", so the manager can see what the grant means.
- [Autocomplete limits hide people the owner expected to find] -> The empty state explains that the admin's sharing settings decide who is listed.

## Open questions

- Should Nextcloud Teams (Circles) be principals too? It needs a `$user.teams` token in OpenRegister first. Proposed as a follow-up change in openregister.
