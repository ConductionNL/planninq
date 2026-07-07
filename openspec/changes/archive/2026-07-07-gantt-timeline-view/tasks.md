# Tasks: gantt-timeline-view

## 1. Backend read surface

- [x] 1.1 Add `lib/Controller/TimelineController.php` (SPDX docblock; `@NoAdminRequired` + `@NoCSRFRequired`; inject `IUserSession`, `ObjectService`, `LoggerInterface`; 401 when no user). `forProject(string $projectId)` (+ `from`/`to`): read the project's tasks via `ObjectService` (RBAC/tenancy-scoped), return each with `startDate`/`dueDate`/`duration` + status, split out dateless tasks as `unscheduled`, and include the tasks' existing dependency links (read, not re-derived).
  - **spec_ref**: `specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis`
  - **acceptance_criteria**:
    - No new schema/storage/engine; reads only existing task + dependency objects
    - RBAC-scoped; unscheduled tasks flagged, not dropped
    - Unit tests: dated tasks, unscheduled split, RBAC scope, windowing
- [x] 1.2 Register `timeline#forProject` (GET `/api/projects/{projectId}/timeline`) in `appinfo/routes.php` with explicit auth; resolves to `TimelineController::forProject` (route-auth + route-reachability PASS).
  - **spec_ref**: `specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis`
  - **acceptance_criteria**:
    - Route registered with explicit auth + resolvable method

## 2. Frontend timeline view

- [x] 2.1 Add `src/api/timeline.js` (stateless functions over `@nextcloud/axios` + `generateUrl`, no store): `fetchProjectTimeline(projectId, from, to)`.
- [x] 2.2 Add `src/views/ProjectTimeline.vue`: horizontal time axis (day/week/month zoom), one bar per task (start→due, label, status colour), dependency arrows from the stored links, an "unscheduled" rail, today-marker; read-only in v1. Strings via `t()`, data via the API/`loadState` (no DOM reads); `NcSelect` carries `inputLabel`; any modal in its own file. Add a "Timeline" tab to the project surface + a manifest menu entry.
  - **spec_ref**: `specs/gantt-timeline-view/spec.md#requirement-the-timeline-renders-the-existing-dependency-links-not-a-new-copy`
  - **acceptance_criteria**:
    - Bars render from seeded task dates; dependency arrows from existing links; unscheduled rail shown

## 3. Verify

- [x] 3.1 `openspec validate gantt-timeline-view --strict` clean; PHPUnit for the controller green; vitest for the view; no dangling refs; route resolves; the view creates/mutates no objects (read-only invariant).
  - **spec_ref**: all
  - **acceptance_criteria**:
    - Strict validation + unit tests green; read-only + dependency-reuse verified
