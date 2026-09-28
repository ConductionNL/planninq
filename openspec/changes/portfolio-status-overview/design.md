# Design: portfolio overview, status per aspect and a multi-project timeline

## Context

What exists at `de35541`:

- **Portfolio page.** `Portfolio.vue` loads `fetchProjects({ status: 'active' })` (`src/views/Portfolio.vue:134`), then `fetchTasks` per project, and renders one table row per project with members, open, overdue and an open-work bar (`:29-69`). `fetchProjects` keeps only projects whose `members` include the viewer (`src/store/projects.js:195-197`). The page is reached from the Reports card "Capacity" (`src/manifest.json:163`); the menu has no Portfolio entry (`:48-58`).
- **Project data.** `project` has `status`, `startDate`, `endDate` and the budget fields (`lib/Settings/planninq_register.json:445-580`), and no health or report field.
- **Timeline.** `TimelineController::forProject` (`lib/Controller/TimelineController.php:123-205`) finds the project through ObjectService with RBAC on, reads its tasks with the single filter `project` (`:214-240`) and its dependencies (`:264`), and returns rows with start, due, duration and status. `src/api/timeline.js` calls it for one project. `ProjectTimeline.vue` draws it.
- **Open navigation change.** `adopt-five-menu-navigation-ia` plans the Portfolio menu of ADR-001. It is not built.

From other changes of this pass: portfolios and `portfolioReaders` (`projects-grouping-hierarchy-fields`), risk scores and bands (`projects-overview-logs-risks`), budget and actual cost per project (`portfolio-finance`).

## Goals / non-goals

Goals:

- One list of a portfolio's projects with the facts a portfolio office asks for.
- GROTIK status per project with history, rolled up per portfolio.
- Several projects on one read-only timeline.

Non-goals:

- Editing on the timeline.
- Automatic status without a person deciding.

## Decisions

### Decision 1: a status report is its own object; the latest one is copied onto the project

`projectStatusReport` has `project`, `reportDate`, and for each aspect a status (`onTrack`, `atRisk`, `offTrack`) and a note: `money`, `organisation`, `time`, `information`, `quality` and `risk`, as flat properties such as `statusMoney` and `noteMoney`. `overall` is a materialised `x-openregister-calculations` value: the worst of the six. The author and save time come from OpenRegister's metadata. Its rights follow the project: managers write, everyone who can read the project reads.

A listener on report create, update and delete copies the newest report's statuses and date onto the project's `health` fields. The portfolio overview then reads projects only, and OpenRegister's aggregation can count `healthMoney` per value with an equality filter on `portfolio`, the only filter shape it applies (`src/manifest.json:170`).

Alternative considered: six status fields on the project, edited in place. It is simpler, but every report overwrites the last one, and the tender asks for status reporting over time.

### Decision 2: planninq suggests, the project leader decides

The report form (`/projects/:id/status`, a tab in `ProjectTabs`) pre-selects nothing. Next to three aspects it shows a suggestion with its reason:

- money: at risk when actual plus commitments pass 90 percent of the budget, off track above 100 percent (from `portfolio-finance`);
- time: at risk when any open task is past its due date, off track when the project's `endDate` has passed with open tasks;
- risk: the band of the highest-scored open risk (from `projects-overview-logs-risks`).

Organisation, information and quality have no suggestion. The thresholds are named constants in one helper so a later admin setting can replace them.

### Decision 3: the portfolio overview is a new page under Portfolio

`PortfolioStatus` at `/portfolio/status` has a portfolio picker and a table with a row per project the viewer can read in that portfolio, including projects the viewer reaches only as a portfolio reader. Columns: project, lifecycle status, progress (done over not cancelled), start and end date, budget and actual cost when the viewer may see money, the six aspect statuses, and the last report date. Each aspect status is shown as a word and an icon as well as a colour token, so colour is never the only signal.

Above the table, the roll-up shows per aspect how many projects are on track, at risk and off track, and the portfolio's state for that aspect, which is the worst of its projects. A project without any report counts as "No report" and is listed separately, not as on track.

