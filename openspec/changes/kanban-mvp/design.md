# Kanban Board MVP

**Status**: approved
**Spec reference**: [kanban-board](../../specs/kanban-board.md)
**Priority**: MVP

## Summary

Add kanban board view to Planix. Users can see tasks organized in columns per project,
with drag-and-drop between columns. Each column represents a workflow stage.

## Scope (2 tasks)

- Backend: ColumnController for managing columns per project
- Frontend: KanbanBoard.vue with columns and task cards

## Out of scope

- WIP limits (V1)
- Swimlanes (V1)
- Column templates (V1)

## Architecture

- Columns stored as OpenRegister objects (schema already defined)
- Backend: ColumnController with CRUD + reorder endpoint
- Frontend: KanbanBoard.vue using CnDataTable for now (drag-drop in V1)
