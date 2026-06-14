# Proposal: Task Collaboration Sidebar

## Summary

Ship the three FEATURES.md MVP collaboration promises that have no spec today — **comments on tasks**, **file attachments on tasks**, and the **activity/audit trail on tasks** — as one change, because all three live on the same surface: the `CnObjectSidebar` of the task detail view. Comments ride Nextcloud's `ICommentsManager` (through OpenRegister's existing notes API), attachments ride Nextcloud Files (through OpenRegister's object-files API), and the audit trail rides OpenRegister's per-object audit trail, complemented by publishing task events to the Nextcloud Activity stream. Closes rows 3–5 of the 2026-06-11 feature re-evaluation (`FEATURE-REEVALUATION-2026-06-11/planix.md`).

## Motivation

`docs/FEATURES.md` §Collaboration lists three MVP items — #38 "Notes/comments on tasks (ICommentsManager)", #39 "File attachments on tasks (CnObjectSidebar Files tab)", #40 "Activity stream on task (CnObjectSidebar Audit Trail tab)" — and the README architecture diagram promises "Vue frontend → Nextcloud Activity". None of these has a spec or an in-flight change. A kanban tool without comments and attachments on its cards is below the floor set by every competitor in FEATURES.md §1 (Deck, Trello, Jira, Plane all have both). The fleet rule (memory: "Content types belong in leaves") forbids an app-local comment or attachment schema: comments are an NC abstraction (`ICommentsManager`), files are NC Files — and OpenRegister already exposes both per object, so planix's cost is almost entirely frontend wiring plus the Activity provider.

## Affected Projects

- [x] Project: `planix` — task detail `CnObjectSidebar` tabs (notes/files/auditTrail), object-store plugins, NC Activity provider + filter + OR-event listener
- [ ] Project: `openregister` — none required: notes (`/api/objects/{register}/{schema}/{id}/notes`, ICommentsManager-backed `NoteService`), object files, and audit trails already exist and are verified present (2026-06-11)

## Scope

### In Scope

- **Comments tab**: surface OpenRegister's notes API (which wraps `OCP\Comments\ICommentsManager`, objectType `openregister`) on the task detail sidebar — list, add, edit own, delete own; author display name + avatar + relative timestamp
- **Files tab**: surface OpenRegister's object-files API on the task detail sidebar — upload, list, download, delete; files live in Nextcloud Files (no planix storage)
- **Audit Trail tab**: surface OpenRegister's per-object audit trail on the task detail sidebar — who changed what, when, old → new value
- **NC Activity publishing**: planix Activity `IProvider` + `IFilter` ("Planix" app filter) and a listener on OpenRegister's `ObjectCreatedEvent`/`ObjectUpdatedEvent`/`ObjectDeletedEvent` scoped to the planix register's `task` schema, publishing human-readable en/nl activity entries (created, status changed, assigned, due date changed, deleted) for project members (pipelinq `lib/Activity/` + `ObjectEventListener` precedent)
- Object-store wiring: `filesPlugin()`, `auditTrailsPlugin()` and the notes fetch on planix's object store so the sidebar tabs have data

### Out of Scope

- `@mention` users in comments and the `task_commented` / `notify_commented` notification dispatch — FEATURES.md V1; when it comes, the dispatch follows ADR-031 (declarative or engine-side), not an app-local sender
- Talk integration (per-task conversation) — V1
- Comments/attachments on projects, columns, or time entries — task-only for MVP (the sidebar pattern generalises later)
- An "Activity feed" view inside planix (FEATURES.md V1 "Activity feed") — this change publishes to NC's own Activity app only
- Any new OpenRegister capability — all three OR APIs used here already exist

## Approach

The task detail view (CnDetailPage — already required by `kanban-board.md` and `dashboard-my-work.md`) mounts `CnObjectSidebar` with the built-in `notes`, `files`, and `auditTrail` tabs (the component ships them; apps opt in via tab config — `hiddenTabs` keeps `tags`/`tasks` off). The sidebar's tabs resolve against the planix register + `task` schema and the task's UUID, hitting OpenRegister's endpoints directly from the frontend per ADR-022 — **no planix pass-through controllers** (gate-17 redundant-controller).

The only planix backend code is the Activity integration, which NC requires to be app-side:

- `lib/Activity/Provider.php` (`OCP\Activity\IProvider`) — renders subjects `task_created`, `task_status_changed`, `task_assigned`, `task_due_date_changed`, `task_deleted` in en/nl
- `lib/Activity/Filter.php` (`OCP\Activity\IFilter`) — "Planix" filter in the Activity app
- `lib/Listener/TaskActivityListener.php` — handles OR object events, ignores everything that is not the planix register + `task` schema, diffs old/new to pick the subject, publishes via `OCP\Activity\IManager` to the task's project members (assignee + actor dedup: actors do not get activity for their own change, matching NC convention)

This is event presentation, not an object notification: gate-18 (`notification-dialect`) governs notifications; Activity publishing via `IManager::publish` from an event listener is the NC-canonical pattern (pipelinq reference implementation).

## New Dependencies

None (no new composer/npm packages — `@conduction/nextcloud-vue` already ships the sidebar tabs and store plugins).

## Cross-Project Dependencies

None blocking. Verified against the installed OpenRegister (2026-06-11): notes routes (`appinfo/routes.php` notes#index/create/update/destroy), `NoteService` (ICommentsManager wrapper), object files, and audit trails all exist; `@conduction/nextcloud-vue` ships `filesPlugin`, `auditTrailsPlugin`, notes fetch, and the `'files'/'notes'/'auditTrail'` sidebar tabs.

## Impact

- `src/views/` task detail surface — mount `CnObjectSidebar` with `notes`, `files`, `auditTrail` tabs (creating the CnDetailPage task detail view if the kanban-board spec's navigation target has not been built yet)
- `src/store/` (or equivalent) — object store gains `filesPlugin()` + `auditTrailsPlugin()` and notes wiring
- `lib/Activity/Provider.php`, `lib/Activity/Filter.php` — new
- `lib/Listener/TaskActivityListener.php` — new; registered in `lib/AppInfo/Application.php` for OR object events
- `appinfo/info.xml` — `<activity>` provider/filter registration; `<version>` bump (NC immutable-cache rule)
- `openspec/specs/` — new `task-collaboration` capability spec
- i18n — en/nl strings for tab labels, comment composer, and all activity subjects

## Risks

### Risk 1: Comment visibility is broader than project membership
**Severity:** Medium — comments fetched via OR notes are gated by OR's object RBAC, not by planix's project-member concept. **Mitigation:** task objects already carry OR RBAC (fleet model: OR-delegated + public group); the spec pins "a user who cannot read the task cannot read or write its comments" as the observable requirement and Newman-tests it, leaving enforcement where it belongs (OR).

### Risk 2: Activity noise on bulk operations
**Severity:** Low — bulk status/assignee updates (tasks spec) fire one OR event per task. **Mitigation:** acceptable for MVP (one activity per task is correct semantically); grouping is an NC Activity rendering concern.

### Risk 3: Task detail surface may not exist yet
**Severity:** Low — kanban-board/dashboard specs require navigation to a CnDetailPage task detail, but the view may be unbuilt. **Mitigation:** building that view is already covered by the existing specs; this change only adds the sidebar tabs requirement on top and notes the ordering in tasks.md.

## Rollback Strategy

Frontend tabs are additive — removing the tab config restores the previous detail view. The Activity provider/filter/listener are self-contained classes registered in `Application.php` and `info.xml`; deregistering them stops publishing, and historical activity entries remain (harmless, NC-managed). No data migration in either direction: comments live in NC's comments store, files in NC Files, audit trails in OR.
