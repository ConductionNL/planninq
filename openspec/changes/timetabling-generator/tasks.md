# Tasks: timetabling-generator

Each numbered section is one PR. Do not start section 1 before the change itself is merged (build-all
DECISIONS.md row 17). Sections 5 onward assume the solver answer recorded in design.md "Needs Ruben".

## 1. Schemas and the week grid

- [x] 1.1 `timetableWish` and `timetableScenario` schemas (design decision 2) with admin-write and `planninq-timetable`-read authorization; register and app version bump; three demo objects each. Verify: PHPUnit `PlanninqRegisterSchemaTest` (fields, enums, rules), and the example payloads of `SolverInput` and a finished scenario validated against the real schema fragments.
- [x] 1.2 Admin setting `timetable_period_grid` (days, periods with start and end) and `timetable_generator_budget_minutes`, with validation. Verify: PHPUnit `TimetableGridServiceTest` and `SettingsControllerTest` (default grid, refused overlapping periods, refused empty day list; the settings live in `TimetableGridService`, not `SettingsService`, for phpmd coupling).

## 2. Activities and the solver input

- [x] 2.1 `TimetableActivitiesQueryEvent` (typed, ADR-041) and `TimetableInputBuilder` that turns activities, rooms, the grid and the wishes into a `SolverInput`. Verify: PHPUnit on the builder with a fixture; an event nobody answers yields an empty input and the reason "No activities: learniq did not answer and no CSV was uploaded".
- [x] 2.2 CSV upload of activities and rooms (same columns as the event), admin only. Verify: PHPUnit on the parser (header check, lessons per week and lesson length as whole numbers, unknown room type refused with the line number); controller refuses a non-admin.
- [x] 2.3 Draft the learniq listener contract to `~/memcap-work/build-all/for-ruben/learniq-timetable-activities-event.md`. Verify: the file names the event class, its fields and the learniq endpoint it mirrors.

## 3. Wishes

- [x] 3.1 Wish editor page (Timetable, Wishes): list by teacher, group, room or activity; add, edit, delete; hard or soft with weight; period picker on the week grid. Verify: vitest on `src/utils/timetableWishes.js` (period key round trip, weight only for soft); Playwright "a timetabler marks a wish as hard". Built (section 3 PR): a declarative index page `TimetableWishes` (`/timetable/wishes`, quick filters per teacher, group, room, activity) whose create and edit dialog is replaced through the `form-dialog` slot by `src/dialogs/TimetableWishDialog.vue`; no new custom page, so the gate-69 ratchet holds.
- [x] 3.2 Row `tt-hard-soft-wishes` is not set built here: it is built when section 5 respects the wishes.

## 4. Scorer

- [x] 4.1 `TimetableScorer`: clashes, hard wish breaches, soft wish breaches with weights, teacher gaps, lessons per day, room use, as `metrics` (design decision 5). Verify: PHPUnit with a hand-made placement for each wish kind, each broken once and kept once.

## 5. Solver and the background run

- [x] 5.1 `TimetableSolver` interface, `SolverInput`/`SolverResult` value objects, `LocalSearchSolver` (greedy most-constrained-first, then simulated annealing on the scorer, seeded). Verify: PHPUnit: a feasible fixture places everything with 0 hard breaches; an infeasible hard wish leaves one lesson unplaced naming that wish; a soft wish is kept when it costs nothing; same seed gives the same result; a 600-lesson fixture finishes inside 60 seconds.
- [x] 5.2 `POST /api/timetable/scenarios/{id}/generate` (admin) and `GenerateTimetableScenario` queued job in 60-second steps up to the budget (design decision 6). Verify: PHPUnit on the job (re-queues while improving, stops at the budget, stores `failed` with the reason on an exception); controller refuses a non-admin.
- [x] 5.3 Scenario list and scenario page (status, progress, unplaced lessons with the blocking wish, broken soft wishes). Set `tt-hard-soft-wishes` built. Verify: Playwright "generate a scenario that respects a hard wish". Built (section 5 PR): index page `TimetableScenarios` and detail page `TimetableScenarioDetail`, whose `sections` slot is `src/components/TimetableScenarioSections.vue` (no new custom page); vitest `timetableScenarios.spec.js`.

## 6. Imported scenarios

- [x] 6.1 "Make a scenario from the current timetable": snapshot the `timetableSession` rows of a window into a scenario with `source` `imported`, scored with the same scorer. Verify: PHPUnit: an imported timetable that breaks a hard wish shows that breach in `metrics`. Built (section 6 PR): `TimetableScenarioImporter` takes the scheduled lessons of the scenario's week (`weekOf`) as a week pattern (a lesson off the grid's period times is unplaced with reason `offGrid`), `POST /api/timetable/scenarios/{id}/import` (admin), button "Take the current timetable" and a "Broken hard wishes" list on the scenario page; PHPUnit `TimetableScenarioImporterTest`, `TimetableScenarioControllerTest`, vitest `timetableScenarios.spec.js`.

## 7. Compare

- [ ] 7.1 Compare page: two or three scenarios, metrics side by side with the best value marked, and the list of lessons whose period or room differs. Set `tt-scenario-compare` built. Verify: vitest on `src/utils/scenarioCompare.js` (best-value marking, lesson diff); Playwright "compare a generated and an imported scenario".

## 8. Publish

- [ ] 8.1 `POST /api/timetable/scenarios/{id}/publish-drafts` (admin): upsert the placements as draft lessons for every week of the window (design decision 7). Verify: PHPUnit: every payload validated against the real `timetableSession` fragment; a second scenario for the same window replaces unpublished drafts and is refused when lessons are already published from `planninq-generator`.

## 9. Docs and verification

- [ ] 9.1 `docs/features/timetable-generator.md`, strings in all 36 locales, Newman folder "Timetable generator". Verify: `npm run check:l10n`, Newman.
- [ ] 9.2 `openspec validate timetabling-generator --type change --strict` passes, and every scenario is covered by a test named above or carries an `@e2e exclude <reason>` note.
