# Contract: school-timetable-target

Contract version: **1**. Every event class carries `public const CONTRACT_VERSION = 1;` and every result array carries `contractVersion: 1`.

## Consumers
- `integriq`: delivers a rostering batch through `TimetableUpsertRequestedEvent` (change `rostering-adapter-targets-planninq`).
- `learniq`: reads sessions through `TimetableSessionsQueryEvent` on the server and through `GET /api/timetable/sessions` when a page needs them (change `sessions-from-planninq`).

## The session shape

One row of schema `timetableSession` in register `planninq`.

| Field | Type | Required | Meaning |
|---|---|---|---|
| `externalRef` | string | yes | The source system's own occurrence id (Zermelo appointment instance, Untis period id, Xedule event id, TimeEdit reservation id). |
| `sourceSystem` | string | yes | Who delivered the row: the integriq Source id (`roster-zermelo`, `roster-untis-oneroster`, `roster-xedule`, `roster-timeedit`) or `manual`. |
| `subject` | string | yes | The subject as the school names it (`Wiskunde`, `wi`). |
| `title` | string | no | Display title. Defaults to `subject`. |
| `startsAt` | date-time | yes | ISO 8601 start. |
| `endsAt` | date-time | yes | ISO 8601 end, after `startsAt`. |
| `groupReference` | string | no | The school's own group code (`3a`, `H3B`). |
| `cohortId` | string | no | The fleet cohort this lesson is for (a learniq `Cohort` uuid), when the deliverer resolved it. |
| `teacherReference` | string | no | The school's own teacher code (`JAN`). |
| `teacherUserId` | string | no | The teacher's Nextcloud user id, when resolved. |
| `roomReference` | string | no | The school's own room code (`A1.12`). |
| `roomLabel` | string | no | Room display name. |
| `status` | `scheduled` \| `cancelled` | no | Default `scheduled`. A source that reports a cancelled lesson sends `cancelled`. |
| `importedAt` | date-time | no | Stamped by planninq on every create or update. Never taken from the caller. |

The upsert key is the pair (`sourceSystem`, `externalRef`). A row written by one source is never matched by another.

## Server-side interface (ADR-041 events)

Consumers MUST NOT import a planninq class. They look the class up by name, `class_exists()`-guard it, construct it with named arguments, `dispatchTyped()` it through `OCP\EventDispatcher\IEventDispatcher`, and read the result slot. When the class does not exist, or `isHandled()` is false after dispatch, planninq is absent and the consumer fails closed (it reports the failure; it never pretends the batch landed).

### `OCA\Planninq\Event\TimetableUpsertRequestedEvent`

```php
new TimetableUpsertRequestedEvent(
    sourceApp: 'integriq',
    sourceSystem: 'roster-zermelo',
    sessions: [ /* rows in the session shape, without importedAt */ ],
    correlationId: '<job or run id, optional>',
);
```

Getters: `getSourceApp()`, `getSourceSystem()`, `getSessions()`, `getCorrelationId()`, `isHandled()`, `getResult(): ?array`. Planninq's listener calls `setResult(array)`, which also marks the event handled.

A row's own `sourceSystem`, when present, is ignored: the event's `sourceSystem` wins, so one batch is always one source.

Result:
```json
{
  "contractVersion": 1,
  "sourceSystem": "roster-zermelo",
  "processed": 3,
  "created": 1,
  "updated": 1,
  "unchanged": 0,
  "rejected": [
    {"externalRef": "zm-1003", "errorCode": "missing-fields", "errorMessage": "Missing required field(s): subject"}
  ],
  "sessionIds": ["<uuid>", "<uuid>"]
}
```

`processed` equals `created + updated + unchanged + count(rejected)`. `sessionIds` lists the rows created, updated or found unchanged, in batch order.

When planninq refuses the batch as a whole (an empty `sourceSystem`, or OpenRegister not available), the event still comes back handled, with every count at 0 and an `error` string. A consumer MUST treat a result with `error` as a failed delivery, not as an empty one.

### `OCA\Planninq\Event\TimetableSessionsQueryEvent`

