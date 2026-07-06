---
capability: portal-identity
status: in-progress
built_by: openspec/changes/portal-identity
---

# portal-identity Specification

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [portal-identity](../../changes/portal-identity/) _(active)_ — additive `contractorRef`/`contractorRefs` UUID scoping properties + version bumps (kind: config)

## Purpose

Planix's portal scoping identity for external subjects (hydra ADR-046 +
contribution contract v2.1, amendment A4). Adds a UUID domain-object reference
(`task.contractorRef`, `timeEntry.contractorRef`, `project.contractorRefs`)
ALONGSIDE each existing Nextcloud-uid field, so portaliq can scope an external
contractor's reads without disturbing the internal dev/IT teams that rely on
`assignedTo` / `user` / `members`. This is the config precondition for the
`portal-contribution` provider.

## Requirements

Detailed requirements (REQ-PID-001 … REQ-PID-002) are defined in the active
change's delta spec —
[`openspec/changes/portal-identity/specs/portal-identity/spec.md`](../../changes/portal-identity/specs/portal-identity/spec.md)
— and merge here by `openspec sync` when the change is archived. The umbrella
requirement below anchors the capability until then.

### Requirement: Planix exposes a UUID portal-scoping identity (REQ-PID-000)

The `task`, `timeEntry` and `project` schemas MUST each carry an additive UUID
domain-object reference property (`contractorRef` / `contractorRefs`) sitting
alongside — never replacing — their Nextcloud-uid field, so a portal subject is
scoped by a contact-object UUID and never by a Nextcloud user id (ADR-046 A4).
The property MUST be optional (fail-closed) and MUST NOT change any register
`authorization` block.

#### Scenario: The scoping identity is present and additive

- GIVEN a planix checkout at this capability's `in-progress` (or later) status
- WHEN `lib/Settings/planix_register.json` is parsed
- THEN `task.contractorRef`, `timeEntry.contractorRef` (both `format: uuid`) and `project.contractorRefs` (array of `format: uuid`) exist
- AND each sits alongside the kept NC-uid field and is absent from every `required` list
- @e2e exclude declarative register configuration with no UI surface — covered by the JSON validity gate and the portal-contribution provider test's register drift-pin (tests/unit/Portal/PortalContributionProviderTest.php)

## Notes

- No RBAC change: the register `authorization` blocks (issues #257–#259) are
  untouched; portaliq performs the scoped portal read server-side.
- Backfilling `contractorRef` onto existing objects is a documented operational
  follow-up, not part of this change (fail-closed until then).
- Related ADRs: hydra ADR-046 (+ amendment A4), ADR-022, ADR-005.
