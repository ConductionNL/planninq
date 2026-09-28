# timetable-draft-review delta for timetable-draft-review

## ADDED Requirements

### Requirement: A draft lesson is readable only by the teacher it names and by admins

The `timetableSession` schema MUST accept the status `draft` besides `scheduled` and `cancelled`. Its read authorization MUST let a signed-in user read a `draft` session only when the session's `teacherUserId` is that user, and MUST let admins read every session. A signed-in user who is not the named teacher MUST NOT receive a `draft` session from any read path, including `GET /api/timetable/sessions` and `TimetableSessionsQueryEvent`.

#### Scenario: The named teacher sees their draft lesson

- **GIVEN** a draft session "Wiskunde 3a" on Monday at 09:00 with `teacherUserId` "jan"
- **WHEN** Jan calls `GET /apps/planninq/api/timetable/sessions?teacherUserId=jan&includeDrafts=true` for that week
- **THEN** the response lists "Wiskunde 3a" with status `draft`

#### Scenario: A learner does not see a draft lesson

- **GIVEN** the same draft session for group `3a`
- **WHEN** a learner of group 3a calls `GET /apps/planninq/api/timetable/sessions?groupReference=3a&includeDrafts=true` for that week
- **THEN** the response does not list "Wiskunde 3a"

### Requirement: Drafts are returned only when the caller asks for them

The read criteria MUST accept `includeDrafts`, default false. When it is false or absent, no `draft` session MUST be returned, even to a caller allowed to read it. This MUST hold for the HTTP read and for `TimetableSessionsQueryEvent`, so a consumer built against contract version 1 keeps receiving published lessons only.

#### Scenario: A consumer that does not ask gets the published timetable

- **GIVEN** Jan's week holds one published and one draft lesson
- **WHEN** learniq dispatches `TimetableSessionsQueryEvent` with `teacherUserId: jan` and no `includeDrafts`
- **THEN** the event's sessions hold the published lesson only

### Requirement: A later delivery never turns a published lesson into a draft

The upsert MUST reject, without saving, a row with status `draft` whose stored session with the same `sourceSystem` and `externalRef` has status `scheduled` or `cancelled`, reporting `errorCode` `already-published`. A row with status `scheduled` for a stored `draft` session MUST update it in place.

#### Scenario: A draft delivery for a published lesson is refused

- **GIVEN** a published session `zm-1001` from `roster-zermelo`
- **WHEN** integriq delivers `zm-1001` from `roster-zermelo` with status `draft`
- **THEN** the upsert result lists `zm-1001` in `rejected` with `already-published`
- **AND** learners still see the published lesson

#### Scenario: Delivering the lesson as scheduled publishes the draft

- **GIVEN** a draft session `zm-2001` from `roster-zermelo`
- **WHEN** the same row arrives with status `scheduled`
- **THEN** the session is updated in place to `scheduled`
- **AND** the upsert result reports `updated: 1`

### Requirement: An admin publishes the drafts of one source in a date window

`POST /apps/planninq/api/timetable/sessions/publish` MUST take `sourceSystem`, `from` and `to`, MUST set every `draft` session of that source overlapping the window to `scheduled`, and MUST answer with `contractVersion`, `published` and `failed`. It MUST NOT carry `#[NoAdminRequired]`, and a non-admin MUST be refused.

#### Scenario: Publishing a week makes the lessons visible to learners

- **GIVEN** five draft sessions from `roster-zermelo` in the week of 5 October 2026
- **WHEN** an admin posts `{"sourceSystem": "roster-zermelo", "from": "2026-10-05T00:00:00+02:00", "to": "2026-10-11T23:59:59+02:00"}` to the publish endpoint
- **THEN** the response reports `published: 5`
- **AND** a learner's read for that week lists the five lessons

#### Scenario: A teacher cannot publish

- **GIVEN** a signed-in teacher who is not an admin
- **WHEN** they post to the publish endpoint
- **THEN** Nextcloud refuses the request and no session changes status
