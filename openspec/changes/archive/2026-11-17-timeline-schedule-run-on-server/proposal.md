# Timeline schedule run on the server

## Why

"Move all" on the timeline wrote the dragged task and every task it pushed from the browser, one
request per task (planning-timeline-editing task 3.3). The browser could not check the run as a
whole, so a run that broke a `blocks` link or named a task of another project was half written
before anything noticed. The project actions now run on the server, and the schedule run moves
with them.

## What changes

- `POST /api/projects/{projectId}/schedule` takes the whole run. It refuses the run, writing
  nothing, when the caller does not work on the project, a task is not in the project, a date is
  malformed, or a `blocks` link the run touches would break.
- A run that passes is written in the run's order, and the first write that fails stops the run.
  The answer names the task it stopped at and how many were written; the tasks before it stay
  moved, as before. The timeline says where the run stopped.
- The browser-side `writeRun` loop and its vitest go; the stop-and-report rule is now tested on
  the server (`ProjectActionServiceTest`) and the message in `tests/vitest/scheduling.spec.js`
  (`runError`). Phase reordering moved to the server in the same way, so the `reorderPatches`
  helper and its vitest go too; `ProjectActionServiceTest::testMovingAPhaseRenumbersThePhases`
  covers it.

## Impact

`lib/Service/PlanningActionService.php`, `lib/Controller/ProjectActionController.php`,
`src/store/projects.js`, `src/utils/scheduling.js`, `src/views/ProjectTimeline.vue`, `openapi.json`.
