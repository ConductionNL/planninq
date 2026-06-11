# Tasks: Task Collaboration Sidebar

## 0. Prerequisite surface

- [ ] Verify the CnDetailPage task detail view (navigation target of `kanban-board.md` / `dashboard-my-work.md`) exists; if not, build it first under those existing specs (route, CnDetailPage with task fields, back navigation) — no new spec, this change only adds the sidebar tabs on top.

## 1. Sidebar tabs (frontend, OR-backed — no planix PHP)

- [ ] Wire the planix object store with `filesPlugin()` + `auditTrailsPlugin()` from `@conduction/nextcloud-vue`; confirm notes fetch resolves against the planix register + `task` schema + task UUID.
- [ ] Mount `CnObjectSidebar` on the task detail view with tabs `notes`, `files`, `auditTrail` and `hiddenTabs: ['tags', 'tasks']`; tab labels via `t('planix', …)` (English source keys).
- [ ] Comments tab: list (author display name, avatar, relative timestamp), composer to add, edit/delete on own comments only — all via the OR notes endpoints (`/api/objects/{register}/{schema}/{id}/notes`); no planix controller (ADR-022 / gate-17).
- [ ] Files tab: upload, list (name, size, modified), download link, delete — via the OR object-files endpoints; verify the file is visible in NC Files under the OR-managed folder.
- [ ] Audit Trail tab: read-only list of changes (user, timestamp, field, old → new) from the OR audit-trail endpoint.
- [ ] Vitest: tab config (correct register/schema/objectId passed, hidden tabs), comment composer disabled-when-empty, own-comment action gating.

## 2. Nextcloud Activity integration (planix PHP)

- [ ] `lib/Activity/Provider.php` implementing `OCP\Activity\IProvider`: render `task_created`, `task_status_changed`, `task_assigned_activity`, `task_due_date_changed`, `task_deleted` as rich subjects in en + nl (English source strings as keys).
- [ ] `lib/Activity/Filter.php` implementing `OCP\Activity\IFilter`: "Planix" filter with app icon.
- [ ] `lib/Listener/TaskActivityListener.php` on OpenRegister's `ObjectCreatedEvent` / `ObjectUpdatedEvent` / `ObjectDeletedEvent`: bail unless (planix register, `task` schema); diff old/new to choose the subject; publish via `OCP\Activity\IManager` to the task's project members, excluding the acting user; never throw (log + skip on malformed events).
- [ ] Register provider/filter/listener in `lib/AppInfo/Application.php` + `appinfo/info.xml` (`<activity>` block); bump `<version>` (immutable-cache rule).
- [ ] PHPUnit: subject selection per diff, non-task and non-planix-register events ignored, actor excluded from own entries, resilience (malformed payload → no throw), Provider en/nl rendering of every subject.

## 3. Integration tests

- [ ] Newman (`tests/integration/*.postman_collection.json`): notes CRUD on a seeded task (create → list → edit own → delete own); RBAC negative — user without read access to the task receives no notes and cannot create one; file upload then present in object-files list; audit trail contains the entry after a status update.
- [ ] Playwright e2e (UI only): task detail → Comments tab post + render; Files tab upload + list; Audit Trail tab shows the status change made in-test; NC Activity app shows the Planix filter and the task-created entry. Reference the unexcluded scenarios from the spec delta (gate-19).

## 4. i18n, quality, docs

- [ ] i18n: nl translations for tab labels, composer placeholder/actions, empty states, and all five activity subjects.
- [ ] Run `composer check:strict` + hydra gates (gate-17 must confirm no pass-through controllers were added; gate-18 unaffected — no notification dispatch in this change). Fix any pre-existing quality issues encountered.
- [ ] Update `docs/FEATURES.md` §Collaboration rows 38–40 status; README architecture claims now true.

## 5. Spec sync

- [ ] On archive: create `openspec/specs/task-collaboration.md` from the delta; cross-link from `tasks.md` (task detail) and `kanban-board.md` (detail navigation) so the sidebar tabs are discoverable.
