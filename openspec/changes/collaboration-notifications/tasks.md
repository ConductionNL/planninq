# Tasks: collaboration-notifications

## 1. In-app assignment notification

- [ ] 1.1 Declare `taskAssignedOnCreate` (trigger `created`) and `taskAssigned` (trigger `updated`, condition `assignedTo` changed) on the task schema in the canonical dialect, and bump the schema version. Verify: PHPUnit `PlanninqRegisterSchemaTest::testAssignmentRulesUseCanonicalDialect`; `hydra-gate-notification-dialect` passes on `lib/Settings/planninq_register.json`.
- [ ] 1.2 `notify_assigned` user key and `SettingsService::setNotifyAssigned` writing the OpenRegister override for both rules; switch in `src/views/settings/UserSettings.vue`. Verify: PHPUnit `SettingsServiceTest::testNotifyAssignedOffWritesOverrideForBothRules` and `testNotifyAssignedOnClearsOverride`.
- [ ] 1.3 Live check: assign a task to a second user and read their notifications. Verify: Playwright `tests/e2e/notifications.spec.ts` "assignee gets a notification with a link to the task", "a task created for someone notifies them", "clearing the assignee notifies nobody" and "switching assignment notifications off stops them".

## 2. Email

- [ ] 2.1 Declare `taskAssignedOnCreateEmail`, `taskAssignedEmail` and `taskDueSoonEmail` (channel `email`, recipient `expression` `planninq.recipients.emailOptIn`). Verify: PHPUnit `PlanninqRegisterSchemaTest::testEmailRulesMirrorInAppRules`.
- [ ] 2.2 `lib/Notification/EmailOptInRecipientResolver.php` registered under `planninq.recipients.emailOptIn`. Verify: PHPUnit `EmailOptInRecipientResolverTest::testOptedInAssigneeIsReturned`, `testOptedOutAssigneeIsNotReturned`, `testUserWithoutEmailIsNotReturned` and `testInAppSwitchOffSilencesMatchingMail`.
- [ ] 2.3 `notify_by_email` user key (default off), `hasEmail` in `GET /api/settings`, and the switch with its hint. Verify: PHPUnit `SettingsServiceTest::testNotifyByEmailDefaultsOff`; Playwright `tests/e2e/notifications.spec.ts` "email switch is disabled without an email address".
- [ ] 2.4 Live check with the CI mail catcher: an opted-in assignee gets a mail, an opted-out one does not. Verify: Playwright `tests/e2e/notifications.spec.ts` "opted-in assignee gets an assignment mail", "without opting in no mail is sent" and "due-date reminder arrives by mail" (the due-soon run triggered with `occ background-job:execute` on OpenRegister's scheduled notification job).

## 3. Verification

- [ ] 3.1 `openspec validate collaboration-notifications --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
