# ADR-004: Time Tracking Scope — Manual Only in MVP

**Status**: accepted

**Date**: 2026-03-26

## Context

Time tracking is a key differentiator for Planix vs. competitors (Plane, Taiga, Nextcloud Deck — none have time tracking). However, time tracking can be implemented at three levels of complexity:

1. **Manual logging only** — user enters duration + date after the fact
2. **Live timers** — start/stop timer on a task; auto-creates a time entry
3. **External integrations** — sync with Toggl, Harvest, etc.

## Decision

MVP includes manual time logging only (TimeEntry entity with task, user, duration in minutes, date, and optional description). Live timers are deferred to V1. External integrations are Enterprise tier.

## Consequences

**Positive:**
- Manual logging covers the primary use case (reporting hours per task) with minimal UI complexity
- TimeEntry data model is identical whether entries are created manually or via a timer — V1 timer feature adds UI only, not schema changes
- Faster MVP delivery; time tracking UI is a form, not a real-time widget

**Negative / trade-offs:**
- Users who prefer live timers must track time externally and log manually in MVP
- No automatic time capture — relies on user discipline to log accurately

## Alternatives Considered

| Option | Reason not chosen |
|--------|------------------|
| Timers in MVP | Adds a persistent UI widget (active timer indicator in navigation/header), background state management, and edge cases (multiple active timers, browser close mid-timer) that significantly increase MVP scope |
| Skip time tracking entirely until V1 | Time tracking is the primary competitive differentiator vs. Plane and Nextcloud Deck; even manual logging establishes the feature and data model in MVP |
