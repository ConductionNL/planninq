# Tasks: platform-demo-data

## 1. Dataset

- [ ] 1.1 Rewrite `lib/Settings/planninq_mock_register.json` from the curated objects (fixed ids, `demo-` slugs, `@operator`, dates around `anchorDate`, valid edges, at least three per schema) and run the generator with `--keep`. Verify: `generate_mock_register.py --check` passes in the gates; PHPUnit `DemoDatasetTest::testEveryReferenceResolves`, `testDependenciesJoinDifferentTasksOfOneProject`, `testDependenciesFormNoCycle` and `testAtLeastThreeObjectsPerSchema`.
- [ ] 1.2 Reduce `components.objects` in `lib/Settings/planninq_register.json` to the five labels and bump the register version. Verify: PHPUnit `PlanninqRegisterSchemaTest::testInstallSeedsOnlyDefaultLabels`.

## 2. Import

- [ ] 2.1 `DemoDataService::install()` replaces `@operator` with the loading admin's uid and shifts dates from `anchorDate` to today before `importFromApp`. Verify: PHPUnit `DemoDataServiceTest::testOperatorBecomesOwnerAndMember`, `testNoPlaceholderSurvives` and `testDatesShiftToLoadDay`.
- [ ] 2.2 Live check through the wizard on a fresh instance. Verify: Playwright `tests/e2e/demo-data.spec.ts` "admin loads example data and sees the projects", "example board shows cards in columns", "my tasks shows example tasks due around today" and "example timeline draws dependency arrows".
- [ ] 2.3 Fresh install without the wizard. Verify: Playwright `tests/e2e/demo-data.spec.ts` "fresh install has labels and no projects".

## 3. Verification

- [ ] 3.1 `openspec validate platform-demo-data --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
