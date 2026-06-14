# Design: Task Collaboration Sidebar

## Summary

One surface, three NC abstractions: the task detail `CnObjectSidebar` gains the built-in `notes` (comments), `files` (attachments), and `auditTrail` tabs — all backed by existing OpenRegister per-object APIs — and planix publishes task events into the Nextcloud Activity stream via an app-side `IProvider`/`IFilter`/listener trio. Zero new storage, zero new planix schemas, zero pass-through controllers.

## Why these backends (and not app-local ones)

| Concern | Backend | Why |
|---|---|---|
| Comments | NC `ICommentsManager` via OR notes API (`/api/objects/{register}/{schema}/{id}/notes`, objectType `openregister`) | Fleet rule: comments are an NC abstraction, never an app-local schema. OR's `NoteService` already wraps ICommentsManager per object UUID — planix adds no PHP at all (ADR-022; a planix CommentsController would be gate-17 redundant) |
| Attachments | NC Files via OR object-files API | FEATURES.md/README promise "Nextcloud Files (attachment via CnObjectSidebar)"; files stay user-visible, shareable, and versioned in NC Files — planix stores only the link relation OR maintains |
| Audit trail | OR per-object audit trails | OR records every object mutation already; the sidebar tab is read-only presentation |
| Activity | NC Activity (`OCP\Activity\IManager`) | The one piece NC requires app code for: a registered `IProvider` to render subjects and an `IFilter` for the app filter. Pipelinq's `lib/Activity/` + `ObjectEventListener` is the fleet reference implementation |

## Sidebar wiring

- The object store is created with `filesPlugin()` + `auditTrailsPlugin()` (per the `createObjectStore` plugin API in `@conduction/nextcloud-vue`); notes use the store's built-in notes fetch.
- The task detail view passes the sidebar config: register = planix register slug, schema = `task`, objectId = task UUID, visible tabs `['notes', 'files', 'auditTrail']`, `hiddenTabs: ['tags', 'tasks']` (planix has its own label and sub-task concepts; the generic tabs would confuse).
- Details/edit content of the task remains the CnDetailPage main column — this change touches tabs only.
- If the CnDetailPage task detail view (navigation target required by `kanban-board.md` "Clicking a task in list view navigates to task detail" and `dashboard-my-work.md`) is not yet built, it is built first under those existing specs; this change's tasks order that explicitly rather than re-speccing the page.

## Activity pipeline

```
OR ObjectCreated/Updated/DeletedEvent
  → planix TaskActivityListener
      - bail unless event object ∈ (planix register, task schema)
      - diff old/new payload → subject:
          created                          → task_created
          status changed                   → task_status_changed
          assignedTo changed               → task_assigned_activity
          dueDate changed                  → task_due_date_changed
          deleted                          → task_deleted
      - audience: project members of the task's project (resolved once per event);
        the acting user is excluded from their own entries (NC convention)
      - IManager::publish() with app='planix', type='planix_task',
        object UUID + rich subject params {actor, task title, old, new}
  → planix Activity\Provider renders en/nl rich subjects in the Activity app
  → planix Activity\Filter exposes the "Planix" filter
```

Notes:

- **Not a notification.** Gate-18 governs the object-notification dialect; Activity stream publishing is presentation of history, the NC-canonical app-side pattern. `task_assigned` *notifications* stay where `tasks.md` already specs them; the activity entry is an independent, silent timeline record (hence the distinct `task_assigned_activity` subject key to avoid colliding with the notification subject).
- **Resilience:** a malformed event or unresolvable project never throws out of the listener (log + skip) — OR's event dispatch must not be broken by planix.
- **No polling, no cron:** strictly event-driven.

## Permissions model (observable, enforced by OR/NC)

- Comments/files/audit trail inherit the task object's OR RBAC: a user who cannot read the task gets no notes/files/trail for it; writes require task read access (comments) per OR's note endpoints.
- Comment edit/delete is author-only (ICommentsManager semantics); NC admins can delete any comment via the comments backend, not via planix UI.
- Activity entries go to project members only — membership resolved at publish time; later membership changes do not rewrite history (NC Activity is append-only).

## Testing strategy

- **PHPUnit:** listener subject selection (created/status/assign/dueDate diffs), non-task events ignored, actor exclusion, malformed-event resilience; Provider renders every subject in en + nl without placeholder leakage.
- **Newman (API):** notes CRUD against a seeded task via the OR notes endpoints (create → list shows author+message, edit own, delete own); RBAC negative (user without task access gets no notes); file upload → appears in object files list; audit trail contains the status-change entry after an update. API assertions live in Newman, not Playwright.
- **Playwright (UI only):** open task detail → Comments tab → post a comment → it renders with own display name; Files tab → uploaded fixture file listed; Audit Trail tab → shows the status change performed in the test; Activity app shows the "Planix" filter with the task-created entry.
