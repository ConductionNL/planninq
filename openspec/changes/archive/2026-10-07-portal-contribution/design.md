# Design: portal-contribution

## Architecture Overview

Portaliq (hydra ADR-046) is the one shared external portal for people without
Nextcloud accounts. Domain apps contribute by shipping a single plain class at a
convention FQCN; portaliq's `PortalContributionRegistry` resolves
`OCA\{App}\Portal\PortalContributionProvider` per installed app and duck-types
it (`method_exists`, never `instanceof`). Planninq adds exactly one new file under
`lib/Portal/` and touches nothing else in the runtime app:

```
portaliq (if installed)
  └─ registry resolves OCA\Planninq\Portal\PortalContributionProvider (FQCN)
       └─ getAudiences() → ['external-employee']   (v2, preferred)
       └─ getAudience()  → 'external-employee'      (v1 fallback)
       └─ getContribution($subject) → manifest (pure data) or null
            └─ collections read via OpenRegister, scoped by
               task.contractorRef / timeEntry.contractorRef == subject claim,
               and subject claim ∈ project.contractorRefs
```

`ucfirst('planninq')` → `Planninq`, which matches this app's composer PSR-4
namespace and `info.xml` `<namespace>Planninq</namespace>` exactly — no casing
subtlety. Without portaliq the class is never instantiated: ~1 KB of inert
dead-weight by design (amendment A1). There is deliberately **no** DI
registration in `lib/AppInfo/Application.php` — discovery is pull-based from
portaliq's side.

## Declarative-vs-imperative note

The contribution is **declarative by nature**: `getContribution()` returns a
pure-data manifest (label, collections, actions, notifications) that portaliq
interprets — the same philosophy as the ADR-024 app manifest and ADR-031
declarative business logic. No behaviour, no I/O, no callbacks live in the
provider. A provider *class* (rather than a JSON file) is used only because it
is the delivery vehicle ADR-046 mandates: autoloadable cross-app by FQCN,
discoverable via the container, and able to branch on the server-derived
`$subject` (audience filtering) without portaliq parsing app-private config. The
one imperative line is that audience branch; everything portaliq renders or
enforces (scoping, trust, RBAC) is data in the manifest, evaluated portaliq-side.

## Additive-remap rationale + claim names

This change consumes the `portal-identity` scoping properties. Planninq scopes all
internal work by Nextcloud uid (verified at HEAD): `task.assignedTo`,
`timeEntry.user`, `project.members`. Amendment A4 forbids scoping an external
subject by an NC uid, so `portal-identity` added a UUID domain ref ALONGSIDE each
(non-destructive — the NC-uid fields are kept for internal teams). This provider
scopes portal reads **exclusively** by the new refs:

| Collection              | Schema      | scopeField       | scopeClaim      | match          |
|-------------------------|-------------|------------------|-----------------|----------------|
| `contractorTasks`       | `task`      | `contractorRef`  | `contractorRef` | equality       |
| `contractorTimeEntries` | `timeEntry` | `contractorRef`  | `contractorRef` | equality       |
| `contractorProjects`    | `project`   | `contractorRefs` | `contractorRef` | array-contains |
| `logTime` (create)      | `timeEntry` | —                | —               | —              |

The single scoping claim is `contractorRef` — the contractor contact object
UUID, resolved server-side by portaliq's auth edge. A row whose ref is unset
matches no contractor and is invisible (fail-closed). **Backfilling** the refs
onto live objects is a `portal-identity` follow-up, not part of this change.

## Read-field projections (what a contractor may see)

Portaliq whitelist-projects rows after per-row verification (identifiers always
survive; a malformed `fields` list degrades to identifiers-only). Every planninq
read is projected to drop internal/estimate/private columns:

### `contractorTasks` (schema `task`)

- **Included:** `title`, `description`, `status`, `priority`, `project`,
  `dueDate`, `startDate`, `completedAt`, `labels`.
- **Excluded (internal / estimate / private):**
  - `assignedTo` — Nextcloud staff uid (A4 identity field).
  - `estimatedDuration` — internal effort estimate.
  - `percentComplete` — internal management metric.
  - `column`, `columnOrder` — internal kanban board structure.
  - `zaakUuid` — internal Procest / government case linkage.
  - `calendarEventUid` — internal CalDAV integration id.
  - `parent` — internal task-hierarchy graph.
  - `contractorRef` — the scope key itself (identifiers survive regardless).

### `contractorTimeEntries` (schema `timeEntry`)

- **Included:** `task`, `date`, `duration`, `description`.
- **Excluded:** `user` — Nextcloud staff uid (A4 identity field);
  `contractorRef` — scope key. There are no approval/billing fields on
  `timeEntry` today; if any are added later they MUST stay excluded here (and
  out of the `logTime` whitelist below).

