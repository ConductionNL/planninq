# Tasks: projects-members-and-roles

## 1. Check what the task rules do today

- [ ] 1.1 As a non-admin project member, read, update and delete a task of your own project and of a project you are not on, through `/apps/openregister/api/objects/planninq/task`. Record whether the `$lookup` rule at `lib/Settings/planninq_register.json:51-143` scopes the answer. If it does not, update design.md (Decision 3) with a denormalised list on each task before starting section 3. Verify: the four answers written into design.md under "Risks / trade-offs", with the OpenRegister sha they were read on. Answered by planninq#681: OpenRegister does not evaluate `$lookup`, so `task`, `column`, `projectPhase` and `plannedTimeEntry` now match their own denormalised `members` list with `{"members": {"$contains": "$userId"}}`.

## 2. Schema and authorization (MVP to Enterprise)

- [x] 2.1 Add `managers`, `viewers`, `managerGroups`, `memberGroups`, `viewerGroups` (arrays of strings, default `[]`) and `ownerGroups` (a list of at most one group; design amendment) to the `project` schema, and bump its version. Verify: `tests/unit/Settings/ProjectRolesSchemaTest.php` asserts each property and its default, and validates a project with every list against the real schema.
- [x] 2.2 Extend the `project` read, update and delete rules as in design.md Decision 3, and add property-level update rules to `owner` and `ownerGroups`. Verify: `tests/unit/Settings/ProjectRolesSchemaTest.php` testTheProjectRulesPerRole and testOnlyTheOwnerOrTheOwningGroupRewritesOwnership.
- [ ] 2.3 Give `task`, `projectPhase` and `column` read rules for all six lists and write rules for managers and members, and `plannedTimeEntry` read rules for all six lists. Verify: PHPUnit cases in `PlanninqRegisterSchemaTest.php`, plus a live check as a viewer that a task PUT answers 403. Built 2 Oct (lane 24) on every project-scoped schema through copied lists (design amendment): `ProjectRolesSchemaTest::testScopedSchemasReadForEveryRoleAndWriteForMembers`, `ProjectMembershipSyncListenerTest::testRolesAndGroupsReachEveryChild`, `ProjectMemberAccessListenerTest::testAGroupMemberWritesAndAViewerIsRefused`. Open: the live check as a viewer (no instance in the lane).
- [ ] 2.4 Measure the board load for a project with 200 tasks before and after 2.3. Verify: both timings recorded in the PR body; the second is within 20 percent of the first.

## 3. Search and member list (MVP)

- [x] 3.1 Replace the OCS provisioning call in `src/components/MemberSearch.vue` with `/ocs/v2.php/core/autocomplete/get` through `generateOcsUrl`, for users and groups, rendered in an `NcSelect` with `inputLabel`. Verify: `tests/vitest/memberSearch.spec.js` mocks the endpoint and asserts the request parameters and the rendered options.
- [ ] 3.2 Show display names and group names in the Members tab of `ProjectSettingsSidebar.vue` instead of raw ids. Verify: `tests/e2e/project-members.spec.ts` "a regular owner adds a colleague by name", run as a non-admin user, and "the admin's search limits apply" with `shareapi_restrict_user_enumeration_to_group` set. Built 2 Oct (lane 25): the Members tab lists people by display name and groups by name with "Everyone in this group" (`memberEntries`, `groupName`, covered in `tests/vitest/memberSearch.spec.js`); a group picked in the search joins `memberGroups`. Open: the two e2e cases are written in `tests/e2e/project-members.spec.ts` and have not run yet (no instance in the lane).

## 4. Roles in the interface (Enterprise)

- [x] 4.1 Add a role helper `projectRole(project, uid, groupIds)` in `src/utils/` and provide the caller's group ids through `IInitialState`. Verify: `tests/vitest/projectRole.spec.js` covers owner, manager, member, viewer, each group list and the highest-role rule.
- [ ] 4.2 Use the helper in `fetchProjects` and `applyLiveProjects` (`src/store/projects.js:194-197`, `:222-229`), in `ProjectBoard.vue:356-366` and in `ProjectList.vue`. Verify: `tests/vitest/projectRole.spec.js` asserts a group-shared project survives the store filter; e2e "a group member sees a project shared with the group". Built 2 Oct (lane 25): `canSeeProject` and `isReadOnlyFor` delegate to `projectRole`; the board's access check and `ProjectList` restore use it (`tests/vitest/projectRole.spec.js` "the store and the board use the role"). Open: the e2e case in `tests/e2e/project-roles.spec.ts` is written and has not run yet (no instance in the lane).
- [ ] 4.3 Add a role picker per row on the Members tab for managers, hide member management from members and viewers, and render the board read-only for viewers. Verify: e2e "a viewer reads the board and cannot change it" and "a member cannot manage members". Built 2 Oct (lane 25): role picker per row for the owner and managers, management hidden with a note for members and viewers, the board read-only for viewers. Open: both e2e cases are written in `tests/e2e/project-roles.spec.ts` and have not run yet.
- [x] 4.4 Add `setMemberRole` to `src/store/projects.js`, writing only the affected lists through `patchProject`. Verify: vitest on the store action with a mocked PATCH body.

## 5. Groups and ownership (V1)

- [ ] 5.1 Let a manager add a group with a role, and let the owner or owning group set "Owned by group". Verify: e2e "a colleague in the owning group manages the project after the creator leaves".
- [x] 5.2 Make `ProjectController::leaveProject` remove the caller from every user list and prefer a manager when handing over ownership. Verify: new cases in `tests/unit/Controller/ProjectControllerTest.php`.
- [ ] 5.3 Add `ProjectPrincipalCleanupListener` for `UserDeletedEvent` and `GroupDeletedEvent`, registered in `Application.php`. Verify: `tests/unit/Listener/ProjectPrincipalCleanupListenerTest.php` constructs the real Nextcloud event classes and covers the owner handover; one live account deletion on the dev instance before hand-back. Built 2 Oct (lane 27): the listener takes a deleted account off `managers`, `members` and `viewers` (a deleted owner hands over to the first manager, else the first member, through `ProjectRoles::withoutUser`) and a deleted group off every group list (`ProjectRoles::withoutGroup`), writing as the system and not silent so the sync listener copies the lists to the work. `ProjectPrincipalCleanupListenerTest` covers both events, validates every saved project against the register schema, and asserts the wiring from `Application::register()`. Open: the live account deletion (no instance in the lane).
- [x] 5.4 Move the membership guard in `lib/Service/DependencyService.php:365` to a PHP role helper with the schema's rules. Verify: `tests/unit/Service/DependencyServiceTest.php` cases for a viewer (refused) and a group member (allowed).

## 6. Verification

- [ ] 6.1 `openspec validate projects-members-and-roles --type change --strict` passes.
- [ ] 6.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
