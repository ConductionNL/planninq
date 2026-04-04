# Dashboard & My Work MVP

**Status**: approved
**Spec reference**: [dashboard-my-work](../../specs/dashboard-my-work.md)
**Priority**: MVP

## Summary

Replace the placeholder sample data on the Planix dashboard with real KPI cards
and a "My Work" view that aggregates tasks from OpenRegister. No new entities —
pure frontend aggregation over existing Task and Project schemas.

## Scope

- Dashboard KPI cards (Open, Overdue, In Progress, Completed Today) with real data
- Recent projects list (5 most recent, with progress bars)
- Tasks due this week section
- My Work view with three groups (Overdue, Due this week, Everything else)
- Empty state handling

## Out of scope

- Quick status update from My Work (requires Task CRUD — separate change)
- Nextcloud Dashboard widget (V1)
- Activity feed (V1)

## Architecture

- Frontend only — no new backend endpoints needed
- Uses OpenRegister object store to query Tasks and Projects
- Dashboard component queries on mount, filters client-side
- My Work is a separate route (/my-work) with its own view component
