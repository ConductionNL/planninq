# Tasks: project-data-export

## 1. Service and job

- [ ] 1.1 `lib/Service/ProjectExportService.php`: for a project id and a user, list the project-scoped schemas (`task`, `projectPhase`, `column`, `plannedTimeEntry`, `projectLogEntry`, `risk`, `projectStatusReport`, `financeLine`, `projectRelease`, `boardFilter`, `forgeLink`, plus `boardView` and `taskReport` through their `projects` list, plus `dependency` rows whose tasks are in the project), ask OpenRegister's `ExportGate::refusalFor()` per schema with profile `planninq-project-export` (design D3), and read the objects through OpenRegister's `ObjectService` as that user. Verify: `tests/unit/Service/ProjectExportServiceTest.php` with a real `ExportGate` double that refuses one schema, asserting the stop on `task` and the skip on `risk`. — not run: needs OpenRegister's `ExportGate` and `ExportAuditRecorder`, which are not in this checkout (cross-repo); not built blind
- [ ] 1.2 Write the zip with `ZipStreamer` (or PHP `ZipArchive` on a temp file streamed into Files): `project.json`, one `<schema>.json` per schema (stored values), `tasks.csv` (rendered: key, title, status, column title, assignee display names, start, due, labels), `comments.json` from `ICommentsManager` (objectType `openregister`), files from OpenRegister's file service under `attachments/<task key>/` and `phase-documents/<phase title>/`, and `manifest.json` (design D2, D4). Verify: the unit test opens the produced zip and asserts each entry and the manifest row counts. — not run: needs OpenRegister's `ExportGate` and `ExportAuditRecorder`, which are not in this checkout (cross-repo); not built blind
- [ ] 1.3 `lib/BackgroundJob/ExportProjectJob.php` (a `QueuedJob`, arguments `projectId`, `userId`): set up the user session, run the service, write the zip to `Planninq exports/` in the user's Files, record through `ExportAuditRecorder::recordCompleted()` or `::recordRefused()`, remove a partial zip on any failure. Verify: `tests/unit/BackgroundJob/ExportProjectJobTest.php` for success, refusal and a `NotEnoughSpaceException`. — not run: needs OpenRegister's `ExportGate` and `ExportAuditRecorder`, which are not in this checkout (cross-repo); not built blind

## 2. Endpoint

- [ ] 2.1 `lib/Controller/ProjectExportController.php::create(string $id)` with `#[NoAdminRequired]`: 403 unless the caller is an owner or manager of the project (read from the project's `managers` and owner group, as the delete path does), else queue `ExportProjectJob` and answer 202 `{ "status": "queued" }`. Route `['name' => 'projectExport#create', 'url' => '/api/projects/{id}/export', 'verb' => 'POST']` in `appinfo/routes.php`. Verify: `tests/unit/Controller/ProjectExportControllerTest.php`; hydra gates route-auth, route-reachability and no-admin-idor pass on the diff. — not run: depends on the export job (1.3) and needs a live instance
- [ ] 2.2 Newman: add to `tests/integration/planninq.postman_collection.json` a member's POST answering 403 and a manager's answering 202. — not run: depends on the export job (1.3) and needs a live instance

## 3. Notification

- [ ] 3.1 `lib/Notification/Notifier.php` (an `INotifier`; register it in `lib/AppInfo/Application.php` if planninq has none yet) with subjects `project_export_ready` (link to the zip in Files) and `project_export_failed` (with the reason). Strings through `IL10N`, in `l10n/nl.json` and `l10n/en.json`. — not run: needs OpenRegister's `ExportGate` and `ExportAuditRecorder`, which are not in this checkout (cross-repo); not built blind
- [ ] 3.2 The job sends one of the two subjects at its end. Verify: `ExportProjectJobTest` asserts the notification for each outcome. — not run: needs OpenRegister's `ExportGate` and `ExportAuditRecorder`, which are not in this checkout (cross-repo); not built blind
- [ ] 3.3 Unit test for the quota failure: no zip left in Files, the failed notification with "not enough storage space".

## 4. Sidebar action

- [ ] 4.1 In `src/components/ProjectSettingsSidebar.vue`, Danger zone tab, above Archive: the text "Download everything in this project as a zip in your Files." and an "Export project" button, shown only when the current user is an owner or manager. — not run: depends on the export job (1.3) and needs a live instance
- [ ] 4.2 `src/dialogs/ProjectExportDialog.vue` (its own file, per the modal-isolation gate): confirm, call `POST /apps/planninq/api/projects/{id}/export`, show "The export is running. You will get a notification when it is in your Files." on 202 and the server's message on an error. — not run: depends on the export job (1.3) and needs a live instance
- [ ] 4.3 Playwright: `tests/e2e/project-export.spec.ts` covering "A manager starts an export", "A member does not see the action", "The zip holds the project and nothing else" (run the job with `occ background-job:execute`, then read the zip through WebDAV), "A manager without the export verb is refused", "A completed export is on the audit trail" and "The ready notification opens the zip". — not run: depends on the export job (1.3) and needs a live instance

## 5. Verification

- [ ] 5.1 `openspec validate project-data-export --type change --strict` passes. — not run: nothing built yet
- [ ] 5.2 Every scenario in `specs/` is covered by a test named in a task above, or carries an `@e2e exclude <reason>` note. — not run: nothing built yet
- [ ] 5.3 On archive, set `plt-data-export` in `openspec/parity/capabilities.json` to built with the evidence and planninq `yes`. — not run: nothing built yet
