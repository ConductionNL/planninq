# Test Plan: school-timetable-target

## Test Cases

### TC-1: Schema declares the session shape and its access rules
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001`
- **type**: regression
- **preconditions**: the register file on the branch
- **steps**: run `PlanninqRegisterSchemaTest`
- **expected result**: required fields, status enum and authorization match the spec; demo rows exist
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter PlanninqRegisterSchemaTest`

### TC-2: Re-delivery is idempotent
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002`
- **type**: regression
- **preconditions**: an ObjectService double that stores saved rows in memory
- **steps**: upsert the same two-row batch twice
- **expected result**: `created: 2`, then `unchanged: 2` and no second save
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableSessionServiceTest`

### TC-3: Moved lesson updates, other source creates
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002`
- **type**: regression
- **preconditions**: one stored session
- **steps**: upsert the same key with a new room; upsert the same `externalRef` from another source
- **expected result**: update in place with the stored id; a separate create for the other source
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableSessionServiceTest`

### TC-4: Validation rejects before writing
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003`
- **type**: regression
- **preconditions**: a batch with a missing subject, reversed times, an unknown status and a duplicate key
- **steps**: upsert it
- **expected result**: each bad row rejected with its code, the valid rows created, the tally adds up
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableSessionServiceTest`

### TC-5: Upsert event is answered
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004`
- **type**: api
- **preconditions**: the listener with a service double
- **steps**: handle a real `TimetableUpsertRequestedEvent`
- **expected result**: handled, result carries `contractVersion: 1`, the event's source wins over a row's
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableUpsertRequestedListenerTest`

### TC-6: Query event is answered in time order, refused without a filter
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005`
- **type**: api
- **preconditions**: the listener with the real service over an ObjectService double
- **steps**: handle a cohort query for a week; handle a query with only a window
- **expected result**: sessions in the week, earliest first; an error and no sessions for the second
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableSessionsQueryListenerTest`

### TC-7: HTTP read and admin upsert
- **spec_ref**: `openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006`
- **type**: security
- **preconditions**: controller with a user session double
- **steps**: call `sessions()` with and without filters and without a user; call `upsert()` with a bad body; inspect attributes
- **expected result**: 200, 400, 401; 400 for the bad body; `upsert()` carries no `NoAdminRequired`
- **test command**: `vendor/bin/phpunit -c phpunit-unit.xml --filter TimetableControllerTest`

## Coverage Summary
- REQ-001: covered by TC-1.
- REQ-002: covered by TC-2, TC-3.
- REQ-003: covered by TC-4.
- REQ-004: covered by TC-5.
- REQ-005: covered by TC-6.
- REQ-006: covered by TC-7.

## Out of Scope
A live instance run against a real OpenRegister: the lane has no instance of its own and must not deploy to the shared one on :8080. The integriq and learniq changes exercise the events from the consumer side with the real event classes.
