# Tasks — Kanban Board MVP

## Task 1: Backend — Column CRUD endpoints
**spec_ref**: kanban-board.md → Requirement: Column Management
**files_likely_affected**: lib/Controller/ColumnController.php (new), appinfo/routes.php
**acceptance_criteria**:
- [ ] `GET /api/columns?projectId={id}` lists columns for a project, ordered by position
- [ ] `POST /api/columns` creates a column (title, projectId, position)
- [ ] `PUT /api/columns/{id}` updates a column (title, position)
- [ ] `DELETE /api/columns/{id}` deletes a column (moves tasks to backlog)
- [ ] Returns 404 for non-existent columns
- [ ] Returns 403 for non-project-members

## Task 2: Frontend — Kanban board view
**spec_ref**: kanban-board.md → Scenario: View kanban board
**files_likely_affected**: src/views/KanbanBoard.vue (new), src/router/index.js
**acceptance_criteria**:
- [ ] /projects/:id/board route shows the kanban board for a project
- [ ] Board displays columns left-to-right with task cards in each
- [ ] Each task card shows: title, assignee avatar, priority indicator, due date
- [ ] Empty columns show "No tasks" placeholder
- [ ] "Add column" button at the right edge of the board
- [ ] Column header shows title and task count
