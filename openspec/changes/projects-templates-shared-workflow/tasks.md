# Tasks: projects-templates-shared-workflow

## 1. Templates and copy (V1)

- [ ] 1.1 Add `isTemplate` (boolean, default false) to `project`, a Templates chip on `ProjectList.vue`, and `isTemplate: false` on the dashboard KPI filters in `src/manifest.json`. Verify: `PlanninqRegisterSchemaTest.php` asserts the property; `tests/e2e/project-templates.spec.ts` "marking a project as a template".
- [ ] 1.2 Add `ProjectCopyService` and `POST /api/projects/{id}/copy` in `appinfo/routes.php`, with the policy check, id remapping, date shift and clean-up on failure. Verify: `tests/unit/Service/ProjectCopyServiceTest.php` covers remapped `column`, `phase`, `parent` and `dependency` references, the date shift, and a failure mid-copy leaving nothing.
- [ ] 1.3 Add a template picker to `ProjectCreationDialog.vue` and `src/dialogs/ProjectCopyDialog.vue` with the part choices. Verify: e2e "starting a project from a template" and "copying a project without its people".
- [ ] 1.4 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for the new `workflow` schema, in step with the other changes of this pass that add schemas. Verify: the PHPUnit suite passes.

## 2. Shared workflow (Enterprise)

- [ ] 2.1 Add the `workflow` schema (`title`, `description`, `columns`, `estimateScale`, `estimateValues`), `project.workflow` and `column.workflowKey`, keeping `column.project` required. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the properties and that `column.required` still holds `project`.
- [ ] 2.2 Add `WorkflowColumnSyncListener` for workflow updates and project `workflow` changes: add, rename, reorder, retype and remove columns per project, moving tasks out of a removed column first. Verify: `tests/unit/Listener/WorkflowColumnSyncListenerTest.php` constructs the real OpenRegister event classes and covers each column operation and a removed column that holds tasks.
- [ ] 2.3 Add the workflows section to the admin page (columns and estimate scale), refuse deleting a workflow in use, and add `occ planninq:workflow:resync`. Verify: e2e `tests/e2e/shared-workflow.spec.ts` "one change reaches every project on the workflow"; a PHPUnit test for the command.
- [ ] 2.4 Show the workflow's estimate on task cards and in TaskDetail, and keep the label picker app-wide. Verify: e2e "story points on a Fibonacci scale" and "labels stay app-wide on a workflow".
- [ ] 2.5 Add the workflow picker to the project settings sidebar with the column mapping preview, and hide column editing on a project that follows a workflow. Verify: `tests/vitest/workflowMapping.spec.js` for the title match and the fallback count; e2e "moving a project onto a workflow".

## 3. Verification

- [ ] 3.1 `openspec validate projects-templates-shared-workflow --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
