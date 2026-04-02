# Design: procest-integration

**Change ID:** procest-integration
**Status:** draft
**Created:** 2026-04-02

---

## Context

The `register-schemas` change defined `caseReference` (on Project) and `zaakUuid` (on Task) as plain string fields in the OpenRegister schemas. The `projects` and `tasks` changes built the views and edit forms — but intentionally left the Procest bridge fields out of scope. This change adds the display layer: badges, links, and edit form inputs.

Planix is a thin client: all data is stored in OpenRegister and retrieved via the standard `useObjectStore` API. No new PHP controllers or database tables are introduced. The Procest app is a separate Nextcloud app; cross-app navigation uses the standard Nextcloud app URL pattern.

---

## Goals

- Render a case badge on projects that have `caseReference` set (list + detail).
- Render a "View in Procest" link in project detail when `caseReference` is set.
- Allow users to manually set/clear `caseReference` via the project settings sidebar.
- Render a read-only case link in task detail when `zaakUuid` is set.
- Allow users to manually set/clear `zaakUuid` via the task edit form.
- Ensure no Procest API calls are made when the bridge is disabled (MVP default).
- Full i18n coverage (en + nl).

## Non-Goals

- V1 bridge API (POST /planix/api/bridge/project).
- Task completion mirroring to Procest.
- Bridge toggle admin setting (not needed for MVP display-only behavior).
- Procest API calls of any kind.
- Admin-configurable Procest base URL (hardcoded for MVP; V1 adds `procest_base_url` admin setting).
- Validation that the entered UUID resolves to a real Procest case.

---

## Decisions

### Decision 1: Case link URL pattern — hardcoded for MVP

**Options considered:**
1. Admin setting `procest_base_url` configurable in Planix admin settings (V1 approach).
2. Hardcode to `/apps/procest/#/cases/{uuid}` using the standard Nextcloud app URL pattern (chosen for MVP).

**Rationale:** The standard Nextcloud relative URL `/apps/procest/#/cases/{uuid}` works for any Nextcloud installation that has Procest installed. Parameterizing the base URL adds an admin setting (with its own UI, PHP config, and validation) for no practical benefit at MVP: Procest and Planix always share the same Nextcloud instance. The V1 admin setting for `procest_base_url` is documented in the spec for future implementation.

**Consequence:** If Procest is not installed, the link navigates to a 404. This is acceptable for MVP — the link only appears when a user manually sets a UUID, implying they know Procest is installed.

---

### Decision 2: Two reusable components — `CaseBadge` and `CaseLink`

**Options considered:**
1. Inline the badge and link HTML in each view component.
2. Create two shared components: `CaseBadge.vue` (status chip with case number) and `CaseLink.vue` (anchor link to Procest case) (chosen).

**Rationale:** The badge appears in two places (project list item and project detail) and the link appears in two places (project detail and task detail). Shared components avoid duplication, ensure consistent styling and ARIA attributes, and make it easy to update the URL pattern when V1 adds the configurable base URL.

`CaseBadge` accepts a `caseReference` prop (the UUID string) and displays "Case: {caseReference}" using `NcChip` or a styled `<span>`. Since `caseReference` is a UUID (not a human-readable case number in MVP), the badge displays the UUID or a truncated form (first 8 characters). V1 may enrich this with a resolved case number from the Procest API.

`CaseLink` accepts a `uuid` prop and a `label` prop (default: "View in Procest"). It renders an `<a>` tag with `href="/apps/procest/#/cases/{uuid}"` and an external-link icon.

---

### Decision 3: Edit form fields — plain text inputs (no UUID validation)

**Options considered:**
1. UUID-validated input with inline error if format is invalid.
2. Plain `NcTextField` input with UUID hint text (chosen for MVP).

**Rationale:** At MVP, users are expected to copy-paste UUIDs from Procest. Adding UUID regex validation gives marginal benefit (the field will simply display but not resolve if the UUID is wrong) and adds complexity. A placeholder of `e.g. 550e8400-e29b-41d4-a716-446655440000` guides the user. V1 may add live validation against the Procest API.

---

### Decision 4: Case reference — dedicated "Link to case" action button

**Options considered:**
1. Inline editable text field in the sidebar Details section.
2. Dedicated "Link to case" action button that opens a small dialog (chosen).

**Rationale:** Case linking is an intentional, infrequent action — not something that belongs alongside everyday fields like title and description. A dedicated button in the sidebar actions (or project detail toolbar) makes the action more discoverable and prevents accidental edits. The dialog shows a UUID input field with paste hint and a "Link" / "Unlink" button. When linked, the sidebar shows the CaseBadge + CaseLink as read-only — the action button changes to "Unlink from case".

---

