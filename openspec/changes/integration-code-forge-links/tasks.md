# Tasks: integration-code-forge-links

## 1. Schema and listener

- [x] 1.1 Add the `forgeLink` schema with the project-scoped read and write rules, list it in the register, bump versions and the exact schema count test. Verify: PHPUnit `PlanninqRegisterSchemaTest::testForgeLinkIsProjectScoped`; Newman request "non-member cannot read a forge link" in `tests/integration/planninq.postman_collection.json`.
- [x] 1.2 `lib/Listener/ForgeLinkResolveListener.php` on `ObjectCreatingEvent`. Verify: PHPUnit `ForgeLinkResolveListenerTest::testKeyResolvesTaskAndProject`, `testUnknownKeyIsRejected`, `testDuplicateExternalIdIsRejected` and `testManualLinkGetsProjectFromTask`.

## 2. Task page

- [x] 2.1 `src/components/TaskForgeLinks.vue` with the pure helper `src/utils/forgeUrl.js` (kind, repository and number from GitHub, GitLab and Gitea URLs). Verify: vitest `tests/vitest/forgeUrl.spec.js` "GitHub pull request", "GitLab merge request", "Gitea commit" and "unknown host is a plain link".
- [x] 2.2 List, add and remove on `TaskDetail`. Verify: Playwright `tests/e2e/forge-links.spec.ts` "task lists its linked merge request", "member pastes a merge request link", "member removes a manual link" and "member cannot remove an integriq link". Written 30 Sep (lane 20) as one test with the admin, since the e2e run has one account; the "cannot remove" case is asserted by PHPUnit `testForgeLinkIsProjectScoped` (update and delete rules) and vitest `forgeUrl.spec.js` "a member removes only a link added by hand".

## 3. Integriq configuration and Beheer

- [ ] 3.1 `lib/Settings/integriq/planninq-code-forge.json` (GitHub and GitLab endpoints, mappings, key rule). Verify: PHPUnit `IntegriqConfigurationTest::testTargetsOnlyForgeLinkFields`; live check with integriq installed: import with preview, send a recorded GitHub push event with "VC-12" in the message to the endpoint, and see the link. Verify: Playwright `tests/e2e/forge-links.spec.ts` "a pushed commit naming the key appears on the task" and "a merged pull request shows merged".
- [ ] 3.2 "Code forges" section in the admin settings with the steps and the file. Verify: Playwright `tests/e2e/forge-links.spec.ts` "admin finds the setup steps and the file".

## 4. Verification

- [ ] 4.1 `openspec validate integration-code-forge-links --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
