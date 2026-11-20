# Tasks: projects-public-share

## 1. Data and rules (V1)

- [ ] 1.1 Add property-level read rules for the `authenticated` group to `assignedTo`, `reporter`, `watchers`, `contractorRef` and `description` on `task`. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts each rule; the existing board and TaskDetail e2e specs still pass for a member. Built 2 Oct (lane 27): the five properties carry `{"read": [{"group": "authenticated"}]}` (task schema 0.12.0, register 0.36.0); `PlanninqRegisterSchemaTest::testTaskPeopleAndFreeTextStayOutOfAccessLinks` asserts each rule, that title, status, due date, labels, column and project stay unruled, and that a task with every field still validates. Open: the board and TaskDetail e2e runs for a member (no instance in the lane).
- [ ] 1.2 Create the project's OpenRegister view and mint a `read` link on it from the store, and check the raw answer of `GET /apps/openregister/api/public/links/{anchor}` without a session. Verify: new requests in the Newman collection `tests/integration/planninq.postman_collection.json` assert none of the five properties appear, and that a revoked and an expired anchor both answer 404.
  - Written 17 Nov: the `Public Share` folder of `tests/integration/planninq.postman_collection.json` seeds a column and a task carrying all five properties, creates the view, mints a `read` link and asserts that the session-less `GET /apps/openregister/api/public/links/{anchor}` serves the task without `assignedTo`, `reporter`, `watchers`, `contractorRef` or `description`; then a revoked and an expired anchor both answer 404. Not run against an instance yet, so this stays open.

## 2. Public board (V1)

- [ ] 2.1 Add the `#[PublicPage]` template route `/apps/planninq/public/{anchor}` and the `PublicBoard` bundle with the password prompt, the 200-card notice and `noindex`. Verify: `tests/e2e/public-share.spec.ts` "creating a link and opening it without an account" (visitor in a fresh browser context) and "a password-protected link".
  - Built 17 Nov: `PublicBoardController::show` (`#[PublicPage]`, rate limited, `X-Robots-Tag: noindex, nofollow`, no data in the page), `templates/public-board.php` (robots meta), the `publicBoard` webpack entry (`src/publicBoard.js`, `src/views/PublicBoard.vue`, `src/utils/publicBoard.js`): password prompt sent as `X-OpenRegister-Link-Password`, the 200-object notice, a read-only board of title, status, due date and labels, and a dead-link message for a revoked or expired anchor. Tests: `tests/unit/Controller/PublicBoardControllerTest.php`, `tests/vitest/publicBoard.spec.js`. The e2e `tests/e2e/public-share.spec.ts` is written ("creating a link and opening it without an account", "a password-protected link", "a revoked link stops working"; it mints through OpenRegister's API until the Share tab exists) and has not run, so this stays open.
- [x] 2.2 Run the hydra route-auth and route-reachability gates on the new route. Verify: both gates pass in the PR's diff check.
  - Ran 17 Nov on the PR head with `--scope-to-diff --base origin/development`: route-auth PASS, route-reachability PASS.

## 3. Share tab (V1)

- [ ] 3.1 Add the Share tab for owners and managers: create with end date and password, list every live link on the project's view with creator and use count, and switch off. Verify: e2e "a revoked link stops working"; `tests/vitest/shareTab.spec.js` for the listing of a link made by someone else.
  - Built 17 Nov: `src/components/ProjectShareSection.vue` as the Share tab of `ProjectSettingsSidebar` (owners and managers only): create with an end date (default 30 days, at most a year) and an optional password, the link with a copy button, the live links on the project's view with end date, password, creator and use count, and Switch off; `src/utils/projectShare.js` finds or creates the view and mints, lists and revokes through OpenRegister. Tests: `tests/vitest/shareTab.spec.js`; e2e "the Share tab creates a link and switches it off" written in `tests/e2e/public-share.spec.ts`, not run. Open: OpenRegister's `GET /api/access-links` lists only the caller's own links and its revoke takes only the caller's own, so a link a member minted does not show up for a manager yet. That needs an OpenRegister change deciding who may list and revoke other people's links on a subject; until then this stays open.
- [x] 3.2 Revoke links and delete the view when a project is deleted. Verify: `tests/vitest/projectDelete.spec.js` asserts the revoke and delete calls in the cascade.
  - Built 17 Nov: `deleteProject` (`src/store/projects.js`), after the server deleted the project, revokes the live links on the project's view and deletes the view, which ends every link on it whoever made it; a failure warns and the project stays deleted. `tests/vitest/projectDelete.spec.js` asserts the calls in order.

## 4. Verification

- [x] 4.1 `openspec validate projects-public-share --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
