---
kind: code
depends_on:
  - portal-identity
---

# Proposal: portal-contribution

**Tracking:** Conduction/planix#19 (Wave-3 portaliq contribution — leave open).

## Summary

Ship planix's ADR-046 portal contribution: one plain, dependency-free class
(`lib/Portal/PortalContributionProvider.php`) that declares what an external
**contractor** (`external-employee` audience) may see and do in planix — read
their own tasks, their own time entries and the projects they are attached to,
and log time — all scoped by the UUID domain refs the `portal-identity` change
added. No portaliq import, no `implements`, no `info.xml` dependency: the class
is inert unless portaliq is installed. This is the **code** link of the planix
portal chain and `depends_on` `portal-identity`.

## Motivation

ADR-046 establishes portaliq as the single external portal for people without
Nextcloud accounts; its contribution contract (v2.1) has domain apps contribute
one duck-typed class discovered by FQCN — no build- or install-time coupling, so
portal support is always optional (amendment A1). Planix is a low-medium-priority
portal target with exactly one honest external audience: the external contractor
who needs to see the work assigned to them and log time against it. This change
delivers that audience and nothing speculative — the `portal-identity` change
made the scoping possible; this one makes it real.

## Affected Projects

- [x] Project: `planix` — new `lib/Portal/PortalContributionProvider.php`, new `tests/unit/Portal/PortalContributionProviderTest.php`. Reads the `contractorRef`/`contractorRefs` properties added by `portal-identity`. No routes, controllers, services, frontend, or info.xml changes.

## Scope

### In Scope

- A plain `OCA\Planix\Portal\PortalContributionProvider` class exposing
  `getAudiences(): array` → `['external-employee']`, `getAudience(): string` →
  `'external-employee'`, and `getContribution(array $subject): ?array`.
- A declarative `external-employee` manifest, all scoped by the `contractorRef`
  claim:
  - `contractorTasks` — schema `task`, `scopeField` `contractorRef`, field-
    projected to contractor-safe columns.
  - `contractorTimeEntries` — schema `timeEntry`, `scopeField` `contractorRef`,
    field-projected.
  - `contractorProjects` — schema `project`, `scopeField` `contractorRefs`
    (array-contains), field-projected.
  - create-action `logTime` — `type: create`, schema `timeEntry`, field
    whitelist exactly `['task', 'date', 'duration', 'description']`.
  - empty `notifications` (no per-subject inbox collection exists — see design).
- `minTrust`: none declared → every collection defaults to low trust.
- PHPUnit unit tests: the full contract + a register drift-pin + an A4-leak
  guard.
- OpenSpec capability `portal-contribution` (this change).

### Out of Scope

- Any portal UI, auth edge, inbox, or rendering — portaliq owns the entire
  external surface (ADR-046); planix ships zero portal frontend.
- The register scoping properties themselves — delivered by `portal-identity`
  (this change `depends_on` it).
- A **client** project-view audience — no client reference exists in the planix
  data model, so none is invented (scope note).
- An inbox surface — planix task notifications are Nextcloud `IManager`
  notifications keyed by the NC uid `assignedTo`, not a per-subject OR collection
  scoped by `contractorRef`; there is nothing resolvable to declare as
  `kind: 'inbox'` (documented in design).
- Backfilling contractor refs onto live objects (a `portal-identity` follow-up).

## Approach

Duck-typed discovery per amendment A1: portaliq's registry resolves
`OCA\Planix\Portal\PortalContributionProvider` by FQCN (`ucfirst('planix')` →
`Planix`, matching the composer PSR-4 namespace) and probes it with
`method_exists`. The provider is a plain class with the three contract methods
and nothing else; the contribution is a pure-data manifest. Scoping follows
amendment A4: reads match `contractorRef` (the contractor contact UUID), never a
Nextcloud uid. Every read is field-projected to drop internal/estimate/private
columns; the create-action whitelists only contractor-submittable fields.
Details, including the projection whitelists, in design.md.

## New Dependencies

None. The provider is dependency-free by contract and inert without portaliq.

## Impact

- `lib/Portal/PortalContributionProvider.php` — new, self-contained (~1 KB
  dead-weight without portaliq).
- `tests/unit/Portal/PortalContributionProviderTest.php` — new.
- No routes, controllers, services, frontend, register, or info.xml changes in
  THIS change (the register edit is `portal-identity`).

## Cross-Project Dependencies

- **In-repo:** `depends_on: [portal-identity]` — the provider reads
  `task.contractorRef`, `timeEntry.contractorRef` and `project.contractorRefs`;
  without them, every scoped read matches nothing. The provider test's
  drift-pin asserts those properties exist, so the two changes are wired.
- **Cross-repo:** none at build or install time (amendment A1). At runtime,
  portaliq — when installed — discovers and renders the contribution.

## Risks

### Risk 1: Contract drift while portaliq evolves

**Severity:** Medium — **Mitigation:** implement both `getAudiences()` (v2) and
`getAudience()` (v1 fallback) and use only manifest keys fixed by the ADR-046
amendment (`label`, `collections`, `actions`, `notifications`, `scopeField`,
`scopeClaim`, create-action `fields`). Unit tests pin the exact shape.

### Risk 2: A projected field leaks internal identity

**Severity:** Medium — **Mitigation:** an A4-leak guard test asserts no
Nextcloud-uid field (`assignedTo` / `user` / `owner` / `members` /
`defaultAssignee`) appears in any read projection or create whitelist, and a
drift-pin asserts every projected field is a real schema property.

## Rollback Strategy

Delete `lib/Portal/` and `tests/unit/Portal/`. Without the provider class,
portaliq discovery finds nothing and the portal shows no planix section — the
app itself is unaffected. The `portal-identity` properties are additive and can
stay or be reverted independently.
