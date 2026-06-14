# Design: Due Date Reminder Dispatch

## Summary

Send `task_due_soon` notifications declaratively: a `taskDueSoon` rule in the canonical `x-openregister-notifications` dialect (ADR-031) on planix's `task` schema, evaluated by OpenRegister's `ScheduledNotificationJob`. Planix contributes configuration (rule annotation, lead-time admin setting) and preference plumbing (user-toggle write-through), never dispatch code.

## Why schema-rule, not a planix BackgroundJob

- ADR-031 mandates declarative business logic on the schema; gate-18 (`notification-dialect`) warns on imperative object-notification dispatch in leaf apps and hard-fails the legacy dialect.
- The OR engine already owns scheduling (`ScheduledNotificationJob`, `intervalSec >= 60`), recipient resolution (`field` kind resolves `assignee` to a uid), channel fan-out (`nc-notification` + automatic push), bilingual subject templating (`{{prop}}` substitution), and override-only per-user preferences (`NotificationPreferenceService`, key per (schemaSlug, notificationKey)).
- A planix job would duplicate all of that, plus need its own dedup table — and would be unreachable by the engine's user-preference UI.

## Rule shape

Declared on `components.schemas.task` in `lib/Settings/planix_register.json`:

| Part | Value | Rationale |
|---|---|---|
| key | `taskDueSoon` | notification key; pairs with subject key `task_due_soon` used by the user-settings spec |
| trigger | `scheduled`, `intervalSec: 3600` | hourly scan keeps worst-case reminder latency ≤ 1 h on a 24 h lead window |
| filter | `dueDate withinNext PT24H` AND `status notEquals done` | **requires engine extension** (see Dependencies); `PT24H` is generated from the admin setting |
| enabled | `true` | schema default; users opt out via override |
| channels | `["nc-notification"]` | MVP; push is automatic |
| recipients | `[{"kind": "field", "field": "assignee"}]` | the assignee is the only person with a deadline obligation; unassigned tasks produce no recipients and are skipped |
| subject | en/nl with `{{title}}` | English source keys per i18n convention |

## Engine dependency (verified 2026-06-11)

Read against `openregister/lib/BackgroundJob/ScheduledNotificationJob.php`:

1. `matchesFilter()` supports only flat `{field: value}` **equality** — no relative-date operator. Needed extension: operator-form filter entries, minimally `withinNext` (ISO-8601 duration vs job `now`, date-only comparison for `date`-typed fields) and `notEquals`. This is the date-threshold sibling of the known `updated`-trigger field-change gap recorded in `hydra/openspec/fleet-notification-plan.md`.
2. `isDue()`/`markFired()` dedup the **rule** per interval, not the object — hourly scanning of a 24 h window would re-notify the same task ~24 times. Needed extension: per-object fired-markers for scheduled rules keyed by (schemaId, ruleKey, objectId, matched-field-value) so editing `dueDate` re-arms the reminder while an unchanged task fires once.

Both extensions live in the openregister repo (one queued engine change); this change is BLOCKED_EXTERNAL on it for the annotation activation only.

## Preference plumbing

Two-layer model, OR override is authoritative for dispatch:

- **UI layer (existing):** the NcAppSettingsDialog toggle backed by planix `IConfig` key `notify_due_reminder` (kept — the dialog reads/writes it as today via `SettingsService`).
- **Dispatch layer (new):** on every toggle write, `SettingsService` also writes the OR override for (`task`, `taskDueSoon`): `{"enabled": false}` when toggled off, **clear the override** (null) when toggled on — so "on" falls through to the schema default per the engine's `user-override ?? schema-default` resolution rather than pinning a stale `true`.
- **Reconciliation (one-shot):** a repair step seeds `{"enabled": false}` overrides for every user whose stored `notify_due_reminder` is already `false` at upgrade time.

`SUBJECT_SETTING_MAP` (admin-user-settings spec note) remains relevant only for any app-side notifications (`task_assigned`); `task_due_soon` is engine-sent and engine-gated.

## Lead time configuration

- Admin `IAppConfig` key `due_reminder_lead_hours`, integer string, default `24`, bounds 1–336 (2 weeks).
- Saving it re-writes the `taskDueSoon` filter's `withinNext` value (`PT{n}H`) on the schema via the OR schema-update API and is also applied by the idempotent register import (the repair-step import pattern already used by planix), so a re-import never resets a customised lead time. Implementation detail: the import source `planix_register.json` carries the default; `SettingsService` patches the live schema after import when the stored setting differs.
- Per-user lead times are out of scope (engine has no per-user filter parameters).

## Dedup semantics (specced as observable behavior)

At most one `task_due_soon` per (task, dueDate, recipient):

- Task enters the window → one notification to the assignee.
- Subsequent hourly scans while still in the window → nothing.
- `dueDate` moved later, then enters the window again → one new notification (the due date the user was warned about no longer exists).
- Task completed (`status: done`) before the window → nothing (filter).
- Reassignment inside the window after firing: acceptable MVP behavior is no second notification for the same (task, dueDate) unless the engine keys markers per recipient; the spec requires only "no duplicates", not "notify late assignees" — noted as a non-goal.

## Relationship to `task-due-date-warning` (in-flight)

That change is frontend-only: a `dueDateStatus` helper + yellow/red badge on the task card, hardcoded 2-day visual threshold, and its proposal explicitly lists "Notification system for due dates" as out of scope. No shared files, no shared spec requirements. Deliberate asymmetry: the badge threshold (2 days, visual nudge) and the reminder lead time (24 h default, push) serve different moments; neither change should couple them. If a later change wants the badge threshold to follow `due_reminder_lead_hours`, that is a new proposal against the kanban-board spec.

## Testing strategy

- **PHPUnit:** `SettingsService` toggle write-through (off → override written, on → override cleared), lead-hours validation + annotation patching, repair-step seeding logic, annotation JSON shape (rule present, dialect-valid).
- **Newman (API):** with the engine release installed — seed a task due in 12 h, trigger the scheduled job, assert one notification exists for the assignee and zero after a second run; assert none for a `done` task and none for an opted-out user. API/contract assertions belong in Newman, not Playwright.
- **Playwright (UI only):** the user-settings toggle scenario (toggle off persists and the OR override endpoint reflects it) — extends the existing settings-dialog coverage.
