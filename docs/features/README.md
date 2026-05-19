# Planix — Features

Planix is a project management app for Nextcloud, providing kanban boards, task management, time tracking, and project organization.

## Features

| Feature | Summary | Standards | Doc |
|---------|---------|-----------|-----|
| Register Schemas | 5 schemas (task, project, column, timeEntry, label) with seed data for project management | OpenRegister v0.2.10+ | [register-schemas.md](register-schemas.md) |
| Projects | Full project management UI: list, create, settings sidebar, member management, archive/delete | Schema.org CreativeWork | [projects.md](projects.md) |
| Tasks | Task lifecycle management: CRUD, priorities, labels, assignees, due dates, subtasks, status workflow | iCalendar VTODO (RFC 5545), Schema.org Action/PlanAction, VNG InterneTaak | [tasks.md](tasks.md) |
| Kanban Board | Visual board per project: configurable columns, drag-and-drop cards, WIP limits, board filters, view toggle | Schema.org ItemList, DefinedTerm, Kanban Guide | [kanban-board.md](kanban-board.md) |
| Dashboard & My Work | Personal landing page with KPI cards, recent projects, tasks due this week, and My Work task list grouped by urgency | Schema.org Action/PlanAction, Nextcloud Dashboard API | [dashboard.md](dashboard.md) |
| Time Tracking | Per-task time estimates and manual time log entries; personal timesheet view with date grouping and range filters | Schema.org QuantitativeValue, iCalendar ESTIMATED-DURATION (RFC 7986) | [time-tracking.md](time-tracking.md) |
| Admin & User Settings | Admin settings panel for default columns and label management; user settings dialog for notification preferences and default view | Nextcloud OCP\IAppConfig, OCP\IConfig, NcAppSettingsDialog | [admin-settings.md](admin-settings.md) |
| Procest Integration | Link tasks and projects to Procest cases via caseReference and zaakUuid fields; case badges and links in the UI | VNG ZGW InterneTaak, Schema.org Action | [procest-integration.md](procest-integration.md) |