### `contractorProjects` (schema `project`)

- **Included:** `title`, `description`, `status`, `color`, `icon`, `labels`.
- **Excluded:** `owner`, `members`, `defaultAssignee` — Nextcloud uid identity
  fields (A4); `caseReference` — internal Procest / government case linkage;
  `contractorRefs` — scope key.

The `project` field is deliberately kept on `contractorTasks` (a plain UUID ref)
so a contractor can correlate a task to the separately-listed project; the flat
ADR-046 contract cannot express deriving the project list by joining tasks'
`project` (a two-hop join), which is exactly why `project.contractorRefs` exists
and is scoped directly.

## Create-action whitelist

`logTime` (create `timeEntry`) exposes only `task`, `date`, `duration`,
`description`. The logging `user` (an NC uid the external subject does not have)
and the `contractorRef` scope key are server-authoritative — portaliq sets them
from the resolved subject, not from client input. Any future approval/billing
fields stay back-office-only. Portaliq enforces the whitelist server-side.

## minTrust

No `minTrust` is declared on any collection or action, so every surface defaults
to **low** trust: a subject bearing a resolved `contractorRef` claim is
sufficient. Planninq's contractor data (task titles, hours) is ordinary business
data, not special-category — unlike, say, pipelinq's booking notes — so no
elevated eIDAS threshold is warranted.

## Inbox

**None.** The manifest's `notifications` is empty. Planninq does emit task
notifications — the declarative `taskDueSoon` rule (`x-openregister-notifications`
on the `task` schema) — but its recipient is the NC uid `assignedTo` via
Nextcloud's `IManager`, NOT a per-subject OpenRegister collection scoped by
`contractorRef`. There is no per-subject notification object collection that
portaliq could list as `kind: 'inbox'`, so declaring one would be fiction. A
future contractor inbox would need a task-notifications OR collection carrying
`contractorRef` — a separate change.

## API Design

None. No routes, controllers, or endpoints. Reads/creates go through
OpenRegister's existing object API, invoked by portaliq server-side with subject
scoping (ADR-022 — no app-local CRUD wrappers).

## Database Changes

None owned by this change. The scoping properties are `portal-identity`'s; this
change adds only a PHP class and its test. No `migration.md`: no data transform,
no schema edit here.

## Nextcloud Integration

- Controllers / Services / Mappers / Entities: none (OR owns storage).
- Events / Hooks: none — no `Application.php` registration by design.

## Security Considerations

- **Server-derived subject only** (ADR-005 / ADR-046 A6): the `$subject` array
  (subjectRef, audience, organisation, trust) is built by portaliq's auth edge.
  The provider only reads `audience` to filter; it never echoes subject data
  into the manifest and never trusts client input.
- **UUID domain-object scoping** (A4): reads match `contractorRef` /
  `contractorRefs` — the contractor contact UUID — never an NC uid.
- **Fail-closed audience filter**: any subject whose `audience` is not exactly
  `'external-employee'` gets `null`.
- **Fail-closed scoping**: an unset `contractorRef` matches nothing.
- **No identity leak**: read projections and the create whitelist exclude every
  NC-uid field; a unit guard asserts this so a future edit cannot regress it.
- No secrets, no tokens, no endpoints in this change.

## File Structure

```
lib/
  Portal/
    PortalContributionProvider.php        (new — plain class, no deps)
tests/
  unit/
    Portal/
      PortalContributionProviderTest.php  (new — contract + drift-pin + A4 guard)
openspec/
  changes/portal-contribution/            (this change)
  specs/portal-contribution/spec.md       (capability status stub)
```

## Seed Data

This change adds no register objects (it ships no register edit). The
`portal-identity` design records the nil-UUID seed policy; existing seed
tasks/time-entries carry no `contractorRef`, so the demo portal shows nothing
until an operator backfills real contractor-contact UUIDs — the intended
fail-closed default.

## Trade-offs

- **Both audience methods vs v2-only** — v2-only would be leaner, but the
  registry's v1 fallback path must keep working; two constant-return methods
  cost nothing.
- **One audience vs many** — planninq honestly has one external audience
  (contractor). A client project-view is out of scope because no client
  reference exists in the model; inventing one to pad the manifest would be
  cargo-culting.
- **Project list via `contractorRefs` vs derived join** — the flat ADR-046
  contract cannot express a two-hop task→project join, so the project read is
  scoped directly by `project.contractorRefs`, at the cost of a backfill.
- **No `minTrust`** — planninq contractor data is ordinary business data; an
  elevated threshold would be speculative and would gate honest reads.
- **Plain class vs shared interface package** — an interface import would give
  static safety but create exactly the coupling A1 forbids; duck typing is the
  accepted cost of optionality.
