# Design: set due and start dates on a task

## Context

Read at development `de35541`:

- Schema `task` in `lib/Settings/planninq_register.json`: `dueDate` and `startDate` are strings
  with `format: date` (no time).
- `src/utils/taskHelpers.js:215-236` `dueDateStatus()` builds `new Date(raw)` and then compares
  local year, month and day. For a `YYYY-MM-DD` string that is UTC midnight, so west of UTC the
  badge is a day early.
- `src/components/TaskCard.vue:16-21` renders the badge; `src/views/TaskDetail.vue:268` shows the
  due date as text in `fields()`; the start date is not shown.
- `src/views/ProjectTimeline.vue:426-430` and `src/utils/timelineHelpers.js:78-79` read both dates.
- The `taskDueSoon` notification rule on the task schema (`x-openregister-notifications`) fires for
  a `dueDate` within the next 24 hours on a task that is not done.
- `updateTask` (`src/store/projects.js:871`) PATCHes arbitrary fields.

## Goals / non-goals

Goals: set, change and clear both dates from the task page and the task dialog.
Non-goals: times of day, rescheduling on the timeline, recurrence.

## Decisions

### Decision 1: native date inputs

Use `NcDateTimePickerNative` with `type="date"` on TaskDetail (inline, saves on change) and in the
dialog. The value is written as `YYYY-MM-DD`. A clear button writes `null`. Alternative:
`NcDateTimePicker` with a time part. Rejected: the schema is date-only and the rows ask for a
whole-day date.

### Decision 2: validate the order on the client and in the schema

The dialog and the inline editor refuse a start date after the due date with "The start date
is after the due date." The same rule is added to the task schema as a declarative constraint
where OpenRegister supports cross-field validation (ADR-031); where it does not, the client check
stands alone and the timeline keeps drawing a start after due as a zero-length bar.

### Decision 3: parse stored dates as local dates

`dueDateStatus` and the timeline helper split `YYYY-MM-DD` into parts and build a local date.

## Risks / trade-offs

- [Inline save on change writes on every pick] -> one PATCH per change is cheap and matches the
  estimate field's behaviour.

## Open questions

- Does OpenRegister's schema validation support a cross-field rule (`startDate <= dueDate`)? If
  not, the rule lives in the client only; task 3.1 finds out.
