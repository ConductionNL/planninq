# Design: school-timetable-target

## Architecture Overview

```
integriq RosterDelivery ──dispatchTyped──▶ TimetableUpsertRequestedEvent
                                              │
                                              ▼
                               TimetableUpsertRequestedListener
                                              │
admin ──POST /api/timetable/sessions/upsert──▶│
                                              ▼
                                   TimetableSessionService ──▶ OpenRegister (planninq / timetableSession)
                                              ▲
learniq TimetableSource ──dispatchTyped──▶ TimetableSessionsQueryEvent ──▶ TimetableSessionsQueryListener
learniq page ──GET /api/timetable/sessions──▶ TimetableController ─────────┘
```

The service owns every rule. The listeners and the controller only translate their input into a service call and the service's answer into their output. Storage is OpenRegister (ADR-022); planninq still owns no table.

## Decisions

### D1: Typed events, not a container lookup
ADR-041 names typed `IEventDispatcher` events as the only sanctioned cross-app command, and gate 27 blocks the alternatives (registry RPC, server-side HTTP to a sibling). The listener runs inside planninq's own DI context, so integriq and learniq never resolve a planninq service. Rejected: `ContainerInterface::get('OCA\Planninq\Service\TimetableSessionService')` from the consumer (the shillinq-contribution shape). It works, but it binds the consumer to planninq's internal class and constructor, which ADR-041 calls cross-container resolution.

### D2: A read is an event too
The query event keeps planninq's field names behind a contract instead of making learniq search planninq's register by slug. Rejected: learniq calling `ObjectService::searchObjectsBySlug('planninq', 'timetableSession', ...)` directly. It needs no planninq code, but every schema rename in planninq would silently empty learniq's timetable, the failure the fleet has shipped before.

### D3: Upsert key is (`sourceSystem`, `externalRef`)
An occurrence id is unique only inside its own system; Untis period `1001` and a Zermelo appointment `1001` are different lessons. Scoping by source also keeps a manual row (`sourceSystem: manual`) out of reach of any import.

### D4: One exact lookup per row, not a prefetch
A prefetch with an `in` filter would save queries, but if OpenRegister ignored the operator the prefetch would come back empty and every row would be created again. One `searchObjectsBySlug` per row with both key fields and `_limit: 2` is exact, and a week of a full school is about 1,500 rows. When a lookup finds two rows (legacy duplicates), the first is updated and a warning is logged.

### D5: Unchanged rows are not saved
The service compares the significant fields (everything in the session shape except `importedAt`) with what is stored. Skipping unchanged rows keeps the audit trail readable after the nightly re-delivery and makes the second run of the same batch observable as `unchanged`.

### D6: Upsert writes with RBAC off; reads with RBAC on
The upsert listener is reachable only from in-process server code, and the HTTP upsert is admin-only by middleware, so the service writes with `_rbac: false` for both. A coordinator who starts a learniq import is not an OpenRegister admin, and must still get the timetable in. Reads always use RBAC as the current user: a timetable is readable by any signed-in user by schema authorization, and nothing else leaks.

### D7: Codes and ids side by side
The school's own codes (`groupReference`, `teacherReference`, `roomReference`) are what every rostering system has. The fleet ids (`cohortId`, `teacherUserId`) are what learniq has. Planninq stores both and filters on either, so learniq can read a group's lessons before anyone maps the code to a cohort. Planninq does not resolve codes itself: the deliverer knows the school's mapping.

## Declarative-vs-imperative decision (ADR-031)

| Behaviour | Path | Rationale |
|---|---|---|
| Session status (`scheduled`, `cancelled`) | Declarative: enum with default on the schema | The source decides; there is no transition a user performs in planninq. |
| Idempotent upsert by external key | Imperative: `TimetableSessionService` | External-system bridge (ADR-031 exception: external integration). OpenRegister has no upsert-by-foreign-key declaration. |
| Read by cohort or teacher in a window | Imperative, thin: the service builds one bounded OpenRegister query | Cross-app contract door; the filtering itself is OpenRegister's. |
| Authorization | Declarative: `authorization` block on the schema | Standard. |

## API Design
See `contract.md`, which is authoritative. Two routes are added to `appinfo/routes.php` before the SPA catch-all:
- `timetable#sessions`: `GET /api/timetable/sessions`
- `timetable#upsert`: `POST /api/timetable/sessions/upsert`

