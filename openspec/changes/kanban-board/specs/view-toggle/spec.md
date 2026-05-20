# Spec: View Toggle — Kanban and List

## ADDED Requirements

---

### Requirement: REQ-VTG-001 — Switch to List View

The system MUST allow users to switch from the kanban board view to a flat sortable list view.

#### Scenario: Switch to list view

- GIVEN a user is on the kanban board view for a project
- WHEN the user clicks the list view toggle button in the project toolbar
- THEN the system MUST replace the kanban board with a sortable flat table (`CnDataTable`)
- AND the table MUST show columns: title (link), assignee (avatar + name), due date (red if overdue), status (`CnStatusBadge`), priority (dot + label), labels (chips)
- AND the URL hash MUST be updated to reflect the active view (e.g. `#view=list`)
- AND reloading the page MUST restore the list view (hash is read on mount)

#### Scenario: Active filter preserved on view switch

- GIVEN a user has an active filter applied on the kanban board (e.g. "Assignee: me")
- WHEN the user switches to list view
- THEN the same filter MUST remain applied in the list view
- AND the URL hash MUST reflect both the active view and the active filter

---

### Requirement: REQ-VTG-002 — Switch Back to Kanban View

The system MUST allow users to switch back from list view to the kanban board view.

#### Scenario: Switch back to kanban view

- GIVEN a user is in the list view for a project
- WHEN the user clicks the kanban view toggle button
- THEN the system MUST render the kanban board with columns and task cards
- AND any active filter MUST remain applied in the kanban view
- AND the URL hash MUST be updated to reflect the kanban view (e.g. `#view=kanban`)

---

### Requirement: REQ-VTG-003 — Task Navigation from List View

The system MUST allow users to open a task's detail page from the list view and return to the list view via the browser back button.

#### Scenario: Open task detail from list view

- GIVEN a user is in the list view
- WHEN the user clicks on a task row (or the task title link)
- THEN the system MUST navigate to the task detail view (`CnDetailPage`)
- AND the browser URL MUST change to the task detail route

#### Scenario: Return to list view via browser back

- GIVEN a user navigated from list view to a task detail page
- WHEN the user presses the browser back button
- THEN the system MUST return to the list view (not the kanban view)
- AND any previously active filter MUST be restored from the URL hash
