---
sidebar_position: 6
title: Log time on a task
description: Set a task estimate, log time across multiple sessions, and review your week in the timesheet.
---

# Log time on a task

Planninq tracks time at the task level. Each task carries an optional **estimate** (how long it should take). Actual time is **logged** as one or more time entries — typed in by hand at the end of a session, or whenever you remember. The **Timesheet** view rolls up all your time entries by date so you can see where the week went.

## Goal

By the end you will have set an estimate on a task, logged at least one time entry against it, watched the progress indicator update, and looked at your week in the Timesheet view.

## Prerequisites

- A task in Planninq you have access to — yours, or one you can edit (see [Add and manage tasks](04-manage-tasks.md)).
- Planninq open and the OpenRegister back end connected.

## Steps

1. Open the task detail. Find the **Estimate** field on the *Time tracking* panel. Type an estimate — Planninq accepts `2h 30m`, `150m`, `1.5h`, `90` (interpreted as minutes), `2h`. It stores the value as an integer minute count and renders it back in human form (`1h 30m`).

   ![Setting an estimate on a task](/screenshots/tutorials/user/06-log-time-01.png)

2. The estimate also shows on the **kanban card** (small clock icon + duration) so the team has a visual hint of how long a card is meant to take. Cards that look short stay short; cards that look long get split.

   ![Task card showing the estimate](/screenshots/tutorials/user/06-log-time-02.png)

3. **Log time**. Click **Log time** on the task detail. The log dialog opens — *duration* (same parser as the estimate), *date* (defaults to today), *description* (optional but useful: "*Pair session with @Bob*", "*Drafted the migration script*"). Save.

   ![Log time dialog](/screenshots/tutorials/user/06-log-time-03.png)

4. The time entry appears on the task. The *Time tracking* panel now shows **logged / estimate** — e.g. `1h 30m / 3h`. Log more time as you go — multiple entries per task per day are fine. Once logged exceeds estimate, the progress indicator turns red as a soft warning that the task is over-running.

   ![Time entries on a task — over-run case](/screenshots/tutorials/user/06-log-time-04.png)

5. Open **Timesheet** from the Planninq navigation. Your time entries roll up by date — newest first — with daily totals, weekly totals, and a date-range filter (*This week*, *Last week*, or a custom range). Each row links back to the task; the **back** button on the task returns to the timesheet at the same scroll position and same filter.

   ![Timesheet view, weekly roll-up](/screenshots/tutorials/user/06-log-time-05.png)

## Verification

The task's estimate is set and shows on the card. The time entries you logged sum to the value shown on the task's *Time tracking* panel. The Timesheet view lists your entries grouped by date with correct daily and weekly totals. Editing or deleting a time entry is allowed *only* on your own entries — the same edit/delete on someone else's entry returns 403.

## Common issues

| Symptom | Fix |
|---|---|
| "Invalid duration" on the estimate field | The parser accepts `2h 30m`, `150m`, `1.5h`, `90`, `2h` — anything else (zero, negative, words) is rejected. Use one of those forms. |
| Time entries sum doesn't match the panel | Browser cache; reload the task. The panel reads through the entries store with no debounce, but a stale tab can drift. |
| Can't edit someone else's time entry | By design — only the entry's author can edit or delete it. Ask them, or have an admin do it via the OpenRegister object directly. |
| Timesheet shows no entries on a day you worked | The custom range filter is too narrow — switch to *This week* or *Last week* and recheck. |
| Estimate shows as `—` on a card | No estimate set on the task; click the card to open it and set one. |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Add and manage tasks](04-manage-tasks.md) — the task that the time entries hang off.
- [Your dashboard and My Work](07-my-work-and-dashboard.md) — the personal landing page that points at your work.
- [Time tracking reference](../../features/time-tracking.md) — the underlying feature spec.
