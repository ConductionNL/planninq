# Tasks: boards-cross-project-board

## 1. Schema

- [ ] 1.1 Add the `boardView` schema (title, owner, members, projects with 1 to 20 items; no column, order or task properties) with owner and members authorization, list it in the register, bump versions and the exact schema count test. Verify: PHPUnit `PlanninqRegisterSchemaTest::testBoardViewSchemaHoldsNoPlacement` and `testBoardViewAuthorization`; `composer test:unit` green.
- [ ] 1.2 Live check of the authorization: a user neither owner nor member cannot read a view; a member cannot update it. Verify: Newman requests "non-member cannot read a cross-project view" and "view member cannot edit it" in `tests/integration/planninq.postman_collection.json`.

## 2. View page

- [ ] 2.1 Extract `StatusLanes` (lanes, drag, keyboard move menu) from `ProjectBoard` without behaviour change. Verify: existing Playwright `tests/e2e/kanban-board.spec.ts` stays green.
- [ ] 2.2 `ProjectsView` page at `/boards/views/:id` (manifest and registry) reading every listed project's tasks in parallel, with the hidden and missing project notices and the reused filter bar. Verify: vitest `tests/vitest/projectsView.spec.js` "merges tasks of all readable projects", "counts hidden and missing projects" and "applies the board filter"; Playwright `tests/e2e/projects-view.spec.ts` "view shows tasks of two projects with project chips".
- [ ] 2.3 Project chip on `TaskCard`. Verify: vitest `tests/vitest/projectsView.spec.js` "card chip carries project title and swatch".
- [ ] 2.4 Move resolution: target lane to the first column of the task's own project mapping that status; same write as the project board; refusal when no column fits. Verify: vitest `tests/vitest/projectsView.spec.js` "resolves the first column mapping the status" and "no matching column refuses the move"; Playwright `tests/e2e/projects-view.spec.ts` "moving a card places it in its project's column", "keyboard move works on the view" and "project without a matching column refuses the move".

## 3. Borden

- [ ] 3.1 "Cross-project views" section and `src/dialogs/ProjectsViewEditDialog.vue` on `src/views/Boards.vue`. Verify: Playwright `tests/e2e/projects-view.spec.ts` "user saves a view of two projects", "project picker offers only member projects" and "view appears for the person it is shared with".
- [ ] 3.2 A viewer outside one of the projects sees none of its tasks. Verify: Playwright `tests/e2e/projects-view.spec.ts` "viewer outside a project sees the hidden-projects notice".

## 4. Verification

- [ ] 4.1 `openspec validate boards-cross-project-board --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
