---
kind: code
---

# A wiki per project with a page tree

## Why

A project has nowhere to keep its documentation. A search for "wiki" in `src/` and `lib/` finds nothing, and the only "Documentation" entry in the app is a footer link to the product site (`src/manifest.json:53`). Teams keep their working agreements, decisions and how-tos in a separate app, away from the tasks they describe, and anyone who joins the project has to be told where they are.

When documentation grows it needs structure. A flat list of pages stops working at around twenty, so teams need pages under pages, a table of contents and a way to move a page to another place in the tree.

OpenProject has a wiki per project with a table of contents and parent pages. Plane has project pages. Plane users ask for nested pages (https://github.com/makeplane/plane/issues/5620), which Plane sells as a paid feature.

Parity rows: `prj-wiki`, `prj-wiki-tree` in planninq's `openspec/parity/capabilities.json`.
Decision: build. The wiki is core to the projects area with two competitors rated yes, and the page tree has a feature request plus one competitor.

## What changes

- Every project gets a Wiki tab. Project members read its pages; members and managers write them, following the project's roles.
- A page is Markdown, written in Nextcloud's own Text editor when the Text app is on.
- Pages sit in a tree: add a subpage, move a page under another, reorder siblings, and browse the tree by keyboard.
- Every page keeps its history, and a manager can restore an earlier version.
- A page links to tasks and other pages, and images and files attach to the page.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-wiki`, `prj-wiki-tree`.

### `prj-wiki`: Keep wiki or documentation pages next to a project's tasks.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "grep -i wiki across src/ and lib/ returns nothing."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Inside a project > Wiki'; corpus: openproject/round4/M1-column.md 9.1 wiki pages searchable (wiki_page.rb:52) ; source read at v17.8.0: lib/redmine/menu_manager/wiki_menu_helper.rb:30-34 builds the project Wiki menu when the module is on; config/routes.rb:496-500 project wiki resources with new page; app/models/wiki_page.rb:31, :52 wiki pages are searchable"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md 'Pages the only feature toggle that defaults on' ; code-census.md §4 Page with versioning, linked to projects via ProjectPage. 'Wikis' as a distinct object and 'Nested Pages' are paid (open-core.md) ; source read at v1.4.2: apps/web/app/(all)/[workspaceSlug]/(projects)/projects/(detail)/[projectId]/pages/(list)/page.tsx and apps/web/core/components/pages/pages-list-main-content.tsx:39,61 createPage; apps/api/plane/app/urls/page.py:23 project pages route, apps/api/plane/app/views/page/base.py:129 create; apps/api/plane/db/models/page.py:135 ProjectPage, apps/api/plane/db/models/project.py:96 page_view default True"

### `prj-wiki-tree`: Organise documentation pages in folders or a tree of subpages.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/manifest.json:53 Documentation menu entry is an href to the product docs site. No in-app page model."
- Note: "Demand row mined from plane (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/makeplane/plane/issues/5620
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: app/views/wiki/index.html.erb:33 'Table of Contents' tree; config/locales/en.yml:1929 'Change parent page'; app/models/wiki_page.rb:37 acts_as_tree"

## Scope

### In scope

- A `wikiPage` schema with project-scoped rights, a parent and an order.
- The Wiki tab with a page tree, a page view and an editor.
- History and restore through OpenRegister's audit trail.
- Attachments through OpenRegister's object files.

### Out of scope

- Real-time editing by two people at once. The Text editor is used without a file-backed session, so a page has one writer at a time, as explained in design.md.
- A wiki that spans projects or lives in a portfolio.
- Importing an existing Collectives or OpenProject wiki.

## Impact

- Schema: new `wikiPage` schema in `lib/Settings/planninq_register.json`.
- Listener: the parent guard of `projects-grouping-hierarchy-fields` also covers `wikiPage.parent`.
- Manifest and registry: page `ProjectWiki` at `/projects/:id/wiki` and `/projects/:id/wiki/:pageId`, and a Wiki entry in `ProjectTabs`.
- Components: `WikiTree`, `WikiPageView`, `WikiPageEditor`, and `src/dialogs/WikiPageMoveDialog.vue`.
- Extends the flat spec `openspec/specs/projects.md`.
- Depends on: `projects-overview-logs-risks` (the project tabs), `projects-members-and-roles` (who may write), `projects-grouping-hierarchy-fields` (the guard listener it reuses).

## Risks

### Risk 1: the Text editor API is not a stable contract

**Severity**: Medium
**Mitigation**: The editor is wrapped in one component. When `window.OCA.Text.createEditor` is missing, the page falls back to a Markdown field with a preview, so the wiki works without the Text app. Task 2.2 checks the API on the Nextcloud versions planninq supports.

### Risk 2: two people save the same page

**Severity**: Medium
**Mitigation**: Opening the editor takes OpenRegister's object lock on the page, so a second writer sees who is editing. A save also sends the `updated` value it started from as `_expectedUpdated`. When the page changed in between, OpenRegister refuses the save and the writer sees "Someone else changed this page. Copy your text, then reload." Nothing is overwritten silently.

### Risk 3: another schema changes the schema count

**Severity**: Low
**Mitigation**: Task 1.3 updates `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72`.
