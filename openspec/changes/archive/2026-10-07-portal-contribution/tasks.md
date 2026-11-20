# Tasks: portal-contribution

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8.
     Acceptance criteria are plain bullets, not checkboxes. -->

## Implementation Tasks

### Task 1: Ship the plain PortalContributionProvider class

- **spec_ref**: `openspec/changes/portal-contribution/specs/portal-contribution/spec.md#requirement-provider-is-a-plain-dependency-free-class-req-pc-001`
- **files**: `lib/Portal/PortalContributionProvider.php`
- **acceptance_criteria**:
  - GIVEN the new class WHEN inspected THEN it is namespace `OCA\Planninq\Portal`, has NO `use` of any portaliq symbol, NO `implements` clause, NO parent, NO constructor, and carries the repo-standard EUPL-1.2/SPDX docblock header plus `@spec` tags
  - GIVEN portaliq is absent WHEN the app runs THEN nothing references the class (no DI registration, no route) — it is inert
  - GIVEN `getAudiences()` / `getAudience()` WHEN called THEN they return `['external-employee']` / `'external-employee'`
- [x] Implement
- [x] Test

### Task 2: Implement the fail-closed external-employee manifest

- **spec_ref**: `openspec/changes/portal-contribution/specs/portal-contribution/spec.md#requirement-contribution-is-a-declarative-contractor-manifest-req-pc-002`
- **files**: `lib/Portal/PortalContributionProvider.php`
- **acceptance_criteria**:
  - GIVEN any subject whose `audience` is not `external-employee` (incl. absent) WHEN `getContribution()` is called THEN it returns `null`
  - GIVEN an `external-employee` subject WHEN `getContribution()` is called THEN the manifest is labelled `Planninq` with collections `contractorTasks` (task, scopeField `contractorRef`), `contractorTimeEntries` (timeEntry, scopeField `contractorRef`) and `contractorProjects` (project, scopeField `contractorRefs`), all `scopeClaim` `contractorRef`, listable, no `minTrust`
  - GIVEN each collection WHEN inspected THEN its `fields` projection excludes every NC-uid field (`assignedTo`/`user`/`owner`/`members`/`defaultAssignee`) and all internal/estimate/private columns enumerated in design.md
- [x] Implement
- [x] Test

### Task 3: Implement the log-time create-action

- **spec_ref**: `openspec/changes/portal-contribution/specs/portal-contribution/spec.md#requirement-contribution-is-a-declarative-contractor-manifest-req-pc-002`
- **files**: `lib/Portal/PortalContributionProvider.php`
- **acceptance_criteria**:
  - GIVEN the manifest WHEN inspected THEN it has exactly one action `logTime` (`type: create`, schema `timeEntry`) with `fields` exactly `['task', 'date', 'duration', 'description']`
  - GIVEN the `logTime` whitelist WHEN checked THEN it excludes `user` and `contractorRef` (both server-authoritative) and any approval/billing field
  - GIVEN the manifest WHEN inspected THEN `notifications` is empty (no per-subject inbox collection exists — documented)
- [x] Implement
- [x] Test

### Task 4: Unit-test the contract, register drift-pin and A4-leak guard

- **spec_ref**: `openspec/changes/portal-contribution/specs/portal-contribution/spec.md#requirement-provider-declares-both-v2-and-v1-audience-methods-req-pc-003`
- **files**: `tests/unit/Portal/PortalContributionProviderTest.php`
- **acceptance_criteria**:
  - GIVEN the test class WHEN it constructs the provider THEN it does so directly (`new`, no mocks/container) following existing `tests/unit/` conventions
  - GIVEN the suite WHEN run (php 8.3 container) THEN it asserts audiences, null for other audiences, the full manifest shape, `scopeClaim: contractorRef`, the exact create whitelist, and NO `minTrust`
  - GIVEN the drift-pin WHEN run THEN it loads `planninq_register.json` and asserts `task`/`timeEntry` carry `contractorRef` (uuid, optional) and `project` carries `contractorRefs` (uuid[], optional), every scopeField/projected field is a real property, and no NC-uid field is projected — and passes
- [x] Implement
- [x] Test

## Quality checklist

- All provider logic covered by PHPUnit unit tests (`tests/unit/Portal/`)
- No new API endpoints → no Newman collection; no UI change → no Playwright (portal renders in portaliq)
- All tests pass (`vendor/bin/phpunit` in the php 8.3 container)
- No user-facing strings added inside planninq (manifest labels are portal-side data; English source per i18n policy)
- `openspec validate portal-contribution` passes