## Database Changes
None. The schema is declared in the register descriptor and created by the existing register import.

## Nextcloud Integration
- Controllers: `TimetableController` (`sessions()` with `#[NoAdminRequired]` + `#[NoCSRFRequired]`; `upsert()` admin-only).
- Services: `TimetableSessionService`, resolving OpenRegister's `ObjectService` by FQCN through the container, the same duck-typed pattern as `TimelineController`.
- Events: `TimetableUpsertRequestedEvent`, `TimetableSessionsQueryEvent` extend `OCP\EventDispatcher\Event`; listeners implement `OCP\EventDispatcher\IEventListener` and are registered in `Application::register()` via `IRegistrationContext::registerEventListener()`.
- OCP: `IUserSession` (controller auth), `IEventDispatcher` (consumers), `Psr\Log\LoggerInterface`.

## Security Considerations
- `GET` is `#[NoAdminRequired]`; the guard is the RBAC read: the service passes `_rbac: true`, so OpenRegister applies the schema's `authorization.read` for the current user (gate 7: the RBAC read is the guard, as in `TimelineController`). An identity filter is mandatory, so no caller can dump the whole table in one call.
- `POST` has no `#[NoAdminRequired]`: Nextcloud's security middleware refuses non-admins before the method runs; it keeps `#[NoCSRFRequired]` off so a browser call needs the request token.
- The upsert copies only the declared fields from each row, so a caller cannot inject OpenRegister metadata (`@self`, `id`, `importedAt`).
- No pupil personal data: a lesson carries a group code, a teacher code and a room, as the integriq design recorded.

## File Structure
```
lib/
  Controller/TimetableController.php        (new)
  Event/TimetableUpsertRequestedEvent.php   (new)
  Event/TimetableSessionsQueryEvent.php     (new)
  Listener/TimetableUpsertRequestedListener.php (new)
  Listener/TimetableSessionsQueryListener.php   (new)
  Service/TimetableSessionService.php       (new: upsert and list rules)
  Service/TimetableSessionQuery.php         (new: one validated, bounded read)
  Service/TimetableSessionRows.php          (new: reads ObjectService rows, shapes sessions)
  AppInfo/Application.php                   (two listener registrations)
  Settings/planninq_register.json           (timetableSession schema, version bump)
  Settings/planninq_mock_register.json      (three demo sessions)
appinfo/routes.php                          (two routes)
l10n/en.json, l10n/nl.json                  (schema strings)
tests/unit/Service/TimetableSessionServiceTest.php
tests/unit/Service/TimetableSessionRowsTest.php
tests/unit/Support/InMemoryObjectService.php
tests/unit/Controller/TimetableControllerTest.php
tests/unit/Listener/TimetableUpsertRequestedListenerTest.php
tests/unit/Listener/TimetableSessionsQueryListenerTest.php
tests/unit/Settings/PlanninqRegisterSchemaTest.php (timetableSession assertions)
docs/features/school-timetable.md           (new)
```

## Seed Data
Demo data only (mock register, imported on demand from the setup walkthrough). A timetable belongs to a school, so no session is seeded into every install.

### Schema: `timetableSession`
| Field | Object 1 | Object 2 | Object 3 |
|-------|----------|----------|----------|
| slug | `timetable-wiskunde-3a` | `timetable-nederlands-3a` | `timetable-biologie-4b` |
| externalRef | `demo-zm-1001` | `demo-zm-1002` | `demo-zm-1003` |
| sourceSystem | `roster-zermelo` | `roster-zermelo` | `roster-zermelo` |
| subject | Wiskunde | Nederlands | Biologie |
| startsAt | 2026-09-28T09:00:00+02:00 | 2026-09-28T10:00:00+02:00 | 2026-09-28T11:15:00+02:00 |
| endsAt | 2026-09-28T09:50:00+02:00 | 2026-09-28T10:50:00+02:00 | 2026-09-28T12:05:00+02:00 |
| groupReference | 3a | 3a | 4b |
| teacherReference | JAN | PIE | KLA |
| roomReference | A1.12 | B2.04 | C0.03 |
| status | scheduled | scheduled | cancelled |

**Related items per object:** none; a lesson links to nothing inside planninq.

## Migration Plan
Deploy: the register version bump (0.5.0 to 0.6.0) makes the existing repair-step import create the schema. Rollback: revert; the schema stays unused.
