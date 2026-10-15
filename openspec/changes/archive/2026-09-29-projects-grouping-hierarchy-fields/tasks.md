# Tasks: projects-grouping-hierarchy-fields

## 1. Portfolios (Enterprise)

- [x] 1.1 Add the `portfolio` schema and `project.portfolio` and `project.portfolioReaders`, with the read rule on `portfolioReaders`. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the schema, the properties and the read rule.
- [x] 1.2 Group the project list and the board picker by portfolio, with foldable sections and a portfolio filter. Verify: `tests/vitest/projectGrouping.spec.js` for the grouping and ordering; `tests/e2e/project-portfolios.spec.ts` "the project list groups by portfolio" and "a project manager moves a project into a portfolio".
- [x] 1.3 Add `ProjectHierarchyGuardListener` for `ObjectCreatingEvent` and `ObjectUpdatingEvent` on `project`, writing `portfolioReaders`, and rewrite it on every project when a portfolio's managers change or the portfolio is deleted. Verify: `tests/unit/Listener/ProjectHierarchyGuardListenerTest.php` constructs the real OpenRegister event classes and covers a move, a manager change, a deletion and a client-sent `portfolioReaders`.
- [x] 1.4 Use the portfolio's `riskScale` in the Risks tab when set. Verify: e2e "a portfolio with a three-level scale".
- [x] 1.5 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `portfolio` and `fieldDefinition`. Verify: the PHPUnit suite passes.
- [x] 1.6 Check that a portfolio manager who is not a member reads a project in their portfolio and cannot write it. Verify: `PlanninqRegisterSchemaTest::testPortfolioSchemaAndTheProjectReaderRule` (read rule on `portfolioReaders`, not on update) and `ProjectHierarchyGuardListenerTest` (the list is stamped); the scenario carries `@e2e exclude` because the e2e suite has one admin account. The live recipe is in PR #714 and in the lane's live-check list; it has not been run on an instance.

Section 1 was built in one PR (see design, "Built at HEAD, section 1"). 1.5 added only `portfolio` (the register test is now `testRegisterDeclaresExactlyTwelveSchemas`); `fieldDefinition` comes with 3.1. 1.6 stays open: the e2e suite has no second, non-admin account, so the check is a live recipe in the PR, with the read rule asserted by `PlanninqRegisterSchemaTest::testPortfolioSchemaAndTheProjectReaderRule` and the stamping by `ProjectHierarchyGuardListenerTest`.

## 2. Subprojects (Enterprise)

- [x] 2.1 Add `project.parent` and the cycle and depth checks to the guard listener. Verify: `ProjectHierarchyGuardListenerTest::testACycleIsRefused` (self, descendant) and `testAFourthLevelIsRefused` (a new fourth level, a subtree that would land on it, an unchanged parent); e2e `tests/e2e/project-hierarchy.spec.ts` "a cycle is refused".
- [x] 2.2 List subprojects on the parent's overview and add their tasks to its progress. Verify: `tests/vitest/projectTree.spec.js` rollupProgress case; e2e "a programme shows its subprojects".
- [x] 2.3 Indent subprojects under their parent in the project list with a toggle button. Verify: `tests/vitest/projectTree.spec.js` treeRows cases; e2e "the project list shows subprojects under their parent".

## 3. Custom fields (Enterprise)

- [x] 3.1 Add the `projectField` schema (named `fieldDefinition` in the design; slugs are global across the fleet) and `project.customFields`, and a declarative index "Project fields" in Beheer. Verify: `PlanninqRegisterSchemaTest::testProjectFieldSchemaAndTheProjectValues`; e2e `tests/e2e/project-custom-fields.spec.ts` "adding a choice field".
- [x] 3.2 Validate `customFields` against the definitions in the guard listener. Verify: `ProjectHierarchyGuardListenerTest::testCustomFieldsOfTheRightTypePass`, `testAWrongValueTypeIsRefusedNamingTheField` (each type) and `testARequiredFieldAndAnUnknownKeyAreRefused`.
- [x] 3.3 Render the inputs on the Details tab and the filled values on the overview. Verify: `tests/vitest/projectFields.spec.js`; e2e "adding a choice field".

## 4. Verification

- [x] 4.1 `openspec validate projects-grouping-hierarchy-fields --type change --strict` passes.
- [x] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
