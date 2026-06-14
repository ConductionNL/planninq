# Admin & User Settings Specification (Delta)

**Status**: proposed
**Scope**: planix
**OpenSpec changes**:
- [due-date-reminder-dispatch](../../) — wires the `notify_due_reminder` toggle to the OpenRegister dispatch and adds the lead-time admin setting

## Purpose

Extends the admin & user settings capability so that the existing `notify_due_reminder` toggle actually gates the engine-sent `task_due_soon` notification (see the `task-notifications` delta), and adds the admin-configurable reminder lead time. Before this change the toggle was stored but gated nothing.

## ADDED Requirements

### Requirement: Due reminder toggle write-through to OpenRegister [MVP]
The `notify_due_reminder` user setting MUST be written through to the OpenRegister notification engine's override-only per-(schema, rule) user preference for (`task`, `taskDueSoon`):

- Toggling OFF MUST store the planix `IConfig` value `false` AND write the OR override `{"enabled": false}`
- Toggling ON MUST store the planix `IConfig` value `true` AND clear the OR override (null), so the user falls through to the schema default rather than pinning a stale explicit value
- The planix `IConfig` key remains the backing value the settings dialog reads; the OR override is the single source of truth for whether the engine delivers

#### Scenario: Toggle due-date reminders off
- GIVEN the user settings dialog is open
- WHEN the user toggles "Notify me 1 day before a task's due date" to off
- THEN the system MUST save `notify_due_reminder = false` via OCP\IConfig
- AND an OpenRegister override `{"enabled": false}` MUST be stored for this user for (`task`, `taskDueSoon`)
- AND subsequent `task_due_soon` notifications MUST NOT be delivered to this user

#### Scenario: Toggle due-date reminders back on
- GIVEN a user previously toggled `notify_due_reminder` off
- WHEN the user toggles it back on
- THEN the system MUST save `notify_due_reminder = true` via OCP\IConfig
- AND the user's OpenRegister override for (`task`, `taskDueSoon`) MUST be cleared (schema default applies)

#### Scenario: Existing opt-outs reconciled on upgrade
@e2e exclude one-shot upgrade repair step, covered by PHPUnit on the repair step
- GIVEN users who set `notify_due_reminder = false` before this change shipped
- WHEN planix is upgraded and the reconciliation repair step runs
- THEN each such user MUST have an OR override `{"enabled": false}` seeded for (`task`, `taskDueSoon`)
- AND running the repair step again MUST NOT alter overrides that users changed in the meantime (idempotent, no clobber)

### Requirement: Admin lead-time setting [MVP]
The admin settings page MUST expose the due-date reminder lead time as an editable field inside an existing CnSettingsSection, backed by `IAppConfig` key `due_reminder_lead_hours` (integer string, default `24`, validated range 1–336 hours).

#### Scenario: Lead-time field shown with default
- GIVEN a Nextcloud admin opens Administration → Planix
- THEN a "Due-date reminder lead time (hours)" field MUST be visible
- AND on a fresh install it MUST show `24`

#### Scenario: Saving a new lead time
- GIVEN the admin changes the lead time to `48` and saves
- THEN `due_reminder_lead_hours = 48` MUST be stored via IAppConfig
- AND the `taskDueSoon` rule window MUST be updated accordingly (per the `task-notifications` capability)

#### Scenario: Invalid lead time rejected in the UI
- GIVEN the admin enters `0` or a non-numeric value
- WHEN the admin attempts to save
- THEN the field MUST show a validation error
- AND no value MUST be persisted

## Acceptance Criteria

- [ ] Toggling `notify_due_reminder` off writes the OR override; toggling on clears it
- [ ] Upgrade repair step seeds overrides for pre-existing opt-outs, idempotently
- [ ] Admin lead-time field present, default 24, range-validated, persisted via IAppConfig
- [ ] Settings UI remains NcAppSettingsDialog (user) / CnSettingsSection (admin) per the base spec

## Notes

- The base spec's `SUBJECT_SETTING_MAP` note continues to apply only to app-sent notifications (`task_assigned`); `task_due_soon` is engine-sent and engine-gated via the override.
- No new settings endpoints: `SettingsService` performs the OR-override write-through server-side when `/settings/user` receives the toggle, keeping the frontend unchanged except for the new admin field.
