# Tasks: platform-demo-data

## 1. Dataset

- [x] 1.1 Rewrite `lib/Settings/planninq_mock_register.json` from the curated objects (fixed ids, `demo-` slugs, `@operator`, dates around `anchorDate`, valid edges, at least three per schema) and run the generator with `--keep`. Verify: `generate_mock_register.py --check` passes in the gates; PHPUnit `DemoDatasetTest::testEveryReferenceResolves`, `testDependenciesJoinDifferentTasksOfOneProject`, `testDependenciesFormNoCycle` and `testAtLeastThreeObjectsPerSchema`.
- [x] 1.2 Reduce `components.objects` in `lib/Settings/planninq_register.json` to the five labels and bump the register version. Verify: PHPUnit `PlanninqRegisterSchemaTest::testInstallSeedsOnlyDefaultLabels`.

## 2. Import

- [x] 2.1 `DemoDataService::install()` replaces `@operator` with the loading admin's uid and shifts dates from `anchorDate` to today before `importFromApp`. Verify: PHPUnit `DemoDataServiceTest::testOperatorBecomesOwnerAndMember`, `testNoPlaceholderSurvives` and `testDatesShiftToLoadDay`.
- [x] 2.2 Live check through the wizard on a fresh instance. Verify: Playwright `tests/e2e/demo-data.spec.ts` "admin loads example data and sees the projects", "example board shows cards in columns", "my tasks shows example tasks due around today" and "example timeline draws dependency arrows".
- [x] 2.3 Fresh install without the wizard. Verify: Playwright `tests/e2e/demo-data.spec.ts` "fresh install has labels and no projects".

## 3. Verification

- [x] 3.1 `openspec validate platform-demo-data --type change --strict` passes.
- [x] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.

## Notes from the build (2026-09-29)

- 1.1: 59 curated objects (at least three per schema, 14 schemas), ids `0000de00-0000-4000-8000-<schema code><n>`, so they never collide with the sample ids older installs received. Extra guards beyond the named tests: `testEveryObjectHasAFixedIdAndADemoSlug`, `testCustomFieldValuesUseDefinedFields`, `testTasksSitInAColumnOfTheirProjectWithTheirStatus`, `testDescriptorNamesItsAnchorAndNoAccount`, and `testEveryFittedObjectPassesItsSchema` (every object after `prepare()`, through the Opis validator OpenRegister uses).
- 2.1: the tests live in `tests/unit/Service/DemoDataServiceTest.php` (namespace `OCA\Planninq\Tests\Unit\Service`), beside the older `tests/Unit/Service/DemoDataServiceTest.php`; plus `testShiftWorksBackwardsAndNeedsAnAnchor` and `testInstallRefusesWithoutASignedInUser`.
- 2.2 and 2.3: `tests/e2e/demo-data.spec.ts` is written against the wizard's own endpoints; Playwright does not run from the lane clone, so it first runs in the nightly e2e job. "No projects on a fresh install" cannot hold on the shared e2e instance, so PHPUnit `PlanninqRegisterSchemaTest::testInstallSeedsOnlyDefaultLabels` asserts it and the e2e checks the labels.
- The Newman collection created its tasks in the seeded "Client Portal v2"; it now creates its own project first.
