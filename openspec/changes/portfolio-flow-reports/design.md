# Design: cumulative flow, lead and cycle time, and reports you build yourself

## Context

What exists at `de35541`:

- **Reports.** `src/manifest.json:152-167` declares a Reports page with three cards: Task status, Capacity and Time spent. Task status (`:168-192`) is a declarative dashboard of stat cards, two donuts grouped by `status` and `priority` (`:179-180`) and a due-soonest table. Its note says OpenRegister's aggregation applies only scalar equality filters, and silently ignores anything else (`:170`).
- **Timestamps.** `task.completedAt` (`lib/Settings/planninq_register.json:317-323`) and `task.resolvedAt` (`:348-354`) are declared; nothing writes or reads them. `lib/Portal/PortalContributionProvider.php:179` lists `completedAt` as a field it passes on.
- **Columns.** The change `boards-configurable-columns` (on `development` since `e31a95d`) makes the board render `column` objects and maps each column to a status. The lane decision for this pass gives it the stamping of `completedAt` too: a server-side `TaskCompletionListener` sets it when a task becomes done and clears it when the task leaves done. Its design text at `e31a95d` still describes the card move writing `completedAt` from the browser (Decision 1); either way this change only reads the field.
- **Audit trail.** OpenRegister records each object change. Per object, readers of the object can fetch it at `GET /apps/openregister/api/objects/{register}/{schema}/{id}/audit-trails`; the index across objects at `/api/audit-trails` is admin-only (`lib/Controller/AuditTrailController.php:265-280` at `ConductionNL/openregister` development `c53dd0685`).
- **Read endpoint precedent.** `TimelineController::forProject` (`lib/Controller/TimelineController.php:123-205`) is a read-only, RBAC-scoped server read that shapes project data for one screen.
- **Saved queries.** Nothing in `src/` saves a query. OpenRegister has saved views (`lib/Db/View.php` at `c53dd0685`) with a `query` and a `presentation` whose `viewType` is validated as `table`, `kanban` or `calendar` (`:152-164`).

## Goals / non-goals

Goals:

- A cumulative flow diagram per project and per portfolio.
- Lead time and cycle time per task, with average and 85th percentile.
- Reports users build, save and share without seeing more than they may.

Non-goals:

- Writing timestamps (owned by `boards-configurable-columns`).
- Sprint charts.

## Decisions

### Decision 1: the flow is replayed from the audit trail on the server

`FlowHistoryService` takes a project and a window. It first finds the project through ObjectService with RBAC on, as `TimelineController` does, so a caller who cannot read the project gets 403. It then reads the audit trail entries of that project's tasks through OpenRegister's audit trail service, server-side, keeping only changes to `column`, `status` and `completedAt`, and replays them into:

- for every day in the window, the number of tasks in each column at the end of that day;
- for every task finished in the window, its created time, the time it first left the project's first column (or first became `in_progress`), and its finish time.

`FlowController` serves this at `GET /apps/planninq/api/projects/{id}/flow?from=&to=` and, for every project in a portfolio the caller can read, at `GET /apps/planninq/api/portfolios/{id}/flow`. It writes nothing.

Finished days are cached per project and day through `ICacheFactory`, because a past day cannot change; today is always recomputed.

Alternative considered: a nightly job that stores per-column counts in a snapshot schema, as Kanboard does. Reading is cheap, but it has no history before the day it was switched on, and the audit trail already holds the same facts.

Alternative considered: reading the per-object audit trail from the browser. A project of 500 tasks would be 500 requests per chart.

### Decision 2: lead time ends at `completedAt`, cycle time starts at the first move off the first column

Lead time is `completedAt` minus the task's creation time. Cycle time is `completedAt` minus the first time the task left its project's first column, or first became `in_progress` when the audit trail has no column change. A task that went straight from the first column to done has a cycle time of zero and is shown as such. When `completedAt` is empty on a done task, the last change to `done` in the audit trail is used and the task is counted as "estimated".

The Flow tab shows the cumulative flow as a stacked area chart per column in board order, with a table view of the same numbers for screen readers and keyboard users, and the lead and cycle time as a scatter of finished tasks with lines for the average and the 85th percentile, plus the ten slowest tasks as links. Colours come from the columns' own colour tokens, and each series is also named in a legend and in the table.

### Decision 3: a saved report is a planninq object that stores a query, not results

`report` has `title`, `description`, `owner` (from OpenRegister's metadata), `shared` (`private` or `readers`), `scope` (a list of projects, or one portfolio), `filters` (equality on `status`, `priority`, `assignedTo`, `labels`, `column`, `issueType`), `groupBy` (one of `status`, `priority`, `assignedTo`, `labels`, `project`, `column`), `metric` (`count`, or `sum` of `storyPoints` or `estimatedDuration`) and `display` (`table`, `bar`, `donut`).

The report page renders a chart through the same widget contract the manifest dashboards use (`dataSource.aggregate` with `groupBy` and `metric`, `src/manifest.json:179`), so aggregation stays on OpenRegister and only equality filters are offered, the ones it applies. A table display lists the matching tasks through the object API.

Every viewer runs the report with their own rights. A shared report that covers projects a viewer cannot read shows the rest and says "2 of 5 projects in this report are not visible to you".

Alternative considered: OpenRegister's saved views. They already store a query and are shared with `isPublic`, but their presentation accepts only `table`, `kanban` and `calendar`, so a chart would need an OpenRegister change first. A later change can move reports onto views when a chart presentation exists there.

## Risks / trade-offs

- [The audit trail misses changes made while recording was off] -> The report shows the number of tasks without history in the window, instead of assuming they did not move.
- [A filter the aggregation ignores] -> The builder only offers equality filters, and a vitest spec asserts the built data source has no other operator.
- [Big portfolios] -> The portfolio flow is limited to 50 projects per request, like the portfolio timeline of `portfolio-status-overview`.
