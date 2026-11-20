# Tasks: planning-phase-gate-document

## 1. Schema and guard

- [x] 1.1 Add `concludingDocument` (nullable string) and the `x-openregister-lifecycle` block to `projectPhase`, and raise the register's `openregister` constraint. Verify: PHPUnit `PlanninqRegisterSchemaTest::testPhaseLifecycleRequiresConcludingDocumentGuard` asserts the transitions, the `requires` tag on completion and the raised constraint together.
- [x] 1.2 `lib/Lifecycle/PhaseConcludingDocumentGuard.php` implementing `LifecycleGuardInterface`, registered under `planninq.phase.concludingDocument`. Verify: PHPUnit `PhaseConcludingDocumentGuardTest::testDeniesWithoutDocument`, `testDeniesWhenFileIsNotOnThePhase`, `testAllowsWithAttachedDocument` and `testNeverWrites`.
- [x] 1.3 Live check against OpenRegister: PATCH `status: completed` on a phase without a document answers 403 with the guard's message, and with a document answers 200. Verify: Newman requests "phase close without document is refused" and "phase close with document succeeds" in `tests/integration/planninq.postman_collection.json`.

## 2. Screens

- [x] 2.1 Phase store actions (`fetchPhases`, `savePhase`, `reorderPhase`, `closePhase`) in `src/store/projects.js`. Verify: vitest `tests/vitest/phases.spec.js` "reorder swaps order values" and "close sends document and status in one write".
- [x] 2.2 `ProjectPhases` page, manifest and registry entries, Phases button on the board, `src/dialogs/PhaseEditDialog.vue`, keyboard reorder. Verify: Playwright `tests/e2e/project-phases.spec.ts` "member adds a phase", "member reorders phases with the keyboard" and "member opens the phases page from the board".
- [x] 2.3 Phase sidebar with Files, Notes and Audit trail tabs. Verify: Playwright `tests/e2e/project-phases.spec.ts` "member uploads a document to a phase".
- [x] 2.4 `src/dialogs/PhaseCloseDialog.vue` (upload, pick the concluding document, close; show the guard's message on 403). Verify: Playwright `tests/e2e/project-phases.spec.ts` "closing without a document explains what is needed" and "closing with a concluding document completes the phase".
- [x] 2.5 "Concluding document missing" flag on completed phases. Verify: vitest `tests/vitest/phases.spec.js` "completed phase without its file is flagged".

## 3. Verification

- [x] 3.1 `openspec validate planning-phase-gate-document --type change --strict` passes.
- [x] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.

Built in one PR (see design, "Built at HEAD"). 1.3 is written as the Newman requests named above plus "attach the concluding document" and "a file id that is not on the phase does not count"; Newman runs nightly, so the live answer (403 without, 200 with) is the PR's live check. 2.3's upload is exercised through the close dialog in the e2e; the sidebar Files tab is OpenRegister's `CnObjectSidebar`.
