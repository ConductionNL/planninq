# Proposal: Due Date Reminder Dispatch

## Summary

Define and ship the dispatch side of the `task_due_soon` notification: a declarative, schema-rule-based reminder that notifies a task's assignee when the task's due date is within a configurable lead time (default 24 hours). Today the `notify_due_reminder` user toggle is specced (`openspec/specs/admin-user-settings.md`) and built, but **no spec or change defines anything that sends the notification** — the toggle gates nothing. This change closes the highest-severity gap from the 2026-06-11 feature re-evaluation (`FEATURE-REEVALUATION-2026-06-11/planix.md`, row 1).

## Motivation

FEATURES.md §3.3 lists the due-date reminder as an MVP notification ("Notify 1 day before a task's due date"). The user settings dialog ships a `notify_due_reminder` toggle (default on) and the spec's own acceptance criteria say "Notification settings are respected by NotificationService" — yet the fleet-wide rule (ADR-031, gate-18 `notification-dialect`) is that leaf apps do **not** dispatch object notifications imperatively. The dispatch therefore belongs in the OpenRegister notification engine, declared on the `task` schema via the canonical `x-openregister-notifications` dialect, with planix contributing only the rule annotation, the lead-time admin setting, and the user-toggle write-through.

## Affected Projects

- [x] Project: `planix` — `taskDueSoon` rule annotation on the `task` schema in `lib/Settings/planix_register.json`, admin lead-time setting, `notify_due_reminder` write-through to the OR per-(schema, rule) user override
- [ ] Project: `openregister` — **dependency, not implemented here** (see Cross-Project Dependencies): date-threshold filter operator and per-object dedup for `scheduled` triggers

## Scope

### In Scope

- A declarative `taskDueSoon` notification rule on the `task` schema (`x-openregister-notifications`, ADR-031 dialect): `scheduled` trigger, `nc-notification` channel, recipient = task `assignee`, bilingual subject
- Due-soon detection semantics: due date within the lead window, task not in a terminal status (`done`)
- Configurable lead time via admin setting `due_reminder_lead_hours` (IAppConfig, default `24`), applied to the rule annotation
- No duplicate reminders: at most one `task_due_soon` notification per (task, due date, recipient); a changed due date re-arms the reminder
- Respecting the existing `notify_due_reminder` user toggle by writing it through to the OR engine's override-only per-(schema, rule) user preference (`task`/`taskDueSoon`), instead of gating inside an app-local NotificationService
- Migration/repair step so users who already disabled `notify_due_reminder` get a matching OR override seeded

### Out of Scope

- Any imperative dispatch from planix code (no planix `BackgroundJob`, no `INotificationManager::notify()` calls for object notifications) — forbidden by ADR-031 / gate-18
- The frontend due-date warning badge — that is the in-flight change `openspec/changes/task-due-date-warning/` (frontend-only `dueDateStatus` helper + badge on the task card). This change is backend/declarative-only; the two share the `dueDate` field but no code or spec surface. The badge's hardcoded 2-day visual threshold is intentionally independent of the reminder lead time.
- `notify_overdue` (V1) — a second rule with a "past due" window can reuse this infrastructure later, but is not specced here
- `task_assigned` dispatch — already specced in `tasks.md` ("Assignment notification sent")
- Email/Talk/webhook channels — `nc-notification` only for MVP (push rides along automatically via notify_push)
- Implementing the OR engine extensions themselves (they land in the openregister repo)

## Approach

Declare the rule on the schema; let the engine do the work:

```jsonc
// lib/Settings/planix_register.json — components.schemas.task
"x-openregister-notifications": {
  "taskDueSoon": {
    "trigger": {
      "type": "scheduled",
      "intervalSec": 3600,
      "filter": {
        "dueDate": { "operator": "withinNext", "value": "PT24H" },
        "status":  { "operator": "notEquals", "value": "done" }
      }
    },
    "enabled": true,
    "channels": ["nc-notification"],
    "recipients": [ { "kind": "field", "field": "assignee" } ],
    "subject": {
      "en": "Task \"{{title}}\" is due soon",
      "nl": "Taak \"{{title}}\" moet binnenkort af"
    }
  }
}
```

The planix admin setting `due_reminder_lead_hours` is translated into the `withinNext` value when the register annotation is written/updated. The user toggle `notify_due_reminder` is mapped onto the OR engine's override-only user preference for (`task`, `taskDueSoon`) — the engine's `NotificationPreferenceService` resolves `user-override ?? schema-default`, so untouched users inherit the schema's `enabled: true`.

## New Dependencies

None (no new composer/npm packages).

## Cross-Project Dependencies

**OpenRegister notification engine — two verified gaps block the rule as written** (checked against `openregister/lib/BackgroundJob/ScheduledNotificationJob.php` on 2026-06-11):

1. **No date-threshold condition on `scheduled` filters.** `matchesFilter()` documents and implements only flat `{ field: value }` *equality* filters ("For v1 we only support flat `{ field: value }` filters"). A relative-date operator (e.g. `withinNext` with an ISO-8601 duration, evaluated against the job's `now`) and `notEquals` do not exist. This is the date-threshold sibling of the already-documented field-change gap on `updated` triggers (`hydra/openspec/fleet-notification-plan.md`, "Cross-cutting engine gap").
2. **Dedup is per-rule, not per-object.** `isDue()`/`markFired()` throttle the *rule* to once per `intervalSec`, but every firing re-notifies **all** matching objects — a task inside a 24 h window scanned hourly would generate ~24 reminders. The engine needs per-object dedup for scheduled rules, keyed at least by (object id, matched field value) so a *changed* due date re-arms the reminder.

This change is **BLOCKED_EXTERNAL on an openregister change** delivering both. The planix-side tasks that do not depend on the new operators (toggle write-through, admin setting, migration) can land first; the rule annotation lands behind the engine release.

## Impact

- `lib/Settings/planix_register.json` — `task` schema gains the `x-openregister-notifications.taskDueSoon` annotation
- `lib/Service/SettingsService.php` — `notify_due_reminder` write-through to the OR override; `due_reminder_lead_hours` admin setting that re-writes the rule's lead window
- `lib/Settings/AdminSettings.php` + admin settings UI — lead-time field in the existing notification/configuration section
- New `lib/Repair/` step — seed OR overrides for users with a pre-existing `notify_due_reminder = false`
- `openspec/specs/` — new `task-notifications` capability spec; `admin-user-settings` gains the write-through requirement

## Risks

### Risk 1: Engine extension slips
**Severity:** Medium — **Mitigation:** all planix work except the final annotation value is engine-independent; the rule can initially ship disabled (`"enabled": false`) and be flipped on when the engine release lands. The dependency is stated explicitly above and in tasks.md.

### Risk 2: Double gating (app toggle AND engine override drift apart)
**Severity:** Medium — **Mitigation:** the OR override becomes the single source of truth for *whether the engine sends*; planix's `IConfig` key is kept only as the UI's backing value and is written through on every toggle. The repair step reconciles pre-existing values once.

### Risk 3: Duplicate reminders if per-object dedup semantics differ from spec
**Severity:** Low — **Mitigation:** the dedup requirement (one per task+dueDate+recipient, re-armed on due-date change) is specced as planix-observable behavior and integration-tested against the engine.

## Rollback Strategy

Remove the `taskDueSoon` annotation from `planix_register.json` and re-import (idempotent repair step) — the engine stops scheduling the rule. The toggle write-through and admin setting are additive and can be reverted by commit; user overrides left behind are inert without the rule.
