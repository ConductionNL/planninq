# Tasks: retention-on-planninq-records

## 1. Register

- [ ] 1.1 Add an `archive` block with a selectielijst category to the `project`, `task` and `plannedTimeEntry` schemas in `lib/Settings/planninq_register.json`, with the archive action date derived when the project reaches `completed` or `archived`. Verify: PHPUnit register import test asserting the retention on an archived project's task.
      (17 Nov, partly built: `project` gains `closedAt`, stamped by `ProjectClosureListener` when the project becomes completed or archived and cleared when it reopens. Its `archive` block dates from it with `ander_datumkenmerk`, which gives no date without one, so an active project is never listed. `task` and `plannedTimeEntry` date from their project's `closedAt` through `gerelateerde_zaak` on `project`. Tests: `PlanninqRegisterSchemaTest` and `ProjectClosureListenerTest`. Still open: the blocks carry no selectielijst category until 2.1 lets the admin pick one, and the test that an archived project's task gets its date needs OpenRegister's archival services, so it belongs to a live check.)

## 2. Admin setting

- [ ] 2.1 Add a "Retention" section to the planninq admin settings that picks the category per schema from OpenRegister's selection list. Verify: vitest mount test; Playwright e2e on the admin page.

## 3. Verification

- [x] 3.1 `openspec validate retention-on-planninq-records --type change --strict` passes.
- [ ] 3.2 On archive, set `plt-destruction-list` in `openspec/parity/capabilities.json` back to built with the new evidence.
