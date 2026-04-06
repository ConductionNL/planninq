# Tasks — Projects CRUD MVP

## Task 1: Backend — Project CRUD endpoints
**spec_ref**: projects.md → Requirement: Project Management
**files_likely_affected**: lib/Controller/ProjectController.php (new), appinfo/routes.php
**acceptance_criteria**:
- [x] `GET /api/projects` lists all projects the user is a member of
- [x] `POST /api/projects` creates a project (title required, description optional, color optional)
- [x] `PATCH /api/projects/{id}` partially updates a project (owner or member only)
- [x] `DELETE /api/projects/{id}` deletes a project (owner only)
- [x] Returns 404 for non-existent project IDs
- [x] Returns 403 for unauthorized access

## Task 2: Frontend — Projects list and form
**spec_ref**: projects.md → Scenario: View projects list
**files_likely_affected**: src/views/Projects.vue (new), src/components/ProjectForm.vue (new), src/router/index.js
**acceptance_criteria**:
- [x] /projects route shows a list of projects as cards
- [x] Each card shows: title, description preview, color indicator, member count
- [x] "New project" button opens a create form dialog
- [x] Form has: title (required), description, color picker
- [x] Clicking a project card navigates to project detail (or shows detail panel)
- [x] Empty state: "No projects yet" with create button
