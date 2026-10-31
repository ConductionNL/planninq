# Design: export a whole project from planninq

## Context

What exists on development at `86b1cd5e`:

- **No export in planninq.** No route in `appinfo/routes.php`, no controller, no page action. The project settings sidebar (`src/components/ProjectSettingsSidebar.vue`) has Details, Members, and Danger zone tabs; the Danger zone holds Archive and Delete (`:258-302`).
- **Project-scoped schemas.** These carry a `project` relation in `lib/Settings/planninq_register.json`: `task`, `projectPhase`, `column`, `plannedTimeEntry`, `projectLogEntry`, `risk`, `projectStatusReport`, `financeLine`, `projectRelease`, `boardFilter`, `forgeLink`. `boardView` and `taskReport` hold a `projects` list. `dependency` links two tasks and has no project field.
- **Files.** Task attachments and phase documents live on OpenRegister's per-object files API (`/api/objects/{register}/{schema}/{id}/files`; planninq reads it in `src/api/phaseFiles.js` and through the Attachments tab in `src/views/TaskDetail.vue:317-330`).
- **Comments.** Task comments live in Nextcloud's comments, objectType `openregister`, object id the task UUID (`openspec/specs/task-collaboration/spec.md`).
- **Access.** Owners and managers of a project are the people allowed to archive or delete it (`openspec/specs/project-membership/spec.md`).

What OpenRegister offers, read at `ConductionNL/openregister` development:

- `lib/Service/Export/ExportGate.php::refusalFor(?Schema, string $profile, ?int $registerId)` returns null when the caller holds the `export` verb, and a refusal response naming the verb otherwise.
- `lib/Service/Export/ExportAuditRecorder.php::recordCompleted()` and `::recordRefused()` write one audit row per export.
- `objects#export` at `/api/objects/{register}/{schema}/export` for one schema, CSV or Excel.

No board on the Zuiddrecht canvas draws this action; planninq is not a canvas app. The action follows the existing Danger zone layout.

## Decisions

### D1. A zip in the user's Files, built by a background job

A project of a few thousand tasks with attachments does not fit one request. The controller queues `ExportProjectJob` with the project id and the requesting user, answers 202, and the job writes `Planninq exports/<project key>-<yyyy-mm-dd-hhmm>.zip` in that user's Files. Files is where the user already keeps documents, and Nextcloud's own sharing and quota apply to the result. The job runs as the requester, so OpenRegister's read rules decide what is in the zip.

### D2. Stored values in JSON, rendered values in one CSV

Each schema becomes `<schema>.json`: an array of objects with their stored values and `@self` metadata, so the file can be read back by a machine. `tasks.csv` adds a human-readable view of the tasks (key, title, status, column title, assignees, dates, labels) for a spreadsheet. `manifest.json` at the root names the project, the export time, the planninq and register versions, the value mode per file and the row count per file.

### D3. The gate is OpenRegister's, called once per schema

Before reading a schema, the service calls `ExportGate::refusalFor($schema, 'planninq-project-export', $registerId)`. If the `project` or `task` schema is refused, the whole export stops and the requester is notified that the `export` permission is missing. A refusal on any other schema leaves that file out and lists it under `skipped` in `manifest.json`. Planninq adds no permission of its own beyond "owner or manager of this project".

### D4. Files and comments travel along

`attachments/<task key>/<file name>` and `phase-documents/<phase title>/<file name>` hold the files, copied through OpenRegister's file service as the requester. `comments.json` holds, per task UUID, the comments with author id, display name, time and message. A file the requester cannot read is skipped and listed in the manifest, never fatal.

### D5. One audit row, one notification

On success the job calls `ExportAuditRecorder::recordCompleted()` with the profile `planninq-project-export` and the total object count, then sends a notification ("Export of <project> is ready") whose link opens the zip in Files. On failure it records the refusal or error and sends "Export of <project> failed: <reason>".

## Risks

- **Size.** A project with large attachments makes a large zip. The job streams files into the zip and never holds them in memory; the quota of the requester applies, and a quota error ends the job with the failure notification.
- **Stale data.** Objects changed while the job runs may or may not be in the zip. The manifest states the time the job started.
