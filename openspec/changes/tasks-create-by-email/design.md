# Design: create a task by sending an email

## Context

What exists at de35541:

- No mail code in `lib/`: no controller, service, listener or background job reads mail, and nothing sends it.
- The task schema (`lib/Settings/planninq_register.json:39-390`) has what an email task needs: `title` and `status` required, `description`, `project`, `column` (null means the backlog, `:241-248`), `reporter` (`:336-341`), `key` (`:330-335`) and `metadata`, a declared catch-all object for source-system fields and provenance keys (`:384-389`). Task create is allowed to members of the task's project (`:76-96`).
- The project schema has `key` (`:524-529`) and `members` (`:483-490`).
- Admin settings are IAppConfig keys with defaults and per-key validation in `SettingsService` (`lib/Service/SettingsService.php:60-64`, `setAdminSettings` at `:239-279`), edited in `src/views/settings/Settings.vue` sections. `docs/ARCHITECTURE.md` section 5 question 5 routes GitHub and GitLab sync through the integration app so planninq owns no forge API code; there is no equivalent decision for mail.
- The lane A changes this depends on, merged on development after de35541: `tasks-create-edit-delete` creates tasks through the object store with `reporter` set, and `tasks-readable-keys` gives a project a unique key of two to ten characters, A-Z and 0-9, and gives new tasks keys like `VC-12` in a pre-save listener.
- Hydra ADR-031 lists "background jobs that orchestrate external systems (mail polling, IMAP sync)" as code an app should still write in PHP. Hydra ADR-064 forbids a secret on any setting or object: an app stores a `credentialRef` and resolves it through OpenRegister's `CredentialBrokerService` at call time (`resolveInjectable()` for self-hosted hosts that cannot be proxied).

## Goals / non-goals

Goals:
- A member mails a request to a project address and finds a task in that project's backlog.
- No secret stored by planninq; no task from an unknown sender.

Non-goals:
- Comments by reply, POP3, per-project mailboxes, tasks from outside users.

## Decisions

### Decision 1: one instance mailbox, read by a planninq background job
An admin configures one IMAP mailbox in a "Create tasks by email" section of the admin settings: host, port, encryption, username, folder (default `INBOX`), the public address, and the password. The password goes to OpenRegister's credential broker as a generic inject-only credential; planninq stores only the returned `mail_intake_credential_ref`. "Test connection" logs in and reports the folder's message count. `MailIntakeJob` is a timed background job (every five minutes, ADR-069 conventions) that does nothing unless `mail_intake_enabled` is on. It resolves the secret at run time, reads unseen messages through `ImapClient` and hands each to `MailIntakeService`. Alternatives: the Nextcloud Mail app's accounts (its PHP services are not public API, and a personal mailbox is the wrong owner for a team address); the integration app (it has no mail source planninq could rely on today; if it gains one, `MailIntakeService` keeps the matching and creating and only the reading moves).

### Decision 2: the address names the project
The address for a project is the mailbox address with the project key as a plus-suffix: `planninq+VC@gemeente.nl`. The job reads the key from the plus-suffix of any `To` or `Cc` address on the mailbox's domain, case-insensitive. When a mail system strips plus-addresses, a subject that starts with `[VC]` works too; the tag is removed from the title. The project settings sidebar shows the project's address with a copy button when intake is on and the project has a key.

### Decision 3: who may create a task this way
The `From` address must be the email address of a Nextcloud user who is a member of the addressed project. With "Only accept mail that passed SPF and DKIM" on (the default), the job also requires the receiving server's `Authentication-Results` header to show a pass. Anything else is not turned into a task and is moved to the `Rejected` folder. Unknown senders get no reply, so the mailbox never sends mail to forged addresses; a known member whose mail names an unknown project, or no project, gets a reply saying so.

### Decision 4: what the task looks like
`title` is the subject without a leading `Re:`, `Fwd:` or `[KEY]` tag, cut to 255 characters; `description` is the plain-text body (HTML converted to text), with quoted replies below a signature marker left out; `project` is the addressed project; `column` is null so the task lands in the backlog for triage; `status` `open`; `reporter` the sender's user id; `metadata.emailMessageId` the Message-ID. The task is saved through `ObjectService` after the membership check, so OpenRegister validation, the audit trail and the key listener of `tasks-readable-keys` run as for any task. Attachments up to the admin's size limit (default 10 MB per message) are stored as files on the task through OpenRegister's object files service; larger ones are skipped and named in the reply.

### Decision 5: never twice, never lost
Before creating, the job looks for a task with the same `metadata.emailMessageId`; if one exists, it only files the message. A message moves to `Processed` only after its task is saved, and to `Rejected` only after its reply (if any) is sent. A failure in between leaves the message unseen in the inbox, so the next run retries it.

### Decision 6: the confirmation reply
The sender gets a reply through Nextcloud's mailer: "Task VC-12 was created in Vergunningen Centrum", the task link, and the list of skipped attachments if any. The reply sets `In-Reply-To` so it threads under the request.

## Risks / trade-offs

- [Spoofing] -> Member match plus SPF and DKIM by default (Decision 3).
- [Duplicate or lost mail] -> Message-ID check, file only after save (Decision 5).
- [Large mailboxes] -> Only unseen messages in one folder; at most 50 per run, the rest next run.
- [Dependency] -> One pure-PHP IMAP client behind `ImapClient`, covered by `composer audit`.
