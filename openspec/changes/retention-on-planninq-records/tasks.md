# Tasks: retention-on-planninq-records

## 1. Register

- [ ] 1.1 Add an `archive` block with a selectielijst category to the `project`, `task` and `plannedTimeEntry` schemas in `lib/Settings/planninq_register.json`, with the archive action date derived when the project reaches `completed` or `archived`. Verify: PHPUnit register import test asserting the retention on an archived project's task.
      (17 Nov, partly built: `project` gains `closedAt`, stamped by `ProjectClosureListener` when the project becomes completed or archived and cleared when it reopens. Its `archive` block dates from it with `ander_datumkenmerk`, which gives no date without one, so an active project is never listed. `task` and `plannedTimeEntry` date from their project's `closedAt` through `gerelateerde_zaak` on `project`. Tests: `PlanninqRegisterSchemaTest` and `ProjectClosureListenerTest`. 22 Nov: the category is now picked in the admin settings (2.1) and written into each block's `classification`, so the blocks carry no category until an admin picks one. Still open: the test that an archived project's task gets its date needs OpenRegister's archival services, so it belongs to a live check.)

## 2. Admin setting

- [ ] 2.1 Add a "Retention" section to the planninq admin settings that picks the category per schema from OpenRegister's selection list. Verify: vitest mount test; Playwright e2e on the admin page.
      (Built: `RetentionCategoryService` lists the categories of the selection list OpenRegister is configured with, keeps the pick in app config and writes it into `archive.classification` of the live `project`, `task` and `plannedTimeEntry` schemas; `GET/PUT /api/settings/retention` is admin only and answers 400 for a category the list does not hold; the `ApplyRetentionCategories` repair step writes the pick again after a register import. The section is `src/components/RetentionSettings.vue` in `src/views/settings/Settings.vue`, with helpers in `src/utils/retention.js`. Tests: `tests/unit/Service/RetentionCategoryServiceTest.php`, `tests/unit/Controller/RetentionSettingsControllerTest.php`, `tests/vitest/retentionSettings.spec.js`. The e2e is written in `tests/e2e/retention-settings.spec.ts` ("an admin picks the category for projects and it is stored", "a non-admin cannot read or change the retention") and has not run, so this stays open.)

## 3. Verification

- [x] 3.1 `openspec validate retention-on-planninq-records --type change --strict` passes.
- [ ] 3.2 On archive, set `plt-destruction-list` in `openspec/parity/capabilities.json` back to built with the new evidence.
