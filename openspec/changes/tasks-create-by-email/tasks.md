# Tasks: tasks-create-by-email

## 1. Settings and connection

- [x] 1.1 Admin keys (`mail_intake_enabled`, host, port, encryption, username, `mail_intake_credential_ref`, folder, address, `mail_intake_require_auth` default on, size limit) with validation, and the password stored through OpenRegister's credential broker. Verify: PHPUnit `SettingsServiceTest::testMailIntakeStoresOnlyACredentialRef` and `testMailIntakeRejectsInvalidPort`.
  - Done in `lib/Service/MailIntake/MailIntakeSettings.php` (keys, validation, `isReady()`, `projectAddress()`) and `MailboxCredentialStore.php` (the password is minted as a `generic-basic` broker credential allowed only to planninq; only the reference and its owner are kept, and the job resolves it with `resolveInjectable()` as that owner). `SettingsService` saves them on the admin path and shows every key to admins, only the switch and the address to others. The tests are `MailIntakeSettingsTest::testMailIntakeStoresOnlyACredentialRef` and `testMailIntakeRejectsInvalidPort` (in their own class, not `SettingsServiceTest`), plus `MailboxCredentialStoreTest`.
- [x] 1.2 "Create tasks by email" section in `src/views/settings/Settings.vue` with "Test connection". Verify: Playwright `tests/e2e/mail-intake.spec.ts` "admin connects a mailbox and tests it" against the CI mail server.
  - Done as `src/components/MailIntakeSettings.vue`, mounted in `Settings.vue`: every key, a write-only password field (left empty keeps the stored password), Save, and "Test connection" on `POST /api/settings/mail-intake/test` (`MailIntakeController`, admin only), which `MailboxConnectionTest` answers with the folder's message count or why not (incomplete, no IMAP client, password not resolved, refused). Unit tests: `MailboxConnectionTestTest`, `MailIntakeControllerTest`, `tests/vitest/mailIntake.spec.js`. The Playwright run against the CI mail server is not done in this lane, and it needs 2.1 to connect.
- [x] 1.3 Project address line with copy button in `src/components/ProjectSettingsSidebar.vue`. Verify: Playwright `tests/e2e/mail-intake.spec.ts` "project settings show the project's address".
  - Done: while intake is on, the General tab shows "Mail tasks to planninq+VC@gemeente.nl" with a copy button, from `projectAddress()` in `src/utils/mailIntake.js` (pinned in `tests/vitest/mailIntake.spec.js`). The Playwright run is not done in this lane.

## 2. Intake

- [ ] 2.1 `lib/Service/ImapClient.php` around a pure-PHP IMAP library added through composer. Verify: PHPUnit `ImapClientTest` against a fake transport; `composer audit` clean.
- [x] 2.2 `lib/Service/MailIntakeService.php`: project from plus-address or `[KEY]`, sender to member, authentication-results check, task fields, Message-ID dedup, attachments, reply, filing. Verify: PHPUnit `MailIntakeServiceTest::testPlusAddressPicksProject`, `testSubjectTagPicksProject`, `testNonMemberSenderIsRejectedWithoutReply`, `testFailedDkimIsRejected`, `testUnknownProjectRepliesToMember`, `testTaskFieldsFromMail`, `testSameMessageIdCreatesNoSecondTask`, `testOversizedAttachmentIsSkippedAndNamed` and `testMessageFiledOnlyAfterSave`.
  - Done. Every named test is in `tests/unit/Service/MailIntakeServiceTest.php`, plus `testAUserOutsideTheProjectIsRejectedWithoutReply` and `testTitleAndDescription`. The reply has `Auto-Submitted: auto-replied`; Nextcloud's `IMessage` cannot set `In-Reply-To`, so the reply threads only by its `Re:` subject.
- [x] 2.3 `lib/BackgroundJob/MailIntakeJob.php` (five minutes, at most 50 messages per run, no-op when disabled), registered in `appinfo/info.xml`. Verify: PHPUnit `MailIntakeJobTest::testDisabledIntakeDoesNothing` and `testProcessesAtMostFiftyMessages`.
  - Done; registered in `appinfo/info.xml`. Tests in `tests/unit/BackgroundJob/MailIntakeJobTest.php`. The job reads through the `MailboxConnector` / `MailboxClient` interfaces and logs a warning while no IMAP client implements them (task 2.1).
- [ ] 2.4 Live check with the CI mail server: send a mail from a member to the project address, run the job, open the backlog. Verify: Playwright `tests/e2e/mail-intake.spec.ts` "member's mail becomes a backlog task with its attachment" and "member receives a confirmation with the task key".

## 3. Verification

- [ ] 3.1 `openspec validate tasks-create-by-email --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
