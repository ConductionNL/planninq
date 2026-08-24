# Task Notifications Specification (Delta)

**Status**: proposed
**Scope**: planix
**OpenSpec changes**:
- [due-date-reminder-dispatch](../../) — adds the declarative `task_due_soon` reminder dispatch

## Purpose

Defines the dispatch of the `task_due_soon` notification: a schema-rule-based reminder, declared on the planix `task` schema in the canonical `x-openregister-notifications` dialect (ADR-031) and evaluated by the OpenRegister notification engine. Closes the gap where the `notify_due_reminder` user toggle (admin-user-settings spec) existed without any sender. Planix MUST NOT dispatch this notification imperatively from app code.

## ADDED Requirements

### Requirement: Declarative taskDueSoon rule on the task schema [MVP]
The `task` schema in `lib/Settings/planix_register.json` MUST declare a `taskDueSoon` notification rule under the top-level `x-openregister-notifications` key, using the canonical dialect:

- `trigger`: type `scheduled` with `intervalSec: 3600` and a filter matching tasks whose `dueDate` falls within the configured lead window AND whose `status` is not `done`
- `enabled`: `true` (schema default; users opt out via the engine's override-only preference)
- `channels`: `["nc-notification"]`
- `recipients`: `[{"kind": "field", "field": "assignee"}]`
- `subject`: English and Dutch templates interpolating the task `{{title}}` (English source strings as i18n keys)

Planix MUST NOT contain a BackgroundJob, event listener, or `INotificationManager` dispatch path for `task_due_soon` (gate-18 `notification-dialect`).

#### Scenario: Rule present in the register definition
@e2e exclude static register-definition assertion, covered by PHPUnit against planix_register.json
- GIVEN the planix register definition `lib/Settings/planix_register.json`
- WHEN the `task` schema is inspected
- THEN it MUST contain `x-openregister-notifications.taskDueSoon` with a `scheduled` trigger, `nc-notification` channel, `assignee` field recipient, and en + nl subjects

#### Scenario: Rule installed on register import
@e2e exclude backend install path, covered by Newman against the OR schema API
- GIVEN a fresh planix install with OpenRegister available
- WHEN the register import runs (repair step / admin "Initialize register")
- THEN the live `task` schema in OpenRegister MUST carry the `taskDueSoon` rule
- AND re-running the import MUST NOT duplicate or reset the rule (idempotent)

#### Scenario: No imperative dispatch in planix code
@e2e exclude static code-shape gate, enforced by hydra gate-18 notification-dialect
- GIVEN the planix `lib/` source tree
- WHEN scanned for object-notification dispatch
- THEN no planix class may send `task_due_soon` via `INotificationManager` or a planix background job

### Requirement: Due-soon detection window [MVP]
A task MUST be detected as due-soon when ALL of the following hold at evaluation time:

- the task has a `dueDate` set
- the `dueDate` is within the configured lead window from now (default 24 hours), using date-only comparison consistent with the `dueDate` field type (iCalendar `DUE`, type `date`)
- the task `status` is not `done`
- the task has an `assignee` (no recipient → no notification, not an error)

Tasks outside the window, without a due date, completed, or unassigned MUST NOT trigger the reminder.

#### Scenario: Task due within the lead window is detected
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN a task assigned to user `alice` with `dueDate` 12 hours from now and status `in-progress`
- AND the lead time is the default 24 hours
- WHEN the engine's scheduled evaluation runs
- THEN `alice` MUST receive one `task_due_soon` Nextcloud notification naming the task title

#### Scenario: Task due far in the future is not detected
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN a task with `dueDate` 3 days from now
- AND the lead time is the default 24 hours
- WHEN the engine's scheduled evaluation runs
- THEN no `task_due_soon` notification MUST be sent for that task

#### Scenario: Completed task is not detected
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN a task with `dueDate` 12 hours from now and status `done`
- WHEN the engine's scheduled evaluation runs
- THEN no `task_due_soon` notification MUST be sent for that task

#### Scenario: Task without due date or assignee is skipped
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN one task with no `dueDate` and one task with `dueDate` 12 hours from now but no `assignee`
- WHEN the engine's scheduled evaluation runs
- THEN no `task_due_soon` notification MUST be sent for either task
- AND evaluation MUST continue normally for other tasks

### Requirement: Configurable lead time [MVP]
The reminder lead time MUST be configurable by a Nextcloud admin via the `IAppConfig` key `due_reminder_lead_hours` (integer string, default `24`, accepted range 1–336). Changing the setting MUST update the `taskDueSoon` rule's window on the live schema, and a subsequent register re-import MUST NOT reset a customised lead time back to the default.

#### Scenario: Default lead time is 24 hours
@e2e exclude config-default assertion, covered by PHPUnit on SettingsService
- GIVEN a fresh planix install where the admin has never changed the setting
- WHEN the effective lead time is resolved
- THEN it MUST be 24 hours

#### Scenario: Admin changes the lead time
- GIVEN a Nextcloud admin opens Administration → Planix
- WHEN the admin sets the due-date reminder lead time to 48 hours and saves
- THEN `due_reminder_lead_hours = 48` MUST be stored via IAppConfig
- AND the live `taskDueSoon` rule's window MUST be updated to 48 hours
- AND a task due in 30 hours MUST now be detected as due-soon on the next evaluation

#### Scenario: Out-of-range lead time is rejected
@e2e exclude input validation, covered by PHPUnit on SettingsService
- GIVEN an admin submits a lead time of `0` or `1000` hours
- WHEN the setting is saved
- THEN the system MUST reject the value with a validation error
- AND the stored setting MUST remain unchanged

### Requirement: No duplicate reminders per task [MVP]
At most one `task_due_soon` notification MUST be delivered per (task, due date, recipient). Repeated scheduled evaluations while a task remains inside the lead window MUST NOT re-notify. Changing a task's `dueDate` to a new value re-arms the reminder: when the new due date later enters the window, one new notification MUST be sent.

#### Scenario: Hourly re-evaluation does not re-notify
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN `alice` already received a `task_due_soon` notification for a task due in 12 hours
- WHEN the scheduled evaluation runs again one hour later and the task is unchanged
- THEN no additional `task_due_soon` notification MUST be sent to `alice` for that task

#### Scenario: Moved due date re-arms the reminder
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN `alice` received a `task_due_soon` notification for a task, after which the task's `dueDate` was moved 5 days later
- WHEN the new due date enters the lead window and the scheduled evaluation runs
- THEN `alice` MUST receive exactly one new `task_due_soon` notification for the task

### Requirement: Per-user opt-out respected [MVP]
The dispatch MUST respect the user's `notify_due_reminder` preference through the OpenRegister engine's override-only per-(schema, rule) user preference for (`task`, `taskDueSoon`). A user whose effective preference is disabled MUST NOT receive `task_due_soon` notifications; users without an override inherit the schema default (`enabled: true`).

#### Scenario: Opted-out assignee receives no reminder
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN `bob` has `notify_due_reminder` toggled off (OR override `{"enabled": false}` stored for `task`/`taskDueSoon`)
- AND a task assigned to `bob` is due in 12 hours
- WHEN the scheduled evaluation runs
- THEN `bob` MUST NOT receive a `task_due_soon` notification
- AND other matching recipients without an override MUST still be notified

#### Scenario: Default-on without an override
@e2e exclude background scheduled-job behavior, covered by Newman integration test
- GIVEN `carol` has never touched her notification settings
- AND a task assigned to `carol` is due in 12 hours
- WHEN the scheduled evaluation runs
- THEN `carol` MUST receive the `task_due_soon` notification (schema default applies)

## Non-Functional Requirements

- **Performance:** detection runs inside the engine's scheduled job; planix adds no polling, listeners, or cron entries of its own
- **Internationalization:** subject templates ship en + nl; English source strings are the keys (ADR-007)
- **Reliability:** an unassigned or malformed task MUST NOT abort the evaluation batch

## Acceptance Criteria

- [ ] `taskDueSoon` rule present in `planix_register.json` and on the live schema after import
- [ ] Task due within lead window + not done + assigned → exactly one notification to the assignee
- [ ] No notification for far-future, completed, unassigned, or due-date-less tasks
- [ ] Lead time configurable (default 24 h, validated range), survives re-import
- [ ] No duplicates across repeated evaluations; due-date change re-arms
- [ ] OR per-user override gates delivery; default is on
- [ ] gate-18 `notification-dialect` passes (canonical dialect, no imperative dispatch)

## Notes

- **BLOCKED_EXTERNAL — OpenRegister engine extensions required** (verified against `openregister/lib/BackgroundJob/ScheduledNotificationJob.php`, 2026-06-11): (1) `scheduled` trigger filters are flat equality only — a date-threshold operator (`withinNext` + `notEquals`) must be added to the dialect and `matchesFilter()`; (2) dedup is per-rule-per-interval (`isDue`/`markFired`), not per-object — per-object fired-markers keyed on (object, matched due date) are required for the no-duplicates requirement. Both are tracked as one openregister change; this rule ships `"enabled": false` (or unmerged) until that lands. This is the date-threshold sibling of the field-change gap already recorded in `hydra/openspec/fleet-notification-plan.md`.
- The in-flight `task-due-date-warning` change is frontend-only (badge, 2-day hardcoded visual threshold) and explicitly excludes notifications; the two thresholds are intentionally independent.
- `notify_overdue` (V1) can be a second rule (`taskOverdue`) reusing this pattern with a past-due window.
