# Tasks — Kanban Board MVP

## Task 1: Backend — Column CRUD endpoints
**spec_ref**: kanban-board.md → Requirement: Column Management
**files_likely_affected**: lib/Controller/ColumnController.php (new), appinfo/routes.php
**acceptance_criteria**:
- [x] `GET /api/columns?projectId={id}` lists columns for a project, ordered by position
- [x] `POST /api/columns` creates a column (title, projectId, position)
- [x] `PUT /api/columns/{id}` updates a column (title, position)
- [x] `DELETE /api/columns/{id}` deletes a column (moves tasks to backlog)
- [x] Returns 404 for non-existent columns
- [x] Returns 403 for non-project-members

## Task 2: Frontend — Kanban board view
**spec_ref**: kanban-board.md → Scenario: View kanban board
**files_likely_affected**: src/views/KanbanBoard.vue (new), src/router/index.js
**acceptance_criteria**:
- [x] /projects/:id/board route shows the kanban board for a project
- [x] Board displays columns left-to-right with task cards in each
- [x] Each task card shows: title, assignee avatar, priority indicator, due date
- [x] Empty columns show "No tasks" placeholder
- [x] "Add column" button at the right edge of the board
- [x] Column header shows title and task count
