# Admin & User Settings Specification (Delta)

**Status**: proposed
**Scope**: planix
**OpenSpec changes**:
- [label-management-admin](../../) — adds app-wide label management to the admin settings page

## Purpose

Extends the admin settings capability with the README/FEATURES.md-promised label management: a CnSettingsSection where a Nextcloud admin creates, edits, and deletes the app-wide labels that tasks reference by UUID. Before this change the `label` schema and seed labels existed with no management surface.

## ADDED Requirements

### Requirement: Label management section [MVP]
The Planix admin settings page MUST contain a "Label Management" CnSettingsSection listing every app-wide label with its color chip, title, optional description, and a usage count (number of tasks whose `labels` array contains the label's UUID). The section MUST offer create, edit, and delete actions. CnVersionInfoCard remains the first section on the page.

#### Scenario: View labels with usage counts
- GIVEN a Nextcloud admin opens Administration → Planix
- AND the seed label `Bug` is referenced by 2 tasks
- THEN the Label Management section MUST list `Bug` with its color chip and "used by 2 tasks"
- AND labels MUST be sorted by title

#### Scenario: Non-admin cannot manage labels
@e2e exclude API permission contract, covered by Newman 403 assertion
- GIVEN a regular (non-admin) Nextcloud user
- WHEN they call the label usage or cascade-delete endpoints directly
- THEN Nextcloud MUST return a 403 Forbidden response

### Requirement: Create and edit labels [MVP]
An admin MUST be able to create a label (required title, 6-digit hex color via a color picker defaulting to `#4376FC`, optional description) and edit an existing label's title, color, and description. Create and edit operate on the OpenRegister `label` schema directly (ADR-022 — no planix pass-through controller); the schema's `^#[0-9A-Fa-f]{6}$` color pattern remains the authoritative validation. Because tasks reference labels by UUID, an edit MUST propagate to every task card chip and board filter without modifying any task.

#### Scenario: Create a label
- GIVEN the admin opens the label dialog from the Label Management section
- WHEN the admin enters title "Tech debt", picks a color, and saves
- THEN the label MUST be created in the OpenRegister `label` schema
- AND it MUST appear in the list with usage count 0
- AND it MUST be selectable on tasks and in the board label filter

#### Scenario: Invalid color is rejected
- GIVEN the label dialog is open
- WHEN the admin enters a color value that is not a 6-digit hex code
- THEN the dialog MUST show a validation error and MUST NOT save
- AND a direct API write with an invalid color MUST be rejected by schema validation (HTTP 400)

#### Scenario: Rename and recolor propagate by reference
- GIVEN the label `Bug` (red) is shown as a chip on a task card
- WHEN the admin renames it to `Defect` and changes the color to orange
- THEN no task object may be modified
- AND the task card chip and the board label filter MUST show `Defect` in orange on next render

### Requirement: Delete a label with cascade [MVP]
Deleting a label MUST require a confirmation dialog stating the usage count. On confirm, the system MUST remove the label's UUID from the `labels` array of every referencing task (server-side, before the label object is deleted) and then delete the label object — no task may retain a dangling label reference. The cascade MUST be idempotent: re-running it after a partial failure completes the sweep. A register re-import MUST NOT resurrect a deleted label or reset an edited one.

#### Scenario: Delete a used label
- GIVEN the label `Bug` is referenced by 12 tasks
- WHEN the admin clicks delete and the dialog warns "It will be removed from 12 tasks" and the admin confirms
- THEN every referencing task's `labels` array MUST no longer contain the label's UUID
- AND the label object MUST be deleted
- AND the chip MUST disappear from board cards and the label filter

#### Scenario: Cascade is idempotent after partial failure
@e2e exclude failure-recovery path, covered by PHPUnit on the cascade service
- GIVEN a cascade delete failed after sweeping only part of the referencing tasks
- WHEN the admin retries the delete
- THEN the remaining tasks MUST be swept
- AND already-swept tasks MUST NOT be modified again

#### Scenario: Re-import does not resurrect deleted seed labels
@e2e exclude backend install path, covered by Newman against the OR API after re-import
- GIVEN the admin deleted the seed label `Feature`
- WHEN the register import runs again (repair step / "Initialize register")
- THEN the `Feature` label MUST NOT be recreated

## Acceptance Criteria

- [ ] Label Management CnSettingsSection lists all labels with color chip, title, description, usage count
- [ ] Create/edit via the OR `label` schema with hex-pattern validation; default color `#4376FC`
- [ ] Rename/recolor propagates everywhere without task writes (UUID reference pinned)
- [ ] Delete shows usage-aware confirmation; server-side cascade leaves no dangling UUIDs; idempotent
- [ ] Usage-count and cascade endpoints are admin-only (403 for non-admins)
- [ ] Re-import never resurrects deleted labels or resets edited ones
- [ ] gate-5 (route-auth), gate-17 (redundant-controller), and modal-isolation pass

## Notes

- The `label` schema and `task.labels` (UUID array) are defined in `register-schemas` and are unchanged by this delta.
- List/create/edit are direct OR object CRUD from the admin frontend; only usage aggregation and the cascade delete are planix endpoints (they add real logic — gate-17 safe).
- A "groups may manage labels" delegation knob is a V1 follow-up alongside `allow_project_creation`; label merge and label analytics are V1+.
