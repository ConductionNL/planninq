# Tasks: Due Date Reminder Dispatch

## 0. External dependency (OpenRegister engine)

- [x] Confirm/queue the openregister engine change covering both verified gaps in `lib/BackgroundJob/ScheduledNotificationJob.php`. **RESOLVED 2026-06-14 — the engine has since shipped both extensions** (the 2026-06-11 verification is stale): (a) operator-form `scheduled` filter entries now exist in `lib/Service/Notification/ScheduledFilterEvaluator.php` — `withinNext` (half-open window `(now, now+duration]`, fail-closed on unparsable dates), `olderThan`, `equals`, `notEquals`; (b) per-object dedup is implemented via `NotificationDedupeStateMapper` (fingerprint keyed by schemaId/ruleKey/objectUuid, re-arms on field change, with a retention sweep) in `ScheduledNotificationJob::fire()`. The `field` recipient kind resolves the assignee uid (`AnnotationNotificationDispatcher::resolveRecipients()`) and verifies the user exists. **Section 2 is therefore unblocked and the rule ships `enabled: true`.**

## 1. Preference plumbing (engine-independent, can land first)

- [x] `lib/Service/SettingsService.php`: on `notify_due_reminder` write, also write through to the OR override for (`task`, `taskDueSoon`) — `{"enabled": false}` on toggle-off, clear (null) on toggle-on — via OpenRegister's `NotificationPreferenceService::setOverride()`. Guards gracefully when OpenRegister is unavailable (stores IConfig, logs, skips override). Wired through a new `SettingsController::updateUser()` (`@NoAdminRequired`) + `/api/settings/user` route + `saveUserSettings` store action + the `notify_due_reminder` toggle on `UserSettings.vue` (the dialog shipped as an empty placeholder).
- [x] New `lib/Repair/ReconcileDueReminderOverrides.php` + `<repair-steps>` `post-migration` entry in `appinfo/info.xml` (runs after `InitializeSettings` so the schema/rule exists): seeds `{"enabled": false}` OR overrides for every user with stored `notify_due_reminder = false`; idempotent and never clobbers an override the user changed since (`hasExistingOverride` check).
- [x] PHPUnit: SettingsService write-through (off writes override, on clears it, OR-unavailable path, getter defaults) and repair-step seeding/idempotency/no-clobber/no-op paths.

## 2. Declarative rule (blocked on section 0)

- [x] Add `x-openregister-notifications.taskDueSoon` to the `task` schema in `lib/Settings/planix_register.json`: `scheduled` trigger `intervalSec: 3600`, filter `dueDate withinNext PT24H` + `status notEquals done`, `enabled: true`, channel `nc-notification`, recipient `{"kind":"field","field":"assignedTo"}`, en + nl subjects with `{{title}}` (English source strings as keys). **Recipient field is `assignedTo` (the real task-schema property) — the spec text said `assignee`, which does not exist on the schema; a unit test guards that the recipient field exists.** Ships `enabled: true` (engine dependency resolved — see section 0).
- [x] Verify idempotent install: register import applies the annotation; the lead-window patch (`SettingsService::patchDueReminderWindow`) updates only the live schema's `withinNext` value, so a re-import (which carries the default `PT24H`) is the documented interplay — a customised lead time is re-applied by the admin save, and `loadConfiguration` uses OR's `importFromApp` which is idempotent on the rule shape.
- [x] PHPUnit: static assertion on `planix_register.json` — rule present, dialect-valid shape (trigger/channels/recipients/subject keys), both locales present, `{{title}}` placeholder, recipient field exists on schema.
- [~] Newman (`tests/integration/*.postman_collection.json`): scheduled-job dispatch is engine-owned and requires a live OR install with the engine + a Planix register. **Deferred — planix is not installed in the dev container (greenfield in CI), so an end-to-end Newman run against the scheduled job cannot be executed here.** The observable behaviours (one notification per task+dueDate, no re-notify, done/unassigned/no-dueDate skipped, opt-out gated) are specced and the engine paths (`ScheduledFilterEvaluator`, dedup mapper, override resolution) are verified by reading the engine; the planix-side rule shape + write-through + override seeding are PHPUnit-covered.

## 3. Admin lead-time setting

- [x] `lib/Service/SettingsService.php`: admin `IAppConfig` key `due_reminder_lead_hours` (integer string, default `24`, validates 1–336; rejects out-of-range/non-numeric without persisting via `validateLeadHours`). On save, patches the live `taskDueSoon` rule window (`PT{n}H`) via the OR `SchemaMapper` (`findBySlug` → `setConfiguration` → `update`), guarded when OR is unavailable. Flows through the existing admin `SettingsController::create()` / `updateSettings()`.
- [x] Admin settings UI: added "Due-date reminder lead time (hours)" numeric field in a new "Notification settings" CnSettingsSection in `Settings.vue`; inline validation error (`Lead time must be between 1 and 336 hours`) on invalid input before submit.
- [x] PHPUnit: validation bounds (1/24/336 accepted; 0/337/1000/abc/empty/12.5/-5 rejected), default resolution (24), stored value, `leadHoursToDuration` formatting.

## 4. Tests, i18n, quality

- [x] Playwright e2e (UI only): `tests/e2e/due-date-reminder-settings.spec.ts` — user-settings dialog toggle off/reload/persists/back-on; admin lead-time field default 24, save 48 persists, `0` shows validation error. Skips cleanly when planix is not installed (greenfield container), per the existing scaffolded-e2e convention.
- [x] i18n: nl translations added for all new UI strings (toggle label, notification-section name/description, admin field label, validation + save messages); en/nl notification subjects live in the register JSON. Existing l10n tooling only — no new languages.
- [x] Hydra gates: all 24 green for the diff (`run-hydra-gates.sh --scope-to-diff`), incl. gate-18 `notification-dialect` (canonical dialect only, no imperative dispatch), gate-16 spec-coverage, gate-7 no-admin-idor. Fixed a pre-existing `SettingsControllerTest` constructor mismatch (missing `IUserSession`) + missing user-session stub in `index()` while touching the controller. PHPUnit (docker php:8.3): 36/36 green across the changed/new test files; the 5 remaining suite failures are a pre-existing `ProjectControllerTest` mock-fidelity artifact (stdClass `addMethods` mocks reject named args in the bare container — they pass in CI where the real `ObjectService` class exists). `composer check:strict` not runnable in the bare container (no NC `lib/base.php`); `php -l` clean on all changed PHP.

## 5. Docs & spec sync

- [x] Update `docs/FEATURES.md` §3.3 status for the due-date reminder (specced → implemented) and note the configurable lead time.
- [x] On archive: sync deltas — `openspec archive` folds the `task-notifications` delta into a new `openspec/specs/task-notifications.md` and the ADDED requirements into `openspec/specs/admin-user-settings.md`.
