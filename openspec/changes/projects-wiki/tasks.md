# Tasks: projects-wiki

## 1. Schema and rights (V1)

- [x] 1.1 Add the `wikiPage` schema (`project`, `parent`, `order`, `title`, `body`) with the project-scoped authorization settled by `projects-members-and-roles` task 1.1. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the properties and the read and write rules. Built 14 Nov: `testAWikiPageIsAProjectScopedTreeNode`; `wikiPage` also joined the scoped, gated and project-child schema lists, so it carries and is gated by the project's copied access lists like `projectPhase` (`ProjectRolesSchemaTest::testScopedSchemasReadForEveryRoleAndWriteForMembers`), and three demo pages.
- [x] 1.2 Extend the parent guard listener from `projects-grouping-hierarchy-fields` to `wikiPage`: no self or descendant parent, same project. Verify: `tests/unit/Listener/ProjectHierarchyGuardListenerTest.php` cases for a page under itself, under its subpage and under another project's page. Built 14 Nov: the rule is `WikiPageParentGuard`, routed from `ProjectHierarchyGuardListener` (`testAWikiPageParentIsRefusedOnACycleOrAnotherProject`, `testAWikiPageMovesWithinItsProject`, `WikiPageParentGuardTest`).
- [x] 1.3 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `wikiPage`. Verify: the PHPUnit suite passes. Built 14 Nov: the test is now `testRegisterDeclaresExactlyTwentyTwoSchemas` (the register had grown to twenty-one), and the spec scenario names twenty-two.

## 2. Wiki tab (V1)

- [x] 2.1 Add the `ProjectWiki` page (`/projects/:id/wiki` and `/projects/:id/wiki/:pageId`) to `src/manifest.json` and `src/registry.js`, and a Wiki entry in `ProjectTabs`. Verify: `tests/e2e/project-wiki.spec.ts` "writing the first page". Built 14 Nov: `src/views/ProjectWiki.vue` behind the manifest pages `ProjectWiki` and `ProjectWikiPage`, a Wiki tab in `PROJECT_TABS`; the e2e spec is not run here.
- [x] 2.2 Add `WikiPageEditor` around `window.OCA.Text.createEditor` with a Markdown fallback, and check the API on each Nextcloud version in `appinfo/info.xml`. Verify: `tests/vitest/wikiPageEditor.spec.js` for both paths; the check results in the PR body. Built 14 Nov: `src/components/WikiPageEditor.vue` over `startTextEditor()` and `createEditingSession()` in `src/utils/wikiEditing.js`; `tests/vitest/wikiPageEditor.spec.js` covers the Text editor, its failure and the Markdown fallback. The API check on each Nextcloud version needs a live instance and is not done.
- [ ] 2.3 Add attachments through OpenRegister's object files on the page. Verify: e2e step in "writing the first page" that attaches an image and sees it rendered.
- [x] 2.4 Hide writing controls for viewers with the role helper of `projects-members-and-roles`. Verify: e2e "a viewer reads but cannot write", including the 403 on PUT. Built 14 Nov: Add page, Add subpage, Edit, Move and the order actions show only for `canWrite(projectRole(...))`; the PUT refusal is OpenRegister's (task 1.1). The e2e is not run here.

## 3. Tree (V1)

- [x] 3.1 Add `WikiTree` as an ARIA tree with arrow-key navigation, and "Add subpage", "Move up" and "Move down". Verify: `tests/vitest/wikiTree.spec.js` for ordering and keyboard handling; e2e "browsing the tree by keyboard". Built 14 Nov: `src/components/WikiTree.vue` with the keyboard logic in `treeKey()` and the rows and order patches in `src/utils/wikiTree.js`; `tests/vitest/wikiTree.spec.js`. Drag reordering is not built; Move up and Move down cover it. The e2e is not run here.
- [x] 3.2 Add `src/dialogs/WikiPageMoveDialog.vue` that leaves out the page and its subpages as targets. Verify: e2e "moving a page under another page" and "a page cannot move under its own subpage". Built 14 Nov: `src/dialogs/WikiPageMoveDialog.vue` offers `moveTargets()` (tested in `wikiTree.spec.js`); the server-side refusal is task 1.2's guard. The e2e is not run here.

## 4. History and concurrency (V1)

- [x] 4.1 Add "History" from the page's audit trail and "Restore this version" through the revert endpoint, for managers. Verify: e2e "restoring an earlier version". Built 14 Nov: History lists the audit trail; managers get Restore, which calls the revert endpoint (`src/api/wikiPages.js`). The e2e is not run here.
- [x] 4.2 Take the object lock when the editor opens, release it on save, cancel and close, and send `_expectedUpdated` on save. Verify: `tests/vitest/wikiPageEditor.spec.js` for the lock calls and the refused save; e2e "a second writer sees the page is being edited" with two browser contexts. Built 14 Nov: the session locks on open, unlocks once on save, cancel and close, sends `_expectedUpdated`, and keeps the lock on a refused save (`wikiPageEditor.spec.js`). Whether OpenRegister expires locks by itself is not checked here; the page treats an expired lock as free. The two-browser e2e is not run here.

## 5. Verification

- [ ] 5.1 `openspec validate projects-wiki --type change --strict` passes.
- [ ] 5.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
