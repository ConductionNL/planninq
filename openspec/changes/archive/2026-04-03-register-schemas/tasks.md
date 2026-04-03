# Tasks: register-schemas

**Change ID:** register-schemas
**Status:** draft
**Created:** 2026-04-02

---

## Tasks

- [x] 1. **Define `task` schema in `planix_register.json`**
  Remove the `example` placeholder entry from `components.schemas` and add the `task` schema with all properties from design.md: `title`, `description`, `status` (enum + default), `priority` (enum + default), `project`, `zaakUuid`, `column`, `columnOrder`, `assignedTo`, `dueDate`, `startDate`, `estimatedDuration`, `percentComplete`, `labels`, `parent`, `calendarEventUid`, `completedAt`. Set `required: ["title", "status"]`.

- [x] 2. **Define `project` schema in `planix_register.json`**
  Add the `project` schema with all properties: `title`, `description`, `status` (enum + default), `color`, `icon`, `members`, `defaultAssignee`, `caseReference`, `labels`. Set `required: ["title", "status"]`.

- [x] 3. **Define `column` schema in `planix_register.json`**
  Add the `column` schema with all properties: `title`, `project`, `order`, `wipLimit`, `color`, `type` (enum + default). Set `required: ["title", "project", "order"]`.

- [x] 4. **Define `timeEntry` schema in `planix_register.json`**
  Add the `timeEntry` schema with all properties: `task`, `user`, `duration`, `date`, `description`. Set `required: ["task", "user", "duration", "date"]`.

- [x] 5. **Define `label` schema in `planix_register.json`**
  Add the `label` schema with all properties: `title`, `color` (default `"#4376FC"`), `description`. Set `required: ["title", "color"]`.

- [x] 6. **Add seed data — Labels (5 objects)**
  Add seed data section to `planix_register.json` (or the OpenRegister seed mechanism used by the project) with 5 Label objects: Bug (#E74C3C), Feature (#4376FC), Docs (#27AE60), Design (#9B59B6), Infrastructure (#F39C12). Each object uses the `@self` envelope with `register: planix`, `schema: label`, and a slug.

- [x] 7. **Add seed data — Projects (3 objects)**
  Add 3 Project seed objects: `client-portal-v2`, `infrastructure-migration`, `onboarding-automation`. Include realistic titles, descriptions, member lists, colors, and icons per design.md.

- [x] 8. **Add seed data — Columns (12 objects — 4 per project)**
  Add 4 Column seed objects per project (12 total): To Do (order 0, active), In Progress (order 1, wipLimit 3, active), Review (order 2, wipLimit 2, active), Done (order 3, done). Use hard-coded UUIDs from the design.md reference table for project cross-references.

- [x] 9. **Add seed data — Tasks (5 objects)**
  Add 5 Task seed objects as specified in design.md: `fix-login-redirect`, `design-dashboard-widgets`, `write-api-docs`, `k8s-namespace-setup`, `onboarding-n8n-workflow`. Include realistic assignees, priorities, due dates, and label references.

- [x] 10. **Add seed data — Time Entries (3 objects)**
  Add 3 TimeEntry seed objects referencing the task seeds: two entries for `fix-login-redirect` (jdoe, 90 min and 60 min) and one for `k8s-namespace-setup` (ksmits, 45 min).

- [x] 11. **Bump register version from `0.1.0` to `0.2.0`**
  Update `info.version` in `planix_register.json` from `0.1.0` to `0.2.0`. Also update `SettingsService.php` if it holds a separate version constant that gates the import. Verify that the version-check logic in `SettingsService` will detect the mismatch and trigger a re-import on next app load.

- [x] 12. **Remove `example` schema reference from `DeepLinkRegistrationListener`**
  Search `lib/Listener/DeepLinkRegistrationListener.php` (and any other PHP or JS files) for references to the slug `"example"` pointing to the Planix register. Remove or replace those references so no code depends on the deleted schema.

- [x] 13. **Verify import in local dev environment**
  Boot the Docker dev environment, enable Planix, and confirm via the OpenRegister admin UI or API that:
  - All 5 schemas are present in the `planix` register.
  - All seed objects are created (5 labels, 3 projects, 12 columns, 5 tasks, 3 time entries).
  - The register version stored in `appconfig` matches `0.2.0`.

- [x] 14. **Verify idempotency — re-import does not create duplicates**
  Manually trigger a second import (e.g. by temporarily resetting the stored version in `appconfig`, then reloading the app). Confirm that object counts remain unchanged after the second import.

- [x] 15. **Verify schema validation**
  Using curl or Postman, send a POST request to the OpenRegister API to create a Task without a `title` field. Confirm HTTP 400 is returned. Repeat with `status: "unknown"` and confirm HTTP 400.

- [x] 16. **Run `composer check:strict`**
  Run `composer check:strict` in the Planix app directory. Fix any PHPCS, PHPMD, Psalm, or PHPStan issues introduced by or encountered during this change.
