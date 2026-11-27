# Tasks: platform-mobile-web

## 1. Layouts

- [x] 1.1 Phone board: one column at a time, column switcher with counts and `aria-pressed`, move menu as the touch path (scroll snap dropped, design amendment). Verify: vitest `tests/vitest/phoneBoard.spec.js`; Playwright `tests/e2e/mobile.spec.ts` "the board shows one column, switches, and moves a card by its menu".
- [x] 1.2 Task page and time entry dialog at 360 pixels, with 44 pixel targets (the running timer moved to `time-timer-and-work-type` task 1.5, design amendment). Verify: Playwright `tests/e2e/mobile.spec.ts` "my tasks, a task page, logging time and the timesheet by tapping" (no sideways scroll on the task page, log time on a phone).
- [x] 1.3 My tasks at 360 pixels. Verify: Playwright `tests/e2e/mobile.spec.ts` "my tasks, a task page, logging time and the timesheet by tapping" (its My tasks step).
- [x] 1.4 Timesheet day list under 600 pixels. Verify: Playwright `tests/e2e/mobile.spec.ts` "my tasks, a task page, logging time and the timesheet by tapping" (its timesheet step).

## 2. Test harness

- [ ] 2.1 `phone-android` and `phone-ios` projects in `tests/e2e/playwright.config.ts` running only `tests/e2e/mobile.spec.ts`, with helpers for sideways-scroll and tap-target checks. Verify: the two projects run in CI and fail on a page with a forced 800 pixel wide element (a negative control in `tests/e2e/mobile.spec.ts`, skipped by default and run once when the helper is written). Left open at archive (30 Sep): the projects, the helpers and the control are written, but no lane could run Playwright against an instance, so the one run of the control (`PHONE_CONTROL=1 npx playwright test --project=phone-android tests/e2e/mobile.spec.ts`) and the first phone run are owed.

## 3. Verification

- [x] 3.1 `openspec validate platform-mobile-web --type change --strict` passes.
- [x] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
