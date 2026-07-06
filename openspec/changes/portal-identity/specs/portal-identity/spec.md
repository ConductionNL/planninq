# portal-identity Specification

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- `openspec/changes/portal-identity/`

## Purpose

Planix contributes a scoping identity for external portal subjects (hydra
ADR-046 + contribution contract v2.1). Because planix scopes all internal work
by Nextcloud user id — which an external contractor never has (amendment A4) —
this capability adds a distinct UUID domain-object reference ALONGSIDE each
NC-uid field, so portaliq can scope an external contractor's reads without
disturbing internal teams. It is the config precondition for the
`portal-contribution` provider.

## ADDED Requirements

### Requirement: Portal scoping uses domain-object UUID references (REQ-PID-001)

The `task` and `timeEntry` schemas in `lib/Settings/planix_register.json` MUST
each carry a `contractorRef` property (`type: string`, `format: uuid`, titled
"Contractor"), and the `project` schema MUST carry a `contractorRefs` property
(`type: array`, items `type: string` `format: uuid`, titled "Contractors").
Each new property MUST be additive — placed alongside the kept Nextcloud-uid
field (`assignedTo`, `user`, `members` respectively), MUST NOT replace or rename
it, and MUST NOT be added to the schema's `required` list. The value is the UUID
of a contractor contact domain object — never a Nextcloud user id (ADR-046
amendment A4).

#### Scenario: Schemas expose the contractor scoping properties

- GIVEN the shipped `planix_register.json`
- WHEN the register configuration is parsed
- THEN `task.contractorRef` and `timeEntry.contractorRef` are defined with `type` `string` and `format` `uuid`
- AND `project.contractorRefs` is defined as an array of `format: uuid` strings
- AND each schema still defines its original NC-uid field (`assignedTo` / `user` / `members`)
- AND none of `contractorRef` / `contractorRefs` appears in any `required` list
- @e2e exclude declarative register configuration with no UI surface — covered by the JSON validity gate (`python3 json.load`) and the `portal-contribution` provider test's register drift-pin (tests/unit/Portal/PortalContributionProviderTest.php)

### Requirement: The additive schema change is version-gated (REQ-PID-002)

The change MUST bump the register version, every touched schema version, and
`appinfo/info.xml`, because OpenRegister's import is version-gated and
re-applies schema changes only on a version increase. Untouched schemas
(`column`, `label`, `dependency`) MUST keep their versions.

#### Scenario: Versions bump so the import applies the properties

- GIVEN the register at HEAD (register 0.2.5; `task` 0.1.3; `timeEntry` 0.1.2; `project` 0.1.2)
- WHEN the change is applied
- THEN register `info.version` and `registers.planix.version` are 0.2.6
- AND `task` is 0.1.4, `timeEntry` is 0.1.3, `project` is 0.1.3
- AND `appinfo/info.xml` `<version>` is 0.2.10
- AND `column`, `label` and `dependency` versions are unchanged
- @e2e exclude declarative version metadata with no UI surface — covered by JSON/XML inspection in the JSON gate and by manual diff review

## Non-Functional Requirements

- **Performance:** none — a static JSON edit.
- **Accessibility:** N/A — no UI; the rendering surface is portaliq's SPA.
- **Internationalization:** property titles ship in English source per fleet
  i18n policy; portaliq owns portal-side translation of contributed labels.

## Acceptance Criteria

- `planix_register.json` is valid JSON with the three additive properties and
  all version bumps present.
- The six-schema drift test (`PlanixRegisterSchemaTest`) still passes — no
  schema added or removed.
- `openspec validate portal-identity` passes.

## Notes

- No RBAC change: the register `authorization` blocks (issues #257–#259) are
  untouched; portaliq performs the scoped portal read server-side.
- Backfilling `contractorRef` onto existing objects is a documented operational
  follow-up, not part of this change (fail-closed until then).
- Related: hydra ADR-046 (+ amendment A4), ADR-022 (apps consume OR
  abstractions), ADR-005 (security — server-derived scope, fail-closed).
