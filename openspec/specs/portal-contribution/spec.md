---
capability: portal-contribution
status: implemented
built_by: openspec/changes/archive/2026-10-07-portal-contribution
---

# portal-contribution Specification

**Status**: implemented
**Scope**: planninq
**OpenSpec changes**:
- [portal-contribution](../../changes/archive/2026-10-07-portal-contribution/) _(archived 2026-10-07)_ — plain ADR-046 provider class (external-employee audience) + unit tests (kind: code, depends_on portal-identity)

## Purpose

Planninq's ADR-046 portal contribution: one plain, dependency-free provider class
(`OCA\Planninq\Portal\PortalContributionProvider`) that declares, for the single
`external-employee` (contractor) audience, the contractor-scoped OpenRegister
reads (tasks, time entries, projects — scoped by `contractorRef` /
`contractorRefs`) and the whitelisted log-time create-action. The class is inert
without portaliq (duck-typed by FQCN) and `depends_on` the `portal-identity`
change for the UUID scoping properties it reads.

## Requirements

Detailed requirements (REQ-PC-001 … REQ-PC-003) are defined in the active
change's delta spec —
[`openspec/changes/portal-contribution/specs/portal-contribution/spec.md`](../../changes/portal-contribution/specs/portal-contribution/spec.md)
— and merge here by `openspec sync` when the change is archived. The umbrella
requirement below anchors the capability until then.

### Requirement: Planninq ships the ADR-046 contractor portal contribution (REQ-PC-000)

The app MUST serve its entire portal contribution through the single plain,
dependency-free `OCA\Planninq\Portal\PortalContributionProvider` class (duck-typed
by FQCN, inert without portaliq), which MUST declare the `external-employee`
audience and a fail-closed, field-projected manifest scoped exclusively by the
`contractorRef` claim. No other portal logic, UI, or dependency may exist in
planninq, and no Nextcloud-uid identity field may appear in any read projection or
create whitelist (ADR-046 A4).

#### Scenario: Contribution surface is exactly the provider

- GIVEN a planninq checkout at this capability's `in-progress` (or later) status
- WHEN portaliq's registry (contract v2) discovers and duck-types the provider
- THEN the whole contribution resolves from `lib/Portal/PortalContributionProvider.php`, scoped by the `contractorRef`/`contractorRefs` properties owned by the `portal-identity` capability
- AND removing that class removes the contribution without affecting any other app behaviour
- @e2e exclude backend-only contract surface with no planninq UI; the portal renders inside portaliq — covered by PHPUnit (tests/unit/Portal/PortalContributionProviderTest.php)

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

## Notes

- Discovery is pull-based from portaliq (`method_exists`, never `instanceof`);
  planninq registers nothing in `lib/AppInfo/Application.php`.
- No inbox: planninq task notifications are Nextcloud `IManager` notifications
  keyed by the NC uid `assignedTo`, not a per-subject OR collection scoped by
  `contractorRef`.
- Related ADRs: hydra ADR-046 (+ amendment A1–A6), ADR-022, ADR-005.
