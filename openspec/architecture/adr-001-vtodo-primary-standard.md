# ADR-001: VTODO as Primary Task Standard

**Status**: accepted

**Date**: 2026-03-26

## Context

Planix stores task data in OpenRegister. We needed a field reference for task properties (title, status, priority, due date, assignee, etc.) that is mature, widely understood, and allows future interoperability with calendar/task systems including Nextcloud's own Tasks app.

We evaluated 8 standards: iCalendar VTODO, Schema.org Action, OpenProject Work Packages API, Nextcloud CalDAV VTODO, GitHub Issues API, VNG InterneTaak, BPMN 2.0 UserTask, and W3C PROV-O.

## Decision

iCalendar VTODO (RFC 5545) is the primary field reference for the Task entity. Schema.org Action provides semantic type annotations. VNG InterneTaak is an API mapping layer only — not a storage model.

> **Data storage uses international standards. Dutch government standards are an API mapping layer.**

## Consequences

**Positive:**
- Task properties (SUMMARY, DESCRIPTION, DTSTART, DUE, STATUS, PRIORITY, PERCENT-COMPLETE, ATTENDEE, CATEGORIES, RELATED-TO) map to a ratified RFC
- Compatible with Nextcloud Tasks app (CalDAV/VTODO) via `calendarEventUid` reference field
- Schema.org annotations make tasks machine-readable / JSON-LD compatible
- Dutch government interoperability via VNG mapping layer, without coupling storage model to Dutch-only standards

**Negative / trade-offs:**
- VTODO does not cover project/board concepts — supplemented with Schema.org ItemList (boards) and DefinedTerm (columns/labels)
- VTODO property names (DTSTART, ATTENDEE) are not used verbatim in JSON — we use camelCase equivalents (startDate, assignedTo)

## Alternatives Considered

| Option | Reason not chosen |
|--------|------------------|
| BPMN 2.0 UserTask | Too process-oriented; no kanban fit |
| VNG InterneTaak as primary | Dutch-only standard; excludes non-government use cases |
| GitHub Issues API as primary | No time tracking fields, no percent-complete |
| W3C PROV-O | Audit trail reference only, not a task model |
