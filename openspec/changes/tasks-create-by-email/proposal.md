---
kind: code
---

# Create a task by sending an email

## Why

A project member cannot turn an email into a task. Requests for dev and IT teams often arrive by mail, and today someone has to copy the subject and body into a new task by hand. Planninq has no inbound mail handling at all: no controller, service, listener or background job in `lib/` reads a mailbox, and nothing uses Nextcloud's mailer either (`grep -rli "mail\|imap" lib` finds nothing).

OpenProject turns incoming mail into work packages, over IMAP, POP3 or Gmail. Jira "can receive emails from licensed users to create issues or add comments".

Parity rows: `tsk-email-create` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because two competitors create work items from mail, and mail is where many requests to dev and IT teams start.

This change extends the flat spec `openspec/specs/tasks.md` through a new capability, `task-mail-intake`.

## What changes

- An admin connects one mailbox to planninq in Beheer. The password is kept by OpenRegister's credential broker, never in planninq's settings or objects.
- Each project with a key gets its own address, for example `planninq+VC@gemeente.nl` for the project with key VC. The project's settings show it.
- A mail to that address from a project member becomes a task in that project's backlog: the subject is the title, the body is the description, the attachments are attached, and the sender is the reporter.
- The sender gets a short reply with the new task's key and link. Mail from unknown senders or to unknown projects never becomes a task, and unknown senders get no reply.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-email-create`.

### `tsk-email-create`: Create a task by sending an email.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No mail-related controller, listener or service exists anywhere in lib/ (no IMailer usage, no inbound-mail handling); grepped lib/Controller, lib/Service, lib/Listener for mail/email with no hits."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 1.5 'the work package handler creates at handlers/work_package.rb:73 ... Transports are IMAP, POP3 and Gmail' ; source read at v17.8.0: app/services/incoming_emails/handlers/work_package.rb:56 default creates a new work package, :73-76 receive_new_work_package; config/initializers/menus.rb:595 admin 'Incoming emails' settings"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/creating-issues-and-comments-from-email-938847641.html): "corpus: jira-data-center/round4/M1-column.md 1.5 'Jira can receive emails from licensed users to create issues or add comments' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/creating-issues-and-comments-from-email-938847641.html 'Jira can receive emails from licensed users to create issues or add comments and attachments' (read 2026-09-26)"


## Scope

### In scope

- Admin settings for one IMAP mailbox, with a `credentialRef` for the password and a "Test connection" button.
- A background job that reads new mail, creates tasks, attaches files, replies to the sender and files the message away.
- Project addresses by plus-addressing on the project key, and a `[KEY]` subject tag as a fallback for mail systems that drop plus-addresses.
- Showing the project address in the project settings.

### Out of scope

- Adding comments to an existing task by replying to a notification (Jira and OpenProject do this; it can follow on the same job).
- POP3 and Gmail's API. One IMAP mailbox covers the self-hosted case this app targets.
- A mailbox per project or per user.
- Mail from people who are not Nextcloud users.

## Impact

- Backend: a new `lib/BackgroundJob/MailIntakeJob.php`, a new `lib/Service/MailIntakeService.php` (parse, match, create, attach, reply, file), a new `lib/Service/ImapClient.php` wrapper around a pure-PHP IMAP library added through composer.
- Settings: new admin keys in `lib/Service/SettingsService.php` (`mail_intake_enabled`, host, port, encryption, username, `mail_intake_credential_ref`, folder, address) and a section in `src/views/settings/Settings.vue`.
- Views: the project address line in `src/components/ProjectSettingsSidebar.vue`.
- Schema: none new. The task's `metadata` (`lib/Settings/planninq_register.json:384-389`) keeps the mail's Message-ID so a message is never turned into two tasks.
- Depends on: `tasks-create-edit-delete` (task creation, reporter, Markdown description) and `tasks-readable-keys` (the project key that forms the address).

## Risks

### Risk 1: a spoofed sender creates tasks
**Severity**: Medium
**Mitigation**: the sender must be a Nextcloud user who is a member of the project, matched on the account's email address. The admin can require that the receiving mail server's authentication results pass (SPF and DKIM) before a message counts, and the setting is on by default.

### Risk 2: the job creates tasks twice or loses mail
**Severity**: Medium
**Mitigation**: the Message-ID is stored on the task and checked before creating; a message is moved to the "Processed" folder only after its task exists, and to "Rejected" when it cannot become one, so a crash between the two leaves it in the inbox to be picked up again, not lost.

### Risk 3: a new runtime dependency
**Severity**: Low
**Mitigation**: the PHP `imap` extension is no longer bundled with PHP 8.4, so a pure-PHP IMAP client is needed. It sits behind one `ImapClient` class, so replacing it touches one file, and `composer audit` covers it in the gates.
