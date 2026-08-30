---
sidebar_position: 4
title: Add and manage tasks
description: Create a task, set its priority and due date, assign it, apply labels, and walk through the task detail view.
---

# Add and manage tasks

A task in Planninq is a single piece of work — a bug, a feature ticket, a deploy step. It carries title, description, status, priority, assignee, due date, labels, and an optional time estimate. Tasks live on the kanban board (assigned to a column) or in the backlog (no column).

## Goal

By the end you will have created a task, set its priority and due date, assigned it to a user, applied a label, and used the task detail view to inspect it.

## Prerequisites

- A project in Planninq you are a member of (see [Create your first project](02-create-project.md)).
- At least one **label** defined in the system if you want to apply one (admins manage labels — see [Manage labels](../admin/02-manage-labels.md)).
- At least one **column** to place the task into. New projects ship with four; otherwise the task lands in the backlog until you give it a column.

## Steps

1. From the board view, click **+ Add task** on a column (or **+ New task** from the board header). The task-create form opens. Fill in **title** (required) and **description**, pick **priority** (low / normal / high / urgent), set a **due date**, pick the **assignee** (any Nextcloud user — typed-ahead search), and choose one or more **labels**.

   ![Task create form](/screenshots/tutorials/user/04-manage-tasks-01.png)

2. Save. The task appears as a card in the column you added it from. The card shows title, assignee avatar, priority dot (red/orange/yellow/blue), due date chip, and label chips. Overdue tasks pick up a red border once the due date is in the past.

   ![New task card on the board](/screenshots/tutorials/user/04-manage-tasks-02.png)

3. Click the card to open the **task detail view**. It uses `CnDetailPage` — a header with title + status, a core info card with the fields you set, a **Time tracking** panel (estimate + log time + total logged), and a sidebar with **Files**, **Notes**, **Tags**, and **Audit trail** tabs.

   ![Task detail view](/screenshots/tutorials/user/04-manage-tasks-03.png)

4. Walk the **status lifecycle**: *open → in_progress → blocked → done → cancelled*. Statuses align with iCalendar VTODO `STATUS` for downstream compatibility (Procest, CalDAV, future RFC tooling). You can change status from the task detail (dropdown) or from the kanban board (drag the card to the corresponding column).

   ![Task status dropdown showing the lifecycle](/screenshots/tutorials/user/04-manage-tasks-04.png)

5. Use the **Audit trail** tab to inspect history. Every edit — title change, status change, column move, assignee change, label change — lands as an entry, with who did it and when. Useful when a task has bounced around the board and you need to reconstruct what happened.

   ![Audit trail tab on a task](/screenshots/tutorials/user/04-manage-tasks-05.png)

## Verification

The task shows in the column you added it to, with the priority dot, assignee avatar, due-date chip, and label chips on the card. The task detail view shows every field you set. The audit trail records every change. The assignee gets a Nextcloud notification on assignment (if their notification settings are on — they are by default).

## Common issues

| Symptom | Fix |
|---|---|
| **+ Add task** missing | You're not a member of the project — ask a project owner to add you on the **Members** tab. |
| Label picker is empty | No labels are defined on the instance yet — an admin adds them under **Settings → Administration → Planninq → Labels** (see [Manage labels](../admin/02-manage-labels.md)). |
| Assignee dropdown empty | The Nextcloud user search returns no matches; try a different query, or check the Nextcloud user directory. |
| Due date in the past saves without a warning | Allowed — overdue tasks get the red border on the card and surface in **My Work → Overdue**. |
| Task created but doesn't appear on the board | The task has no column — it's in the backlog. Open the **View Backlog** link from the board (see [Manage the backlog](05-manage-backlog.md)). |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Work with the kanban board](03-work-with-boards.md) — board-side flows for the tasks you created.
- [Manage the backlog](05-manage-backlog.md) — task without a column.
- [Log time on a task](06-log-time.md) — task → time entry.
- [Tasks reference](../../features/tasks.md) — the underlying feature spec.
