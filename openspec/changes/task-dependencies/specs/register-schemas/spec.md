# Register Schemas Specification (Delta)

**Status**: proposed
**Scope**: planix
**OpenSpec changes**:
- [task-dependencies](../../) — adds the `dependency` schema to the register definition

## Purpose

Extends the register definition with the `dependency` schema (directed task-to-task edge) required by the task-dependencies capability. The schema count moves from 5 to 6.

## MODIFIED Requirements

### Requirement: All 6 schemas defined [MVP]

The register file MUST declare exactly the schemas `task`, `project`, `column`, `timeEntry`, `label`, and `dependency` in `components/schemas`. The placeholder `example` schema MUST NOT be present.

(Modification: adds `dependency` to the previously exact set of 5; all existing scenarios of this requirement remain valid with the enlarged key set.)

#### Scenario: Register file contains required schemas

- GIVEN the file `lib/Settings/planix_register.json` is loaded
- WHEN its `components.schemas` keys are enumerated
- THEN the keys MUST include `task`, `project`, `column`, `timeEntry`, `label`, and `dependency`
- AND the key `example` MUST NOT be present

#### Scenario: Dependency schema has required fields declared

- GIVEN the `dependency` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `blocker` and `blocked`
- AND both properties MUST be declared as `type: string` with `format: uuid`

## Acceptance Criteria

- [ ] `lib/Settings/planix_register.json` contains the `dependency` schema with required `blocker` + `blocked` UUID fields
- [ ] Register re-import is idempotent with the new schema (no duplication, no reset)

## Notes

- No seed dependency objects are required: the seed task set remains meaningful without edges, and the Newman suite creates its own.
- This delta only enlarges the exact-schema-set assertion; field requirements of the existing 5 schemas are untouched.
