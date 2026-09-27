# Design: portfolios, subprojects and custom project fields

## Context

What exists at `de35541`:

- **Project list.** `ProjectList.vue` renders `filteredProjects` as one `ul` of `ProjectListItem` (`src/views/ProjectList.vue:86-92`), filtered by status chips (`:220-227`) and a search term (`:238-249`). `Boards.vue` shows the same projects as cards (`src/views/Boards.vue:22-40`).
- **Project schema.** No category, folder, portfolio or parent property (`lib/Settings/planninq_register.json:445-580`). `metadata` (`:576-581`) is a declared catch-all for import provenance. Read access is `members` only (`:404-443`).
- **Listeners.** Planninq already listens to OpenRegister object events: `TaskDependencyCleanupListener` handles `ObjectDeletingEvent` and uses `TaskScopeResolver` to tell a planninq task from any other object (`lib/Listener/TaskScopeResolver.php:88`). Listeners are registered in `lib/AppInfo/Application.php:466-510`.
- **Existing portfolio page.** `Portfolio.vue` at `/portfolio` aggregates capacity per project (`src/manifest.json:151`). It knows no portfolio entity.

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- `ObjectCreatingEvent` and `ObjectUpdatingEvent` are stoppable: a listener can reject a write with `stopPropagation()` and `setErrors()` (`lib/Event/ObjectUpdatingEvent.php:33`, `:126`, `:139`).

## Goals / non-goals

Goals:

- Portfolios as a shared way to group projects, with read rights for the people who run them.
- One level of meaning for hierarchy: a project can have a parent, and the parent sees its children.
- Admin-defined project fields with typed values.

Non-goals:

- Roll-ups beyond progress (next changes in this series).
- Task custom fields.

## Decisions

### Decision 1: a portfolio is its own schema, not a kind of project

`portfolio` has `title`, `description`, `color`, `managers` (user ids), `order` and an optional `riskScale` that overrides the app-wide `risk_scale`. Every signed-in user can read a portfolio's name, so the list can group by it. Admins and the portfolio's managers edit it. A project manager sets `project.portfolio`.

Alternative considered: a portfolio as a project with `kind: 'portfolio'` and children, as OpenProject does with workspace types. It would give a portfolio a board, tasks and members it has no use for, and it would mix grouping with the parent chain of Decision 3.

### Decision 2: portfolio managers read the projects in their portfolio

A portfolio office has to see every project it is accountable for, including ones it is not a member of. `project` gains `portfolioReaders`, a user id list, and a read rule `{"portfolioReaders": {"$contains": "$userId"}}`. It is derived, never typed in: the listener of Decision 3 writes it when `project.portfolio` changes, and a portfolio update rewrites it on every project in that portfolio. Any value a client sends is replaced.

The project-scoped schemas (`task`, `column`, `projectPhase`, `plannedTimeEntry`, and `risk` and `projectLogEntry` from `projects-overview-logs-risks`) gain the same path on read only, through whichever project-scoped mechanism `projects-members-and-roles` task 1.1 settles on. The role helper of that change returns `viewer` for a portfolio reader, so the board opens read-only.

This is the only derived list in the design, and it exists because of the `$lookup` question in `projects-members-and-roles` task 1.1. If that task shows a lookup rule scopes correctly, the derived list is replaced by a lookup on `portfolio.managers` and removed.

### Decision 3: a parent reference, guarded by a listener

`project.parent` is an optional `$ref` to another project. `ProjectHierarchyGuardListener` handles `ObjectCreatingEvent` and `ObjectUpdatingEvent` for the `project` schema and rejects a write when the new parent is the project itself or one of its descendants, or when the chain would be deeper than three levels (programme, project, subproject). It uses the same scope check as `TaskScopeResolver`.

A child can sit in a different portfolio from its parent. The parent's overview lists its children with their own progress, and adds their tasks to its progress figure. The project list shows children indented under their parent when both are visible, with an expand control that is a real button and announces its state.

Alternative considered: a `programme` schema above projects. It fixes the depth at two and cannot express a subproject under a project, which OpenProject users rely on.

### Decision 4: custom fields are definitions plus one value object

`fieldDefinition` has `key` (stable, lower camel case), `label`, `type` (`text`, `number`, `date`, `choice`, `person`, `boolean`), `options` (for `choice`), `required`, `order` and `appliesTo` (`project` only in this change). Admins manage them in Beheer, and project managers see them read-only there, following ADR-001 rule 7.

Values live in `project.customFields`, an object keyed by `key`. The guard listener checks each value against its definition and rejects wrong types and empty required fields. The Details tab of the project settings sidebar renders one input per definition, each with a visible label, and the overview shows the filled ones.

Alternative considered: letting admins add properties to the `project` schema itself. The next register import would overwrite them, because `lib/Settings/planninq_register.json` is the source the repair step imports.

## Risks / trade-offs

- [A deleted portfolio strands its projects] -> Deleting a portfolio clears `project.portfolio` and `portfolioReaders` on its projects in the same listener pass, and the dialog says how many projects that affects.
- [A deleted field definition leaves values behind] -> Values stay in `customFields` but are not shown. Restoring a definition with the same `key` brings them back.
- [Project lists get slower with grouping and trees] -> Grouping and nesting are done in the browser over the list `fetchProjects` already loads. Nothing new is fetched per group.
