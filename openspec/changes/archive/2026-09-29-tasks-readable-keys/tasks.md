# Tasks: tasks-readable-keys

## 1. Project key

- [x] 1.1 Add the key field with suggestion and format check to `src/dialogs/ProjectCreationDialog.vue`. Verify: vitest spec for the suggestion and format helper. `tests/vitest/workItemKeys.spec.js`.
- [x] 1.2 Reject a duplicate key in `ProjectController::create` with 409, and add `GET /api/projects/key-available`. Verify: PHPUnit tests in `tests/Unit/Controller/` for both. `tests/unit/Controller/ProjectKeyControllerTest.php`.
- [x] 1.3 Show the key in the settings sidebar; editable while no task carries it, read-only after. Verify: vitest mount test. Built with the rule in `keyEditable()` (`tests/vitest/workItemKeys.spec.js`); the repo has no component mount harness.

## 2. Task numbering

- [x] 2.1 Add integer `nextTaskNumber` to the project schema. Verify: PHPUnit register test; `npm run check:schema-l10n` exits 0. `PlanninqRegisterSchemaTest::testProjectCarriesTheTaskCounter`.
- [x] 2.2 A pre-create listener that assigns `{projectKey}-{n}` under a per-project lock and keeps an incoming key. Verify: PHPUnit tests for numbering, a kept imported key, and two creates against one counter. `tests/unit/Listener/WorkItemKeyListenerTest.php`.
- [x] 2.3 A queued job numbering existing keyless tasks when a project first gets a key. Verify: PHPUnit test on the job. `WorkItemKeyListenerTest::testTheJobNumbersTheKeylessTasksInOrder`.

## 3. Display

- [x] 3.1 Key on `src/components/TaskCard.vue`, the TaskDetail header and the document title. Verify: Playwright e2e creates a project with key VERG, adds a task and sees VERG-1. `tests/e2e/task-keys.spec.ts`.

## 4. Copy and verification

- [x] 4.1 New strings in every locale the app ships (36). Verify: `npm run check:l10n` exits 0.
- [x] 4.2 `openspec validate tasks-readable-keys --type change --strict` passes.
