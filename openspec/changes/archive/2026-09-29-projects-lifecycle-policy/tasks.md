# Tasks: projects-lifecycle-policy

## 1. Lifecycle on the project schema (MVP)

- [x] 1.1 Add the statuses `requested` and `rejected`, the fields `requestReason`, `reviewedBy`, `reviewedAt` and `reviewNote`, and an `x-openregister-lifecycle` block with `archive`, `restore`, `approve` and `reject` to `project` in `lib/Settings/planninq_register.json`, and bump its version. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the enum, the fields and each transition's `from` and `to`.
- [ ] 1.2 On the dev instance, as a member without update rights, call the transition endpoint with `restore` on an archived project. Verify: the answer is 403 and the status is unchanged; the result goes in the PR body. Not run live yet (the lane has no instance on this branch). Read instead at OpenRegister development `4abd834`: `TransitionEngine::resolveTransitionSubject()` refuses a transition without `update` permission on the object (403), and `LifecycleValidationListener` refuses a direct status write that no transition allows.
- [x] 1.3 Replace the PATCH in `archiveProject` with the transition endpoint and add `restoreProject` to `src/store/projects.js`. Verify: `tests/vitest/projectLifecycle.spec.js` asserts the request URL and body for both actions.
- [x] 1.4 Show "Restore project" in the Danger zone tab for an archived project and "Archive project" otherwise, based on `available-actions`, and add "Restore" to the actions of an archived project in the list. Verify: `tests/e2e/project-lifecycle.spec.ts` "restore from the project settings sidebar" and "restore from the archived list".

## 2. Creation by group (V1)

- [x] 2.1 Teach `SettingsService::canCurrentUserCreateProject` the `groups` value and the `project_creation_groups` key, and return a `canCreateProject` flag in the settings payload. Verify: new cases in `tests/unit/Service/SettingsServiceTest.php` for a member and a non-member of a listed group.
- [x] 2.2 Offer "Members of these groups" with a group `NcSelect` (with `inputLabel`) on the admin page in `src/views/settings/Settings.vue`, and read the flag in `ProjectList.vue`. Verify: e2e "only the chosen groups may create" (`tests/e2e/project-creation-groups.spec.ts`, through the API for the two users and the admin page for the picker); `tests/vitest/creationPolicy.spec.js`.

## 3. Project requests (Enterprise)

- [x] 3.1 Add the `project_requests` setting and make `ProjectController::create` store `status: 'requested'` when the caller may only request. Verify: new cases in `tests/unit/Controller/ProjectControllerTest.php` for allowed, requesting and refused callers. Built as `tests/unit/Controller/ProjectRequestControllerTest.php` (real in-memory ObjectService, payload validated against the project schema).
- [x] 3.2 Add `ProjectPolicySchemaService` that writes the reviewer groups into the live `project` schema on save, and a repair step that re-applies it after the register import. Verify: `tests/unit/Service/ProjectPolicySchemaServiceTest.php` covers save, import then repair, and a deleted group.
- [x] 3.3 Add `src/dialogs/ProjectRequestDialog.vue` (two steps) and the "Request a project" button, and a "Requested" chip on the project list. Verify: e2e "a requester fills in the guided form" (`tests/e2e/project-requests.spec.ts`: the request goes in through the requester's own API call; the browser runs as the admin, who may create and so gets no Request button); `tests/vitest/projectRequests.spec.js` covers the two steps and the payload.
- [x] 3.4 Add `src/dialogs/ProjectRequestReviewDialog.vue` with approve and reject, and the waiting and rejected banners on the board. Verify: e2e "a reviewer approves a request" and "a reviewer rejects a request with a reason".
- [x] 3.5 Add notification rules on `project` for the `approve` and `reject` transitions, with the owner as recipient. Verify: PHPUnit schema test for both rules, plus the notification assertions in the two e2e scenarios of 3.4. Built: `PlanninqRegisterSchemaTest::testProjectRequestsNotifyTheRequester` and OpenRegister's own NotificationAnnotationValidator (0 findings, control 1). The e2e does not assert the Nextcloud notification.

## 4. Verification

- [x] 4.1 `openspec validate projects-lifecycle-policy --type change --strict` passes.
- [x] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
