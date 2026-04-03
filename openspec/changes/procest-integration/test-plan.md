# Test Plan: procest-integration

## Test Cases

### TC-1: Case badge appears on project list item when caseReference is set
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-render-case-badge-in-project-list`
- **type**: functional
- **persona**: any authenticated Nextcloud user who is a project member
- **preconditions**: A project exists with `caseReference` set to a valid UUID; another project has `caseReference: null`
- **steps**: Navigate to the project list at `/apps/planix/projects`
- **expected result**: The project with `caseReference` shows a `CaseBadge` displaying "Case: {first 8 chars of UUID}"; the badge has an `aria-label` with the full UUID; the project without `caseReference` shows no badge
- **test command**: /test-functional

### TC-2: Case badge and case link appear in project detail header
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-render-case-badge-in-project-detail`
- **type**: functional
- **persona**: any authenticated Nextcloud user who is a project member
- **preconditions**: A project with `caseReference` set is open in the project board view
- **steps**: Navigate to `/projects/:id` for the project with a case reference
- **expected result**: `CaseBadge` is visible in the project header/detail area alongside the project title; "View in Procest" link is visible with href `/apps/procest/#/cases/{caseReference}`; link opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`)
- **test command**: /test-functional

### TC-3: No case badge or link when caseReference is null
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-no-badge-when-casereference-is-null`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A project with `caseReference: null` is open
- **steps**: Navigate to the project detail of a project without a case reference
- **expected result**: No `CaseBadge` appears in the project list item or project detail header; no "View in Procest" link appears
- **test command**: /test-functional

### TC-4: Set caseReference via project settings sidebar
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-edit-casereference-in-project-settings-sidebar`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: A project exists with `caseReference: null`; project settings sidebar is open
- **steps**: Open project settings sidebar; locate the "Case reference (Procest UUID)" text input; enter a valid UUID; click "Save"
- **expected result**: Input is visible with placeholder "e.g. 550e8400-e29b-41d4-a716-446655440000"; `updateProject` is called with the new `caseReference` value; `CaseBadge` and `CaseLink` appear in the project detail immediately without a page reload
- **test command**: /test-functional

### TC-5: Clear caseReference removes badge and link
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-clear-casereference`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: A project has a `caseReference` set; settings sidebar is open
- **steps**: Clear the "Case reference" input; click "Save"
- **expected result**: `updateProject` is called with `caseReference: null`; `CaseBadge` and `CaseLink` disappear from the project detail and project list immediately
- **test command**: /test-functional

### TC-6: Case link appears in task detail when zaakUuid is set
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-render-case-link-in-task-detail`
- **type**: functional
- **persona**: any authenticated Nextcloud user with task access
- **preconditions**: A task exists with `zaakUuid` set to a valid UUID
- **steps**: Navigate to the task detail view (`/tasks/:id`)
- **expected result**: Task detail panel shows a read-only "Case" field rendered as a `CaseLink` pointing to `/apps/procest/#/cases/{zaakUuid}`; link opens in a new tab
- **test command**: /test-functional

### TC-7: No case link in task detail when zaakUuid is null
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-no-case-link-when-uuid-is-null`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A task with `zaakUuid: null` is open in task detail
- **steps**: Navigate to the task detail
- **expected result**: No "Case" field appears in the task detail panel
- **test command**: /test-functional

### TC-8: Set zaakUuid via task edit form
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-edit-zaakuuid-in-task-edit-form`
- **type**: functional
- **persona**: any authenticated Nextcloud user with task edit access
- **preconditions**: An existing task with `zaakUuid: null`; the task edit form is open
- **steps**: Locate the "Case UUID (Procest)" field at the bottom of the edit form; enter a valid UUID; save
- **expected result**: Field is present in edit mode with placeholder "e.g. 550e8400-e29b-41d4-a716-446655440000"; `updateTask` is called with the new `zaakUuid`; "Case" link appears in the task detail immediately
- **test command**: /test-functional

