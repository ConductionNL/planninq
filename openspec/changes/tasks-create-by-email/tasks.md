# Tasks: tasks-create-by-email

## 1. Settings and connection

- [ ] 1.1 Admin keys (`mail_intake_enabled`, host, port, encryption, username, `mail_intake_credential_ref`, folder, address, `mail_intake_require_auth` default on, size limit) with validation, and the password stored through OpenRegister's credential broker. Verify: PHPUnit `SettingsServiceTest::testMailIntakeStoresOnlyACredentialRef` and `testMailIntakeRejectsInvalidPort`.
- [ ] 1.2 "Create tasks by email" section in `src/views/settings/Settings.vue` with "Test connection". Verify: Playwright `tests/e2e/mail-intake.spec.ts` "admin connects a mailbox and tests it" against the CI mail server.
- [ ] 1.3 Project address line with copy button in `src/components/ProjectSettingsSidebar.vue`. Verify: Playwright `tests/e2e/mail-intake.spec.ts` "project settings show the project's address".

## 2. Intake

- [ ] 2.1 `lib/Service/ImapClient.php` around a pure-PHP IMAP library added through composer. Verify: PHPUnit `ImapClientTest` against a fake transport; `composer audit` clean.
- [ ] 2.2 `lib/Service/MailIntakeService.php`: project from plus-address or `[KEY]`, sender to member, authentication-results check, task fields, Message-ID dedup, attachments, reply, filing. Verify: PHPUnit `MailIntakeServiceTest::testPlusAddressPicksProject`, `testSubjectTagPicksProject`, `testNonMemberSenderIsRejectedWithoutReply`, `testFailedDkimIsRejected`, `testUnknownProjectRepliesToMember`, `testTaskFieldsFromMail`, `testSameMessageIdCreatesNoSecondTask`, `testOversizedAttachmentIsSkippedAndNamed` and `testMessageFiledOnlyAfterSave`.
- [ ] 2.3 `lib/BackgroundJob/MailIntakeJob.php` (five minutes, at most 50 messages per run, no-op when disabled), registered in `appinfo/info.xml`. Verify: PHPUnit `MailIntakeJobTest::testDisabledIntakeDoesNothing` and `testProcessesAtMostFiftyMessages`.
- [ ] 2.4 Live check with the CI mail server: send a mail from a member to the project address, run the job, open the backlog. Verify: Playwright `tests/e2e/mail-intake.spec.ts` "member's mail becomes a backlog task with its attachment" and "member receives a confirmation with the task key".

## 3. Verification

- [ ] 3.1 `openspec validate tasks-create-by-email --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