```php
new TimetableSessionsQueryEvent(
    sourceApp: 'learniq',
    criteria: [
        'cohortId' => '<uuid>',          // or groupReference, teacherUserId, teacherReference
        'from' => '2026-09-28T00:00:00+02:00',
        'to' => '2026-10-04T23:59:59+02:00',
        'limit' => 500,
    ],
);
```

Criteria keys: `cohortId`, `groupReference`, `teacherUserId`, `teacherReference` (at least one is required), `from`, `to` (optional ISO 8601 window; a session is included when it overlaps the window), `limit` (default 500, capped at 1,000), `includeCancelled` (default true).

Getters: `getSourceApp()`, `getCriteria()`, `isHandled()`, `getSessions(): ?array`, `getError(): ?string`. The listener calls `setSessions(array)` on success or `setError(string)` when the criteria are invalid; both mark the event handled. The read runs with OpenRegister RBAC on, as the current user.

Each session in the result:
```json
{
  "id": "<uuid>",
  "externalRef": "zm-1001",
  "sourceSystem": "roster-zermelo",
  "subject": "Wiskunde",
  "title": "Wiskunde",
  "startsAt": "2026-09-28T09:00:00+02:00",
  "endsAt": "2026-09-28T09:50:00+02:00",
  "groupReference": "3a",
  "cohortId": null,
  "teacherReference": "JAN",
  "teacherUserId": null,
  "roomReference": "A1.12",
  "roomLabel": "A1.12",
  "status": "scheduled"
}
```

Sessions are sorted by `startsAt` ascending.

## Endpoints

### `GET /apps/planninq/api/timetable/sessions`
**Auth**: Nextcloud session, any signed-in user (`#[NoAdminRequired]`). Rows are read through OpenRegister with RBAC on, so a caller sees only what the schema lets them read.

**Request:** query parameters `cohortId`, `groupReference`, `teacherUserId`, `teacherReference`, `from`, `to`, `limit`, as the query event.

**Response (200):**
```json
{"results": [ /* sessions as above */ ], "total": 1, "window": {"from": "2026-09-28T00:00:00+02:00", "to": null}}
```

**Errors:**
| Code | Condition |
|------|-----------|
| 400  | No identity filter, an unparseable `from`/`to`, or `from` after `to`. |
| 401  | No signed-in user. |
| 503  | OpenRegister is not available. |

### `POST /apps/planninq/api/timetable/sessions/upsert`
**Auth**: Nextcloud session, admins only. The method carries `#[AuthorizedAdminSetting]` for planninq's admin settings and no `#[NoAdminRequired]`, so Nextcloud's security middleware refuses ordinary users; an explicit admin check in the method then refuses a delegated settings admin with 403.

**Request:**
```json
{"sourceSystem": "roster-zermelo", "sessions": [ /* rows in the session shape */ ]}
```

**Response (200):** the upsert result above.

**Errors:**
| Code | Condition |
|------|-----------|
| 400  | `sourceSystem` empty, or `sessions` not a list. |
| 403  | Caller is not an admin (middleware, or the explicit check for a delegated settings admin). |
| 503  | OpenRegister is not available. |

## Error Codes
Row-level rejection codes in an upsert result:

| Code | Meaning | Condition |
|------|---------|-----------|
| `missing-fields` | A required field is absent | `externalRef`, `subject`, `startsAt` or `endsAt` empty. |
| `invalid-dates` | The times do not make a lesson | `startsAt` or `endsAt` unparseable, or `endsAt` not after `startsAt`. |
| `invalid-status` | Unknown status | `status` present and not `scheduled` or `cancelled`. |
| `duplicate-external-ref` | Same key twice in one batch | An earlier row with the same `externalRef`; the last one wins. |
| `save-failed` | OpenRegister refused the write | The save threw or returned nothing. |

## Versioning
Contract version 1. Additive changes (a new optional field, a new criteria key, a new rejection code) keep version 1. Consumers MUST ignore unknown result keys.

## Breaking Change Policy
A rename or removal of a field, criteria key, event class or constructor argument bumps `CONTRACT_VERSION` and ships as a new event class next to the old one for one release, announced in the PR bodies of both consumers.

## SLA
In-process events: no network. An upsert of 1,500 rows performs one lookup and at most one save per row. A read returns at most 1,000 rows.
