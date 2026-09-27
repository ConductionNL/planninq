# Tasks: projects-public-share

## 1. Data and rules (V1)

- [ ] 1.1 Add property-level read rules for the `authenticated` group to `assignedTo`, `reporter`, `watchers`, `contractorRef` and `description` on `task`. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts each rule; the existing board and TaskDetail e2e specs still pass for a member.
- [ ] 1.2 Create the project's OpenRegister view and mint a `read` link on it from the store, and check the raw answer of `GET /apps/openregister/api/public/links/{anchor}` without a session. Verify: new requests in the Newman collection `tests/integration/planninq.postman_collection.json` assert none of the five properties appear, and that a revoked and an expired anchor both answer 404.

## 2. Public board (V1)

- [ ] 2.1 Add the `#[PublicPage]` template route `/apps/planninq/public/{anchor}` and the `PublicBoard` bundle with the password prompt, the 200-card notice and `noindex`. Verify: `tests/e2e/public-share.spec.ts` "creating a link and opening it without an account" (visitor in a fresh browser context) and "a password-protected link".
- [ ] 2.2 Run the hydra route-auth and route-reachability gates on the new route. Verify: both gates pass in the PR's diff check.

## 3. Share tab (V1)

- [ ] 3.1 Add the Share tab for owners and managers: create with end date and password, list every live link on the project's view with creator and use count, and switch off. Verify: e2e "a revoked link stops working"; `tests/vitest/shareTab.spec.js` for the listing of a link made by someone else.
- [ ] 3.2 Revoke links and delete the view when a project is deleted. Verify: `tests/vitest/projectDelete.spec.js` asserts the revoke and delete calls in the cascade.

## 4. Verification

- [ ] 4.1 `openspec validate projects-public-share --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
