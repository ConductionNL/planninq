---
sidebar_position: 8
title: Link a task to a Procest case
description: Tie a Planninq task to a Procest case (zaak) so case-work and task tracking stay in sync — VNG InterneTaak field mapping included.
---

# Link a task to a Procest case

Planninq is the sister app to **Procest** (case management). When a government case (*zaak*) needs concrete tasks tracked on a kanban board, Procest writes the case-UUID onto a Planninq task and Planninq renders it back with a deep-link. The integration is metadata-driven — Planninq stores the case reference, Procest reads it; no direct API calls between the apps are needed.

## Goal

By the end you will have a Planninq task with a Procest case linked to it, the read-only **Case** badge visible on the task detail, and you'll know how the status-change syncs back into Procest's VNG InterneTaak fields when the task is marked done.

## Prerequisites

- A task in Planninq (see [Add and manage tasks](04-manage-tasks.md)).
- A case in **Procest** with a known UUID, *or* a Procest case-create flow that wrote a Planninq task into your project automatically.
- The **Procest bridge** enabled in Planninq admin settings (default on; if Procest isn't installed, the bridge is a no-op — case-reference fields are still stored, just not actionable).

## Steps

1. Open the task detail. Find the **Case** (Procest `zaakUuid`) field on the core info card. If Procest pre-populated the task it's already filled — read-only. If you're linking by hand, paste the case UUID and save.

   ![Case field on a task — Procest UUID](/screenshots/tutorials/user/08-link-procest-01.png)

2. With the link saved, the task detail renders the **Case** badge with a deep-link to the case in the Procest app. Clicking the badge opens the case in a new tab (Procest's case detail).

   ![Case badge linking to Procest](/screenshots/tutorials/user/08-link-procest-02.png)

3. The project too can carry a Procest case reference (`caseReference`). Open the project's settings sidebar (gear icon → Details tab), paste the case UUID into the **Case reference** field, save. Projects with a case reference get a `Case: {caseNumber}` badge in the project list and project detail.

   ![Project with case reference](/screenshots/tutorials/user/08-link-procest-03.png)

4. Mark the linked task as **Done**. Planninq writes the completion time onto the task's `completedAt` field. The VNG InterneTaak mapping means Procest will pick the completion time up as the `afhandelingsdatum` on its side when it next syncs. Procest sees the task as handled; the case can advance.

   ![Task marked done, completedAt set](/screenshots/tutorials/user/08-link-procest-04.png)

5. To remove the link without deleting the task, clear the **Case** field. The task stays in Planninq, just no longer tied to a Procest case; the badge disappears. The project's case reference works the same way.

   ![Case link cleared, badge gone](/screenshots/tutorials/user/08-link-procest-05.png)

## Verification

The task carries the case UUID on its `zaakUuid` field, the **Case** badge renders and deep-links to Procest, and the project (if linked) carries `caseReference` and surfaces its own badge. Marking the task done writes a `completedAt` value; Procest sees the case as handled.

## Common issues

| Symptom | Fix |
|---|---|
| Case badge doesn't link to Procest | The Procest app isn't installed/enabled on this Nextcloud instance — the case UUID is still saved as read-only metadata, just nowhere to link to. |
| Pasted UUID is rejected | The field expects a UUID v4; Procest case numbers (human-readable, e.g. `Z-2026-001`) are different — use the Procest case object's UUID, not its case number. |
| Procest bridge disabled banner on settings | An admin toggled the bridge off — re-enable it in **Settings → Administration → Planninq** if both apps are installed. |
| Task marked done but Procest doesn't reflect it | Procest reads Planninq tasks on a schedule (or on case open); give it a few minutes, or re-open the case in Procest. |
| Project list filter doesn't filter by case | Filtering by case is not a built-in filter; the badge is for navigation, not filtering. Use search by case-UUID if you need to find all projects against one case. |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Add and manage tasks](04-manage-tasks.md) — the task fields the bridge populates.
- [Manage Planninq settings](../admin/03-admin-settings.md) — the Procest bridge toggle.
- [Procest integration reference](../../features/procest-integration.md) — VNG InterneTaak mapping, bridge model.
