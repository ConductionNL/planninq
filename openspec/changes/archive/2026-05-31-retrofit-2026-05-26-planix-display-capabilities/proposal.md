# Proposal: Planix Display Capabilities (Retrofit)

## Summary

Retroactively capture four project-list display behaviors in Planix that were
previously marked `@spec exclude` under an early, looser coverage policy. These
methods are not framework plumbing — they encode product behavior (which
project statuses exist, their human-readable labels, their chip semantics, the
available status filters, and the member-count figure shown per project). Under
the current strict policy, true capabilities must carry a spec; only genuine
framework plumbing may stay excluded.

## Motivation

The methods below transform or encode product meaning and therefore belong in
the spec, not behind an exclude marker:

- `ProjectListItem.statusLabel` — maps each `status` enum value to a translated
  label. This encodes the set of known statuses and their user-facing names.
- `ProjectListItem.statusType` — maps each `status` to an `NcChip` type
  (`success` / `warning` / `default`). This encodes status semantics.
- `ProjectList.statusChips` — defines the status-filter chip set shown above the
  project list. This encodes the available filters.
- `ProjectListItem.memberCount` — surfaces the per-project member count, a
  product-meaningful figure shown on every list row.

These behaviors already ship on `development`; this change captures them so the
strict spec-coverage gate reflects them as real capabilities rather than hidden
gaps.

## Affected Projects

- [x] Project: `planix` — Frontend-only: retroactive spec annotation, no code behavior change

## Scope

### In Scope

- A new `project-display` capability delta with four requirements describing the
  existing display behaviors
- Replacing the four `@spec exclude` markers with `@spec openspec/...` references

### Out of Scope

- Any change to the runtime behavior of the four methods
- The genuine-plumbing excludes (store/auth passthroughs, provide/inject,
  asset-path/URL builders, lifecycle bootstraps, event-wiring glue) which
  correctly remain excluded

## Approach

Document the observed behavior of each method as a SHALL/MUST requirement with
scenarios covering each enum branch and the fallback. Then point each method's
docblock `@spec` line at the corresponding task. No code logic changes.

## New Dependencies

None

## Impact

- `src/components/ProjectListItem.vue` — `memberCount`, `statusLabel`, `statusType` annotations updated
- `src/views/ProjectList.vue` — `statusChips` annotation updated
- New spec delta `openspec/changes/.../specs/project-display/spec.md`

## Cross-Project Dependencies

None

## Risks

### Risk 1: Spec drift from code
**Severity:** Low — **Mitigation:** Scenarios enumerate the exact status keys
and labels present in the code; any future status addition must update both.

## Rollback Strategy

Revert the single commit. Purely additive spec + annotation changes; no runtime
behavior is affected.
