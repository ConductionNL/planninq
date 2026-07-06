# Design: portal-identity

## Architecture Overview

Planix is a thin OpenRegister client: it owns no database tables, and its whole
data model is the six schemas in `lib/Settings/planix_register.json`, imported
by the repair step via `ConfigurationService::importFromApp()`. Adding a portal
scoping identity therefore means one thing only — three additive properties in
that register JSON, plus the version bumps that make the version-gated import
re-apply them. No PHP, no routes, no UI.

```
lib/Settings/planix_register.json
  task      + contractorRef  (uuid)      alongside assignedTo (NC uid)
  timeEntry + contractorRef  (uuid)      alongside user       (NC uid)
  project   + contractorRefs (uuid[])    alongside members    (NC uid[])
       ↑ read EXCLUSIVELY by the portal-contribution provider (next change)
```

## Declarative-vs-imperative note

This is a purely **declarative** change: the scoping identity is expressed as
schema data (OpenAPI property definitions in the register), interpreted by
OpenRegister and — for the portal — by portaliq. There is no imperative
surface: no service resolves `contractorRef`, no controller reads it, no
migration transforms data. That is the ADR-022 posture (apps consume OR
abstractions) and the ADR-046 posture (portaliq owns the external surface). The
imperative consumer is the provider in the dependent `portal-contribution`
change, and even that is pure data assembly.

## Additive-remap rationale (why not repurpose the NC-uid fields?)

Planix scopes every work item by Nextcloud user id today, verified at HEAD:

| Schema      | NC-uid field (kept)     | New UUID domain-ref (added) |
|-------------|-------------------------|-----------------------------|
| `task`      | `assignedTo` (string)   | `contractorRef` (uuid)      |
| `timeEntry` | `user` (string, req.)   | `contractorRef` (uuid)      |
| `project`   | `members` (string[])    | `contractorRefs` (uuid[])   |

ADR-046 amendment A4 forbids scoping a portal subject by an NC uid: an external
contractor has no Nextcloud account, so `assignedTo`/`user`/`members` can never
name them. The tempting shortcut — repurpose `assignedTo` to hold a UUID — is
rejected because internal dev/IT teams depend on those fields being NC uids
(assignment, the `taskDueSoon` notification recipient is `assignedTo`, time-log
ownership RBAC keys on `user`, project membership RBAC keys on `members`).
Repurposing would be a destructive, regression-causing remap.

So the approach is **additive and non-destructive**: add a NEW property
alongside the NC-uid one. The provider scopes portal reads EXCLUSIVELY by the
new property. The two identity spaces stay cleanly separated — internal teams by
NC uid, external contractors by contact-object UUID.

### Claim names

The portal subject's scoping claim is `contractorRef` (the contractor contact
object UUID, resolved server-side by portaliq's auth edge). The provider maps it
to `task.contractorRef` / `timeEntry.contractorRef` (equality) and
`project.contractorRefs` (array-contains). No other claim is consumed.

### Fail-closed + backfill follow-up

A row whose `contractorRef` is unset matches no contractor and is invisible to
the portal — fail-closed by construction, which is why the property is optional
(not `required`) and no existing object breaks. Backfilling `contractorRef` onto
live tasks/time-entries and `contractorRefs` onto projects (mapping each
contractor contact to the work they may see) is operational data work, NOT a
schema change — it is a **documented follow-up**, deliberately out of this
change. Until an operator backfills, the planix portal section is empty, not
broken.

## API Design

None. No routes, controllers, or endpoints. OpenRegister's existing object API
serves reads/creates; portaliq invokes it server-side with subject scoping
(ADR-022).

## Database Changes

None owned by planix (thin OR client). The register JSON gains three additive,
optional properties; the version-gated import (repair step →
`ConfigurationService::importFromApp()`, `force: false`) applies them on upgrade
because register + schema + `info.xml` versions bump together. No `migration.md`
artifact: no data transformation, no required-field change, no rollback beyond
reverting the JSON.

## Nextcloud Integration

None. No controllers, services, mappers, events, or `Application.php` changes.

## Security Considerations

- **A4 compliance** — the new refs are UUID domain-object references, never NC
  uids. The NC-uid fields are untouched and keep their internal RBAC meaning.
- **No RBAC change** — the register's `authorization` blocks are NOT modified;
  the members/owner/user row-filters that guard internal access stay exactly as
  audited (issues #257–#259). Portaliq performs the scoped portal read as a
  privileged service, applying the provider's `scopeField`; it does not rely on
  these NC-internal blocks.
- **Fail-closed** — unset `contractorRef` ⇒ no portal visibility.
- **Optional, not required** — no existing object is invalidated; no data
  migration is forced.

## File Structure

```
lib/
  Settings/
    planix_register.json    (+ contractorRef/contractorRefs; versions bumped)
appinfo/
  info.xml                  (0.2.9 → 0.2.10)
openspec/
  changes/portal-identity/          (this change)
  specs/portal-identity/spec.md     (capability status stub)
```

## Seed Data

The register embeds demo objects (labels, projects, columns, tasks, time
entries). This change adds **no** demo objects and backfills **none** of the
existing seed rows with a `contractorRef`. Any future portal demo seed MUST use
the nil-UUID placeholder `00000000-0000-0000-0000-000000000000` for
`contractorRef`/`contractorRefs` (self-evidently fake, never colliding with live
data) and MUST keep the property optional. Leaving the existing seeds' refs
unset is the intended fail-closed default: the demo portal shows nothing until
an operator backfills real contractor-contact UUIDs.

## Trade-offs

- **Additive vs repurpose** — additive keeps internal teams working and is
  non-destructive; the cost is one extra property per schema and a backfill
  follow-up. Repurposing would regress internal assignment/RBAC/notifications.
- **`contractorRefs` (array) on project vs a single ref** — a project has many
  contractors and a contractor spans many projects, so the plural array is the
  honest shape; it mirrors `members` (also an array) and lets portaliq
  array-contains scope the project list.
- **Optional vs required** — required would guarantee scoping on every row but
  break every existing object and force a migration; optional keeps the change
  additive and fail-closed.
- **No project scoping without the new prop** — a contractor's project list is
  driven by `project.contractorRefs`, not derived by joining their tasks'
  `project` (portaliq's flat contract cannot express a two-hop join). This keeps
  the read set expressible in one scoped collection at the cost of a backfill.
