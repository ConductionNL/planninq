---
kind: code
---

# Import a Gantt plan made in Microsoft Project

## Why

A project leader who receives a contractor's plan from Microsoft Project has to retype it into planninq. There is no import for it: `lib/Service/RegisterImportService.php` imports only the app's own register configuration (`load`, `reload` and `import` at `:72-160`), and OpenRegister's generic import takes tabular rows, which cannot rebuild a plan's hierarchy or its links between tasks. The pieces a plan needs do exist in the register: tasks with `startDate`, `dueDate`, `percentComplete`, `parent` and `phase` (`lib/Settings/planninq_register.json:220-310`), project phases (`:584-752`) and dependency edges (`:1106-1166`).

Gemeente Sittard-Geleen asked for this in its tender for a project management tool: TenderNed 365739 (published 2025-01-31), requirement 4132, a wish: import contractors' Gantt charts from MS Project ("GANTT-diagrammen van aannemers vanuit MS-project importeren"). No competitor in the matrix is rated yes; the demand is the tender.

Parity rows: `int-import-msproject` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because a public tender asks for it and every object a Gantt plan needs already exists in planninq's register.

This change extends the flat spec `openspec/specs/projects.md` through a new capability, `msproject-import`.

## What changes

- A project owner can upload a plan saved from Microsoft Project in its XML format on the project's timeline.
- Before anything is written, they see what the import will create: phases, tasks, sub-tasks, milestones and links, and what it cannot carry over.
- Confirming creates the plan in the project: summary tasks become phases, tasks keep their dates and progress, and finish-to-start links become dependencies the timeline draws.
- Importing a newer version of the same plan updates the tasks it created before instead of duplicating them.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `int-import-msproject`.

### `int-import-msproject`: Import a Gantt plan made in Microsoft Project.

- Area `integration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No Microsoft Project import in src/ or lib/. lib/Service/RegisterImportService.php imports the app's own configuration. The nearest path is OpenRegister's generic register import (the int-import-other row), which takes tabular rows and would not rebuild a Gantt plan with dependencies."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirement 4132, wish (GANTT-diagrammen van aannemers vanuit MS-project importeren)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.


## Scope

### In scope

- Microsoft Project's XML format (MSPDI, "Save as XML" in Project).
- A preview, then a confirmed import into one existing project.
- Phases from top-level summary tasks, tasks, one level of sub-tasks, milestones, notes, progress, and dependencies.
- Re-import of a newer file from the same plan, matched on Project's task UID.

### Out of scope

- The binary `.mpp` format. It has no open specification and no PHP reader; Project saves the same plan as XML, and the import screen says how.
- Resources, calendars, costs and baselines. Contractor resources are rarely Nextcloud users; the preview lists what was left out.
- Export back to Microsoft Project.
- Deleting tasks that disappeared from a newer file. They are listed, not removed.

## Impact

- Backend: a new `lib/Service/MsProjectImportService.php` (parse, map, preview, write) and a new `lib/Controller/ProjectImportController.php` with a preview and a commit route in `appinfo/routes.php`. This is import logic, not a pass-through (ADR-022).
- Views and dialogs: an "Import from Microsoft Project" button on `src/views/ProjectTimeline.vue` and a new `src/dialogs/MsProjectImportDialog.vue`.
- Schema: none new. Imported objects carry their Project UID in `metadata` (task `:384-389`); `projectPhase` gains the same `metadata` catch-all.
- Depends on: none of this pass's changes. `planning-phase-gate-document` adds the page where imported phases can be seen and managed.

## Risks

### Risk 1: a malicious XML file
**Severity**: High
**Mitigation**: the parser loads no external entities and no network resources, rejects a DOCTYPE, and refuses files over 10 MB or with more than 2,000 tasks, before any mapping runs.

### Risk 2: a plan does not fit planninq's model
**Severity**: Medium
**Mitigation**: the mapping is fixed and shown in the preview: deeper outline levels collapse to one sub-task level, links other than finish-to-start become "related" links, and lags are dropped. Every loss is listed with a count before the owner confirms.

### Risk 3: a half-finished import
**Severity**: Medium
**Mitigation**: the write runs in dependency order (phases, tasks, sub-tasks, links) and records each created object's Project UID, so running the same import again completes it without duplicates. A failure reports how far it got.
