# Projects CRUD MVP

**Status**: approved
**Spec reference**: [projects](../../specs/projects.md)
**Priority**: MVP

## Summary

Add project management CRUD to Planix. Users can create, view, edit, and delete
projects. Each project has a title, description, color, and members list. Projects
are stored as OpenRegister objects.

## Scope (2 tasks)

- Backend: ProjectController with CRUD endpoints via OpenRegister
- Frontend: Projects list view + create/edit form

## Out of scope

- Kanban boards per project (separate spec)
- Project templates
- Project archiving

## Architecture

- Projects stored as OpenRegister objects (schema already in register)
- Backend: ProjectController with GET/POST/PUT/DELETE
- Frontend: ProjectsList.vue (list + cards) + ProjectForm.vue (create/edit dialog)
- Uses OpenRegister object store for data access
