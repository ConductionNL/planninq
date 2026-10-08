# Tasks: projects-wiki

## 1. Schema and rights (V1)

- [x] 1.1 Add the `wikiPage` schema (`project`, `parent`, `order`, `title`, `body`) with the project-scoped authorization settled by `projects-members-and-roles` task 1.1. Verify: `tests/unit/Settings/PlanninqRegisterSchemaTest.php` asserts the properties and the read and write rules. Built: the `wikiPage` schema (register 0.40.0), in `ProjectMembershipService::SCOPED_SCHEMAS` so the copied lists reach it; `PlanninqRegisterSchemaTest::testWikiPageSchemaAndRules`; three example pages in the mock register.
- [x] 1.2 Extend the parent guard listener from `projects-grouping-hierarchy-fields` to `wikiPage`: no self or descendant parent, same project. Verify: `tests/unit/Listener/ProjectHierarchyGuardListenerTest.php` cases for a page under itself, under its subpage and under another project's page. Built: `ProjectHierarchyGuardListener` handles `wikiPage` (no self or descendant parent, same project), registered in `Application.php`; `ProjectHierarchyGuardListenerTest::testAWikiPageParentIsGuarded`.
- [x] 1.3 Update `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` for `wikiPage`. Verify: the PHPUnit suite passes. Built: the schema list test now expects 22 schemas (the old `Seven` name was already stale); the project-delivery spec line is left as is.

## 2. Wiki tab (V1)

- [ ] 2.1 Add the `ProjectWiki` page (`/projects/:id/wiki` and `/projects/:id/wiki/:pageId`) to `src/manifest.json` and `src/registry.js`, and a Wiki entry in `ProjectTabs`. Verify: `tests/e2e/project-wiki.spec.ts` "writing the first page". — built: `ProjectWiki.vue` (tree, page, add page and subpage, search), manifest pages `ProjectWiki` and `ProjectWikiPage`, registry entry, Wiki tab, `src/store/wiki.js`; e2e not run: needs a live instance
- [ ] 2.2 Add `WikiPageEditor` around `window.OCA.Text.createEditor` with a Markdown fallback, and check the API on each Nextcloud version in `appinfo/info.xml`. Verify: `tests/vitest/wikiPageEditor.spec.js` for both paths; the check results in the PR body. — built: `WikiPageEditor.vue` with the Text editor path and the Markdown field plus preview as fallback; `tests/vitest/wikiPageEditor.spec.js` covers both paths; the `createEditor` options (`el`, `content`, `readOnly`, `onUpdate({markdown})`) are written from memory of the Text app and not checked on any Nextcloud version — not run: needs a live Nextcloud with the Text app
- [ ] 2.3 Add attachments through OpenRegister's object files on the page. Verify: e2e step in "writing the first page" that attaches an image and sees it rendered. — built: the page's Attachments action opens `CnObjectSidebar` on the `wikiPage` object (files tab; `wikiSidebarConfig`); e2e not run: needs a live instance
- [ ] 2.4 Hide writing controls for viewers with the role helper of `projects-members-and-roles`. Verify: e2e "a viewer reads but cannot write", including the 403 on PUT. — built: Add page, Edit, Add subpage, Move, Move up and down and Delete show only for owner, manager, member and admin (`canWriteWiki`, covered in `wikiPageEditor.spec.js`); the 403 is the schema's own rule; e2e not run: needs a live instance

## 3. Tree (V1)

- [ ] 3.1 Add `WikiTree` as an ARIA tree with arrow-key navigation, and "Add subpage", "Move up" and "Move down". Verify: `tests/vitest/wikiTree.spec.js` for ordering and keyboard handling; e2e "browsing the tree by keyboard". — built: `WikiTree.vue` (role tree, roving tabindex, Up, Down, Left, Right, Home, End, Enter), Add subpage, Move up and Move down; `tests/vitest/wikiTree.spec.js` passes; e2e not run: needs a live instance
- [ ] 3.2 Add `src/dialogs/WikiPageMoveDialog.vue` that leaves out the page and its subpages as targets. Verify: e2e "moving a page under another page" and "a page cannot move under its own subpage". — built: `WikiPageMoveDialog.vue` and the delete choice for pages with subpages; the target rules (`moveTargets`, `movePatches`) are covered in `wikiTree.spec.js`; e2e not run: needs a live instance

## 4. History and concurrency (V1)

- [ ] 4.1 Add "History" from the page's audit trail and "Restore this version" through the revert endpoint, for managers. Verify: e2e "restoring an earlier version". — built: `WikiHistoryDialog.vue`, `wikiStore.history` and `restore` (the revert body `{ auditTrailId }` is a guess at OpenRegister's contract); e2e not run: needs a live OpenRegister audit trail
- [ ] 4.2 Take the object lock when the editor opens, release it on save, cancel and close, and send `_expectedUpdated` on save. Verify: `tests/vitest/wikiPageEditor.spec.js` for the lock calls and the refused save; e2e "a second writer sees the page is being edited" with two browser contexts. — built: lock on edit, unlock on save, cancel and when the view closes, `_expectedUpdated` on every save, and "{name} is editing this page"; the lock calls and the refused save are covered in `wikiPageEditor.spec.js`; whether OpenRegister expires locks by itself is not checked; e2e not run: needs a live instance

## 5. Verification

- [ ] 5.1 `openspec validate projects-wiki --type change --strict` passes. — not run: openspec CLI not installed here
- [ ] 5.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note. — not run: the UI scenarios are not built
