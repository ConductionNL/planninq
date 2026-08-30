---
sidebar_position: 1
title: Configure default project columns
description: Set the columns every new Planninq project starts with — what the team's lane structure looks like, with optional WIP limits.
---

# Configure default project columns

Every new Planninq project is seeded with a default column set on creation. Out of the box that's *To Do*, *In Progress*, *Review*, *Done* — four columns, no WIP limits. If your team uses a different flow (you have a *Blocked* lane, or a *Ready to deploy* lane, or you want WIP limits on the active columns), set the default here once and every new project picks it up.

## Goal

By the end the instance will have a default-column set tailored to your team's flow, and a freshly-created test project will use that set.

## Prerequisites

- Admin on the Nextcloud instance.
- The **Planninq** app installed and enabled with the Planninq register initialised (see [Manage Planninq settings](03-admin-settings.md)).
- A view on what the team's actual lane structure looks like — read the columns off your current real project board if you have one, or talk it through with the team.

## Steps

1. Open **Settings → Administration → Planninq** and scroll to the **Default Project Configuration** section.

   ![Default Project Configuration section](/screenshots/tutorials/admin/01-configure-default-columns-01.png)

2. Review the current default columns. Each row shows **order**, **title**, **colour**, and **WIP limit** (empty if unset). The default Planninq install ships *To Do*, *In Progress*, *Review*, *Done*.

   ![Current default columns](/screenshots/tutorials/admin/01-configure-default-columns-02.png)

3. Edit the columns. **Add column** appends a new row; the **drag handle** reorders; the **trash** removes a column; the **WIP limit** field accepts an integer or stays blank (no limit). Use distinct colours so the kanban board reads at a glance.

   ![Edited default columns — Blocked + WIP limits](/screenshots/tutorials/admin/01-configure-default-columns-03.png)

4. **Save**. The change applies to **projects created after the save**; existing projects keep the column set they were created with. To migrate an existing project, edit its columns by hand on the board.

   ![Settings saved confirmation](/screenshots/tutorials/admin/01-configure-default-columns-04.png)

5. Verify. Create a test project (see [Create your first project](../user/02-create-project.md)). Its board should open with exactly the column set you configured, in the right order, with the right colours and WIP limits.

   ![New project picks up the new defaults](/screenshots/tutorials/admin/01-configure-default-columns-05.png)

## Verification

The **Default Project Configuration** section shows the column set you saved. A new project created after the save uses that set verbatim; an old project still uses its original set. The WIP limits, if you set any, appear in the new project's column headers.

## Common issues

| Symptom | Fix |
|---|---|
| Save action does nothing | The save endpoint returned a 4xx — check the Nextcloud log; usually the user-rights check (only admins can save). |
| New project still uses the old defaults | The save didn't land — reload **Settings → Administration → Planninq** and check the column rows match what you intended. |
| Existing project doesn't pick up the new defaults | By design — default columns only apply on project creation. Edit existing projects' columns manually on the board. |
| Column count limit | No fixed cap, but kanban best practice is 4–7 columns; more than that and the board scrolls horizontally on a 1280px viewport. |
| Colour picker is empty | Brand-token CSS variables didn't load — graceful-restart Apache or hard-reload the settings page. |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Create your first project](../user/02-create-project.md) — what users see after these defaults take effect.
- [Work with the kanban board](../user/03-work-with-boards.md) — where the columns live.
- [Manage labels](02-manage-labels.md) — the other big admin task.
- [Kanban board reference](../../features/kanban-board.md) — column model.
- [Admin settings reference](../../features/admin-settings.md) — the full admin surface.
