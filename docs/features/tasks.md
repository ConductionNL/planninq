# Tasks

The core unit of work in Planninq — create, assign, prioritize, and track tasks across projects.

## Overview

A task represents a piece of work within a project. Tasks carry a title, description, status, priority, assignee, due date, labels, and time estimate. They can be placed on the kanban board (assigned to a column) or held in the backlog (no column). The task detail view provides a full edit form, time tracking panel, and a sidebar with files, notes, tags, and an audit trail.

## Key Capabilities

- **Task CRUD** — create, view, edit, and delete tasks; required fields are title and status
- **Status lifecycle** — open → in_progress → blocked → done → cancelled (aligned with iCalendar VTODO STATUS)
- **Priority** — four levels: low, normal, high, urgent; displayed as colored dots on task cards
- **Assignee** — assign to any Nextcloud user; avatar shown on kanban cards and task detail
- **Due date** — set a due date; overdue tasks are highlighted on the board and in My Work
- **Labels** — apply one or more app-wide labels (color-coded) for cross-project categorization
- **Task detail view** — `CnDetailPage` with a core info card, time tracking panel, and `CnObjectSidebar` (Files, Notes, Tags, Audit Trail tabs)
- **Kanban placement** — assign a task to a board column to move it from the backlog onto the board
- **Procest link** — optional `zaakUuid` field links a task to a Procest case (see [Procest Integration](procest-integration.md))
- **Task dependencies** — mark a task as blocked by another task in the same project; a blocked badge shows on the card and in task detail; the server rejects self, duplicate, and cyclic dependency edges
- **Notifications** — task assignment and due-date reminders sent via Nextcloud Notifications (configurable in user settings)

## Standards

- iCalendar VTODO (RFC 5545) — primary field reference for task properties
- Schema.org Action / PlanAction — semantic type annotations
- VNG InterneTaak (Klantinteracties) — API mapping layer for Procest bridge

## Spec

- [tasks spec](https://github.com/ConductionNL/planninq/blob/development/openspec/specs/tasks.md)
