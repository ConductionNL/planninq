# ADR-002: Flow-Based Kanban (No Sprints)

**Status**: accepted

**Date**: 2026-03-26

## Context

Planix is positioned for developer and IT teams who manage continuous work. Two models exist for kanban-based project management:

- **Flow-based (continuous)**: work items move through columns at their own pace; no time-boxed iterations
- **Sprint-based (Scrum)**: work is planned into 2-week sprints with velocity tracking, burndown charts, and sprint reviews

Competitors like Jira support both; Linear and Plane are flow-based only. Nextcloud Deck is flow-based but lacks backlog and WIP limits.

## Decision

Planix is flow-based only. Projects have one persistent kanban board with configurable columns. There are no sprints, sprint planning, velocity charts, or burndown charts in any tier (MVP, V1, or Enterprise). A backlog is implicit — tasks not assigned to a board column live there.

## Consequences

**Positive:**
- Simpler mental model — no sprint ceremonies or planning overhead
- Matches how most small dev/IT teams actually work
- Aligns with Plane and Linear positioning (modern, developer-first)
- Less UI surface area; faster to build and maintain
- Cumulative flow diagrams (V1) replace burndown as the primary flow metric

**Negative / trade-offs:**
- Scrum teams requiring sprint planning and velocity tracking are excluded
- No roadmap/milestone grouping at MVP — teams with release planning needs must use labels or project naming conventions as a workaround

## Alternatives Considered

| Option | Reason not chosen |
|--------|------------------|
| Sprint support in V1 | Adds significant complexity (Sprint entity, task-sprint assignment, velocity calculation) for a minority use case; better served by a dedicated Scrum tool |
| Optional sprint mode per project | Doubles the UI surface and creates two divergent user mental models within one app |
