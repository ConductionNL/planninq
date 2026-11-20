# Design: put the dependency editor and the blocked badge where people can reach them

## Context

Read at development `de35541`:

- `src/components/TaskDependencies.vue` takes props `task` and `projectTasks`
  (`:108-120`), renders "Blocked by", "Blocks", a same-project `NcSelect` picker and an inline
  error (`:1-76`), and calls `createEdge(selected.id, taskId)` (`:271`). No file imports it.
- `src/components/BlockedBadge.vue` takes `blocked` and `openBlockerCount`. No file imports it.
- `src/utils/taskHelpers.js:105` `isBlocked`, `:134` `deriveBlockedTaskIds`, `:160`
  `openBlockerIds`, `:184` `dependencyPickerCandidates`: exported, only used by the unmounted
  component.
- `src/store/dependencies.js:42` `fetchEdges`, `:81` `createEdge(blocker, blocked)` posting to
  `/apps/planninq/api/dependencies`, `:112` `deleteEdge`.
- `lib/Service/DependencyService.php:112` `create(blocker, blocked)` and `:215`
  `assertEdgeIsValid` (self, duplicate, cross-project, cycle via `DependencyGraph`).
- Schema `dependency`: `blocker`, `blocked`, `type` enum; authorization create, update and delete
  are admin-only, so writes go through `DependencyController` after its membership check.
- `src/views/ProjectTimeline.vue:86` draws `edgeLines` from the timeline payload's dependencies.
- `openspec/specs/task-dependencies/spec.md` carries the requirements with `@e2e exclude` notes
  saying the render layer is not built.

## Goals / non-goals

Goals: every dependency scenario in the main spec reachable and tested end to end; non-blocking
links. Non-goals: new validation rules, automatic rescheduling, merging.

## Decisions

### Decision 1: mount the existing component, do not rebuild it

TaskDetail loads the project's tasks and edges on mount and renders `<TaskDependencies>` below the
fields, passing `task` and `projectTasks`. The component already handles the picker, removal and
the inline server error.

### Decision 2: the board derives blocked ids once

ProjectBoard calls `fetchEdges` with the project's tasks, computes `deriveBlockedTaskIds` once per
render, and passes `blocked` and the open blocker count to each `TaskCard`, which renders
`BlockedBadge`. The badge never gates a move (main spec, "Blocked task can still be moved").

### Decision 3: a type on the link

The picker gains a type choice: "Blocked by" (default), "Relates to", "Duplicates". The store posts
`type`; `DependencyController` passes it on; `DependencyService::create` accepts it, runs the cycle
check for `blocks` only, and stores it. The section lists related links in their own group. Old
edges without a type are read as `blocks`.

### Decision 4: replace the e2e excludes with tests

Playwright tests in `tests/e2e/` cover adding, removing, a refused cycle, the badge appearing and
clearing, and the timeline arrow. The `@e2e exclude` notes in the main spec are replaced when this
change is archived.

## Risks / trade-offs

- [An extra edges request per board] -> one request per board load; edges are small.

## Built at HEAD (29 Sep 2026)

- The picker offers "Blocked by", "Relates to" and "Duplicates"; `DependencyService::create()` takes the type, refuses a type the schema does not name, and runs the cycle check for `blocks` only. A related link is stored with this task as `blocker` and the picked task as `blocked`; the direction carries no meaning for a non-blocking link.
- `fetchEdges()` read one page of 20 links and dropped `type`; it now asks for 1000 and keeps the type, so a busy instance no longer loses links on the task page and the board.
- The timeline e2e sits in `tests/e2e/task-dependencies.spec.ts` with the other link tests. Draft PR #641 (blocked badge and filter) was untouched since 23 Sep; this change supersedes its badge.
