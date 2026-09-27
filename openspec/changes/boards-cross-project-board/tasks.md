# Tasks: boards-cross-project-board

## 1. Schema

- [ ] 1.1 Add the `board` schema (title, owner, members, projects with 1 to 20 items) with owner and members authorization, list it in the register, bump versions and the exact schema count test. Verify: PHPUnit `PlanninqRegisterSchemaTest::testBoardSchemaAuthorization` and `composer test:unit` green.
- [ ] 1.2 Live check of the authorization: a user neither owner nor member cannot read a board; a member cannot update it. Verify: Newman requests "non-member cannot read a shared board" and "board member cannot edit it" in `tests/integration/planninq.postman_collection.json`.

## 2. Board page

- [ ] 2.1 Extract `StatusLanes` (lanes, drag, keyboard move menu, label filter) from `ProjectBoard` without behaviour change. Verify: existing Playwright `tests/e2e/kanban-board.spec.ts` stays green.
- [ ] 2.2 `SharedBoard` page at `/boards/:id` (manifest and registry) reading every listed project's tasks in parallel, with the hidden and missing project notices. Verify: vitest `tests/vitest/sharedBoard.spec.js` "merges tasks of all readable projects" and "counts hidden and missing projects"; Playwright `tests/e2e/shared-board.spec.ts` "board shows tasks of two projects with project chips".
- [ ] 2.3 Project chip on `TaskCard`. Verify: vitest `tests/vitest/sharedBoard.spec.js` "card chip carries project title and swatch".
- [ ] 2.4 Status moves on the shared board. Verify: Playwright `tests/e2e/shared-board.spec.ts` "moving a card changes its status in its own project" and "keyboard move works on a shared board".

## 3. Borden

- [ ] 3.1 "Shared boards" section and `src/dialogs/SharedBoardEditDialog.vue` on `src/views/Boards.vue`. Verify: Playwright `tests/e2e/shared-board.spec.ts` "user creates a shared board from two projects", "project picker offers only member projects" and "shared board appears for the person it is shared with".
- [ ] 3.2 A viewer who is not in one of the projects sees none of its tasks. Verify: Playwright `tests/e2e/shared-board.spec.ts` "viewer outside a project sees the hidden-projects notice".

## 4. Verification

- [ ] 4.1 `openspec validate boards-cross-project-board --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
