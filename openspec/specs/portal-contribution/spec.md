---
capability: portal-contribution
status: in-progress
built_by: openspec/changes/portal-contribution
---

# portal-contribution Specification

**Status**: in-progress
**Scope**: planninq
**OpenSpec changes**:
- [portal-contribution](../../changes/portal-contribution/) _(active)_ — plain ADR-046 provider class (external-employee audience) + unit tests (kind: code, depends_on portal-identity)

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

## Notes

- Discovery is pull-based from portaliq (`method_exists`, never `instanceof`);
  planninq registers nothing in `lib/AppInfo/Application.php`.
- No inbox: planninq task notifications are Nextcloud `IManager` notifications
  keyed by the NC uid `assignedTo`, not a per-subject OR collection scoped by
  `contractorRef`.
- Related ADRs: hydra ADR-046 (+ amendment A1–A6), ADR-022, ADR-005.
