# Tasks: portfolio-status-overview

## 1. Status reports (Enterprise)

- [x] 1.1 Add the `projectStatusReport` schema with the six statuses and notes, `overall` as a materialised calculation, and project-scoped rights. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the calculation; a live POST with a wrong `overall` stores the worst status.
- [x] 1.2 Add the `health` fields to `project` with a property rule that no user writes them, and a listener that copies the newest report on create, update and delete. Verify: `tests/unit/Listener/ProjectStatusListenerTest.php` constructs the real OpenRegister event classes and covers a newer report, an older report and deleting the newest.
- [x] 1.3 Add the Status tab (`/projects/:id/status`) with the report form, history and suggestions for money, time and risk. Verify: `tests/vitest/statusSuggestions.spec.js` for each threshold; `tests/e2e/project-status.spec.ts` "writing a status report" and "a time suggestion from late tasks".
- [x] 1.4 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `projectStatusReport`. Verify: the PHPUnit suite passes.

Done in the first PR (see design, "Built at HEAD"): 1.1 is proven by `PlanninqRegisterSchemaTest::testProjectStatusReportSchemaCalculatesTheOverallStatus` and by OpenRegister's own CalculationEvaluator on four payloads (quality off track with overall "onTrack" sent: stored "offTrack"); 1.2 by `tests/unit/Listener/ProjectStatusListenerTest.php` on the real event classes; 1.3 by `tests/vitest/statusSuggestions.spec.js` and `tests/e2e/project-status.spec.ts`; 1.4 renamed the test to `testRegisterDeclaresExactlyElevenSchemas`.

## 2. Portfolio overview (Enterprise)

- [x] 2.1 Add the `PortfolioStatus` page (`/portfolio/status`) with the portfolio picker, the project rows and the money columns for viewers who may see money. Verify: e2e `tests/e2e/portfolio-status.spec.ts` "a portfolio manager opens the overview".
- [x] 2.2 Add the roll-up from OpenRegister aggregation on the `health` fields with an equality filter on `portfolio`, the "No report" count, and the out-of-date marker driven by a `status_report_period_days` admin setting (default 30). Verify: `tests/vitest/portfolioRollup.spec.js` for the worst-state rule and the no-report count; e2e "the roll-up of a portfolio".
- [x] 2.3 Add Reports cards for the status overview and the timeline in `src/manifest.json`. Verify: the Reports page e2e lists both cards.

## 3. Multi-project timeline (V1)

- [x] 3.1 Add `GET /api/timeline?projects=` to `TimelineController`, reusing the per-project read, with `skipped` and cross-project edges, and measure 50 projects of 100 tasks. Verify: new cases in `tests/unit/Controller/TimelineControllerTest.php` for skipped projects and cross-project edges; the timing in the PR body.
- [x] 3.2 Add the `PortfolioTimeline` page (`/portfolio/timeline`) with summary bars that open by click and Enter, reusing the bar code of `ProjectTimeline.vue`. Verify: `tests/e2e/portfolio-timeline.spec.ts` "a portfolio on one axis".

## 4. Verification

- [x] 4.1 `openspec validate portfolio-status-overview --type change --strict` passes.
- [x] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.

Done in the second PR (see design, "Built at HEAD (second PR)"): 2.1 by `tests/e2e/portfolio-status.spec.ts` and `canSeeMoney()` in `tests/vitest/portfolioRollup.spec.js`; 2.2 by `tests/vitest/portfolioRollup.spec.js` (worst state, no-report count, out of date) and `SettingsServiceTest::testStatusReportPeriodDefaultsToThirtyAndAcceptsWholeDays`; 2.3 by `tests/e2e/app-chrome.spec.ts` (both cards listed); 3.1 by `TimelineControllerTest` (`testForProjectsListsUnreadableProjectsUnderSkipped`, `testForProjectsIncludesCrossProjectEdges`, `testForProjectsSummarySpanAndPhases`, `testForProjectsLimitsTheListToFifty`, `testForProjectsUnauthenticatedReturns401`), timing in the design; 3.2 by `tests/vitest/portfolioTimeline.spec.js` and `tests/e2e/portfolio-timeline.spec.ts`.
