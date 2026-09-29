# Tasks: portfolio-finance

## 1. Terms and lines (V1)

- [x] 1.1 Add `project.billingModel` and the `financeLine` schema with read rights for owner, managers and portfolio readers, write rights for managers on manual lines, and for the group `planninq-finance-import` on imported lines. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php::testFinanceLineSchemaAndItsRules` asserts the properties and the rules; `tests/unit/Listener/FinanceLineListenerTest.php` asserts that only the owner writes manual lines and only the import group writes imported ones (built as a listener, see design decision 5).
- [x] 1.2 Add `finance_categories` to `SettingsService` and the admin page. Verify: `tests/unit/Service/SettingsServiceTest.php` for defaults and a saved list.
- [x] 1.3 Add the Finance tab (`/projects/:id/finance`) with the terms form, hiding amounts for members and viewers. Verify: `tests/e2e/project-finance.spec.ts` "setting a fixed-price budget" and "members do not see the money".
- [x] 1.4 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `financeLine`. Verify: the PHPUnit suite passes.

## 2. Budget against cost (V1)

- [x] 2.1 Add `laborCost(projectId)` reading hours and rates in one place, with the "Hours are not available" state for a missing humaniq after the time move. Verify: `tests/vitest/laborCost.spec.js` for the entry rate, the project rate fallback and the missing owner.
- [x] 2.2 Add the category and phase table and `src/dialogs/FinanceLineDialog.vue` for manual lines; show imported lines read-only. Verify: e2e "budget against actual cost with booked time" and "lines from the finance system cannot be edited".

## 3. Import contract and portfolio (Enterprise)

- [ ] 3.1 Enforce uniqueness of (`source`, `externalRef`) and write the integration contract (fields, group, matching on `project.key`) into `docs/`. Verify: Newman requests in `tests/integration/planninq.postman_collection.json` post the same line twice and read one line back.
- [ ] 3.2 Add the declarative "Unmatched finance lines" index in Beheer and the assign action. Verify: e2e "assigning an unmatched line".
- [x] 3.3 Keep `financeLine.portfolio` and the access copies in step through the listener of `projects-grouping-hierarchy-fields` and the project membership sync. Verify: `ProjectHierarchyGuardListenerTest::testPortfolioChangesReachTheFinanceLines` and `ProjectMembershipSyncListenerTest::testANewOwnerOrPortfolioReachesTheFinanceLines`.
- [ ] 3.4 Add the portfolio finance page (`/portfolio/finance`) on OpenRegister aggregation with an equality filter on `portfolio`, plus labour per project. Verify: `tests/vitest/portfolioFinance.spec.js` for totals and the cross-check; e2e "totals for a portfolio" and "a project outside your reach is not totalled".

## 4. Verification

- [ ] 4.1 `openspec validate portfolio-finance --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
