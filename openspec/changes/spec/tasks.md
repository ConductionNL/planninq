# Tasks — Projects CRUD MVP

## Task 1: Backend — Project CRUD endpoints
**spec_ref**: projects.md → Requirement: Project Management
**files_likely_affected**: lib/Controller/ProjectController.php (new), appinfo/routes.php
**acceptance_criteria**:
- [ ] `GET /api/projects` lists all projects the user is a member of
- [ ] `POST /api/projects` creates a project (title required, description optional, color optional)
- [ ] `PUT /api/projects/{id}` updates a project (owner or member only)
- [ ] `DELETE /api/projects/{id}` deletes a project (owner only)
- [ ] Returns 404 for non-existent project IDs
- [ ] Returns 403 for unauthorized access

## Task 2: Frontend — Projects list and form
**spec_ref**: projects.md → Scenario: View projects list
**files_likely_affected**: src/views/Projects.vue (new), src/components/ProjectForm.vue (new), src/router/index.js
**acceptance_criteria**:
- [ ] /projects route shows a list of projects as cards
- [ ] Each card shows: title, description preview, color indicator, member count
- [ ] "New project" button opens a create form dialog
- [ ] Form has: title (required), description, color picker
- [ ] Clicking a project card navigates to project detail (or shows detail panel)
- [ ] Empty state: "No projects yet" with create button
