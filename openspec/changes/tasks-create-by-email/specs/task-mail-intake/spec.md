# task-mail-intake delta for tasks-create-by-email

## ADDED Requirements

### Requirement: An admin connects a mailbox for task intake

The admin settings MUST let an admin connect one IMAP mailbox for task intake with host, port, encryption, username, password, folder and public address, test the connection, and switch intake on or off. The password SHALL be stored through OpenRegister's credential broker, and planninq SHALL store only a credential reference. Tier: V1 (docs/FEATURES.md, integration; this change adds the row).

#### Scenario: An admin connects a mailbox and tests it

- **GIVEN** an admin on the planninq admin settings, section "Create tasks by email"
- **WHEN** they enter the mailbox details and password and choose "Test connection"
- **THEN** the page reports that the connection works and how many messages the folder holds
- **AND** planninq's stored settings hold a credential reference and no password

### Requirement: Each project with a key has a task address

When intake is on, each project with a key MUST have the address formed by the mailbox address with the project key as plus-suffix, and the project settings SHALL show it to project members with a copy button. Tier: V1.

#### Scenario: Project settings show the project's address

- **GIVEN** intake on with address `planninq@gemeente.nl` and the project "Vergunningen Centrum" with key VC
- **WHEN** a project member opens the project settings
- **THEN** they see "Mail tasks to planninq+VC@gemeente.nl" with a copy button

### Requirement: A member's mail to a project address becomes a task

The system MUST turn a mail sent to a project's address, or to the mailbox with a `[KEY]` subject tag, by a Nextcloud user who is a member of that project, into a task in that project's backlog: the subject without reply prefixes or tag as title, the plain-text body as description, the sender as reporter, and the attachments within the size limit attached. The system SHALL reply to the sender with the new task's key and link. A message SHALL NOT become more than one task. Tier: V1.

#### Scenario: A member's mail becomes a backlog task with its attachment

- **GIVEN** Anna, a member of project VC, whose Nextcloud email is anna@gemeente.nl
- **WHEN** she mails planninq+VC@gemeente.nl with subject "Printer 2nd floor broken", a short body and a photo, and the intake job runs
- **THEN** project VC has a new task "Printer 2nd floor broken" in its backlog with that body as description and Anna as reporter
- **AND** the photo is attached to the task

#### Scenario: A member receives a confirmation with the task key

- **GIVEN** the task above was created as VC-12
- **WHEN** Anna checks her mail
- **THEN** she has a reply "Task VC-12 was created in Vergunningen Centrum" with a link to the task

#### Scenario: The same message is not turned into two tasks

- **GIVEN** a message whose task was created but which was not yet moved out of the inbox
- **WHEN** the intake job runs again
- **THEN** no second task is created and the message is moved to "Processed"

### Requirement: Mail that may not create a task is set aside

The system MUST NOT create a task from a mail whose sender is not a member of the addressed project, whose project cannot be found, or, when the admin requires it, whose sender authentication did not pass. Such mail SHALL be moved to a "Rejected" folder. A sender who is not a Nextcloud user SHALL NOT get a reply; a member whose mail names no known project SHALL get a reply that says so. Tier: V1.

#### Scenario: A non-member's mail is rejected without a reply

- **GIVEN** a mail to planninq+VC@gemeente.nl from an address that belongs to no Nextcloud user
- **WHEN** the intake job runs
- **THEN** no task is created, the mail is in "Rejected" and no reply is sent

#### Scenario: A mail with a failed signature check is rejected

- **GIVEN** "Only accept mail that passed SPF and DKIM" on, and a mail from Anna's address whose authentication results show a DKIM failure
- **WHEN** the intake job runs
- **THEN** no task is created and the mail is in "Rejected"

#### Scenario: A member mailing an unknown project is told so

- **GIVEN** Anna mails planninq+XYZ@gemeente.nl and no project has key XYZ
- **WHEN** the intake job runs
- **THEN** no task is created
- **AND** Anna gets a reply "No project has the key XYZ."
