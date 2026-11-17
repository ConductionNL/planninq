## 1. Schedule run

- [x] 1.1 `PlanningActionService::schedule()` checks the run whole, then writes it in order and stops at the first failed write, answering `{written, failed}`; `ProjectActionController::schedule()` returns that. Verify: `ProjectActionServiceTest::testAScheduleRunIsWrittenWholeOrNotAtAll` and `testAScheduleRunStopsAtTheFirstFailedWriteAndReportsIt`.
- [x] 1.2 `scheduleTasks()` in the projects store returns the answer, and the timeline shows where a run stopped through `runError()` in `src/utils/scheduling.js`. Verify: vitest `tests/vitest/scheduling.spec.js` "write run stops at the first failure and reports it".
- [ ] 1.3 Playwright `tests/e2e/timeline-autoschedule.spec.ts` "slip shows the preview and moves the chain" against the server run. Verify: the spec passes on a live instance.
