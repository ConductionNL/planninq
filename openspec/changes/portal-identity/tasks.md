# Tasks: portal-identity

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6.
     Acceptance criteria are plain bullets, not checkboxes. -->

## Implementation Tasks

### Task 1: Add the UUID domain-ref scoping properties

- **spec_ref**: `openspec/changes/portal-identity/specs/portal-identity/spec.md#requirement-portal-scoping-uses-domain-object-uuid-references-req-pid-001`
- **files**: `lib/Settings/planix_register.json`
- **acceptance_criteria**:
  - GIVEN the shipped register WHEN parsed THEN `task` and `timeEntry` each define `contractorRef` (`type: string`, `format: uuid`, title "Contractor") and `project` defines `contractorRefs` (`type: array`, items `format: uuid`, title "Contractors")
  - GIVEN each new property WHEN checked THEN it sits ALONGSIDE the kept NC-uid field (`assignedTo` / `user` / `members`) and is NOT added to the schema's `required` list
  - GIVEN the edited file WHEN loaded with `python3 -c "import json; json.load(...)"` THEN it parses without error
- [x] Implement
- [x] Test

### Task 2: Bump versions so the version-gated import applies the change

- **spec_ref**: `openspec/changes/portal-identity/specs/portal-identity/spec.md#requirement-the-additive-schema-change-is-version-gated-req-pid-002`
- **files**: `lib/Settings/planix_register.json`, `appinfo/info.xml`
- **acceptance_criteria**:
  - GIVEN the register WHEN compared to HEAD THEN `task` 0.1.3 → 0.1.4, `timeEntry` 0.1.2 → 0.1.3, `project` 0.1.2 → 0.1.3, and register 0.2.5 → 0.2.6 (`info.version` + `registers.planix.version`)
  - GIVEN `appinfo/info.xml` WHEN compared to HEAD THEN `<version>` 0.2.9 → 0.2.10 so the repair-step import re-runs
  - GIVEN `column`, `label` and `dependency` WHEN checked THEN their versions are unchanged (only touched schemas bump)
- [x] Implement
- [x] Test

### Task 3: Register the capability spec

- **spec_ref**: `openspec/changes/portal-identity/specs/portal-identity/spec.md`
- **files**: `openspec/specs/portal-identity/spec.md`, `openspec/changes/portal-identity/*`
- **acceptance_criteria**:
  - GIVEN the declared capability WHEN the change is in flight THEN `openspec/specs/portal-identity/spec.md` exists with status `in-progress` pointing at this change
  - GIVEN `openspec validate portal-identity` WHEN run THEN it passes (deltas present, each requirement has a scenario)
  - GIVEN the six-schema drift test WHEN run THEN `PlanixRegisterSchemaTest` still passes (no schema added or removed; count stays exactly six)
- [x] Implement
- [x] Test

## Quality checklist

- Register JSON valid (`python3 -c "import json; json.load(...)"`)
- Additive only: no property removed/renamed, no NC-uid field touched, nothing added to `required`
- `openspec validate portal-identity` passes
- No user-facing strings, no code, no routes, no UI in this change
