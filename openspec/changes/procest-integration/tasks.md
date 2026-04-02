# Tasks: procest-integration

**Change ID:** procest-integration
**Status:** draft
**Created:** 2026-04-02

---

## Tasks

### Group A: Case Badge Component

#### Task 1: Create `src/components/CaseBadge.vue`

- **spec_ref:** Delta spec — Requirement: Case Badge Component
- **files:** `src/components/CaseBadge.vue`
- **acceptance_criteria:**
  - [ ] Component accepts a `caseReference` prop (String, nullable)
  - [ ] Renders `NcChip` with text `Case: {first 8 chars of caseReference}`
  - [ ] Has `aria-label` containing the full UUID
  - [ ] Renders nothing (`v-if`) when `caseReference` is null or empty
  - [ ] Uses `t('planix', ...)` for all display text — no hardcoded strings
- [ ] implement
- [ ] test

#### Task 2: Render `CaseBadge` in project list item

- **spec_ref:** Delta spec — Requirement: Case Badge Component — Scenario: Render case badge in project list
- **files:** `src/views/ProjectList.vue`, `src/components/ProjectListItem.vue`
- **acceptance_criteria:**
  - [ ] `ProjectListItem` receives `caseReference` from the project object
  - [ ] `CaseBadge` is rendered inside the list item when `caseReference` is non-null
  - [ ] No badge renders when `caseReference` is null
- [ ] implement
- [ ] test

#### Task 3: Render `CaseBadge` in project detail

- **spec_ref:** Delta spec — Requirement: Case Badge Component — Scenario: Render case badge in project detail
- **files:** `src/views/ProjectBoard.vue`
- **acceptance_criteria:**
  - [ ] `CaseBadge` is rendered in the project header or detail area when `activeProject.caseReference` is set
  - [ ] Badge is visible alongside the project title
  - [ ] No badge renders when `caseReference` is null
- [ ] implement
- [ ] test

---

### Group B: Case Link Component

#### Task 4: Create `src/components/CaseLink.vue`

- **spec_ref:** Delta spec — Requirement: Case Link Component
- **files:** `src/components/CaseLink.vue`
- **acceptance_criteria:**
  - [ ] Component accepts a `uuid` prop (String, nullable) and a `label` prop (String, optional)
  - [ ] Renders an `<a>` tag with `href="/apps/procest/#/cases/{uuid}"`
  - [ ] Link opens in new tab: `target="_blank"` with `rel="noopener noreferrer"`
  - [ ] Includes an open-in-new icon (MDI `OpenInNew`)
  - [ ] Default label is `t('planix', 'View in Procest')` when `label` prop is not provided
  - [ ] Renders nothing when `uuid` is null or empty
- [ ] implement
- [ ] test

---

### Group C: Project Edit Form

#### Task 5: Add `caseReference` field to project settings sidebar

- **spec_ref:** Delta spec — Requirement: Case Reference Edit Field on Project
- **files:** `src/components/ProjectSettingsSidebar.vue`
- **acceptance_criteria:**
  - [ ] "Case reference (Procest UUID)" `NcTextField` added at the bottom of the Details section
  - [ ] Field pre-populated with current `caseReference` value (empty if null)
  - [ ] Placeholder text: `e.g. 550e8400-e29b-41d4-a716-446655440000`
  - [ ] Helper text: `t('planix', 'Link this project to a Procest case by entering the case UUID.')`
  - [ ] On save, `updateProject` is called with updated `caseReference` value
  - [ ] Clearing the field and saving sets `caseReference` to `null`
  - [ ] `CaseBadge` and `CaseLink` in the project detail update immediately after save (reactive)
- [ ] implement
- [ ] test

---

### Group D: Project Detail Case Link

#### Task 6: Render `CaseLink` in project detail

- **spec_ref:** Delta spec — Requirement: Case Link Component — Scenario: Render case link in project detail
- **files:** `src/views/ProjectBoard.vue`
- **acceptance_criteria:**
  - [ ] `CaseLink` renders in the project detail area when `activeProject.caseReference` is set
  - [ ] Link href is `/apps/procest/#/cases/{caseReference}`
  - [ ] Link opens in a new tab
  - [ ] No link renders when `caseReference` is null
- [ ] implement
- [ ] test

---

### Group E: Task Edit Form

#### Task 7: Add `zaakUuid` field to task edit form

- **spec_ref:** Delta spec — Requirement: Case UUID Edit Field on Task
- **files:** `src/components/dialogs/TaskEditDialog.vue`
- **acceptance_criteria:**
  - [ ] "Case UUID (Procest)" `NcTextField` added at the bottom of the task edit form
  - [ ] Field is only present in edit mode (not in task creation dialog)
  - [ ] Field pre-populated with current `zaakUuid` value (empty if null)
  - [ ] Placeholder text: `e.g. 550e8400-e29b-41d4-a716-446655440000`
  - [ ] Helper text: `t('planix', 'Link this task to a Procest case by entering the case UUID.')`
  - [ ] On save, `updateTask` is called with updated `zaakUuid` value
  - [ ] Clearing the field and saving sets `zaakUuid` to `null`
