# Tasks: Task Collaboration Sidebar

## 0. Prerequisite surface

- [x] Verify the task detail view exists; it did not (the board is still a placeholder), so a `TaskDetail.vue` view was built: route `/projects/:id/tasks/:taskId`, task fields rendered, back navigation, and `fetchTask` on the projects store. (The full kanban board remains the responsibility of `kanban-board.md`; this change delivers the minimal detail surface needed to host the sidebar tabs.)

## 1. Sidebar tabs (frontend, OR-backed — no planix PHP)

- [x] Mount `CnObjectSidebar` on the task detail view (legacy hardcoded-tabs mode, `use-registry="false"`, since the installed `@conduction/nextcloud-vue` beta.66 ships the built-in notes/files/auditTrail tabs there) with `hiddenTabs: ['tags', 'tasks']`; tab labels via `t('planix', …)` (English source keys: Comments / Attachments / Activity). The library's built-in tabs resolve their data against the planix register + `task` schema + task UUID through OR's per-object endpoints — no `filesPlugin()`/`auditTrailsPlugin()` store wiring needed in this mode (the tabs fetch directly via `apiBase`).
- [x] Comments tab: built-in Notes tab over the OR notes endpoints (`/api/objects/{register}/{schema}/{id}/notes`), list/add and edit/delete own — ICommentsManager-backed; no planix controller (ADR-022 / gate-17 PASS).
- [x] Files tab: built-in Files tab over the OR object-files endpoints (upload/list/download/delete); files live in NC Files under the OR-managed folder. (Storage-location is asserted by Newman against the OR files API, not the UI.)
- [x] Audit Trail tab: built-in read-only Audit Trail tab from the OR audit-trail endpoint.
- [x] Vitest: sidebar config helper (`taskCollaborationSidebarConfig`) — correct register/schema/objectId passed, hidden tabs, legacy mode, id coercion (4 tests). Composer-disabled-when-empty / own-comment action gating live inside the library component (`CnObjectSidebar`), not in planix, so they are covered by the library's own tests, not re-tested here.

## 2. Nextcloud Activity integration (planix PHP)

- [x] `lib/Activity/Provider.php` implementing `OCP\Activity\IProvider`: renders `task_created`, `task_status_changed`, `task_assigned_activity`, `task_due_date_changed`, `task_deleted` as rich subjects (delegated to `ProviderSubjectHandler`), localized via `IFactory` (en + nl/de/fr/es/it shipped, English source keys).
- [x] `lib/Activity/Filter.php` implementing `OCP\Activity\IFilter`: "Planix" filter with app icon, scoped to type `planix_task`.
- [x] `lib/Listener/TaskActivityListener.php` (+ extracted `TaskScopeResolver`) on `ObjectCreatedEvent` / `ObjectUpdatedEvent` / `ObjectDeletedEvent`: bails unless (planix register, `task` schema — slugs resolved via OR mappers); diffs old/new (status > assignee > due date precedence) to choose the subject; publishes via `OCP\Activity\IManager` to the task's project members, excluding the acting user; never throws (log + skip).
- [x] Listener registered in `lib/AppInfo/Application.php`; provider + filter registered via the `<activity>` block in `appinfo/info.xml` (`IRegistrationContext` has no activity-registration methods in this NC version); `<version>` bumped 0.2.8 → 0.2.9.
- [x] PHPUnit (14 tests): subject selection per diff (create/status/assignee/dueDate/delete), no-tracked-change → no publish, foreign-register + non-task-schema ignored, actor excluded, unresolvable project → no throw; Provider subject rendering of every subject (parsed + rich, no placeholder leakage).

## 3. Integration tests

- [x] Newman (`tests/integration/planix.postman_collection.json` → "Task Collaboration" folder): notes CRUD on a seeded task (create → list-shows-author+message → edit own → delete own); RBAC negative — notes of an inaccessible task return no comments (200-empty / 403 / 404); status update then audit trail carries an entry. (File-upload assertion deferred — see note below.)
- [x] Playwright e2e (`tests/e2e/task-collaboration.spec.ts`, UI only): Comments tab post + render with own action affordance; Files tab upload affordance; read-only Audit Trail tab; NC Activity app shows the Planix filter. References the unexcluded spec scenarios (gate-19 PASS). Tests skip cleanly when planix/seed data is absent.

## 4. i18n, quality, docs

- [x] i18n: nl/de/fr/es/it translations for the tab labels, empty states, and all five activity subjects (English source keys); English fallback for the remaining required locales. Also fixed 32 pre-existing l10n parity gaps (dependency + label-management keys) so the l10n-parity gate now PASSES across all 36 required locales.
- [x] Hydra gates: ALL 24 GREEN (gate-17 redundant-controller PASS — no pass-through controllers; gate-18 notification-dialect PASS — no notification dispatch). PHPCS / Psalm / PHPStan / PHPMD clean on all new PHP. `composer check:strict` not run as a single command (the cloned repo has no NC env for the test:all step); checks were run individually inside the dev `nextcloud` container instead.
- [x] Updated `docs/FEATURES.md` §Collaboration rows 38–40 (marked built).

## 5. Spec sync

- [x] On archive: `openspec/specs/task-collaboration.md` created from the delta (this tasks file completed before archive).

## Deferred / notes

- [ ] Newman file-upload + object-files-list assertion — deferred. The OR object-files upload is a multipart request that the basic-auth Newman collection cannot cleanly express alongside the JSON requests; the Files tab is exercised by the Playwright e2e (upload affordance) and the storage-location contract scenario is annotated `@e2e exclude`. Add the multipart Newman request when the collection gains a file fixture.
- [~] Frontend `npm run build` — NOT green, but pre-existing: a css-loader / `@nextcloud/webpack-vue-config` toolchain breakage (`Unknown word import` in `src/assets/app.css` + `node_modules/splitpanes/dist/splitpanes.css`) fails the build identically on the clean `development` baseline (verified by building with this change stashed). Out of scope for this feature change. The frontend JS/Vue is lint-clean and unit-tested via vitest (29 tests).
