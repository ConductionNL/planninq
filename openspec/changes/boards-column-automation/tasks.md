# Tasks: boards-column-automation

## 1. Schema and listener

- [ ] 1.1 Add `column.automation` (array of `{ action, value }`, action enum as in design Decision 1) and bump the schema version. Verify: PHPUnit `PlanninqRegisterSchemaTest::testColumnAutomationShape`.
- [ ] 1.2 `lib/Listener/ColumnAutomationListener.php` on `ObjectUpdatingEvent` and `ObjectCreatingEvent`, registered in `Application.php`, scoped to planninq tasks, running only on a column change. Verify: PHPUnit `ColumnAutomationListenerTest::testAssignRuleSetsAssignee`, `testAssignMoverUsesCurrentUser`, `testSetPriorityRuleSetsPriority`, `testAddLabelAppendsOnce`, `testNoColumnChangeRunsNothing` and `testCreateInColumnRunsRules`.
- [ ] 1.3 Value validation and merge behaviour. Verify: PHPUnit `ColumnAutomationListenerTest::testNonMemberAssigneeIsSkipped`, `testInvalidPriorityIsSkipped` and `testMergesWithDataFromAnotherListener`.
- [ ] 1.4 Live check through the API: PATCH a task's `column` to a column with an `assign` rule and read the stored task. Verify: Newman request "moving a task into Blocked sets priority high" in `tests/integration/planninq.postman_collection.json`.

## 2. Board

- [ ] 2.1 `src/dialogs/ColumnRulesDialog.vue` with valid-value pickers and the "No longer a project member" marker. Verify: Playwright `tests/e2e/column-automation.spec.ts` "owner adds an assign rule to Review", "member who is not the owner sees no Rules entry", "dialog offers only project members" and "rule for a former member is marked".
- [ ] 2.2 Rule icon and count in the column header; card refreshed from the write response; live-region announcement. Verify: Playwright `tests/e2e/column-automation.spec.ts` "moving a card into Review assigns the mover" and "moving a card by keyboard applies the rules".

## 3. Verification

- [ ] 3.1 `openspec validate boards-column-automation --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