- [ ] implement
- [ ] test

---

### Group F: Task Detail Case Link

#### Task 8: Render read-only case link in task detail

- **spec_ref:** Delta spec — Requirement: Case Link Component — Scenario: Render case link in task detail; Base spec — Requirement: Task Case Link
- **files:** `src/components/TaskDetail.vue`
- **acceptance_criteria:**
  - [ ] Task detail shows a read-only "Case" field when `task.zaakUuid` is set
  - [ ] The field value renders as a `CaseLink` pointing to `/apps/procest/#/cases/{zaakUuid}`
  - [ ] Field label uses `t('planix', 'Case')`
  - [ ] No field renders when `zaakUuid` is null
- [ ] implement
- [ ] test

---

### Group G: Bridge-Disabled Behavior

#### Task 9: Verify no Procest API calls on task completion

- **spec_ref:** Delta spec — Requirement: Bridge-Disabled Display Behavior
- **files:** (no code change required — verification only)
- **acceptance_criteria:**
  - [ ] Confirm that marking a task with `zaakUuid` as `done` makes no HTTP requests to any Procest endpoint
  - [ ] Confirm that `caseReference` and `zaakUuid` are still stored and displayed when bridge is not configured
  - [ ] Add a code comment in `TaskEditDialog.vue` and any task status change logic referencing that V1 bridge calls are intentionally absent
- [ ] implement
- [ ] test

---

### Group H: i18n

#### Task 10: Add English i18n strings

- **spec_ref:** Delta spec — Requirement: i18n for Case Fields
- **files:** `l10n/en.json`
- **acceptance_criteria:**
  - [ ] All strings from the i18n inventory in `design.md` are present in `l10n/en.json`
  - [ ] Keys match the exact `t('planix', '...')` call strings used in the components
  - [ ] Parameterized strings use the correct placeholder format (`{short}`, `{uuid}`)
- [ ] implement
- [ ] test

#### Task 11: Add Dutch translations

- **spec_ref:** Delta spec — Requirement: i18n for Case Fields — Scenario: Dutch translations present
- **files:** `l10n/nl.json`
- **acceptance_criteria:**
  - [ ] All strings from Task 10 are translated into Dutch in `l10n/nl.json`
  - [ ] Translations match the Dutch strings specified in the delta spec i18n table
  - [ ] No English placeholders used — all translations are human-readable Dutch
- [ ] implement
- [ ] test

---

## Verification

Manual smoke tests to confirm the change works end-to-end:

- [ ] **V1: Project list badge** — Navigate to `/projects`. Confirm a project with `caseReference` set shows the `CaseBadge`. Confirm a project without `caseReference` shows no badge.
- [ ] **V2: Project detail badge and link** — Open a project with `caseReference` set. Confirm `CaseBadge` and `CaseLink` appear in the project detail. Click the link. Confirm it opens `/apps/procest/#/cases/{uuid}` in a new tab.
- [ ] **V3: Set caseReference via sidebar** — Open project settings. Enter a UUID in the Case reference field. Save. Confirm badge and link appear immediately in the project detail and the list item. Clear the field. Save. Confirm badge and link disappear.
- [ ] **V4: Task detail case link** — Open a task with `zaakUuid` set. Confirm the "Case" field with a link appears in the task detail panel. Confirm the link opens the correct Procest URL.
- [ ] **V5: Set zaakUuid via task edit** — Edit a task. Enter a UUID in the Case UUID field. Save. Confirm the case link appears in the task detail. Clear the field. Save. Confirm the link disappears.
- [ ] **V6: No bridge calls on task completion** — Mark a task with `zaakUuid` as done. Use browser devtools network tab to confirm no requests to any Procest endpoint are made.

---

## Tests (ADR-009)

- [ ] **Unit: CaseBadge** — renders badge when `caseReference` is set; renders nothing when null; uses truncated UUID in badge text; full UUID in aria-label.
- [ ] **Unit: CaseLink** — renders link with correct href; opens in new tab; renders nothing when `uuid` is null; defaults to "View in Procest" label.
- [ ] **Unit: ProjectSettingsSidebar** — Case reference field is present; calls `updateProject` with new value on save; calls `updateProject` with null when field is cleared.
- [ ] **Unit: TaskEditDialog** — Case UUID field is absent in create mode; present in edit mode; calls `updateTask` with new value on save.
- [ ] **Unit: TaskDetail** — Case field renders when `zaakUuid` is set; does not render when null.

---

## Documentation (ADR-010)

- [ ] Update `docs/features/procest-integration.md` (or create if absent) to document: case badge behavior, case link URL pattern, how to manually link a project or task to a Procest case.
- [ ] Note in documentation that V1 bridge API is not yet implemented (documented in spec, not in this change).

---

## i18n (ADR-005)

- [ ] All new strings go through `t('planix', '...')` — no hardcoded English strings in component templates.
- [ ] `l10n/en.json` updated with all new keys (Task 10).
- [ ] `l10n/nl.json` updated with all Dutch translations (Task 11).
- [ ] Parameterized strings use named placeholders (`{short}`, `{uuid}`) — not positional.
