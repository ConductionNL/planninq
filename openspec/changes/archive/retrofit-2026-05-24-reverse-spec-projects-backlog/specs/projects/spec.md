---
retrofit_extensions:
  - REQ-Project-Backlog-Route
---

# Projects — Retrofit Spec Delta

This delta adds 1 new REQ to the `projects` capability describing the existing
Backlog placeholder route. The behavior already ships on `development`; this
spec retroactively captures it so the route stops being orphaned (no REQ →
unannotatable) until tasks#REQ-Task-CRUD lands and replaces the placeholder.

## Implementation Requirements

### Requirement: Project Backlog Route [MVP]

The system MUST provide a navigable Backlog route per project that renders
inside the project context (breadcrumb back to project board) and shows a
placeholder until task management ships.

#### Scenario: Navigate to backlog route
- GIVEN the user is authenticated and a project exists with id `:id`
- WHEN the user navigates to `/projects/:id/backlog`
- THEN the system MUST render the `ProjectBacklog` view
- AND the breadcrumb MUST show `Projects > {projectTitle} > Backlog`
- AND each breadcrumb segment except the current one MUST be a clickable
  `NcButton` routing back to the parent view

#### Scenario: Hydrate project context on direct deep link
- GIVEN the user opens `/projects/:id/backlog` as the first navigation
  (e.g. via a bookmark) and the projects store has no `activeProject`
- WHEN the `ProjectBacklog` view mounts
- THEN the view MUST call `projectsStore.fetchProject(:id)` so the
  breadcrumb title resolves to the project title rather than the raw UUID

#### Scenario: Placeholder until task management is implemented
- GIVEN the `ProjectBacklog` view has rendered
- WHEN there is no task-management implementation yet
- THEN the view MUST show an `NcEmptyContent` with name "Backlog view coming
  soon" and description "Task management will be available in a future update."
- AND the placeholder MUST use the `FormatListBulleted` MDI icon so the
  feature intent is recognisable

#### Notes
- The placeholder copy is deliberate. It MUST stay aligned with the
  unimplemented `tasks#REQ-Task-CRUD` and `kanban-board#REQ-Kanban-Board-View`
  REQs (currently Bucket 3b — planned, never started). When task-management
  lands, this REQ should either be retired in favour of a real backlog REQ
  or rewritten to describe the populated list view.
- The view does not own loading or error states beyond what the projects
  store provides — those are covered by `projects#REQ-Loading-and-Error-States`
  (also Bucket 3b at the time of retrofit).
