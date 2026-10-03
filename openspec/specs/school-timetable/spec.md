# school-timetable Specification

## Purpose
Planninq holds a school's timetable as `timetableSession` rows (schema:Event; iCalendar VEVENT: `UID` = `externalRef`, `DTSTART` = `startsAt`, `DTEND` = `endsAt`, `SUMMARY` = `title`, `LOCATION` = `roomLabel`, `STATUS` = `status`). Rows arrive from a rostering system through the integriq adapter and are upserted idempotently by source and occurrence id. Other apps read them by cohort, group or teacher in a date window. Decision D10 (learniq round 1) makes planninq the timetable owner; ADR-041 fixes the cross-app mechanism as typed events; ADR-022 keeps storage in OpenRegister.

## Requirements

### Requirement: A timetable session schema carries the school's own ids (REQ-001)
The register MUST declare a `timetableSession` schema whose required fields are `externalRef`, `sourceSystem`, `subject`, `startsAt` and `endsAt`, and whose optional fields are `title`, `groupReference`, `cohortId`, `teacherReference`, `teacherUserId`, `roomReference`, `roomLabel`, `status` (`scheduled` or `cancelled`, default `scheduled`) and `importedAt`. Its authorization MUST let the `planninq-timetable` group, the teacher a lesson names (`teacherUserId` equal to the caller) and admins read, MUST NOT let every signed-in user read (planninq#711), and MUST let only admins create, update or delete. The register version and the schema version MUST be bumped. Feature tier: V1.

#### Scenario: The schema is declared with its required fields
- GIVEN `lib/Settings/planninq_register.json`
- WHEN the `timetableSession` schema is read
- THEN its `required` list is exactly `externalRef`, `sourceSystem`, `subject`, `startsAt`, `endsAt`
- AND `status` declares the enum `scheduled`, `cancelled` with default `scheduled`
- AND `authorization.read` is exactly the `planninq-timetable` group, the `authenticated` group matched on `teacherUserId` equal to `$userId`, and `admin`
- AND no read rule admits the `authenticated` group without a match
- AND `create`, `update` and `delete` admit only `admin`

#### Scenario: A pupil cannot read another group's lessons
- GIVEN lessons for groups 4A and 4B, each naming its own teacher
- AND a signed-in pupil of 4A who is not in `planninq-timetable` and is named by no lesson
- WHEN the `timetableSession` read rule is applied to that pupil
- THEN no lesson of 4B is readable, and no lesson of 4A either
- AND the teacher `klaas` reads exactly the lessons that name `klaas`
- AND a member of `planninq-timetable` and an admin read every lesson

#### Scenario: Demo data covers the schema
- GIVEN `lib/Settings/planninq_mock_register.json`
- WHEN its objects are grouped by schema
- THEN at least one object has schema `timetableSession` and carries every required field

### Requirement: Upsert is idempotent by source and occurrence id (REQ-002)
`TimetableSessionService::upsert()` MUST match an incoming row to an existing session only when both `sourceSystem` and `externalRef` are equal. A match with unchanged significant fields MUST be counted `unchanged` and not saved; a match with a changed field MUST be updated in place; no match MUST create a new session. Every create or update MUST stamp `importedAt` server-side. A row whose key appears again later in the same batch MUST be rejected with `duplicate-external-ref`, and the last occurrence MUST win. Feature tier: V1.

#### Scenario: Delivering the same timetable twice creates no duplicates
- GIVEN an empty timetable
- WHEN a batch of two rows from `roster-zermelo` is upserted twice
- THEN the first run reports `created: 2`
- AND the second run reports `created: 0`, `updated: 0`, `unchanged: 2`
- AND no save is made on the second run

#### Scenario: A moved lesson is updated, not duplicated
- GIVEN a session `zm-1001` from `roster-zermelo` in room `A1.12`
- WHEN the same `externalRef` arrives with room `B2.04`
- THEN the existing session is saved with its own id and room `B2.04`
- AND the result reports `updated: 1`

#### Scenario: The same externalRef from another source is a different lesson
- GIVEN a session `1001` from `roster-untis-oneroster`
- WHEN a row `1001` arrives from `roster-zermelo`
- THEN a new session is created
- AND the Untis session is left untouched

### Requirement: Upsert validates before it writes (REQ-003)
The service MUST reject, without any write, a row that lacks a required field (`missing-fields`), whose times are unparseable or do not end after they start (`invalid-dates`), or whose `status` is not `scheduled` or `cancelled` (`invalid-status`). A save that throws or returns nothing MUST be reported as `save-failed`. The result MUST satisfy `processed = created + updated + unchanged + count(rejected)`. Feature tier: V1.

#### Scenario: An incomplete row is rejected and the rest lands
- GIVEN a batch where one row has no `subject`
- WHEN the batch is upserted
- THEN that row appears in `rejected` with `errorCode` `missing-fields`
- AND the other rows are created
- AND `processed` equals the batch size

#### Scenario: A lesson that ends before it starts is rejected
- GIVEN a row with `endsAt` earlier than `startsAt`
- WHEN it is upserted
- THEN it is rejected with `invalid-dates` and nothing is saved

### Requirement: Another app delivers a batch through a typed event (REQ-004)
Planninq MUST publish `OCA\Planninq\Event\TimetableUpsertRequestedEvent` (contract version 1) carrying `sourceApp`, `sourceSystem`, `sessions` and `correlationId`, and MUST register a listener that runs the upsert with the event's `sourceSystem` and writes the result into the event's result slot, marking it handled. The listener MUST write with RBAC off, because only in-process server code can dispatch the event. Feature tier: V1.

#### Scenario: Integriq's dispatch is answered with a result
- GIVEN planninq is installed
- WHEN a `TimetableUpsertRequestedEvent` for `roster-zermelo` with two valid rows is dispatched
- THEN after dispatch `isHandled()` is true
- AND `getResult()` reports `contractVersion: 1` and `created: 2`

#### Scenario: A row's own sourceSystem cannot redirect the batch
- GIVEN an event for `roster-zermelo` whose row says `sourceSystem: roster-xedule`
- WHEN it is handled
- THEN the session is stored with `sourceSystem` `roster-zermelo`

### Requirement: Another app reads sessions through a typed event (REQ-005)
Planninq MUST publish `OCA\Planninq\Event\TimetableSessionsQueryEvent` (contract version 1) and a listener that answers it with the sessions matching the criteria, read with RBAC off. Only in-process server code can dispatch the event, and the requesting app MUST ask only for a cohort or teacher its user may see; planninq holds no cohort membership it could check that against (planninq#711). At least one of `cohortId`, `groupReference`, `teacherUserId`, `teacherReference` MUST be given, or the listener MUST set an error instead of sessions. A session MUST be included when it overlaps the optional `from`/`to` window. Results MUST be sorted by `startsAt` and bounded by `limit` (default 500, at most 1,000). Feature tier: V1.

#### Scenario: A cohort's week is returned in time order
- GIVEN three sessions for cohort `c-1`, one of them outside the requested week
- WHEN a query event asks for `cohortId: c-1` in that week
- THEN the two sessions inside the week are returned, earliest first
- AND the read ran with RBAC off

#### Scenario: A query without an identity filter is refused
- GIVEN a query event with only a date window
- WHEN it is handled
- THEN `getSessions()` is null and `getError()` names the missing filter

### Requirement: Signed-in users read sessions over HTTP; only admins upsert (REQ-006)
`GET /api/timetable/sessions` MUST be open to any signed-in user, MUST read through OpenRegister with RBAC on, so a caller receives only the lessons the schema's read rule grants them, and MUST answer 400 when no identity filter is given or the window is invalid. `POST /api/timetable/sessions/upsert` MUST NOT carry `#[NoAdminRequired]`, so Nextcloud refuses non-admins, and MUST answer 400 when `sourceSystem` is empty or `sessions` is not a list. Feature tier: V1.

#### Scenario: A teacher lists their own week
- GIVEN a signed-in teacher with sessions under `teacherUserId`
- WHEN they call `GET /api/timetable/sessions?teacherUserId=<uid>&from=...&to=...`
- THEN the response is 200 with `results` and `total`

#### Scenario: A read without filters is refused
- GIVEN a signed-in user
- WHEN they call `GET /api/timetable/sessions` with no filter
- THEN the response is 400
