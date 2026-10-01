# Tasks: portfolio-flow-reports

## 1. Flow history (V1)

- [x] 1.1 Add `FlowHistoryService` that finds the project with RBAC on, reads its tasks' audit trail entries for `column`, `status` and `completedAt` server-side, and replays them into daily column counts and task timings. Verify: `tests/unit/Service/FlowHistoryServiceTest.php` replays a fixed history into known counts, lead and cycle times, and the "estimated" fallback.
- [x] 1.2 Add `FlowController` with `GET /api/projects/{id}/flow` and `GET /api/portfolios/{id}/flow` in `appinfo/routes.php`, with the 180-day and 50-project limits and a per-day cache. Verify: `tests/unit/Controller/FlowControllerTest.php` for 403 on an unreadable project, the limits and a cached past day.
- [ ] 1.3 Measure a project of 1,000 tasks over 180 days, cold and cached. Verify: both timings in the PR body; the cold one under five seconds. Open (1 Oct, lane 23): the replay itself was timed synthetically (`lane23/flow-bench.php`: 1,000 tasks with seven audit rows each over 180 days); the cold read on a live instance, which adds one audit-trail query per task, is owed by the first lane with an instance of its own.

## 2. Flow screens (V1 and Enterprise)

- [x] 2.1 Add the Flow tab (`/projects/:id/flow`) to `ProjectTabs` with the stacked area chart, its table view, the period picker and the lead and cycle time scatter with the slowest tasks. Verify: `tests/e2e/project-flow.spec.ts` "a queue growing before review" and "cycle time of finished tasks" on seeded audit history.
- [x] 2.2 Add the portfolio flow page (`/portfolio/flow`) with a project filter. Verify: e2e "cycle time across a portfolio".

## 3. Custom reports (V1)

- [ ] 3.1 Add the `report` schema with owner-only write and read for the owner, or for everyone when `shared` is `readers`. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the properties and rules.
- [ ] 3.2 Add `src/dialogs/ReportBuilderDialog.vue` that builds only equality filters into the aggregation data source. Verify: `tests/vitest/reportBuilder.spec.js` asserts the built `dataSource` for each display and that no non-equality operator appears.
- [ ] 3.3 Add the report page (`/reports/custom/:id`), "My reports" and "Shared reports" on the Reports page, and the "not visible to you" count. Verify: `tests/e2e/custom-reports.spec.ts` "open tasks per assignee across two projects" and "a viewer on fewer projects".
- [ ] 3.4 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `report`. Verify: the PHPUnit suite passes.

## 4. Verification

- [ ] 4.1 `openspec validate portfolio-flow-reports --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
