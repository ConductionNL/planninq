# Procest Bridge Specification

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [procest-integration](../../) — MVP: case badge, case link, manual linking; V1: bridge API, task mirroring

## Purpose

Defines the integration contract between Planix and Procest (case management). MVP adds bridge fields (`caseReference` on Project, `zaakUuid` on Task) and surfaces them in the UI. V1 adds a dedicated bridge API for automated project creation and task-completion mirroring aligned with the VNG ZGW InterneTaak standard.

---

## ADDED Requirements — MVP

### Requirement REQ-PRC-001: Case Reference on Project [MVP]

The Project entity MUST support a `caseReference` field (string, Procest case UUID) that can be manually set via the project edit form.

#### Scenario: Project list shows case badge when caseReference is set
- GIVEN a project has `caseReference` set to a valid Procest case UUID
- WHEN a user views the project list
- THEN the project card MUST display a badge with text "Case: {caseNumber}" using `CnStatusBadge`
- AND the badge MUST be rendered using Nextcloud CSS variables for colour

#### Scenario: Project detail shows case link when caseReference is set
- GIVEN a project has `caseReference` set to a valid Procest case UUID
- WHEN a user opens the project detail
- THEN the detail MUST show a "Case" row with a hyperlink to the Procest case
- AND the link MUST open the Procest case in a new browser tab

#### Scenario: Manually set caseReference via project edit form
- GIVEN a user is editing a project
- WHEN they enter a valid Procest case UUID in the "Case reference" input and save
- THEN the system MUST persist `caseReference` on the project object via `ObjectService.saveObject()`
- AND the case badge MUST appear in the project list and detail immediately after save

#### Scenario: caseReference not set — no badge displayed
- GIVEN a project has no `caseReference` (null or empty string)
- WHEN the project card is rendered
- THEN the card MUST NOT display a case badge

---

### Requirement REQ-PRC-002: Task Case Link [MVP]

The Task entity MUST support a `zaakUuid` field (string, Procest case UUID) that can be manually set via the task edit form.

#### Scenario: Task detail shows case link when zaakUuid is set
- GIVEN a task has `zaakUuid` set to a Procest case UUID
- WHEN a user views the task detail
- THEN the detail MUST show a read-only "Case" row with a hyperlink to the Procest case
- AND the link MUST open the Procest case in a new browser tab

#### Scenario: Manually set zaakUuid via task edit form
- GIVEN a user is editing a task
- WHEN they enter a valid Procest case UUID in the "Case UUID" field and save
- THEN the system MUST persist `zaakUuid` on the task object
- AND the case link MUST appear in the task detail immediately after save

#### Scenario: zaakUuid not set — no case row displayed
- GIVEN a task has no `zaakUuid`
- WHEN the task detail is rendered
- THEN the task detail MUST NOT display a case link row

---

### Requirement REQ-PRC-003: Bridge Disabled — Fields Still Stored and Displayed [MVP]

When the Procest bridge is disabled (or not yet configured), bridge fields MUST still be stored and displayed without triggering any Procest API calls.

#### Scenario: Bridge disabled — caseReference and zaakUuid still visible
- GIVEN the Procest bridge toggle is disabled in admin settings
- AND a project has `caseReference` set and a task has `zaakUuid` set
- WHEN a user views the project or task
- THEN `caseReference` and `zaakUuid` MUST be displayed as read-only metadata
- AND Planix MUST NOT send any request to any Procest endpoint

#### Scenario: Bridge not configured — task completion does not call Procest
- GIVEN the Procest bridge is not configured (no base URL or token stored)
- WHEN a task with `zaakUuid` is marked done
- THEN Planix MUST update the task to `done` without errors
- AND Planix MUST NOT attempt any HTTP call to Procest

---

## ADDED Requirements — V1

### Requirement REQ-PRC-004: Bridge API — Create Project from Case [V1]

When the Procest bridge is enabled, the system MUST expose a bridge endpoint for Procest to create a Planix project linked to a case.

#### Scenario: Procest creates a Planix project via bridge API
- GIVEN the Procest bridge is enabled in Planix admin settings
- WHEN Procest sends `POST /planix/api/bridge/project` with a valid shared API token, `caseUuid`, and `caseNumber`
- THEN Planix MUST create a Project with `title = "Case {caseNumber}"` and `caseReference = caseUuid`
- AND the default column set MUST be applied to the new project
- AND the response MUST include the Planix project ID (for Procest to store as a back-reference)

#### Scenario: Bridge API rejects unauthenticated request
- GIVEN Procest (or any caller) sends a bridge request without a valid shared API token
- WHEN the request reaches `BridgeController`
- THEN Planix MUST return `401 Unauthorized`
- AND no project MUST be created