### TC-9: zaakUuid field does NOT appear in task creation dialog
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-edit-zaakuuid-in-task-edit-form`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task creation dialog is open
- **steps**: Open `TaskCreateDialog`; inspect all form fields
- **expected result**: No "Case UUID" field is present in the creation dialog; the field only appears in the edit form
- **test command**: /test-functional

### TC-10: Clear zaakUuid removes case link from task detail
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-clear-zaakuuid`
- **type**: functional
- **persona**: any authenticated Nextcloud user with task edit access
- **preconditions**: A task with a set `zaakUuid` is being edited
- **steps**: Clear the "Case UUID" field in the task edit form; save
- **expected result**: `updateTask` is called with `zaakUuid: null`; the "Case" link disappears from the task detail
- **test command**: /test-functional

### TC-11: Bridge-disabled — case fields stored and displayed without Procest API calls
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-fields-displayed-when-bridge-is-not-configured`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Procest bridge is not configured (default MVP state); a project with `caseReference` and a task with `zaakUuid` exist
- **steps**: View the project list, project detail, and task detail; monitor network requests
- **expected result**: `CaseBadge` and `CaseLink` render normally; NO network request is made to any Procest endpoint; no Procest-related errors appear in the browser console
- **test command**: /test-functional

### TC-12: Task completion does not trigger Procest API call
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-task-completion-does-not-trigger-procest-call`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A task with `zaakUuid` set exists; Procest bridge is not configured
- **steps**: Change the task status to "done" using the inline status update
- **expected result**: Task status is updated in OpenRegister; no request is made to Procest; no error related to Procest is shown to the user
- **test command**: /test-functional

### TC-13: Dutch translations render for all case-related fields
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-dutch-translations-present`
- **type**: functional
- **persona**: any authenticated Nextcloud user with locale set to Dutch (`nl`)
- **preconditions**: Nextcloud locale is set to `nl`; a project with `caseReference` and a task with `zaakUuid` exist
- **steps**: View the project list, project detail, project settings sidebar, and task detail/edit form
- **expected result**: Case badge shows "Zaak: {short}"; "View in Procest" link shows "Bekijk in Procest"; project sidebar label shows "Zaakverwijzing (Procest UUID)"; task field label shows "Zaak-UUID (Procest)"; NO English strings appear as fallback for these fields
- **test command**: /test-functional

### TC-14: Case badge aria-label contains full UUID
- **spec_ref**: `openspec/changes/procest-integration/specs/procest-integration/spec.md#scenario-render-case-badge-in-project-list`
- **type**: accessibility
- **persona**: screen reader user
- **preconditions**: A project with `caseReference` set to a UUID exists in the project list
- **steps**: Inspect the `CaseBadge` element in the DOM
- **expected result**: `aria-label` attribute contains the full UUID string, not just the 8-char abbreviation shown visually
- **test command**: /test-accessibility

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| Case Badge Component [MVP] | Project list, project detail, null case (no badge), aria-label | TC-1, TC-2, TC-3, TC-14 |
| Case Link Component [MVP] | Project detail link, task detail link, null case (no link) | TC-2, TC-6, TC-7 |
| Case Reference Edit Field on Project [MVP] | Set, clear | TC-4, TC-5 |
| Case UUID Edit Field on Task [MVP] | Set (edit form), not in create dialog, clear | TC-8, TC-9, TC-10 |
| Bridge-Disabled Display Behavior [MVP] | No API calls, task completion no Procest call | TC-11, TC-12 |
| i18n for Case Fields [MVP] | Dutch translations for all case strings | TC-13 |

## Out of Scope

- Bridge API (`POST /planix/api/bridge/project`) — deferred to V1, not implemented in this change
- Task completion mirroring to Procest (`PATCH` to InterneTaak) — deferred to V1
- Bridge authentication (shared API token) — deferred to V1
- Admin setting `procest_base_url` — deferred to V1; case link URL is hardcoded to `/apps/procest/#/cases/{uuid}` in MVP
- Graceful degradation / retry queue when Procest is unreachable — deferred to V1
