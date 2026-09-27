# Tasks: tasks-dates

## 1. Helpers

- [ ] 1.1 Parse `YYYY-MM-DD` as a local date in `dueDateStatus` (`src/utils/taskHelpers.js`) and `parseDay` (`src/utils/timelineHelpers.js`). Verify: vitest cases run with `TZ=America/New_York` and `TZ=Europe/Amsterdam`.
- [ ] 1.2 Add `validateTaskDates(startDate, dueDate)` returning an error key when start is after due. Verify: vitest spec.

## 2. Task page

- [ ] 2.1 Replace the read-only due date in `src/views/TaskDetail.vue` with an inline date control, add the start date the same way, save through `updateTask`, and allow clearing. Verify: Playwright e2e sets a due date and sees the badge on the board card.

## 3. Dialog and schema

- [ ] 3.1 Add due and start date fields to `src/dialogs/TaskFormDialog.vue` with the order check; add the cross-field rule to the task schema if OpenRegister supports it. Verify: vitest mount test for the refusal; PHPUnit register test if the rule lands in the schema.

## 4. Copy and verification

- [ ] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate tasks-dates --type change --strict` passes.
