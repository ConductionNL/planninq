# Design: a wiki per project with a page tree

## Context

What exists at `de35541`:

- **No wiki.** Nothing in `src/`, `lib/` or `lib/Settings/planninq_register.json` models a page. The footer entry "Documentation" links to the product site (`src/manifest.json:53`).
- **Project pages.** The project pages are `type: "custom"` manifest pages resolved through `src/registry.js:42-51`. `projects-overview-logs-risks` adds a shared `ProjectTabs` row.
- **Task collaboration.** The task detail uses `CnObjectSidebar` for notes, files and audit trail, all read from OpenRegister's per-object endpoints (`src/views/TaskDetail.vue:124-137`). Files attached there land in Nextcloud Files under the register's folder.
- **Integration principle.** `docs/ARCHITECTURE.md` section 3.7 says to reuse Nextcloud's own objects where possible, and names Files for "project documents".
- **Editor.** Nothing in `src/` uses the Text app's editor today.

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- Per-object audit trails at `GET /api/objects/{register}/{schema}/{id}/audit-trails` (`appinfo/routes.php:1344`) and a revert at `POST /api/objects/{register}/{schema}/{id}/revert` (`:1453`).
- Object locks at `POST /api/objects/{register}/{schema}/{id}/lock` and `/unlock` (`:1246-1247`).
- Optimistic concurrency: a write that carries `_expectedUpdated` or `If-Match` is refused when the object changed since (`lib/Controller/ObjectsController.php:3912`, `:4948-4975`).

## Goals / non-goals

Goals:

- Project documentation next to the tasks, with the same rights as the project.
- A page tree that stays usable at a hundred pages.
- No lost work when two people edit.

Non-goals:

- Real-time co-editing.
- A wiki outside a project.

## Decisions

### Decision 1: pages are planninq OpenRegister objects, edited with the Nextcloud Text editor

A page is a `wikiPage` object: `project` (`$ref` project), `parent` (`$ref` wikiPage, empty for a top-level page), `order`, `title` and `body` (Markdown). Its rights are the project's rights, copied from the project-scoped rule that `projects-members-and-roles` settles: everyone who can read the project reads its pages, managers and members write them, viewers read only.

The body is edited with the Text app's editor API, `window.OCA.Text.createEditor`, which edits Markdown that is not a file. When the Text app is off, the page falls back to a Markdown field with a preview. Images and files attach to the page through OpenRegister's object files, so they land in Nextcloud Files like task attachments do.

The reason is access. A project's rights come from its roles, its groups and its portfolio readers (`projects-members-and-roles`, `projects-grouping-hierarchy-fields`), and OpenRegister checks them on every read. A page stored as an OpenRegister object inherits them with no second copy to keep in step.

Alternative considered, and the main one: **Nextcloud Collectives**. Collectives gives a page tree, versions and real-time editing through Text, stored as Markdown files. Its access runs through a Nextcloud Team (Circle) per collective. Planninq would have to create a Team per project and keep its members in step with the project's users, groups, roles and portfolio readers, with two places deciding who may read a page. Collectives is also not shipped with Nextcloud, so the wiki would be missing on a default install. Planninq could link a project to an existing collective as a later, separate integration.

Alternative considered: **Markdown files in a shared folder** edited with Text. The files would belong to one user and reach the others through shares, which again have to follow every change of membership.

This follows ARCHITECTURE.md section 3.7 where it applies: the editor is Nextcloud's own, and attachments are Nextcloud files. The page model is planninq's, like projects and tasks are.

### Decision 2: the tree is a parent reference with an order

Each page has one `parent` and an `order` among its siblings. The Wiki tab shows the tree on the left as an ARIA tree: arrow keys move, Right and Left open and close, Enter opens a page. "Add subpage" creates a child. "Move" opens `src/dialogs/WikiPageMoveDialog.vue` with the tree to pick a new parent, and siblings reorder by drag or by "Move up" and "Move down" actions for keyboard users.

The parent guard listener from `projects-grouping-hierarchy-fields` also handles `wikiPage`: a page cannot move under itself or its own subpage, and a page's parent must belong to the same project.

Deleting a page with subpages asks whether to move them up a level or delete them too.

### Decision 3: history is the audit trail, and restore is a revert

"History" on a page lists its audit trail entries with author and time. A manager can open an earlier version and press "Restore this version", which calls OpenRegister's revert. The restore is itself a new entry in the history.

### Decision 4: one writer at a time

Opening the editor takes OpenRegister's lock on the page; another member sees "Ada Jansen is editing this page" and a read-only view. The lock is released on save, on cancel and when the page is closed. Every save also sends `_expectedUpdated`, so a save after a lock expired still cannot overwrite a newer version.

### Decision 5: links and search

A page links to a task with the task's key or URL, and to another page by title through the editor's link picker. The Wiki tab's search box filters the tree by title and searches bodies through OpenRegister's search, scoped to the project.

## Risks / trade-offs

- [The Text editor API changes between Nextcloud versions] -> One wrapper component, a fallback field, and task 2.2 checks the API on each supported version.
- [A lock left behind by a closed tab] -> The page shows who took the lock and when, and a manager can release it through the unlock endpoint. Task 4.2 checks whether OpenRegister expires locks by itself and records the answer here.
- [Deep trees get hard to read] -> The tree opens only the path to the current page, and the page shows its breadcrumb.

## Open questions

- Should a project be able to link an existing Collectives collective instead of, or next to, its own wiki? Proposed as a later integration change under Beheer.
