---
sidebar_position: 1
title: Open Planix for the first time
description: Open Planix, find your way around the dashboard and navigation, and confirm the OpenRegister back end is connected.
---

# Open Planix for the first time

A first look at Planix — the dashboard, the navigation, and how to confirm Planix is wired up to OpenRegister so projects, tasks, boards, and time entries can load.

## Goal

By the end you will have opened the Planix app, recognised the dashboard KPIs and the navigation, and confirmed that the OpenRegister back end is connected.

## Prerequisites

- A Nextcloud account on an instance where the **Planix** app is installed and enabled.
- The **OpenRegister** app installed and enabled — Planix stores everything (projects, columns, tasks, time entries, labels) in OpenRegister, so it is a hard dependency.
- The Planix register and its schemas initialised. An admin runs this once from **Settings → Administration → Planix** (see [Manage Planix settings](../admin/03-admin-settings.md)).

## Steps

1. Open the Nextcloud app menu in the top bar and pick **Planix**. You land on the **Dashboard**.

   ![Planix dashboard](/screenshots/tutorials/user/01-first-launch-01.png)

2. Read the four KPI cards — **Open tasks** (open or in_progress assigned to you), **Overdue tasks** (past their due date), **In Progress**, **Completed today**. On a fresh install they read `0`; they fill in as tasks are assigned and worked on. Click any card to jump straight to **My Work** with that filter applied.

   ![Dashboard KPI cards](/screenshots/tutorials/user/01-first-launch-02.png)

3. Below the KPIs you see **Recent projects** (the five most recently active projects you are a member of) and **Due this week** (tasks assigned to you with a due date in the next seven days, sorted by due date). New users with no projects see a *No projects yet* empty state with a **Create project** button.

   ![Recent projects and Due this week](/screenshots/tutorials/user/01-first-launch-03.png)

4. Open the left navigation. Top-level entries: **Dashboard**, **My Work**, **Projects**, **Timesheet**. Below the divider you have **Settings** (the per-user dialog, via the gear icon). The **Projects** entry takes you to the project list — empty on a fresh install.

   ![Planix navigation](/screenshots/tutorials/user/01-first-launch-04.png)

## Verification

You are set up correctly when: the Planix dashboard renders without an error banner, the KPI cards display (zero counts are fine), the left navigation lists the entries above, and clicking **Projects** loads the project list — either with rows or a clean *No projects yet* empty state, not a load error.

## Common issues

| Symptom | Fix |
|---|---|
| "OpenRegister is not installed or enabled" banner | Install and enable the OpenRegister app, then reload Planix. |
| KPI cards show `—` instead of `0` | The Planix register isn't initialised yet — an admin runs **Initialize register** from **Settings → Administration → Planix** (see [Manage Planix settings](../admin/03-admin-settings.md)). |
| Planix is missing from the app menu | The app is not enabled for your account — ask an administrator to enable it (and check it is not restricted to a group you are not in). |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Create a project](02-create-project.md) — the natural next step once the app loads.
- [Your dashboard and My Work](07-my-work-and-dashboard.md) — what the KPI cards point at.
- [Manage Planix settings](../admin/03-admin-settings.md) — register initialisation, version info.
- [Dashboard & My Work reference](../../features/dashboard.md) — the underlying feature spec.