---

### Requirement REQ-PRC-005: Task Completion Mirroring [V1]

When a task with `zaakUuid` is marked done and the bridge is enabled, Planix MUST mirror the completion to Procest.

#### Scenario: Task completion triggers InterneTaak PATCH
- GIVEN a task has `zaakUuid` set
- AND the Procest bridge is enabled
- WHEN the task status changes to `done`
- THEN Planix MUST send a PATCH to the Procest InterneTaak endpoint for the linked case
- AND the PATCH payload MUST include `afhandelingsdatum` mapped from `task.completedAt`
- AND Planix MUST record the mirroring event in the task's audit trail (via `AuditTrailService`)

#### Scenario: Procest unreachable — graceful degradation
- GIVEN the Procest bridge is enabled
- AND a task with `zaakUuid` is marked done
- WHEN the Procest API is unreachable (timeout or 5xx response)
- THEN the task MUST still be updated to `done` in Planix (task update MUST NOT fail)
- AND Planix MUST log a warning server-side with the failed mirroring attempt details
- AND the user MUST NOT see any error message related to Procest

---

### Requirement REQ-PRC-006: Bridge API Authentication [V1]

Bridge endpoints MUST authenticate all incoming requests using a shared API token configured in admin settings.

#### Scenario: Valid token accepted
- GIVEN a valid bridge API token is configured in Planix admin settings
- WHEN Procest sends a bridge request with the correct `Authorization: Bearer {token}` header
- THEN `BridgeController` MUST accept the request and proceed with processing

#### Scenario: Invalid or missing token rejected
- GIVEN a bridge request arrives with an invalid or missing `Authorization` header
- WHEN `BridgeController` processes the request
- THEN Planix MUST return `401 Unauthorized`
- AND the token comparison MUST use constant-time string comparison to prevent timing attacks

---

## VNG InterneTaak Field Mapping

| Planix Task field | VNG InterneTaak field          |
|-------------------|--------------------------------|
| `title`           | `gevraagdeHandeling`           |
| `assignedTo`      | `toegewezenAanGebruikersnaam`  |
| `dueDate`         | `gevraagdeDatum`               |
| `status` (done)   | triggers `afhandelingsdatum`   |
| `completedAt`     | `afhandelingsdatum`            |

---

## Non-Functional Requirements

- **Security (ADR-005):** Bridge token stored via `IAppConfig` with `sensitive: true` — never returned to frontend, never logged. Constant-time comparison for token validation.
- **Graceful degradation:** Mirroring failures MUST be caught and logged — NEVER propagated to the task-update call chain.
- **Audit trail:** Every mirroring attempt (success or failure) MUST be recorded via `AuditTrailService`.
- **Accessibility (ADR-010):** Case badge text MUST be readable by screen readers; colour MUST NOT be the sole indicator (WCAG 1.4.1). Badge text supports Dutch and English translations (ADR-007).
- **Internationalisation (ADR-007):** All user-visible strings via `t('planix', '...')`. Dutch translations in `l10n/nl.json`.
- **Spec traceability (ADR-003):** Every new PHP class and public method MUST have `@spec openspec/changes/procest-integration/tasks.md#task-N` PHPDoc tag.

---

## Acceptance Criteria

**MVP:**
- [ ] `caseReference` field exists on Project schema; validated as UUID format
- [ ] `zaakUuid` field exists on Task schema; validated as UUID format
- [ ] `CnFormDialog` edit form exposes both fields for manual entry
- [ ] Projects with `caseReference` show a case badge (`CnStatusBadge`) in list and detail
- [ ] Task detail shows a read-only case link row when `zaakUuid` is set
- [ ] When bridge is disabled/unconfigured, fields are displayed but no Procest API calls are made

**V1:**
- [ ] `POST /planix/api/bridge/project` creates a project with correct `title` and `caseReference`
- [ ] Unauthenticated bridge requests return `401 Unauthorized`
- [ ] Task completion with `zaakUuid` set sends PATCH to Procest when bridge is enabled
- [ ] If Procest is unreachable, task update succeeds and a warning is logged
- [ ] Mirroring events appear in the task audit trail
- [ ] Bridge token stored as sensitive in `IAppConfig`; constant-time comparison enforced

## Notes

- The bridge controller is separate from the OpenRegister CRUD API — it is a minimal dedicated surface.
- MVP delivers value immediately without coordination with the Procest team.
- V1 mirroring requires agreement on the Procest InterneTaak PATCH endpoint spec before implementation.
- All bridge fields are additive to existing schemas — no migration required.
