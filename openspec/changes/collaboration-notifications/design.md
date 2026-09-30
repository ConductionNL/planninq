# Design: notify people when a task is assigned to them, and by email when they ask for it

## Context

What exists at de35541:

- The task schema declares one notification rule, `taskDueSoon` (`lib/Settings/planninq_register.json:145-175`): trigger `scheduled` every hour with a filter on `dueDate` within the next 24 hours and status not done, channel `nc-notification`, recipient `{ kind: field, field: assignedTo }`, subject in English and Dutch. OpenRegister delivers it; planninq has no notifier and no `IManager::notify()` call (the flat spec `openspec/specs/task-notifications.md` requires "No imperative dispatch in planninq code").
- The per-user switch for that rule is `notify_due_reminder`: `SettingsService::setNotifyDueReminder` stores it in Nextcloud user config and writes it through to OpenRegister's per-(schema, rule) override with `NotificationPreferenceService::setOverride` (`lib/Service/SettingsService.php:442-463`, `writeDueReminderOverride` at `:498-524`, rule key constant `DUE_REMINDER_RULE_KEY` at `:79`). `src/views/settings/UserSettings.vue:12-17` shows it as the only switch in the Notifications section.
- `TaskActivityListener` publishes `task_assigned_activity` to the Activity stream when `assignedTo` changes (`lib/Listener/TaskActivityListener.php:182-210`); status changes take precedence in the same save (`:191-195`). That stays as it is.
- `lib/Activity/` holds `Filter.php`, `Provider.php` and `ProviderSubjectHandler.php`; there is no activity `ISetting`.
- The canonical dialect (hydra ADR-031, section "The `x-openregister-notifications` dialect"): trigger types include `created` and `updated`; `updated` takes a `condition` such as `{ "field": "assignedTo", "operator": "changed" }` and fails closed when old data is missing; channels include `nc-notification` and `email`; recipients include `{ kind: field }` (a Nextcloud uid) and `{ kind: expression, resolver: <DI tag> }`, a class implementing OpenRegister's `RecipientResolverInterface` (`resolve(ObjectEntity $object, array $context): array`). The hydra gate `notification-dialect` rejects the legacy dialect and warns on imperative dispatch in a leaf app.
- OpenRegister's engine spec (read at 63ddfd5, `openspec/specs/notificatie-engine/spec.md`, "The dispatcher MUST consult the merged preference before delivering the in-app/push channel") gates the in-app channel on the user's override; it does not say the same for `email`.

## Goals / non-goals

Goals:
- A notification in the Nextcloud bell for every new assignment, with a per-user off switch.
- Mail for the people who ask for it, for assignments and due-date reminders.
- Declared rules only, no dispatch code.

Non-goals:
- Notifications for comments, status changes, overdue tasks or `sharedWith`; digest mail; Activity app mail.

## Decisions

### Decision 1: two in-app rules for assignment
`taskAssignedOnCreate` with trigger `created` and `taskAssigned` with trigger `updated` and condition `{ field: assignedTo, operator: changed }`. Both: channel `nc-notification`, recipient `{ kind: field, field: assignedTo }`, subject "Task \"{{title}}\" was assigned to you" and "Taak \"{{title}}\" is aan jou toegewezen", `originApp: planninq`, the implicit "View" action to the task. Two rules because one rule has one trigger. A task created without an assignee and an update that clears the assignee resolve no recipient, so they notify nobody.

### Decision 2: the in-app switch writes through like the due reminder
A new user key `notify_assigned` (default on, listed as MVP in `docs/FEATURES.md` section 3.2). `NotificationSwitchService::setAssigned` (amended at build time: a service of its own, applied by `SettingsController` beside `SettingsService`, because `SettingsService` is at phpmd's complexity 75 and coupling 13 limits) stores it and writes the OpenRegister override `{ enabled: false }` for both assignment rules when off, and clears it when on, reusing the write-through of `setNotifyDueReminder`. The switch "Notify me when a task is assigned to me" sits above the due-date switch in `UserSettings.vue`.

### Decision 3: email is separate rules with an opt-in resolver
`taskAssignedEmail` (same triggers as `taskAssigned`) and `taskDueSoonEmail` (same trigger and filter as `taskDueSoon`) declare channel `email` only, and their recipient is `{ kind: expression, resolver: planninq.recipients.emailOptIn }`. `EmailOptInRecipientResolver` returns the task's `assignedTo` when that user has `notify_by_email` on in Nextcloud user config and has an email address, and nothing otherwise. Because the email channel is not documented as preference-gated, the resolver is the gate, checked on every dispatch. A creation with an assignee is covered by a third email rule `taskAssignedOnCreateEmail` with trigger `created`, so the email rules mirror the in-app rules one to one. The alternative, adding `email` to the in-app rules and letting users narrow channels through OpenRegister overrides, depends on an override shape the engine describes as "when supported", and would mail everyone by default.

### Decision 4: one email switch
A new user key `notify_by_email` (default off). The switch "Also send these to me by email" in `UserSettings.vue` covers both assignment and due-date mail. It is disabled with the hint "Add an email address in your Nextcloud personal settings to get mail." when the account has no email address; `GET /api/settings` returns whether it has one.

### Decision 5: the in-app switches also silence the matching mail
The resolver returns nobody for `taskAssignedEmail` when `notify_assigned` is off, and nobody for `taskDueSoonEmail` when `notify_due_reminder` is off. One mental model for the user: the first switches choose what they hear about, the email switch chooses whether it also comes by mail.

## Risks / trade-offs

- [Mail nobody asked for] -> Off by default; the resolver checks the switch on every dispatch.
- [Rule keys multiply] -> Five new keys, all mirrors of two existing events; covered by one PHPUnit test that asserts each email rule matches its in-app twin's trigger.
- [The dialect gate] -> The resolver is an `expression` recipient of a declared rule, not dispatch code; `hydra-gate-notification-dialect` is run on the register before push.

## Open questions

- Does OpenRegister pass the acting user to a notification rule, either to recipients or in the resolver `context`? If it does, the assignment rules use a resolver that skips a user who assigned the task to themselves. If it does not, self-assignment notifies, which is accepted until it does.