The existing `Portfolio.vue` stays the capacity page (see `portfolio-people-capacity`). Where the Portfolio landing page sits is decided by `adopt-five-menu-navigation-ia`; until then the Reports page gets cards for the status overview and the timeline.

### Decision 4: the multi-project timeline reads through one endpoint

`TimelineController` gains `GET /apps/planninq/api/timeline?projects=<id,id,...>` for up to 50 projects. It runs the same RBAC-scoped read as `forProject` for each id and leaves out any project the caller cannot read, listing it in `skipped` rather than failing the whole answer. Each project comes back with a summary span (its `startDate` and `endDate`, else the earliest start and latest due date of its tasks), its phases and its task rows. Dependencies between tasks of two different projects in the answer are included as edges.

`PortfolioTimeline` at `/portfolio/timeline` draws one summary bar per project, sorted by start date, which opens into phases and tasks on click or Enter. It is read-only and reuses the bar and axis code of `ProjectTimeline.vue`.

Alternative considered: calling the per-project endpoint once per project from the browser. For a portfolio of 40 projects that is 40 requests before anything draws, and cross-project edges would need a second pass.

## Risks / trade-offs

- [A 50-project answer is slow] -> The endpoint reads each project's tasks with one filtered query, as `forProject` does; task 3.1 measures 50 projects of 100 tasks and the limit drops if the answer takes over two seconds.
- [Portfolio readers without the `$lookup` answer] -> The overview relies on `portfolioReaders` from `projects-grouping-hierarchy-fields`, which works without a lookup rule.
- [A stale report looks current] -> The table shows the report date, and a report older than the admin's reporting period (default 30 days) is marked "Out of date" in text.

## Built at HEAD (first PR, 29 Sep 2026)

The code at `94c2eda` differs from what this design assumed in four places. The first PR follows the code and says so here.

- **Who writes a report.** `projects-members-and-roles` is not built, so there is no manager role. The project owner and admins write reports, as they manage columns. `ProjectStatusListener` enforces it on `ObjectCreatingEvent`, `ObjectUpdatingEvent` and `ObjectDeletingEvent` with the error code `planninq-not-the-project-owner`; members read reports through the `members` rule like every project-scoped schema. When the roles land, the owner check widens to managers.
- **Where the copy runs.** ADR-078 (gate-61) keeps synchronous writes out of post-event listeners, so the copy runs in the report's own pre-event: the listener knows the report's new data (or that it is leaving) and works out the newest report from the stored ones plus that one. It writes the project inside OpenRegister's `SystemOperationContext`. Newest is the latest `reportDate`; on a tie the report being saved wins.
- **How the health fields are protected.** OpenRegister's `readOnly` would refuse the project settings form, which sends the whole project back with its health. The same listener instead restores the stored health on a project update from anyone but the system scope, and empties it on a project create. Nobody gets an error; nobody changes the health either.
- **The money suggestion.** No cost is recorded anywhere yet: `portfolio-finance` adds cost lines. The money suggestion therefore says "No suggestion: no costs are recorded for this project yet." (or "the project has no budget"), and `suggestMoney()` already applies the 90 and 100 percent thresholds, named in `src/utils/statusReports.js` as `MONEY_AT_RISK_RATIO`, for when `portfolio-finance` passes a cost.
- **A wiring gap it found.** `ProjectMemberAccessListener` was subscribed for `task`, `column`, `projectPhase` and `plannedTimeEntry` only, while `ProjectMembershipService::SCOPED_SCHEMAS` also held `projectLogEntry` and `risk` (#708). Log entries and risks were therefore never stamped with their project's members on a live instance. The subscription now lists every scoped schema, including `projectStatusReport`, and `BoardColumnWiringTest` asserts the two lists are equal. The app version bump runs `BackfillProjectMembers`, which stamps the entries and risks already written.

Sections 2 and 3 need portfolios (`project.portfolio` and portfolio readers from `projects-grouping-hierarchy-fields`), which are not built. They follow in a second PR once portfolios exist, and this change is archived with that PR.