### Decision 5: Task case UUID field placement in task edit form

The "Case UUID" field is placed at the bottom of the task edit form, after the existing fields (title, description, assignee, due date, column). It is labeled "Case UUID (Procest)" with helper text "Link this task to a Procest case by entering the case UUID."

The field is not shown in the task creation dialog — case linking is an enrichment action performed after a task exists. It is only shown in the task edit form (edit mode on an existing task).

---

### Decision 6: Bridge-disabled behavior — display only, no guard

No bridge toggle is checked before displaying the fields. `caseReference` and `zaakUuid` are plain string fields on their respective objects; if they are populated, the badge and link render. If they are not populated, nothing renders. The "bridge disabled" scenario in the spec means no Procest API calls — this is trivially satisfied because MVP makes no Procest API calls at all.

A future bridge toggle admin setting (V1) would gate the mirroring behavior, not the display behavior.

---

## Component Architecture

```
src/
  components/
    CaseBadge.vue                    # New — NcChip-based badge: "Case: {uuid[:8]}"
    CaseLink.vue                     # New — <a> link to /apps/procest/#/cases/{uuid}
    ProjectSettingsSidebar.vue       # Modified — add caseReference NcTextField in Details
    TaskDetail.vue                   # Modified — render CaseLink when zaakUuid is set
    dialogs/
      TaskEditDialog.vue             # Modified — add zaakUuid NcTextField
  views/
    ProjectList.vue                  # Modified — render CaseBadge on ProjectListItem
    ProjectBoard.vue                 # Modified — render CaseBadge + CaseLink in header area
  components/
    ProjectListItem.vue              # Modified — accept and render CaseBadge
```

---

## CaseBadge Component

```vue
<!-- src/components/CaseBadge.vue -->
<template>
  <NcChip
    v-if="caseReference"
    :text="badgeText"
    :aria-label="t('planix', 'Linked to Procest case {uuid}', { uuid: caseReference })"
    class="case-badge"
    no-close />
</template>

<script setup>
import { computed } from 'vue'
import { NcChip } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'

const props = defineProps({
  caseReference: { type: String, default: null },
})

const badgeText = computed(() =>
  props.caseReference
    ? t('planix', 'Case: {short}', { short: props.caseReference.slice(0, 8) })
    : ''
)
</script>
```

---

## CaseLink Component

```vue
<!-- src/components/CaseLink.vue -->
<template>
  <a
    v-if="uuid"
    :href="caseUrl"
    target="_blank"
    rel="noopener noreferrer"
    class="case-link">
    <OpenInNewIcon :size="16" />
    {{ label || t('planix', 'View in Procest') }}
  </a>
</template>

<script setup>
import { computed } from 'vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import { t } from '@nextcloud/l10n'

const props = defineProps({
  uuid: { type: String, default: null },
  label: { type: String, default: null },
})

const caseUrl = computed(() =>
  props.uuid ? `/apps/procest/#/cases/${props.uuid}` : '#'
)
</script>
```

---

## Seed Data

No seed data changes are required. The `register-schemas` change already loaded 3 seed projects; `caseReference` is nullable and defaults to null. Optionally, one seed project can have `caseReference` set to a placeholder UUID to demonstrate the badge UI — but this is not required for the change to be mergeable.

---

## i18n String Inventory

| Key context | Example string |
|-------------|---------------|
| Case badge | `Case: {short}` |
| Case badge aria | `Linked to Procest case {uuid}` |
| Case link default label | `View in Procest` |
| Project sidebar field label | `Case reference (Procest UUID)` |
| Project sidebar field helper | `Link this project to a Procest case by entering the case UUID.` |
| Project sidebar field placeholder | `e.g. 550e8400-e29b-41d4-a716-446655440000` |
| Task detail field label | `Case` |
| Task edit field label | `Case UUID (Procest)` |
| Task edit field helper | `Link this task to a Procest case by entering the case UUID.` |
| Task edit field placeholder | `e.g. 550e8400-e29b-41d4-a716-446655440000` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Procest not installed — case link leads to 404 | Low | Link only appears when user has manually set a UUID, implying awareness of Procest. A tooltip "Opens Procest" clarifies intent. |
| UUID truncation in badge hides useful info | Low | Full UUID available via `aria-label` and as tooltip. V1 will resolve to a human-readable case number. |
| `caseReference` UUID is not validated — user may enter non-UUID text | Low | Helper text and placeholder guide input. The field is display-only; invalid values render but do not cause errors. |
| `ProjectSettingsSidebar` diverges from `projects` change design | Low | The `projects` change (task 5.4) already noted `caseReference` would be displayed as read-only. This change upgrades it to editable — a natural extension, not a conflict. |
