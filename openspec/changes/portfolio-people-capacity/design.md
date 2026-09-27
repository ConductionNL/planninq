# Design: capacity per person across projects

## Context

What exists at `de35541`:

- **Page and promise.** `src/manifest.json:151` declares the `Portfolio` page at `/portfolio` with the note "Capacity across projects: one row per assignee aggregating allocation over the projects they appear in". The Reports card "Capacity" (`:163`) says "How allocation is spread across the people working on projects".
- **What it does.** `Portfolio.vue` loads `fetchProjects({ status: 'active' })` (`src/views/Portfolio.vue:134`), which keeps only projects whose `members` include the viewer (`src/store/projects.js:195-197`), reads each project's tasks with `fetchTasks` (`Portfolio.vue:137`), and pushes one row per project with its member count, open and overdue counts (`:139-147`). The table has the columns Project, Members, Open, Overdue and Open work (`:29-69`).
- **Helper.** `summariseProjectTasks` (`src/utils/portfolioHelpers.js:28-41`) counts open tasks (status not in `CLOSED_STATUSES`) and overdue ones through `dueDateStatus`. It is unit-tested in `tests/vitest/portfolio.spec.js`.
- **Task fields.** `assignedTo` is one uid (`lib/Settings/planninq_register.json:255-259`). `estimatedDuration` and `remainingEstimate` are minutes (`:280-284`, `:361-366`). Lane A's `tasks-assignment-priority-labels` (on `development` since `e31a95d`) keeps `assignedTo` as the primary assignee and adds `sharedWith`, an array of further uids.
- **Existing spec.** `openspec/specs/capacity-planning-resource.md` specifies the per-project summary as MVP and lists "Per-member allocation / availability modelling" as a follow-up.

## Goals / non-goals

Goals:

- Answer "who has how much open work, and where" across the projects the viewer can read.
- Keep the per-project summary the existing spec requires.
- Make the page's descriptions true.

Non-goals:

- Availability, leave and contract hours.
- Editing work from the report.

## Decisions

### Decision 1: group in the browser with a pure helper

`summariseByAssignee(tasksByProject, now)` in `src/utils/portfolioHelpers.js` returns one entry per person: open tasks, overdue tasks, remaining minutes (`remainingEstimate`, else `estimatedDuration`), the number of open tasks without either, open tasks due in the next 14 days, shared tasks, and a per-project breakdown. Tasks without `assignedTo` go to an "Unassigned" entry. It reuses `CLOSED_STATUSES` and `dueDateStatus`, so "open" and "overdue" mean the same on both views.

The page reads what it reads today, one `fetchTasks` per project, and groups the result. No server aggregation is added: OpenRegister's aggregation can group by `assignedTo` but cannot sum one field while falling back to another, and the per-project breakdown needs the task rows anyway.

Alternative considered: an aggregation widget grouped by `assignedTo` on the manifest dashboard, like "Per person" on the Time spent report (`src/manifest.json:203`). It gives one number per person, not the breakdown, and it cannot count tasks without an estimate.

### Decision 2: primary assignee carries the hours

A task's remaining estimate counts once, for `assignedTo`. People in `sharedWith` see the task in their "Shared" count, without hours. This keeps the total of the hours column equal to the total remaining estimate of the projects.

### Decision 3: one page, two views, and a truthful label

`Portfolio.vue` gets a two-button toggle, "By person" (default) and "By project", as a labelled group with `aria-pressed`. "By project" is today's table, unchanged, so the MVP requirement of `capacity-planning-resource.md` still holds. A portfolio picker and a project filter limit which projects are read; without them the page covers every active project the viewer can read, including projects reached as a portfolio reader.

Each person's row is a disclosure: a button with the person's avatar and display name that shows the per-project breakdown under it. Hours are shown rounded to whole hours with the unit, and "9 tasks without an estimate" next to them.

The manifest `_note` of the `Portfolio` page and the Capacity card description are rewritten to say what the page shows: open work per person and per project.

## Risks / trade-offs

- [A person with work in a project the viewer cannot read] -> The row covers only projects the viewer can read, and the page says "Showing work in the projects you can see". It never implies a full picture of someone's load.
- [Display names for people who left] -> A uid without a Nextcloud account is shown as the uid with "(no longer an account)", after the clean-up of `projects-members-and-roles` has removed them from projects but not from old tasks.

## Open questions

- Availability per person (contract hours, part-time factors, leave) lives in humaniq. A follow-up change can add an "Available" column read from humaniq with `requiredApp: humaniq`, hidden when humaniq is absent, as the time move already does.
