# Tasks: projects-members-and-roles

## 1. Check what the task rules do today

- [ ] 1.1 As a non-admin project member, read, update and delete a task of your own project and of a project you are not on, through `/apps/openregister/api/objects/planninq/task`. Record whether the `$lookup` rule at `lib/Settings/planninq_register.json:51-143` scopes the answer. If it does not, update design.md (Decision 3) with a denormalised list on each task before starting section 3. Verify: the four answers written into design.md under "Risks / trade-offs", with the OpenRegister sha they were read on.

## 2. Schema and authorization (MVP to Enterprise)

- [ ] 2.1 Add `managers`, `viewers`, `managerGroups`, `memberGroups`, `viewerGroups` (arrays of strings, default `[]`) and `ownerGroup` (nullable string) to the `project` schema, and bump its version. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts each property and its default.
- [ ] 2.2 Extend the `project` read, update and delete rules as in design.md Decision 3, and add property-level update rules to `owner` and `ownerGroup`. Verify: new PHPUnit cases in `PlanninqRegisterSchemaTest.php` next to `testProjectUpdateAuthorizationEnforcesOwner`, one per role.
- [ ] 2.3 Give `task`, `projectPhase` and `column` read rules for all six lists and write rules for managers and members, and `plannedTimeEntry` read rules for all six lists. Verify: PHPUnit cases in `PlanninqRegisterSchemaTest.php`, plus a live check as a viewer that a task PUT answers 403.
- [ ] 2.4 Measure the board load for a project with 200 tasks before and after 2.3. Verify: both timings recorded in the PR body; the second is within 20 percent of the first.

## 3. Search and member list (MVP)

- [ ] 3.1 Replace the OCS provisioning call in `src/components/MemberSearch.vue` with `/ocs/v2.php/core/autocomplete/get` through `generateOcsUrl`, for users and groups, rendered in an `NcSelect` with `inputLabel`. Verify: `tests/vitest/memberSearch.spec.js` mocks the endpoint and asserts the request parameters and the rendered options.
- [ ] 3.2 Show display names and group names in the Members tab of `ProjectSettingsSidebar.vue` instead of raw ids. Verify: `tests/e2e/project-members.spec.ts` "a regular owner adds a colleague by name", run as a non-admin user, and "the admin's search limits apply" with `shareapi_restrict_user_enumeration_to_group` set.

## 4. Roles in the interface (Enterprise)

- [ ] 4.1 Add a role helper `projectRole(project, uid, groupIds)` in `src/utils/` and provide the caller's group ids through `IInitialState`. Verify: `tests/vitest/projectRole.spec.js` covers owner, manager, member, viewer, each group list and the highest-role rule.
- [ ] 4.2 Use the helper in `fetchProjects` and `applyLiveProjects` (`src/store/projects.js:194-197`, `:222-229`), in `ProjectBoard.vue:356-366` and in `ProjectList.vue`. Verify: `tests/vitest/projectRole.spec.js` asserts a group-shared project survives the store filter; e2e "a group member sees a project shared with the group".
- [ ] 4.3 Add a role picker per row on the Members tab for managers, hide member management from members and viewers, and render the board read-only for viewers. Verify: e2e "a viewer reads the board and cannot change it" and "a member cannot manage members".
- [ ] 4.4 Add `setMemberRole` to `src/store/projects.js`, writing only the affected lists through `patchProject`. Verify: vitest on the store action with a mocked PATCH body.

## 5. Groups and ownership (V1)

- [ ] 5.1 Let a manager add a group with a role, and let the owner or owning group set "Owned by group". Verify: e2e "a colleague in the owning group manages the project after the creator leaves".
- [ ] 5.2 Make `ProjectController::leaveProject` remove the caller from every user list and prefer a manager when handing over ownership. Verify: new cases in `tests/unit/Controller/ProjectControllerTest.php`.
- [ ] 5.3 Add `ProjectPrincipalCleanupListener` for `UserDeletedEvent` and `GroupDeletedEvent`, registered in `Application.php`. Verify: `tests/unit/Listener/ProjectPrincipalCleanupListenerTest.php` constructs the real Nextcloud event classes and covers the owner handover; one live account deletion on the dev instance before hand-back.
- [ ] 5.4 Move the membership guard in `lib/Service/DependencyService.php:365` to a PHP role helper with the schema's rules. Verify: `tests/unit/Service/DependencyServiceTest.php` cases for a viewer (refused) and a group member (allowed).

## 6. Verification

- [ ] 6.1 `openspec validate projects-members-and-roles --type change --strict` passes.
- [ ] 6.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
