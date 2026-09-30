---
kind: code
---

# Notify people when a task is assigned to them, and by email when they ask for it

## Why

A person who is given a task in planninq does not hear about it. The change is recorded in the Nextcloud Activity stream: `TaskActivityListener` picks the subject `task_assigned_activity` when `assignedTo` changes (`lib/Listener/TaskActivityListener.php:182-210`), adds the new assignee to the audience (`:225-246`) and publishes an activity entry (`:258-283`). An activity entry is not a notification: nothing appears in the Nextcloud bell, on a phone, or in a mail. The only notification rule the register declares is `taskDueSoon` on the task schema (`lib/Settings/planninq_register.json:145-175`), and planninq registers no notifier of its own. `docs/FEATURES.md` lists "Notification: task assigned" and the user setting `notify_assigned` as MVP; neither is built.

Nothing reaches anyone by email either. `taskDueSoon` declares one channel, `nc-notification` (`lib/Settings/planninq_register.json:162-164`). `lib/Activity/` holds a filter and a provider but no activity setting, so the Activity app offers no mail option for planninq events.

Deck, OpenProject, Plane, Kanboard, Jira and Untis notify people of assignments; those six plus TimeEdit send notifications by email.

Parity rows: `col-notify-assigned`, `col-email-notify` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because the assignment half is only an activity entry while six competitors notify, and seven competitors deliver notifications by email while planninq delivers none.

This change extends the flat spec `openspec/specs/task-notifications.md` through two new capabilities, `assignment-notification` and `email-notifications`.

## What changes

- A person gets a Nextcloud notification when a task is created for them or assigned to them.
- Each user can switch assignment notifications off in the planninq personal settings, like the due-date reminder today.
- Each user can ask for assignment notifications and due-date reminders by email as well. Email is off until they switch it on.
- Every rule is declared on the task schema in the `x-openregister-notifications` dialect and delivered by OpenRegister. Planninq writes no notification dispatch code.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `col-notify-assigned`, `col-email-notify`.

### `col-notify-assigned`: Get notified when a task is assigned to you.

