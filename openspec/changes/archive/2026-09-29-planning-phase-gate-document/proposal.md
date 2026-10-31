---
kind: code
---

# Close a project phase only once its concluding document is uploaded

## Why

A project leader cannot close a phase against a document, because planninq has no phase screen at all. The register declares `projectPhase` (`lib/Settings/planninq_register.json:584-752`) with a title, a status (`open`, `in_progress`, `completed`, `cancelled`), an order, a budget and planned dates, and the task schema can point at a phase (`task.phase`, `:220-227`). No page lists phases, no store action reads them (`grep -rn phase src` hits only an unrelated comment in `src/App.vue:122`), and nothing stops a phase from being marked `completed` through the OpenRegister API with nothing uploaded.

Gemeente Sittard-Geleen asked for exactly this in its tender for a project management tool: TenderNed 365739 (published 2025-01-31), requirement 4130, a wish: closing a phase depends on uploading a phase document ("faseafsluiting afhankelijk van het uploaden van een fasedocument"). No competitor in the matrix is rated yes; the demand is the tender.

Parity rows: `pln-phase-gate-document` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because a public tender asks for it and the phase schema it needs already exists without any screen.

This change extends the shipped capability `project-delivery` ("A project may be broken into phases", `openspec/specs/project-delivery/spec.md:53-78`) through a new capability, `project-phases`.

## What changes

- A project member can see, add, edit and reorder the phases of a project on a phases page.
- A project member can upload documents to a phase and mark one as the phase's concluding document.
- Nobody can move a phase to completed without a concluding document. The rule holds on the page and on the OpenRegister API, because it is declared on the schema and checked by OpenRegister.
- The phase shows its concluding document, and its audit trail shows who closed it and when.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `pln-phase-gate-document`.

### `pln-phase-gate-document`: Close a project phase only once the document that concludes it has been uploaded.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Schema only: lib/Settings/planninq_register.json:584-760 declares projectPhase (title, status open/in_progress/completed/cancelled, order, budget). grep for 'projectPhase' and 'phase' across src/ finds nothing but an unrelated comment in src/App.vue:122. No page lists phases and no guard checks for a concluding document."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirement 4130, wish (faseafsluiting afhankelijk van het uploaden van een fasedocument)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.


## Scope

### In scope

- A phases page per project at `/projects/:id/phases`, reached from the project board header.
- Phase documents stored as files on the phase object through OpenRegister's object files API, the same API the task Attachments tab uses.
- A `concludingDocument` reference on `projectPhase` and a close step that sets it.
- A declared lifecycle on `projectPhase` whose transition to `completed` requires a planninq guard that checks the concluding document.

### Out of scope

- Approval workflows on a phase (who must sign the document). A guard checks that the document is there, not what it says.
- Phase templates per project type (`projects-templates-shared-workflow`, lane C).
- Gantt bars for phases on the timeline.
- Reopening rules beyond "a project member can reopen a completed phase", which is kept simple and audited.

## Impact

- Schema: `projectPhase` gains `concludingDocument` and an `x-openregister-lifecycle` block.
- Backend: a new `lib/Lifecycle/PhaseConcludingDocumentGuard.php` implementing OpenRegister's `LifecycleGuardInterface`, registered in `lib/AppInfo/Application.php` under the tag the schema names.
- Store and views: phase actions in `src/store/projects.js`, a new `ProjectPhases` page (manifest and `src/registry.js`), a Phases button in the `ProjectBoard` header.
- Dialogs: `src/dialogs/PhaseEditDialog.vue`, `src/dialogs/PhaseCloseDialog.vue`.
- OpenRegister: needs a version that ships `x-openregister-lifecycle` with guard tags; the register's `openregister` constraint (`lib/Settings/planninq_register.json:11`) moves up to it.
- Depends on: none of this pass's changes. `projects-overview-logs-risks` (lane C) may later host the phases page as a project tab.

## Risks

### Risk 1: the guard is bypassed by a write outside OpenRegister's save path
**Severity**: Medium
**Mitigation**: OpenRegister's lifecycle listener runs on every `saveObject()` update, which is the path the object API, the planninq store and imports through the API take. Its own spec notes that direct mapper writes skip it; planninq has none.

### Risk 2: an OpenRegister without lifecycle support accepts the transition
**Severity**: Medium
**Mitigation**: the register raises its minimum OpenRegister version, and a PHPUnit test asserts the constraint and the lifecycle block together, so neither can ship without the other.

### Risk 3: the concluding document is removed after the phase closed
**Severity**: Low
**Mitigation**: the phase keeps `concludingDocument`, and the audit trail records both the close and the file removal. The phases page shows "Concluding document missing" on a completed phase whose file is gone.
