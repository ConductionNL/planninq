# Tasks: Due Date Reminder Dispatch

## 0. External dependency (OpenRegister engine)

- [ ] Confirm/queue the openregister engine change covering both verified gaps in `lib/BackgroundJob/ScheduledNotificationJob.php`: (a) operator-form `scheduled` filter entries — minimally `withinNext` (ISO-8601 duration vs job `now`, date-only comparison for `date`-typed fields) and `notEquals`; (b) per-object dedup for scheduled rules keyed by (schemaId, ruleKey, objectId, matched due-date value) so an unchanged task fires once and a moved due date re-arms. Link it from `hydra/openspec/fleet-notification-plan.md` next to the existing `updated` field-change gap. **All tasks in section 2 are blocked on this; sections 1 and 3–5 are not.**

## 1. Preference plumbing (engine-independent, can land first)

- [ ] `lib/Service/SettingsService.php`: on `notify_due_reminder` write, also write through to the OR override for (`task`, `taskDueSoon`) — `{"enabled": false}` on toggle-off, clear (null) on toggle-on — via OpenRegister's notification-preference service/API. Guard gracefully when OpenRegister is unavailable (store IConfig, log, skip override).
- [ ] New `lib/Repair/ReconcileDueReminderOverrides.php` + `<repair-steps>` entry in `appinfo/info.xml` (fleet Repair-step pattern, NOT a migration): seed `{"enabled": false}` OR overrides for every user with stored `notify_due_reminder = false`; idempotent and never clobbers an override the user changed since.
- [ ] PHPUnit: SettingsService write-through (off writes override, on clears it, OR-unavailable path) and repair-step seeding/idempotency.

## 2. Declarative rule (blocked on section 0)

- [ ] Add `x-openregister-notifications.taskDueSoon` to the `task` schema in `lib/Settings/planix_register.json`: `scheduled` trigger `intervalSec: 3600`, filter `dueDate withinNext PT24H` + `status notEquals done`, `enabled: true`, channel `nc-notification`, recipient `{"kind":"field","field":"assignee"}`, en + nl subjects with `{{title}}` (English source strings as keys). Ship `enabled: false` if merging ahead of the engine release.
- [ ] Verify idempotent install: register import (repair step / admin "Initialize register") applies the annotation; re-import neither duplicates nor resets it, and does not reset a customised lead window (see section 3 interplay).
- [ ] PHPUnit: static assertion on `planix_register.json` — rule present, dialect-valid shape (trigger/channels/recipients/subject keys), both locales present.
- [ ] Newman (`tests/integration/*.postman_collection.json`): with the engine release installed — seed task due in 12 h assigned to test user → trigger scheduled job → exactly one `task_due_soon` notification; second run → zero new; `done` task → zero; unassigned/no-dueDate task → zero; opted-out user → zero while default user still notified; move dueDate later then back into window → exactly one new.

## 3. Admin lead-time setting

- [ ] `lib/Service/SettingsService.php` + `SettingsController`: admin `IAppConfig` key `due_reminder_lead_hours` (integer string, default `24`, validate 1–336; reject out-of-range/non-numeric without persisting). On save, patch the live `taskDueSoon` rule window (`PT{n}H`) via the OR schema-update API.
- [ ] Admin settings UI: add "Due-date reminder lead time (hours)" numeric field to the existing notification/configuration CnSettingsSection (after CnVersionInfoCard, per conventions); inline validation error on invalid input.
- [ ] PHPUnit: validation bounds, default resolution, annotation-patch call shape.

## 4. Tests, i18n, quality

- [ ] Playwright e2e (UI only): user-settings dialog — toggle `notify_due_reminder` off, reload, toggle persists; admin settings — lead-time field default 24, save 48 persists, `0` shows validation error. Reference the unexcluded scenarios from both spec deltas (gate-19).
- [ ] i18n: nl translations for all new strings (toggle label exists; new admin field label, validation message, en/nl notification subjects live in the register JSON).
- [ ] Run `composer check:strict` + hydra gates; gate-18 `notification-dialect` must pass (canonical dialect only, no imperative dispatch, no legacy dialect in `lib/Settings/*register*.json`). Fix any pre-existing issues encountered.

## 5. Docs & spec sync

- [ ] Update `docs/FEATURES.md` §3.3 status for the due-date reminder (specced → implemented once shipped) and note the configurable lead time.
- [ ] On archive: sync deltas — create `openspec/specs/task-notifications.md` from the delta and fold the ADDED requirements into `openspec/specs/admin-user-settings.md`; cross-link from `tasks.md` spec ("Assignment notification sent") so all task notifications are discoverable from one place.
