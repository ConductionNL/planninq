# Delta Spec: procest-integration

**Capability:** procest-integration
**Change ID:** procest-integration
**Delta type:** implementation (MVP)
**Base spec:** [openspec/specs/procest-integration.md](../../../../../specs/procest-integration.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the MVP display layer for Procest integration. The base spec (`openspec/specs/procest-integration.md`) defines all business requirements, scenarios, user stories, and acceptance criteria for both MVP and V1. The delta below documents:

1. UI component patterns required for the case badge and case link.
2. Edit form field requirements for `caseReference` (Project) and `zaakUuid` (Task).
3. Bridge-disabled display behavior (explicit: no Procest API calls in MVP).
4. i18n requirements for all new strings.
5. Case link URL pattern used in MVP.

All base spec MVP requirements are implemented as-is. No base spec requirement is modified or removed. V1 requirements are explicitly deferred.

---

## ADDED Requirements

### Requirement: Case Badge Component [MVP]

A reusable `CaseBadge` component MUST be created to display a visual indicator when a project has a `caseReference` set.

#### Scenario: Render case badge in project list
- GIVEN a project has `caseReference` set to a non-null UUID string
- WHEN the project is displayed in the project list
- THEN a `CaseBadge` MUST appear on the project list item
- AND the badge MUST display text in the format `Case: {first 8 chars of UUID}`
- AND the badge MUST have an `aria-label` containing the full UUID for accessibility

#### Scenario: No badge when caseReference is null
- GIVEN a project has `caseReference` set to null or empty string
- WHEN the project is displayed in the project list
- THEN NO case badge MUST appear on the project list item

#### Scenario: Render case badge in project detail
- GIVEN a project has `caseReference` set
- WHEN the user views the project detail (ProjectBoard)
- THEN the `CaseBadge` MUST appear in the project header or detail area
- AND it MUST be visible alongside the project title

---

### Requirement: Case Link Component [MVP]

A reusable `CaseLink` component MUST be created to render a navigable link to a Procest case.

#### Scenario: Render case link in project detail
- GIVEN a project has `caseReference` set to a non-null UUID
- WHEN the user views the project detail
- THEN a `CaseLink` MUST appear with label "View in Procest" (or equivalent i18n string)
- AND the link href MUST be `/apps/procest/#/cases/{caseReference}`
- AND the link MUST open in a new tab (`target="_blank"`) with `rel="noopener noreferrer"`

#### Scenario: Render case link in task detail
- GIVEN a task has `zaakUuid` set to a non-null UUID
- WHEN the user views the task detail panel
- THEN the task detail MUST display a read-only "Case" field
- AND the field value MUST render as a `CaseLink` pointing to `/apps/procest/#/cases/{zaakUuid}`

#### Scenario: No case link when UUID is null
- GIVEN a project or task has no `caseReference` / `zaakUuid` set
- WHEN the user views the project detail or task detail
- THEN NO case link MUST appear

---

### Requirement: Case Reference Edit Field on Project [MVP]

The project settings sidebar MUST expose a text input for setting `caseReference`.

#### Scenario: Edit caseReference in project settings sidebar
- GIVEN the user opens the project settings sidebar for a project
- WHEN they view the Details section
- THEN a "Case reference (Procest UUID)" text input MUST be visible at the bottom of the Details section
- AND the field MUST be pre-populated with the current `caseReference` value (or empty if null)
- AND the field MUST show a placeholder: `e.g. 550e8400-e29b-41d4-a716-446655440000`

#### Scenario: Save caseReference
- GIVEN the user has entered a UUID in the Case reference field
- WHEN they click "Save" in the sidebar
- THEN `updateProject` MUST be called with the new `caseReference` value
- AND the `CaseBadge` and `CaseLink` in the project detail MUST update immediately without a page reload

#### Scenario: Clear caseReference
- GIVEN the user clears the Case reference field (empties the input)
- WHEN they click "Save"
- THEN `updateProject` MUST be called with `caseReference: null`
- AND the `CaseBadge` and `CaseLink` MUST disappear from the project detail and list

---

### Requirement: Case UUID Edit Field on Task [MVP]

The task edit form MUST expose a text input for setting `zaakUuid`.

#### Scenario: Edit zaakUuid in task edit form
- GIVEN the user opens the task edit form for an existing task
- WHEN they view the form
- THEN a "Case UUID (Procest)" text input MUST be visible at the bottom of the form
- AND the field MUST be pre-populated with the current `zaakUuid` value (or empty if null)
- AND the field MUST show a placeholder: `e.g. 550e8400-e29b-41d4-a716-446655440000`
- AND the field MUST NOT appear in the task creation dialog (edit mode only)

#### Scenario: Save zaakUuid
- GIVEN the user has entered a UUID in the Case UUID field
- WHEN they save the task edit form
- THEN `updateTask` MUST be called with the new `zaakUuid` value
- AND the "Case" link MUST appear in the task detail immediately

#### Scenario: Clear zaakUuid
- GIVEN the user clears the Case UUID field
- WHEN they save the task edit form
- THEN `updateTask` MUST be called with `zaakUuid: null`
- AND the "Case" link MUST disappear from the task detail

---

### Requirement: Bridge-Disabled Display Behavior [MVP]

When the Procest bridge is not configured (the default state in MVP), `caseReference` and `zaakUuid` fields MUST still be stored and displayed. No Procest API calls MUST be made.

#### Scenario: Fields displayed when bridge is not configured
- GIVEN the Procest bridge toggle is absent or disabled (default MVP state)
- WHEN a project with `caseReference` or a task with `zaakUuid` is displayed
- THEN the `CaseBadge` and `CaseLink` MUST render as normal
- AND no API call to any Procest endpoint MUST be made by Planix

#### Scenario: Task completion does not trigger Procest call
- GIVEN a task has `zaakUuid` set and the bridge is not configured
- WHEN the task status is changed to `done`
- THEN Planix MUST update the task status in OpenRegister
- AND Planix MUST NOT send any request to Procest
- AND no error related to Procest MUST be shown to the user

---

### Requirement: i18n for Case Fields [MVP]

All user-visible strings introduced by this change MUST be covered by i18n in both `en` and `nl`.

#### Scenario: Case badge text is translatable
- GIVEN the UI renders a `CaseBadge`
- THEN the badge text MUST use `t('planix', 'Case: {short}', { short })` — not a hardcoded string

#### Scenario: Dutch translations present
- GIVEN the user's Nextcloud locale is set to `nl`
- WHEN any case badge, case link, or case field label is rendered
- THEN the Dutch translation MUST be shown, not the English fallback

**Required Dutch translations:**

| English | Dutch |
|---------|-------|
| `Case: {short}` | `Zaak: {short}` |
| `View in Procest` | `Bekijk in Procest` |
| `Case reference (Procest UUID)` | `Zaakverwijzing (Procest UUID)` |
| `Link this project to a Procest case by entering the case UUID.` | `Koppel dit project aan een Procest-zaak door het zaak-UUID in te voeren.` |
| `Case` | `Zaak` |
| `Case UUID (Procest)` | `Zaak-UUID (Procest)` |
| `Link this task to a Procest case by entering the case UUID.` | `Koppel deze taak aan een Procest-zaak door het zaak-UUID in te voeren.` |
| `Linked to Procest case {uuid}` | `Gekoppeld aan Procest-zaak {uuid}` |

---

## Deferred to V1

The following requirements from the base spec are explicitly deferred and NOT implemented by this change:

- Bridge API: `POST /planix/api/bridge/project`
- Task completion mirroring: `PATCH` to Procest InterneTaak endpoint
- Bridge authentication: shared API token
- Graceful degradation / retry queue for Procest unreachable scenarios
- Admin setting `procest_base_url` (case link URL is hardcoded to `/apps/procest/#/cases/{uuid}` in MVP)
