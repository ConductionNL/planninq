# Design: show a draft timetable to the teachers it names before learners see it

## Context

Read on 28 September 2026. `development` at `4248783` has no timetable: `lib/Settings/planninq_register.json` declares `task`, `project`, `projectPhase`, `column`, `plannedTimeEntry`, `label` and `dependency`. The timetable arrives with `school-timetable-target`, planninq PR #685, read at its head `9801e16`:

- `lib/Settings/planninq_register.json` (at `9801e16`, from `:1169`): schema `timetableSession` with `status` enum `scheduled`, `cancelled`, default `scheduled`; `authorization.read` is `[{"group": "authenticated"}, {"group": "admin"}]`; create, update and delete are admin only.
- `lib/Service/TimetableSessionService.php`: `STATUSES = ['scheduled', 'cancelled']` (`:118`), `upsert()` (`:162`), `upsertRow()` defaults the status to `scheduled` (`:293`, `:315`), `validate()` (`:363`), `findExisting()` by source and `externalRef` (`:401`), `isUnchanged()` (`:443`), `save()` (`:498`), `rejection()` (`:559`).
- `lib/Service/TimetableSessionQuery.php`: the bounded read with `includeCancelled` (default true, `:104`, `:125-127`) and `matches()` applying it in PHP (`:179-184`).
- `lib/Controller/TimetableController.php`: `sessions()` (`:99`, signed-in, RBAC read) and `upsert()` (`:153`, admin only through `#[AuthorizedAdminSetting]` and an explicit admin check, per `contract.md`).
- `openspec/changes/school-timetable-target/contract.md`: contract version 1; additive changes (a new optional field, a new criteria key, a new rejection code) keep version 1.
- The same PR carries a security note (comment of 27 September 22:57): every signed-in account can read every group's and teacher's lessons. This change does not settle that note. It adds one rule on top: whatever the published read becomes, a draft row is never part of it.
- Settled since by planninq#711: `authorization.read` is now the `planninq-timetable` group, `teacherUserId` equal to `$userId`, and admins; and the query event reads with RBAC off, because learniq decides which cohorts its user may see. Decision 2 below therefore has to put the draft condition on the `planninq-timetable` rule, and on the query-event path the only draft guard is `includeDrafts` in `TimetableSessionQuery::matches()`.

OpenRegister evaluates `match` conditions in authorization rules with `$eq`, `$ne`, `$in`, `$nin` and `$contains` (`lib/Service/OperatorEvaluator.php:7` in openregister at `555af72`) and substitutes `$userId` for the caller (`lib/Service/ConditionMatcher.php:325`). Planninq already uses that pattern on `task` (`"members": {"$contains": "$userId"}` in `lib/Settings/planninq_register.json` at `4248783`).

## Goals / non-goals

Goals:
- A draft lesson reaches the teacher it names and no learner.
- Publishing is one admin action per source and window.
- Consumers that never ask for drafts see exactly what they see today.

Non-goals:
- A comment, flag or sign-off per lesson.
- A draft copy of an already published lesson. A published lesson stays published; changes to it arrive as normal updates.

## Decisions

### Decision 1: `draft` is a status value, not a second schema
A draft lesson has the same shape as a published one and the same upsert key. A status value keeps one row per occurrence and lets publishing be an update in place. Rejected: a `timetableDraft` schema copied into `timetableSession` on publish, which doubles every rule and every read.

### Decision 2: the read rule lives on the schema
`authorization.read` becomes three rules: signed-in users where `status` is `$in` `scheduled`, `cancelled`; signed-in users where `teacherUserId` equals `$userId`; admins. OpenRegister applies it to every read path, including the query event, so no PHP filter is the only guard. When PR #685's security note narrows the first rule, the `status` condition stays part of it.

### Decision 3: drafts only on request
`TimetableSessionQuery` gains `includeDrafts` (default false). `matches()` drops `draft` rows unless it is true, next to the existing `includeCancelled`. A consumer that never sends the key gets the published timetable even when the caller could read a draft. The HTTP read takes the same parameter.

### Decision 4: a published lesson never goes back to draft
In `upsertRow()`, when `findExisting()` returns a row whose status is `scheduled` or `cancelled` and the incoming status is `draft`, the row is rejected with the new code `already-published` and nothing is saved. A lesson learners have seen cannot vanish from their timetable because a later delivery marked it draft.

### Decision 5: publish is an admin endpoint over the service
`POST /api/timetable/sessions/publish` takes `sourceSystem`, `from` and `to`, and sets every `draft` row of that source overlapping the window to `scheduled`, stamping `importedAt`. It follows `upsert()`: `#[AuthorizedAdminSetting]`, no `#[NoAdminRequired]`, the explicit admin check, and a result with `contractVersion: 1`, `published` and `failed`. A rostering adapter can also publish by delivering the same rows with status `scheduled`, which the existing upsert updates in place.

## Declarative-vs-imperative decision (ADR-031)

| Behaviour | Path | Rationale |
|---|---|---|
| Who may read a draft | Declarative: `authorization.read` on the schema | Standard; applies to every read path. |
| Draft never replaces a published lesson | Imperative: `TimetableSessionService::upsertRow()` | The rule compares the stored and the incoming status of an external delivery (ADR-031 exception: external integration). |
| Publish a window | Imperative, thin: one bounded search and one save per row in the service | Admin command over existing rows. |

## Security considerations

- The draft read rule is additive to the schema's rules and names the caller through `$userId`, so it cannot widen the published read.
- Publish is admin only, like upsert.
- No new personal data: a draft carries the same fields as a published lesson.
