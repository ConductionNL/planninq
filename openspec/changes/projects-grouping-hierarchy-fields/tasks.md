# Tasks: projects-grouping-hierarchy-fields

## 1. Portfolios (Enterprise)

- [ ] 1.1 Add the `portfolio` schema and `project.portfolio` and `project.portfolioReaders`, with the read rule on `portfolioReaders`. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the schema, the properties and the read rule.
- [ ] 1.2 Group the project list and the board picker by portfolio, with foldable sections and a portfolio filter. Verify: `tests/vitest/projectGrouping.spec.js` for the grouping and ordering; `tests/e2e/project-portfolios.spec.ts` "the project list groups by portfolio" and "a project manager moves a project into a portfolio".
- [ ] 1.3 Add `ProjectHierarchyGuardListener` for `ObjectCreatingEvent` and `ObjectUpdatingEvent` on `project`, writing `portfolioReaders`, and rewrite it on every project when a portfolio's managers change or the portfolio is deleted. Verify: `tests/unit/Listener/ProjectHierarchyGuardListenerTest.php` constructs the real OpenRegister event classes and covers a move, a manager change, a deletion and a client-sent `portfolioReaders`.
- [ ] 1.4 Use the portfolio's `riskScale` in the Risks tab when set. Verify: e2e "a portfolio with a three-level scale".
- [ ] 1.5 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `portfolio` and `fieldDefinition`. Verify: the PHPUnit suite passes.
- [ ] 1.6 Check live that a portfolio manager who is not a member reads a project in their portfolio and cannot write it. Verify: e2e "a portfolio manager sees a project they are not on".

## 2. Subprojects (Enterprise)

- [ ] 2.1 Add `project.parent` and the cycle and depth checks to the guard listener. Verify: `ProjectHierarchyGuardListenerTest.php` cases for self, descendant and a fourth level; e2e `tests/e2e/project-hierarchy.spec.ts` "a cycle is refused".
- [ ] 2.2 List subprojects on the parent's overview and add their tasks to its progress. Verify: `tests/vitest/projectProgress.spec.js` roll-up case; e2e "a programme shows its subprojects".
- [ ] 2.3 Indent subprojects under their parent in the project list with a toggle button. Verify: e2e "the project list shows subprojects under their parent".

## 3. Custom fields (Enterprise)

- [ ] 3.1 Add the `fieldDefinition` schema and `project.customFields`, and a custom fields section on the admin page. Verify: `PlanninqRegisterSchemaTest.php` asserts both; e2e `tests/e2e/project-custom-fields.spec.ts` "adding a choice field".
- [ ] 3.2 Validate `customFields` against the definitions in the guard listener. Verify: `ProjectHierarchyGuardListenerTest.php` cases for each type, a required field and an unknown key.
- [ ] 3.3 Render the inputs on the Details tab and the filled values on the overview. Verify: e2e "adding a choice field".

## 4. Verification

- [ ] 4.1 `openspec validate projects-grouping-hierarchy-fields --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