- Area `collaboration`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "lib/Listener/TaskActivityListener.php:182 selectSubject emits task_assigned_activity on an assignee change -> :225 resolveAudience adds the assignee -> :258 publish to the Activity stream"
- Note: "The new assignee gets an Activity entry, not a notification: planninq registers no INotifier, and the register declares only the taskDueSoon notification rule, none for assignment."
- Demand: none recorded on the row.
- Competitors rated yes (6):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/code-census.md notification kinds 'card-assigned' ; M1-column.md 6.8 'four notifications reached jdevries ... two assignments' ; source read at v1.19.0: lib/Service/AssignmentService.php:77-78 assigning another user calls lib/Notification/NotificationHelper.php:107-121 sendCardAssigned with subject 'card-assigned', rendered by lib/Notification/Notifier.php; UI trigger src/components/card/AssignmentSelector.vue:17"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/notifications/notification-settings/README.md:18 'Be notified of activities on some or all of the work packages in which you are participating (as assignee, responsible or watcher)' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/models/notification.rb:34 reason 'assigned'; app/models/notification_setting.rb:33 ASSIGNEE setting; app/services/notifications/create_from_model_service/work_package_strategy.rb derives the recipients; config/initializers/menus.rb:301-303 'Notifications and email' settings page"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/M1-column.md 6.8 'Notification with read_at, snoozed_till, archived_at' ; source: apps/api/plane/bgtasks/issue_activities_task.py:396-408 a new assignee is added as IssueSubscriber, and bgtasks/notification_task.py:303-315 sends them 'in_app:issue_activities:assigned' ; source read at v1.4.2: apps/web/app/(all)/[workspaceSlug]/(projects)/notifications/page.tsx:12,31 NotificationsRoot inbox; apps/api/plane/bgtasks/issue_activities_task.py:357-371 track_assignees and :395-398 adds the new assignee as IssueSubscriber; apps/api/plane/bgtasks/notification_task.py:280-311 notifies subscribers"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 6.8 web notifications with the assignee filter ; source: app/Subscriber/NotificationSubscriber.php task events to app/Notification/ (web, mail) ; source read at v1.2.54: app/Subscriber/NotificationSubscriber.php:27 TaskModel::EVENT_ASSIGNEE_CHANGE handled; app/Template/notification/task_assignee_change.php:9 'Assigned to %s'; app/Template/header/user_notifications.php:3 bell with unread notifications"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html, https://confluence.atlassian.com/jirasoftwareserver/push-notifications-993923359.html): "docs: https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html 'Jira can send email notifications to users when significant events occur' (corpus: jira-data-center/round4/M1-column.md 8.6); Issue Assigned is a notification scheme event ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html 'Jira can send email notifications to users when significant events occur'; https://confluence.atlassian.com/jirasoftwareserver/push-notifications-993923359.html mobile push for 'Issues you're assigned to ... New assignee' (read 2026-09-26)"
  - Untis, WebUntis and Untis Mobile (https://www.untis.at/produkte/webuntis/online-vertretungsplanung, https://help.untis.at/hc/de/articles/360014966620): "docs read 2026-09-27: https://www.untis.at/produkte/webuntis/online-vertretungsplanung 'Die ausgewählte Vertretungslehrkraft wird umgehend mithilfe einer Push-Benachrichtigung am Smartphone via Untis Mobile App bei einer neuen Vertretung benachrichtigt' ; https://help.untis.at/hc/de/articles/360014966620 option 'Benachrichtigung des Aufgaben- und Ticketsystems erhalten' for new tickets"

### `col-email-notify`: Receive notifications by email.

- Area `collaboration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the only declared notification channel is 'nc-notification' (lib/Settings/planninq_register.json taskDueSoon.channels); lib/Activity registers a Filter and Provider but no activity Setting, so the Activity app offers no mail option for planninq events"
- Note: "A flow with a SendEmail node (OpenRegister flows, reachable from the Flows page) could be built to mail on a task event, but nothing ships that."
- Demand: none recorded on the row.
- Competitors rated yes (7):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 8.6 'platform: the notification (8.4) is mailed when the user's notification settings say so; Deck sends no mail itself' ; source: lib/Activity/SettingBase.php activity mail choices ; source read at v1.19.0: lib/Activity/SettingBase.php:79-89 each Deck activity type can be mailed by the platform, off by default; appinfo/info.xml:69-71 SettingChanges, SettingDescription, SettingComment; searched lib/ for 'IMailer': none, Deck sends no mail itself (platform)"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/account-settings/notification-and-email/README.md:23-25 'Email reminders ... configure the email reminders which you receive' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/services/notifications/mail_service.rb:44 sends mail per notification strategy; app/components/my/reminders/show_page_component.html.erb:33 immediate reminders and :45 daily email reminders; config/routes.rb:1138-1140 update_reminders and update_email_alerts"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §6 'Email delivery is batched by bgtasks/email_notification_task.py, scheduled every five minutes', needs SMTP (menu-tree.md god-mode 'Email SMTP') ; source read at v1.4.2: apps/web/core/components/settings/profile/content/pages/notifications/email-notification-form.tsx:69,89,131,151 email toggles; apps/api/plane/bgtasks/email_notification_task.py:47 stack_email_notification and :153 send_email_notification; apps/api/plane/celery.py:47 scheduled batching. Needs SMTP set by the instance admin"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 8.6 'the overdue command sends e-mail' ; code-census.md notifications 'app/Notification/ (web, mail, webhook, activity stream)' ; source read at v1.2.54: app/ServiceProvider/NotificationProvider.php:31 'Email' notification type; app/Notification/MailNotification.php:31 notifyUser; app/Template/user_view/notifications.php:9 user picks methods"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html): "docs: https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html 'Jira can send email notifications to users when significant events occur' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/configuring-email-notifications-938847633.html 'Jira can send email notifications to users when significant events occur. For example, when an issue is created or completed' (read 2026-09-26)"
  - Untis, WebUntis and Untis Mobile (https://help.untis.at/hc/de/articles/360014966620): "docs read 2026-09-27: https://help.untis.at/hc/de/articles/360014966620 'Um interne Nachrichten in WebUntis an Ihre persönliche E-Mail-Adresse weiterzuleiten, können Sie in Ihrem Benutzer-Profil die Option Empfangene Nachrichten an E-Mail-Adresse weiterleiten aktivieren'"
  - TimeEdit (https://www.academy.timeedit.com/guides-tutorials/te-reserve-how-to-book-request-a-room, https://www.academy.timeedit.com/product-updates/230991642): "docs read 2026-09-27: https://www.academy.timeedit.com/guides-tutorials/te-reserve-how-to-book-request-a-room 'If you decide to reject the requests, it will remove the entry, and send an email back to the booker' ; https://www.academy.timeedit.com/product-updates/230991642 'emails sent from Viewer ... Now, Viewer sends individual emails per recipient ... also applied for Core, Reserve and Plan'"


## Scope

### In scope

- Two in-app rules on `task`: one for a task created with an assignee, one for a change of `assignedTo`.
- Three email rules on `task`, mirroring the two assignment rules and the due-soon reminder, delivered only to users who opted in.
- A recipient resolver for the email rules that returns the assignee only when they switched email on.
- Two switches in the planninq personal settings.

### Out of scope

- Notifications for `sharedWith` (the second-person list of `tasks-assignment-priority-labels`), comments, status changes and overdue tasks. They are further rules in the same dialect and can follow.
- Digest mail. The engine's batching is left at its defaults.
- An Activity app mail setting. The notification dialect is the fleet's one path for mail (ADR-031); a second path through the Activity app would send the same news twice.

## Impact

- Schema: `task` gains the rules `taskAssignedOnCreate`, `taskAssigned`, `taskAssignedOnCreateEmail`, `taskAssignedEmail` and `taskDueSoonEmail` in its `x-openregister-notifications` block.
- Backend: a new `lib/Notification/EmailOptInRecipientResolver.php` implementing OpenRegister's `RecipientResolverInterface`, registered in `lib/AppInfo/Application.php`; `lib/Service/SettingsService.php` gains the `notify_assigned` and `notify_by_email` user keys and writes the `notify_assigned` override through to OpenRegister, as `setNotifyDueReminder` does today.
- View: `src/views/settings/UserSettings.vue` gains two switches.
- Depends on: none of this pass's changes. `tasks-assignment-priority-labels` keeps `assignedTo` a single user id, which is what a `field` recipient needs.

## Risks

### Risk 1: people get mail they did not ask for
**Severity**: Medium
**Mitigation**: email is off for everyone until they switch it on, and the resolver checks that switch on every dispatch, so a rule change cannot send mail to people who never opted in.

### Risk 2: a user without an email address switches email on
**Severity**: Low
**Mitigation**: the settings switch shows "Add an email address in your Nextcloud personal settings to get mail." when the account has none, and the resolver skips such users.

### Risk 3: people are notified of their own assignment
**Severity**: Low
**Mitigation**: the open question in design.md names it. The resolver can skip the acting user once the engine passes it; until then a self-assignment notifies the assignee, which is noise, not harm.
