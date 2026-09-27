# Tasks: platform-mobile-web

## 1. Layouts

- [ ] 1.1 Phone board: one column at a time, column switcher with counts and `aria-pressed`, scroll snap, move menu as the touch path. Verify: Playwright `tests/e2e/mobile.spec.ts` "phone board shows one column and switches" and "card moves by its menu on a phone".
- [ ] 1.2 Task page, time entry dialog and running timer at 360 pixels, with 44 pixel targets. Verify: Playwright `tests/e2e/mobile.spec.ts` "task page has no sideways scroll", "log time on a phone" and "start and stop a timer on a phone".
- [ ] 1.3 My tasks at 360 pixels. Verify: Playwright `tests/e2e/mobile.spec.ts` "my tasks on a phone opens a task".
- [ ] 1.4 Timesheet day list under 600 pixels. Verify: Playwright `tests/e2e/mobile.spec.ts` "timesheet shows a day list on a phone".

## 2. Test harness

- [ ] 2.1 `phone-android` and `phone-ios` projects in `tests/e2e/playwright.config.ts` running only `tests/e2e/mobile.spec.ts`, with helpers for sideways-scroll and tap-target checks. Verify: the two projects run in CI and fail on a page with a forced 800 pixel wide element (a negative control in `tests/e2e/mobile.spec.ts`, skipped by default and run once when the helper is written).

## 3. Verification

- [ ] 3.1 `openspec validate platform-mobile-web --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
