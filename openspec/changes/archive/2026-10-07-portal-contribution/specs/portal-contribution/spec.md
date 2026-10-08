# portal-contribution Specification

**Status**: in-progress
**Scope**: planninq
**OpenSpec changes**:
- `openspec/changes/portal-contribution/`

## Purpose

Planninq contributes an external-contractor section to portaliq, the shared
external portal for people without Nextcloud accounts (hydra ADR-046 +
contribution contract v2.1). The contribution is one plain, dependency-free
provider class declaring — for the single `external-employee` audience — the
contractor-scoped OpenRegister reads and the whitelisted log-time create-action.
It `depends_on` the `portal-identity` change, which adds the UUID scoping
properties it reads.

## ADDED Requirements

### Requirement: Provider is a plain, dependency-free class (REQ-PC-001)

The app MUST ship `OCA\Planninq\Portal\PortalContributionProvider` as a plain PHP
class: no imports from portaliq, no `implements` clause, no `info.xml`
dependency on portaliq, no parent class, and no constructor dependencies.
Portaliq discovers it by convention FQCN and duck-types it via `method_exists`
(never `instanceof`), so without portaliq installed the class MUST be inert and
MUST NOT change any app behaviour (ADR-046 amendment A1).

#### Scenario: Provider constructs standalone

- GIVEN a PHP runtime where portaliq is not installed and no portaliq class is autoloadable
- WHEN `new PortalContributionProvider()` is called
- THEN the class instantiates without error
- AND it declares no `implements` clause, no parent, no constructor, and no `use` of any portaliq symbol
- @e2e exclude backend-only contract class with no planninq UI surface; the portal renders inside portaliq — covered by PHPUnit (tests/unit/Portal/PortalContributionProviderTest.php)

### Requirement: Provider declares both v2 and v1 audience methods (REQ-PC-003)

The provider MUST implement `getAudiences(): array` returning
`['external-employee']` (contract v2, preferred by the registry) AND
`getAudience(): string` returning `'external-employee'` (v1 fallback), so it
works against both registry generations (ADR-046 amendment A2).

#### Scenario: Audience methods agree

- GIVEN a constructed provider
- WHEN `getAudiences()` and `getAudience()` are called
- THEN `getAudiences()` returns exactly `['external-employee']`
- AND `getAudience()` returns `'external-employee'`
- AND the v1 primary audience is one of the v2 audiences
- @e2e exclude backend-only contract methods with no planninq UI surface — covered by PHPUnit (tests/unit/Portal/PortalContributionProviderTest.php)

### Requirement: Contribution is a declarative contractor manifest (REQ-PC-002)

`getContribution(array $subject): ?array` MUST return `null` unless
`$subject['audience']` is exactly `'external-employee'`. For an external-employee
subject it MUST return a declarative manifest labelled `'Planninq'` with:

- collection `contractorTasks` — register `planninq`, schema `task`, `scopeField`
  `contractorRef`, `scopeClaim` `contractorRef`, listable, `fields` projected to
  `title, description, status, priority, project, dueDate, startDate,
  completedAt, labels`;
- collection `contractorTimeEntries` — schema `timeEntry`, `scopeField`
  `contractorRef`, `scopeClaim` `contractorRef`, listable, `fields`
  `task, date, duration, description`;
- collection `contractorProjects` — schema `project`, `scopeField`
  `contractorRefs`, `scopeClaim` `contractorRef`, listable, `fields`
  `title, description, status, color, icon, labels`;
- create-action `logTime` — `type: 'create'`, schema `timeEntry`, `fields`
  whitelist exactly `['task', 'date', 'duration', 'description']`;
- empty `notifications`;
- NO `minTrust` on any collection or action (default low).

The manifest MUST be pure data — no callbacks, no service calls. No read
projection and no create whitelist may contain a Nextcloud-uid identity field
(`assignedTo`, `user`, `owner`, `members`, `defaultAssignee`) — ADR-046 A4. All
subject identity is server-derived by portaliq and MUST NOT be echoed back or
trusted from the client.

#### Scenario: External-employee subject receives the manifest

- GIVEN a subject array with `audience` `'external-employee'`, a `subjectRef` UUID, an organisation and a `low` trust level
- WHEN `getContribution($subject)` is called
- THEN it returns a manifest labelled `'Planninq'` whose collections are `contractorTasks`, `contractorTimeEntries` and `contractorProjects`
- AND the task/timeEntry collections scope by `contractorRef` and the project collection by `contractorRefs`, all with `scopeClaim` `contractorRef`
- AND a `logTime` create-action whose `fields` whitelist is exactly `task`, `date`, `duration`, `description`
- AND `notifications` is empty and no collection declares `minTrust`
- @e2e exclude manifest is consumed and rendered by portaliq, not by any planninq UI — covered by PHPUnit (tests/unit/Portal/PortalContributionProviderTest.php)

#### Scenario: Non-contractor subject receives null

- GIVEN a subject array whose `audience` is `'client'`, `'customer'`, any other value, or absent
- WHEN `getContribution($subject)` is called
- THEN it returns `null`
- @e2e exclude backend-only fail-closed filter with no planninq UI surface — covered by PHPUnit (tests/unit/Portal/PortalContributionProviderTest.php)

#### Scenario: No projected field leaks internal identity

- GIVEN the external-employee manifest
- WHEN every collection's `fields` projection and the `logTime` create whitelist are inspected against `planninq_register.json`
- THEN every listed field is a real property of its schema
- AND none of them is a Nextcloud-uid identity field (`assignedTo`, `user`, `owner`, `members`, `defaultAssignee`)
- @e2e exclude backend-only projection logic with no planninq UI surface — covered by the provider test's drift-pin + A4-leak guard (tests/unit/Portal/PortalContributionProviderTest.php)

## Non-Functional Requirements

- **Performance:** `getContribution()` is pure data assembly — no I/O, no
  container access; sub-millisecond by construction.
- **Accessibility:** N/A in planninq — the rendering surface is portaliq's SPA
  (ADR-046), which owns WCAG compliance.
- **Internationalization:** manifest labels ship in English source per fleet
  i18n policy; portaliq owns portal-side translation of contributed labels.

## Acceptance Criteria

- Unit suite proves: audiences, null for non-contractor subjects, the full
  manifest shape (scopeField/scopeClaim, projections, create whitelist, no
  minTrust), the register drift-pin, and the A4-leak guard.
- `php -l`, phpcs, and phpstan pass on the new files.
- `openspec validate portal-contribution` passes.

## Notes

- The provider is deliberately NOT registered in `lib/AppInfo/Application.php`
  — discovery is by FQCN from portaliq's side.
- `depends_on: [portal-identity]` — the provider reads `contractorRef` /
  `contractorRefs`; the drift-pin fails loudly if those properties drift.
- No inbox: planninq task notifications are Nextcloud `IManager` notifications
  keyed by the NC uid `assignedTo`, not a per-subject OR collection scoped by
  `contractorRef`; there is nothing resolvable to declare as `kind: 'inbox'`.
- Related: hydra ADR-046 (+ amendment A1–A6), ADR-022 (apps consume OR
  abstractions), ADR-005 (security — server-derived scope, fail-closed).
