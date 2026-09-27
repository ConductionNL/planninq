# Tasks: integration-msproject-import

## 1. Parser and mapping

- [ ] 1.1 `MsProjectImportService::parse()` with XML hardening and the size and task caps. Verify: PHPUnit `MsProjectImportServiceTest::testRejectsDoctype`, `testRejectsExternalEntity`, `testRejectsOverTwoThousandTasks`, `testMppIsRefusedWithHint` and `testParsesSampleContractorPlan` (a fixture exported from Project in `tests/fixtures/msproject/`).
- [ ] 1.2 The mapping of Decision 2 as a pure function returning objects and losses. Verify: PHPUnit `MsProjectImportServiceTest::testSummaryLevelOneBecomesPhase`, `testDeepLevelsCollapseToOneSubtaskLevel`, `testMilestoneMapping`, `testProgressSetsStatus`, `testFinishToStartBecomesBlocks`, `testOtherLinkTypesBecomeRelates` and `testLossesAreCounted`.
- [ ] 1.3 Declared `metadata` object on `projectPhase` and a schema version bump. Verify: PHPUnit `PlanninqRegisterSchemaTest::testPhaseHasMetadata`.

## 2. Endpoints

- [ ] 2.1 `ProjectImportController` preview and commit routes with the owner-or-admin check. Verify: PHPUnit `ProjectImportControllerTest::testMemberWhoIsNotOwnerIsRefused`, `testPreviewWritesNothing` and `testCommitCreatesPhasesTasksAndLinks`; hydra gates `route-auth`, `no-admin-idor` and `route-reachability` pass.
- [ ] 2.2 Re-import by UID. Verify: PHPUnit `ProjectImportControllerTest::testReimportUpdatesMatchedTasks`, `testReimportListsMissingTasks` and `testRerunAfterFailureCreatesNoDuplicates`.

## 3. Timeline

- [ ] 3.1 "Import from Microsoft Project" in the timeline header for the owner and `src/dialogs/MsProjectImportDialog.vue`. Verify: Playwright `tests/e2e/msproject-import.spec.ts` "owner previews a contractor plan", "owner imports it and sees it on the timeline" and "member who is not the owner sees no import button".

## 4. Verification

- [ ] 4.1 `openspec validate integration-msproject-import --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
