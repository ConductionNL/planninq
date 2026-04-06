# ADR-001: Task Status Model

**Status:** proposed
**Category:** data-model
**Priority:** 1
**Depends on:** []
**Features depending on this ADR:** 0

## Context

How should Planix model task statuses? Tasks move through columns on a kanban board, but the status model affects filtering, reporting, and integration with Procest cases and Nextcloud Tasks (CalDAV VTODO).

## Decision

Task status is determined by which kanban column the task is in — not a separate field. Columns are ordered within a project and define the workflow. Special column types: backlog (initial), active (in-progress), done (completed), archived. CalDAV VTODO STATUS maps: backlog→NEEDS-ACTION, active→IN-PROCESS, done→COMPLETED.

## Consequences

Every task query filters by column type, not status field. Kanban drag-and-drop changes status implicitly. Procest integration maps case status to column type. Reports group by column type for velocity metrics.
