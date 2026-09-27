# Design: assign people, set priority and attach labels on a task

## Context

Read at development `de35541`:

- Schema `task` (`lib/Settings/planninq_register.json`): `assignedTo` string (one uid),
  `priority` enum low, normal, high, urgent, `labels` array of label uuids, `watchers` array of
  uids (unused).
- Readers of `assignedTo`: `src/components/TaskCard.vue:65-66`, `src/views/TaskDetail.vue:267`,
  `src/store/projects.js:642` (member task count before removal),
  `lib/Listener/TaskActivityListener.php:197-230` (assignee change subject and audience), the
  `taskDueSoon` notification rule (recipients `field: assignedTo`), and the portal exclusion list
  in `lib/Portal/PortalContributionProvider.php:20,61`.
- `src/utils/taskHelpers.js:34` hides the library's `tags` and `tasks` sidebar tabs.
- Labels: schema `label` is readable by every authenticated user and writable by admins only;
  `fetchLabels` (`src/store/projects.js:806`) reads them; `resolveTaskLabels`
  (`src/utils/labelHelpers.js:126`) maps a task's uuids to label objects.
- Board card actions: the `NcActions` menu per card (`src/views/ProjectBoard.vue:152-165`) holds
  the keyboard move targets.
- Project members: `project.members` (uids); `MemberSearch.vue` looks users up for the
  settings sidebar.

## Goals / non-goals

Goals: set and change the responsible person, extra people, priority and labels from planninq.
Non-goals: notifications (lane B), board filters, label administration.

## Decisions

### Decision 1: one responsible person plus a shared-with list

`assignedTo` keeps its meaning and type. A new optional `sharedWith` (array of uids) holds the
others. The picker shows the responsible person first and "Also working on this" for the rest.
Alternative: change `assignedTo` to an array. Rejected: a type change on a field that a
notification rule, a listener, a portal filter and a ZGW mapping read is a breaking schema change.

### Decision 2: pick from project members only

The picker's options are `project.members`, shown with `NcAvatar` and display name. Assigning
someone who is not a member is not offered. A member removed from the project keeps their
assignments until reassigned; the removal dialog already warns with the count.

### Decision 3: priority inline and on the card

TaskDetail shows priority as an `NcSelect` that saves on change through `updateTask`. The card's
action menu gains a "Priority" submenu with the four levels, so a triage pass needs no page
change.

### Decision 4: a label picker instead of the generic tag tab

TaskDetail shows a multi-select of all labels (title and colour swatch) writing `task.labels`.
The generic `tags` tab stays hidden: it stores Nextcloud system tags, not planninq labels.

### Decision 5: the Activity audience includes `sharedWith`

`TaskActivityListener::resolveAudience` adds every uid in `sharedWith`, so the people sharing a
task see its events.

## Risks / trade-offs

- [Two people fields to read] -> the "my tasks" queries (`portfolio-my-work-dashboard`) match on
  either field; documented there.
