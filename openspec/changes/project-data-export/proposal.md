---
kind: code
depends_on: [openregister/export-as-its-own-right]
---

# Export a whole project from planninq

## Why

A project owner cannot take their own project out of planninq. OpenRegister closed its half in `export-as-its-own-right` (all 14 tasks ticked): export is a permission verb of its own, an export profile declares its fields and value mode, and every export lands on the audit trail. An admin can also take the whole register out as JSON from the OpenRegister app. None of that is reachable from a planninq page, none of it is scoped to one project, and nothing carries the files attached to tasks and phases or the comments on tasks.

Parity row: `plt-data-export` ("Export all project data in an open format to leave the product."), planninq `partial`, state `specified`, reached on "OpenRegister app, admin only; not a planninq page". Nextcloud Deck, OpenProject, Kanboard and Jira rate `yes`; Deck exports a board as JSON and CSV from the board's own menu (`src/components/navigation/BoardExportModal.vue`), which is the shape this change follows.

Delivered so far: `openregister/export-as-its-own-right` (the verb, the profile, the audit entry). Missing, and specified here: the planninq part.

## What changes

- The project settings sidebar gets an "Export project" action for the project's owners and managers.
- Pressing it queues a background job that writes one zip to the requester's Nextcloud Files: one JSON file per planninq schema holding that project's objects, a CSV of the tasks, every file attached to a task or a phase, and the comments on every task.
- Every object goes through OpenRegister's export gate, so a user without the `export` verb is refused with the verb named, and every completed or refused export is recorded through OpenRegister's audit recorder.
- When the zip is ready, the requester gets a Nextcloud notification that opens it in Files.

## Rows

- `plt-data-export`

## Out of scope

- Importing such a zip back into planninq. MS Project import exists (`msproject-import`); a round trip of this format is a separate change.
- An instance-wide export. OpenRegister's register export already serves the admin.
- Scheduled project exports. OpenRegister's scheduled report runner covers that through a profile when someone asks for it.

## Impact

- New: `lib/Service/ProjectExportService.php`, `lib/BackgroundJob/ExportProjectJob.php`, `lib/Controller/ProjectExportController.php`, a notifier, a route, a sidebar action and a dialog.
- Consumes, does not rebuild (ADR-022): OpenRegister `ObjectService` search, `ExportGate`, `ExportAuditRecorder`, the per-object files API and the notes (comments) API.
- No schema change in `lib/Settings/planninq_register.json`.
