---
kind: code
---

# Proposal: school-timetable-target

## Summary
Planninq becomes the place a school timetable lives. It gains a `timetableSession` schema that carries one lesson as the school's rostering system knows it: the source's own occurrence id (`externalRef`), the group, the teacher, the room and the subject, each with the school's own code and, where known, the fleet id it resolves to. An idempotent upsert keyed by source system and `externalRef` lets the integriq rostering adapter deliver a Zermelo, Untis, Xedule or TimeEdit timetable again and again without duplicates. A read API lists the sessions of a cohort or a teacher in a date range, so learniq can show timetables it no longer stores.

## Motivation
Decision D10 (learniq round 1, `market-intelligence/learniq/_round1/compare/decisions.md`, Ruben, 2026-09-27) says planninq owns the timetable, the integriq adapter delivers into planninq, and learniq reads sessions from there. D25 says it is built now.

Today planninq has no timetable concept at all. Its register holds `task`, `project`, `projectPhase`, `column`, `plannedTimeEntry`, `label` and `dependency`; nothing carries a start and end time, a room or a group. The integriq rostering adapter merged as integriq #2166 maps a lesson onto learniq's `DataExchangeJob` payload, and learniq's `TimetableImportHandler` writes learniq `Session` rows from it. Once D10 moves the target, integriq needs a planninq shape to deliver into, and learniq needs a planninq read to replace its own rows.

Corpus evidence:
- M3 row `I11` (`_round1/compare/M3-integrations.md`): a live timetable koppeling from Zermelo, Untis or Xedule is documented by every VO, MBO and HE incumbent surveyed (vo-las#11.7, mbo-he-sis#11.7, DPIA "SIS<=>Xedule: Uitwisselen van student, groepen en roosterinformatie"). Section (b) of that file recommends planninq own the rostering-import contract.
- Planninq's own parity matrix (`openspec/parity/capabilities.json`, planninq #665) carries Zermelo, Untis, Xedule and TimeEdit as timetabling competitors; `pln-calendar-view` quotes each one showing day and week timetables per teacher, group or room (rung: competitor docs read 2026-09-27).

## Affected Projects
- [x] Project: `planninq`: new `timetableSession` schema, idempotent upsert service, two ADR-041 events (upsert request, sessions query), a read endpoint and an admin upsert endpoint.
- [ ] Project: `integriq`: consumer, repointed in the follow-up change `rostering-adapter-targets-planninq`.
- [ ] Project: `learniq`: consumer, reads through the follow-up change `sessions-from-planninq`.

## Scope

### In Scope
- `timetableSession` schema in `lib/Settings/planninq_register.json` with authorization (read for signed-in users, write for admins), demo rows in the mock register, and catalogue keys for every new schema string.
- `TimetableSessionService`: validate, normalise and upsert a batch by (`sourceSystem`, `externalRef`); report created, updated, unchanged and rejected rows; list sessions by cohort, group, teacher or teacher code in a date window, bounded by a limit.
- `TimetableUpsertRequestedEvent` and `TimetableSessionsQueryEvent` in `OCA\Planninq\Event`, each with a result slot, and the listeners that answer them through the service (ADR-041).
- `GET /api/timetable/sessions` (signed-in users, read through OpenRegister with RBAC on) and `POST /api/timetable/sessions/upsert` (admins only).
- A `school-timetable` capability spec and the contract document the two consumers build against.

### Out of Scope
- A timetable page inside planninq. Learniq renders the school timetable; a planninq calendar is the separate `planning-calendar` change.
- The wire binding to Zermelo, Untis, Xedule or TimeEdit. That stays in integriq and stays dormant until each institution onboards (D9).
- Resolving a school's group or teacher code to a fleet id. Planninq stores what the deliverer resolved; integriq's target configuration does the mapping.
- Substitutions, cancellation notices and conflict detection. Learniq keeps those and runs them on the data this change exposes.

## Approach
One schema, one service, two events, one controller. The service owns every rule: required fields, date sanity, the upsert key and the query bounds. The events and the controller are thin doors onto it. Writes from the upsert event run with RBAC off because only server code in another app can dispatch them; the admin endpoint is protected by Nextcloud's admin middleware. Reads always run with RBAC on as the current user. Details and alternatives are in design.md.

## New Dependencies
None.

## Impact
- `lib/Settings/planninq_register.json`: one new schema, register `info.version` 0.5.0 to 0.6.0.
- `lib/Settings/planninq_mock_register.json`: three demo sessions.
- New PHP: `lib/Service/TimetableSessionService.php`, `lib/Event/TimetableUpsertRequestedEvent.php`, `lib/Event/TimetableSessionsQueryEvent.php`, `lib/Listener/TimetableUpsertRequestedListener.php`, `lib/Listener/TimetableSessionsQueryListener.php`, `lib/Controller/TimetableController.php`.
- `appinfo/routes.php`: two routes. `lib/AppInfo/Application.php`: two listener registrations.
- `l10n/en.json`, `l10n/nl.json`: schema strings.

## Cross-Project Dependencies
Integriq and learniq consume this change through `contract.md`. Neither imports a planninq class: both look the event class up by name and fail closed when planninq is absent, the way shillinq reaches filinq today. Their changes are stacked on this one in time, not in git: each works against the contract and degrades cleanly when planninq is not installed.

## Risks

### Risk 1: Upsert runs without RBAC
**Severity:** Medium. **Mitigation:** only in-process server code can dispatch the event, and the listener accepts nothing but the declared fields. The HTTP door to the same service is admin-only.

### Risk 2: A school's group code never resolves to a cohort
**Severity:** Medium. **Mitigation:** the read API filters on the school's own `groupReference` as well as on `cohortId`, so a consumer can still find a group's lessons before a mapping exists.

### Risk 3: Large timetables
**Severity:** Low. **Mitigation:** one lookup per record keeps the upsert exact; a week for a full VO school is about 1,500 lessons, well inside one request. Reads are bounded (default 500, maximum 1,000).

## Rollback Strategy
Revert the merge commit. The schema and its rows stay in OpenRegister but nothing reads or writes them; integriq and learniq fall back to their current paths because their class lookup finds no event.

## Open Questions
None. The deliverer-side mapping of school codes to fleet ids is recorded as an integriq concern in the follow-up change.
