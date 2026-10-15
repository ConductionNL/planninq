# Tasks: projects-overview-logs-risks

## 1. Overview and tabs (MVP)

- [x] 1.1 Add `src/components/ProjectTabs.vue` and use it in `ProjectBoard.vue`, `ProjectBacklog.vue` and `ProjectTimeline.vue` in place of the Backlog and Timeline buttons. Verify: `tests/e2e/project-overview.spec.ts` "moving between project pages by keyboard".
- [x] 1.2 Add the `ProjectOverview` page (`/projects/:id/overview`) to `src/manifest.json` and `src/registry.js`, with progress computed from `fetchTasks`. Verify: `tests/vitest/projectProgress.spec.js` for the done over not-cancelled rule; e2e "the overview shows where the project stands" and "an empty project says so".
- [x] 1.3 Update the exact-schema-count test in `tests/unit/Settings/PlanninqRegisterSchemaTest.php` (was `testRegisterDeclaresExactlyEightSchemas`, now `testRegisterDeclaresExactlyTenSchemas`) and the scenario in `openspec/specs/project-delivery/spec.md` to the new schema list. Verify: the PHPUnit suite passes with ten schemas named.

## 2. Project log (V1)

- [x] 2.1 Add the `projectLogEntry` schema with the project-scoped authorization settled by `projects-members-and-roles` task 1.1. Verify: `PlanninqRegisterSchemaTest.php` asserts the properties and the read rule; a live GET as an outsider returns none of the entries.
- [x] 2.2 Add the `ProjectLog` page (`/projects/:id/log`) with type filters and `src/dialogs/LogEntryEditDialog.vue`. Verify: `tests/e2e/project-log.spec.ts` "recording a meeting".
- [x] 2.3 Add "Add action", which creates a task with `issueType: 'action'` through the task create path of `tasks-create-edit-delete` and links it in `actions`. Verify: e2e "turning a meeting outcome into an action".

## 3. Risk register (V1)

- [x] 3.1 Add the `risk` schema with `score` as a materialised calculation and the project-scoped authorization. Verify: `PlanninqRegisterSchemaTest.php` asserts the calculation; a live POST with a wrong score stores the calculated one.
- [x] 3.2 Add the `ProjectRisks` page (`/projects/:id/risks`), `src/components/RiskHeatMap.vue` and `src/dialogs/RiskEditDialog.vue`. Verify: `tests/vitest/riskHeatMap.spec.js` for cell counts and band text; e2e `tests/e2e/risk-register.spec.ts` "adding a risk".
- [x] 3.3 Add the declarative `RiskIndex` page (`/projects/risks`, `type: "index"`) under Projecten. Verify: e2e "a project leader scans risks across projects", run as a user on two of three seeded projects.
- [x] 3.4 Add `risk_scale` to the admin settings (`RiskScaleService`, `SettingsController`, `SettingsService`) with validation against the levels in use, and its form on the admin page. Verify: `tests/unit/Service/RiskScaleServiceTest.php` and `tests/unit/Controller/SettingsControllerTest.php` for a valid scale and a refused one; e2e "switching to a three-level scale" and "a smaller scale is refused while risks use a higher level".

## 4. Verification

- [x] 4.1 `openspec validate projects-overview-logs-risks --type change --strict` passes.
- [x] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
