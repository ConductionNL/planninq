# Tasks: projects-import-from-other-tools

## 1. Mappings

- [x] 1.1 Add import mappings for a Trello board export and a Jira project CSV to OpenRegister's register import, as configuration in `lib/Settings/`. Verify: PHPUnit test that each sample export maps to a project, columns and tasks. Built 14 Nov: the mappings are `lib/Settings/board-import/trello.json` and `jira-csv.json`, read by `BoardExportMapper` and written by `BoardExportImportService` (`BoardExportMapperTest`, `BoardExportImportServiceTest`, samples in `tests/fixtures/board-import/`). Not OpenRegister's migration packs: a pack maps rows onto one schema, and an import here creates a project, its columns and tasks that point at those columns.
- [x] 1.2 Offer the import on the planninq admin page. Verify: Playwright e2e "an admin imports a Trello board". Built 14 Nov: `BoardImportController` (`GET /api/import/boards`, `POST /api/import/boards/{tool}`, admin settings only) and the "Import a project from another tool" section (`BoardImportSettings.vue`, `tests/vitest/boardImport.spec.js`). Open: the e2e case (no instance in this lane). Passed live 2026-11-17: `tests/e2e/board-import.spec.ts` imports `tests/fixtures/board-import/Vergunningen.json` and finds the project, three open-list columns and the dated task.

## 2. Verification

- [x] 2.1 `openspec validate projects-import-from-other-tools --type change --strict` passes.
