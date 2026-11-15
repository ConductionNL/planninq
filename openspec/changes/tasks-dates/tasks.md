# Tasks: tasks-dates

## 1. Helpers

- [x] 1.1 Parse `YYYY-MM-DD` as a local date in `dueDateStatus` (`src/utils/taskHelpers.js`) and `parseDay` (`src/utils/timelineHelpers.js`). Verify: vitest cases run with `TZ=America/New_York` and `TZ=Europe/Amsterdam`. (verified: tests/vitest/taskDates.spec.js runs every case in New York, Amsterdam, Auckland and UTC; the New York cases fail on the old parsing)
- [x] 1.2 Add `validateTaskDates(startDate, dueDate)` returning an error key when start is after due. Verify: vitest spec. (verified: tests/vitest/taskDates.spec.js `validateTaskDates`)

## 2. Task page

- [ ] 2.1 Replace the read-only due date in `src/views/TaskDetail.vue` with an inline date control, add the start date the same way, save through `updateTask`, and allow clearing. Verify: Playwright e2e sets a due date and sees the badge on the board card. (BUILT, unchecked until the e2e runs: inline start and due date pickers on TaskDetail save on change through `saveTask(datesPatch(...))`, clear writes null, a start after due is refused with nothing saved; the patch logic is covered in tests/vitest/taskDateEditing.spec.js. The Playwright run needs an instance.)

## 3. Dialog and schema

- [ ] 3.1 Add due and start date fields to `src/dialogs/TaskFormDialog.vue` with the order check; add the cross-field rule to the task schema if OpenRegister supports it. Verify: vitest mount test for the refusal; PHPUnit register test if the rule lands in the schema. (BUILT, unchecked: the dialog has both date pickers and refuses a start after due (save disabled, alert shown, save() returns); the dialog golden is re-recorded. planninq has no component-mount harness (no @vue/test-utils), so the refusal is tested through `validateTaskDates` and the golden, not a mount. The schema rule is not added: OpenRegister's cross-field validation is the design's open question and the register declares none today, so the client check stands alone, as design decision 2 allows.)

## 4. Copy and verification

- [x] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. (verified: "The start date is after the due date." in all 37 catalogues, .json and .js; parity OK)
- [ ] 4.2 `openspec validate tasks-dates --type change --strict` passes.
