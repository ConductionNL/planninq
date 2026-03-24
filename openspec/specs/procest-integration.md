# Procest Integration Specification

**Status**: idea

**Standards**: VNG ZGW InterneTaak, Schema.org Action, OpenRegister object references
**Feature tier**: MVP (caseReference field); V1 (full bridge API)

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

## Purpose

Planix is a sister app to Procest (case management). When a Procest case requires task tracking on a kanban board, Planix provides the board. Tasks created in the context of a case appear in a dedicated Planix project. Task completions can optionally mirror back to the case status in Procest. This spec defines the bridge between the two apps — the `caseReference` on Project, the `zaakUuid` on individual Tasks, and the API surface for cross-app communication.

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for cross-app relationship diagram.

**Bridge fields**:

| Entity | Field | Type | Description |
|--------|-------|------|-------------|
| Project | `caseReference` | string (Procest case UUID) | Links this project to a Procest case |
| Task | `zaakUuid` | string (Procest case UUID) | Links an individual task to a case (for tasks not in a case-project) |

**VNG InterneTaak mapping** (for tasks bridged from Procest):

| Planix Task field | VNG InterneTaak field |
|-------------------|-----------------------|
| `title` | `gevraagdeHandeling` |
| `assignedTo` | `toegewezenAanGebruikersnaam` |
| `dueDate` | `gevraagdeDatum` |
| `status` (done) | triggers `afhandelingsdatum` |
| `completedAt` | `afhandelingsdatum` |

## Requirements

### Requirement: Case Reference on Project [MVP]
The system MUST support a `caseReference` field on Project to link it to a Procest case.

#### Scenario: Project with case reference
- GIVEN a project has `caseReference` set to a valid Procest case UUID
- WHEN a user views the project list
- THEN the project MUST display a "Case: {caseNumber}" badge
- AND the project detail MUST show a link to the Procest case

#### Scenario: Individual task linked to a case
- GIVEN a task has `zaakUuid` set to a Procest case UUID
- WHEN the task is displayed
- THEN the task detail MUST show a link to the Procest case
- AND the task MUST appear in the Procest case's task list (via Procest's cross-app query)

### Requirement: Procest Bridge — Create Project from Case [V1]
When the Procest bridge is enabled, the system MUST allow Procest to create a Planix project for a case via API.

#### Scenario: Procest creates a Planix project
- GIVEN the Procest bridge is enabled in Planix admin settings
- WHEN Procest sends a POST to `/planix/api/bridge/project` with case UUID and case number
- THEN Planix MUST create a project with `title = "Case {caseNumber}"` and `caseReference = caseUuid`
- AND the default column set MUST be applied
- AND the project ID MUST be returned in the response for Procest to store as a back-reference

#### Scenario: Task completion mirrors to Procest
- GIVEN a task has `zaakUuid` set and the Procest bridge is enabled
- WHEN the task status changes to `done`
- THEN Planix MUST send a PATCH to the Procest case tasks API to mark the InterneTaak as afgehandeld
- AND Planix MUST log the mirroring event in the task's audit trail

#### Scenario: Procest bridge disabled
- GIVEN the Procest bridge is disabled in admin settings
- WHEN a task with `zaakUuid` is marked done
- THEN Planix MUST NOT send any request to Procest
- AND the `caseReference` field MUST still be stored and displayed in the UI

#### Scenario: Bridge API authentication
- GIVEN Procest sends a bridge request to Planix
- THEN Planix MUST authenticate the request using a shared API token configured in admin settings
- AND unauthenticated bridge requests MUST return 401 Unauthorized

## User Stories

- As a case handler in Procest, I want a Planix kanban board automatically created for my case so that I can track implementation tasks visually
- As a team member, I want tasks linked to a case to show the case reference so that I know the business context
- As a case manager, I want task completions in Planix to mirror back to the case status so that Procest stays up to date
- As a Planix user, I want to see a link to the Procest case from a task or project so that I can navigate to the case without searching

## Acceptance Criteria

- [ ] `caseReference` field exists on Project entity and is stored/displayed correctly
- [ ] `zaakUuid` field exists on Task entity
- [ ] Projects with `caseReference` show a case badge in the project list and detail
- [ ] Task detail shows a link to the Procest case when `zaakUuid` is set
- [ ] Bridge toggle in admin settings enables/disables cross-app API calls
- [ ] (V1) Procest can create a Planix project via the bridge API (POST `/planix/api/bridge/project`)
- [ ] (V1) Task completion triggers a mirroring update to Procest when bridge is enabled
- [ ] (V1) Bridge API authenticates via shared token
- [ ] Bridge errors are logged and do not crash the Planix task update flow (graceful degradation)

## Notes

- The Procest bridge API (`/planix/api/bridge/`) is a minimal, dedicated controller — NOT part of the main OpenRegister CRUD API.
- In MVP, only the `caseReference` and `zaakUuid` fields are implemented (no API bridge). The fields allow manual linking via task/project edit forms.
- The full bridge API (V1) requires coordination with the Procest team on the InterneTaak endpoint specification.
- Graceful degradation is critical: if Procest is unreachable when a task is marked done, Planix MUST queue the mirroring update and retry (or log a warning) rather than failing the task update.
