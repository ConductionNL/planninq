# Tasks: school-timetable-target

## Implementation Tasks

### Task 1: Declare the timetableSession schema, demo rows and catalogue keys (V1)
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001`
- **files**: `lib/Settings/planninq_register.json`, `lib/Settings/planninq_mock_register.json`, `l10n/en.json`, `l10n/nl.json`, `tests/unit/Settings/PlanninqRegisterSchemaTest.php`
- **acceptance_criteria**:
  - GIVEN the register WHEN parsed THEN `timetableSession` exists with the required fields, status enum and authorization
  - GIVEN the mock register WHEN grouped THEN three demo sessions exist
  - GIVEN `npm run check:schema-l10n` WHEN run THEN it exits 0
- [x] Implement
- [x] Test

### Task 2: TimetableSessionService upsert and list (V1)
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002`
- **files**: `lib/Service/TimetableSessionService.php`, `lib/Service/TimetableSessionQuery.php`, `lib/Service/TimetableSessionRows.php`, `tests/unit/Service/TimetableSessionServiceTest.php`, `tests/unit/Service/TimetableSessionRowsTest.php`
- **acceptance_criteria**:
  - GIVEN a batch delivered twice WHEN upserted THEN the second run is all `unchanged`
  - GIVEN invalid rows WHEN upserted THEN each is rejected with its code before any write
  - GIVEN a cohort and a window WHEN listed THEN overlapping sessions come back sorted and bounded
- [x] Implement
- [x] Test

### Task 3: The two ADR-041 events and their listeners (V1)
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004`
- **files**: `lib/Event/TimetableUpsertRequestedEvent.php`, `lib/Event/TimetableSessionsQueryEvent.php`, `lib/Listener/TimetableUpsertRequestedListener.php`, `lib/Listener/TimetableSessionsQueryListener.php`, `lib/AppInfo/Application.php`, listener tests
- **acceptance_criteria**:
  - GIVEN an upsert event WHEN handled THEN the result slot carries the service result and the event is handled
  - GIVEN a query event without an identity filter WHEN handled THEN it carries an error and no sessions
- [x] Implement
- [x] Test

### Task 4: TimetableController read and admin upsert routes (V1)
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006`
- **files**: `lib/Controller/TimetableController.php`, `appinfo/routes.php`, `tests/unit/Controller/TimetableControllerTest.php`
- **acceptance_criteria**:
  - GIVEN a signed-in user WHEN they read with a filter THEN 200; without a filter THEN 400
  - GIVEN the upsert method WHEN inspected THEN it carries no `NoAdminRequired`
- [x] Implement
- [x] Test

### Task 5: Feature documentation (V1)
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005`
- **files**: `docs/features/school-timetable.md`
- **acceptance_criteria**:
  - GIVEN an integrator WHEN they read the page THEN they find the session shape, both events and both endpoints
- [x] Implement

## Verification
- [x] All tasks checked off
- [x] `openspec validate school-timetable-target` passes

## Quality checklist

- New business logic covered by PHPUnit unit tests (`tests/unit/`).
- No Newman collection: the endpoints are covered by controller unit tests; the lane has no instance of its own.
- No UI change, so no Playwright test.
- English and Dutch catalogue values for every new schema string (ADR-007).
