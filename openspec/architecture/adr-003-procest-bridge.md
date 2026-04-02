# ADR-003: Procest Bridge via Schema Fields (Loose Coupling)

**Status**: accepted

**Date**: 2026-03-26

## Context

Planix's sister app Procest handles case management (ZGW-aligned). A common workflow is: a case in Procest generates one or more tasks that need to be tracked on a kanban board in Planix.

Three integration approaches were considered:

1. **Tight coupling** — Planix calls the Procest API to fetch/create tasks
2. **Loose coupling** — Tasks carry optional metadata fields that reference a Procest case; no direct API calls between apps
3. **Separate bridge service** — a dedicated integration layer translates between the two apps

## Decision

Loose coupling via schema fields. The Task entity has two optional fields:

- `caseReference` (string) — human-readable case identifier
- `zaakUuid` (UUID) — machine-readable ZGW case UUID

Planix does not call Procest APIs in MVP. Procest creates tasks in Planix via OpenRegister directly, populating these fields. Planix displays them as read-only metadata on the task detail view.

**Project ownership is configurable — Procest's UI decides.** When creating tasks for a case, Procest's UI presents a project picker. The user can create a new Planix project (with `caseReference` linking back to the case) or add tasks to an existing project (with `zaakUuid` on each task). Planix has no routing or default-project mechanism — it reads whatever Procest wrote to OpenRegister.

## Consequences

**Positive:**
- No circular dependency between apps at runtime
- Planix works fully without Procest installed
- Tasks remain valid OpenRegister objects regardless of case status
- `zaakUuid` enables future ZGW API mapping without changing the data model

**Negative / trade-offs:**
- No real-time status sync between Procest case and Planix task in MVP
- Planix cannot initiate case creation in Procest
- `caseReference` is a display-only string — no validation against Procest data

## Alternatives Considered

| Option | Reason not chosen |
|--------|------------------|
| Tight API coupling | Creates runtime dependency; if Procest is down, Planix task operations are affected |
| Separate bridge service | Over-engineering for MVP; adds infrastructure complexity before the integration pattern is proven |
