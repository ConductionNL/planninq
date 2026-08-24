# Procest Integration Specification

**Status**: in-progress

**Standards**: VNG ZGW InterneTaak, Schema.org Action, OpenRegister object references
**Feature tier**: MVP (caseReference field); V1 (full bridge API)

**OpenSpec changes:**
- [procest-integration](../changes/procest-integration/) — MVP: case badge, case link, manual linking via edit forms

## Purpose

Planninq is a sister app to Procest (case management). When a Procest case requires task tracking on a kanban board, Planninq provides the board. Tasks created in the context of a case appear in a dedicated Planninq project. Task completions can optionally mirror back to the case status in Procest. This spec defines the bridge between the two apps — the `caseReference` on Project, the `zaakUuid` on individual Tasks, and the API surface for cross-app communication.

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for cross-app relationship diagram.

**Bridge fields**:

| Entity | Field | Type | Description |
|--------|-------|------|-------------|
| Project | `caseReference` | string (Procest case UUID) | Links this project to a Procest case |
| Task | `zaakUuid` | string (Procest case UUID) | Links an individual task to a case (for tasks not in a case-project) |

**VNG InterneTaak mapping** (for tasks bridged from Procest):

| Planninq Task field | VNG InterneTaak field |
|-------------------|-----------------------|
| `title` | `gevraagdeHandeling` |
| `assignedTo` | `toegewezenAanGebruikersnaam` |
| `dueDate` | `gevraagdeDatum` |
| `status` (done) | triggers `afhandelingsdatum` |
| `completedAt` | `afhandelingsdatum` |

## Requirements

---

## MVP Requirements

### Requirement: Case Reference on Project [MVP]
The system MUST support a `caseReference` field on Project to link it to a Procest case.

#### Scenario: Project with case reference
- GIVEN a project has `caseReference` set to a valid Procest case UUID
- WHEN a user views the project list
- THEN the project MUST display a "Case: {caseNumber}" badge
- AND the project detail MUST show a link to the Procest case

#### Scenario: Manually set caseReference via project edit form
- GIVEN a user is editing a project
- WHEN they enter a Procest case UUID in the "Case reference" field and save
- THEN the system MUST store `caseReference` on the project
- AND the case badge MUST appear in the project list and detail immediately

### Requirement: Task Case Link [MVP]
The system MUST support a `zaakUuid` field on Task to link an individual task to a Procest case.

#### Scenario: Individual task linked to a case
- GIVEN a task has `zaakUuid` set to a Procest case UUID
- WHEN the task is displayed
- THEN the task detail MUST show a read-only "Case" field with a link to the Procest case
- AND the task MUST appear in the Procest case's task list (via Procest's cross-app query)

#### Scenario: Manually set zaakUuid via task edit form
- GIVEN a user is editing a task
- WHEN they enter a Procest case UUID in the "Case UUID" field and save
- THEN the system MUST store `zaakUuid` on the task
- AND the case link MUST appear in the task detail immediately

#### Scenario: Bridge disabled — fields still displayed
- GIVEN the Procest bridge toggle is disabled in admin settings (or not yet configured)
- WHEN a task with `zaakUuid` is marked done
- THEN Planninq MUST NOT send any request to Procest
- AND the `caseReference` and `zaakUuid` fields MUST still be stored and displayed in the UI as read-only metadata

---

## V1 Requirements

### Requirement: Procest Bridge — Create Project from Case [V1]
When the Procest bridge is enabled, the system MUST allow Procest to create a Planninq project for a case via API.

#### Scenario: Procest creates a Planninq project
- GIVEN the Procest bridge is enabled in Planninq admin settings
- WHEN Procest sends a POST to `/planninq/api/bridge/project` with case UUID and case number
- THEN Planninq MUST create a project with `title = "Case {caseNumber}"` and `caseReference = caseUuid`
- AND the default column set MUST be applied
- AND the project ID MUST be returned in the response for Procest to store as a back-reference

#### Scenario: Task completion mirrors to Procest
- GIVEN a task has `zaakUuid` set and the Procest bridge is enabled
- WHEN the task status changes to `done`
- THEN Planninq MUST send a PATCH to the Procest case tasks API to mark the InterneTaak as afgehandeld
- AND Planninq MUST log the mirroring event in the task's audit trail

#### Scenario: Procest unreachable — graceful degradation
- GIVEN the Procest bridge is enabled and a task with `zaakUuid` is marked done
- WHEN the Procest API is unreachable
- THEN the task MUST still be updated to `done` in Planninq (task update MUST NOT fail)
- AND Planninq MUST log a warning with the failed mirroring attempt
- AND the user MUST NOT see an error related to Procest

#### Scenario: Bridge API authentication
- GIVEN Procest sends a bridge request to Planninq
- THEN Planninq MUST authenticate the request using a shared API token configured in admin settings
- AND unauthenticated bridge requests MUST return 401 Unauthorized

## User Stories

- As a case handler in Procest, I want a Planninq kanban board automatically created for my case so that I can track implementation tasks visually (V1)
- As a team member, I want tasks linked to a case to show the case reference so that I know the business context
- As a case manager, I want task completions in Planninq to mirror back to the case status so that Procest stays up to date (V1)
- As a Planninq user, I want to see a link to the Procest case from a task or project so that I can navigate to the case without searching
- As a user, I want to manually link a task or project to a Procest case so that I can bridge cases without the full API bridge enabled
- As a user, I want Planninq to remain fully functional even when Procest is unreachable so that my task updates are never blocked

## Acceptance Criteria

**MVP:**
- [ ] `caseReference` field exists on Project entity; can be set manually via project edit form
- [ ] `zaakUuid` field exists on Task entity; can be set manually via task edit form
- [ ] Projects with `caseReference` show a case badge in the project list and detail
- [ ] Task detail shows a read-only case link when `zaakUuid` is set
- [ ] When bridge is disabled, `caseReference` and `zaakUuid` fields are still stored and displayed; no Procest API calls are made

**V1:**
- [ ] Procest can create a Planninq project via the bridge API (POST `/planninq/api/bridge/project`)
- [ ] Task completion with `zaakUuid` set triggers a mirroring PATCH to Procest when bridge is enabled
- [ ] If Procest is unreachable during task completion, the task update succeeds and a warning is logged (graceful degradation)
- [ ] Bridge API authenticates via shared token; unauthenticated requests return 401

## Notes

- The Procest bridge API (`/planninq/api/bridge/`) is a minimal, dedicated controller — NOT part of the main OpenRegister CRUD API.
- In MVP, only the `caseReference` and `zaakUuid` fields are implemented (no API bridge). The fields allow manual linking via task/project edit forms.
- The full bridge API (V1) requires coordination with the Procest team on the InterneTaak endpoint specification.
- Graceful degradation is critical: if Procest is unreachable when a task is marked done, Planninq MUST queue the mirroring update and retry (or log a warning) rather than failing the task update.
