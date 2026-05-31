# Project Display Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [retrofit-2026-05-26-planix-display-capabilities](../../) — retroactively captures project-list display behaviors

## Purpose

Defines how Planix presents a project's status and membership in the project
list. These behaviors encode product meaning — the set of known statuses, their
human-readable labels, their chip semantics, the available status filters, and
the per-project member count — and are therefore specified rather than treated
as framework plumbing.

## ADDED Requirements

### Requirement: Project status label mapping (REQ-PXD-001)
The project list item MUST map each project `status` value to a translated,
human-readable label.

- `active` MUST render as the translated label "Active".
- `archived` MUST render as the translated label "Archived".
- `completed` MUST render as the translated label "Completed".
- Any unknown or empty `status` MUST fall back to the raw status string, or to
  the translated label "Active" when the status is empty.

Labels MUST be translatable (ADR-007), supporting at minimum Dutch and English.

#### Scenario: Active status
- GIVEN a project with `status` = `active`
- WHEN the project list item computes its status label
- THEN it MUST return the translated "Active" label

#### Scenario: Archived status
- GIVEN a project with `status` = `archived`
- WHEN the project list item computes its status label
- THEN it MUST return the translated "Archived" label

#### Scenario: Completed status
- GIVEN a project with `status` = `completed`
- WHEN the project list item computes its status label
- THEN it MUST return the translated "Completed" label

#### Scenario: Unknown status falls back to raw value
- GIVEN a project with `status` = `on-hold` (not a known value)
- WHEN the project list item computes its status label
- THEN it MUST return the raw string `on-hold`

#### Scenario: Empty status falls back to Active
- GIVEN a project with no `status` set (null or empty)
- WHEN the project list item computes its status label
- THEN it MUST return the translated "Active" label

### Requirement: Project status chip type mapping (REQ-PXD-002)
The project list item MUST map each project `status` to an `NcChip` type that
conveys the status semantics.

- `active` MUST map to chip type `success`.
- `archived` MUST map to chip type `warning`.
- `completed` MUST map to chip type `default`.
- Any unknown or empty `status` MUST map to chip type `default`.

#### Scenario: Active status is a success chip
- GIVEN a project with `status` = `active`
- WHEN the project list item computes its chip type
- THEN it MUST return `success`

#### Scenario: Archived status is a warning chip
- GIVEN a project with `status` = `archived`
- WHEN the project list item computes its chip type
- THEN it MUST return `warning`

#### Scenario: Completed status is a default chip
- GIVEN a project with `status` = `completed`
- WHEN the project list item computes its chip type
- THEN it MUST return `default`

#### Scenario: Unknown status is a default chip
- GIVEN a project with an unrecognized `status`
- WHEN the project list item computes its chip type
- THEN it MUST return `default`

### Requirement: Project status filter chips (REQ-PXD-003)
The project list view MUST present a fixed set of status-filter chips above the
list, defining which statuses a user can filter by.

The chip set MUST contain, in order:
- "All" with filter value `null` (no filtering)
- "Active" with filter value `active`
- "Archived" with filter value `archived`
- "Completed" with filter value `completed`

All chip labels MUST be translatable (ADR-007).

#### Scenario: Filter chip definitions
- GIVEN the project list view is rendered
- WHEN the status-filter chips are computed
- THEN there MUST be exactly four chips
- AND their values MUST be `null`, `active`, `archived`, `completed` in that order
- AND their labels MUST be the translated "All", "Active", "Archived", "Completed"

### Requirement: Project member count display (REQ-PXD-004)
The project list item MUST surface the number of members on the project as a
non-negative integer.

- When `members` is an array, the count MUST equal the array length.
- When `members` is missing or not an array, the count MUST be `0`.

#### Scenario: Project with members
- GIVEN a project whose `members` array contains 3 entries
- WHEN the project list item computes its member count
- THEN it MUST return `3`

#### Scenario: Project with no members array
- GIVEN a project with no `members` property (null or undefined)
- WHEN the project list item computes its member count
- THEN it MUST return `0`

## Non-Functional Requirements

- **Internationalization:** All user-facing labels MUST support Dutch and
  English (ADR-007).
- **Accessibility:** Status MUST NOT be conveyed by chip color alone — the
  translated status label always accompanies the chip (WCAG 1.4.1).

## Acceptance Criteria

- [x] `statusLabel` returns correct translated labels for all known statuses and both fallbacks
- [x] `statusType` returns correct chip types for all known statuses and the fallback
- [x] `statusChips` returns the four-chip filter definition in order
- [x] `memberCount` returns the array length or `0`

## Notes

- The status enum (`active`, `archived`, `completed`) matches the Project schema
  in `register-schemas`. Any future status addition MUST update REQ-PXD-001,
  REQ-PXD-002, and REQ-PXD-003 together.
