---
kind: config
---

# Proposal: portal-identity

**Tracking:** Conduction/planix#19 (Wave-3 portaliq contribution — leave open).

## Summary

Add the portal-subject scoping identity that planix currently lacks: a UUID
domain-ref property on the three schemas an external contractor needs to read
or write. `task` and `timeEntry` each gain a `contractorRef` (`format: uuid`)
alongside their existing Nextcloud-uid fields (`assignedTo`, `user`); `project`
gains a `contractorRefs` (`uuid[]`) alongside `members`. Nothing is removed —
the NC-uid fields stay exactly as they are for internal dev/IT teams. This is
the **config** link of the planix ADR-046 portal chain; the **code** link
(`portal-contribution`) ships the provider that scopes portal reads by these
new refs and depends on this change.

## Motivation

ADR-046 makes portaliq the ONE external portal for people WITHOUT Nextcloud
accounts, and its contribution contract (v2.1) amendment A4 is categorical:
portal scoping MUST use UUID *domain-object* references, never Nextcloud user
ids — because an external subject has no NC account by premise. Planix scopes
all work by NC uid today: `task.assignedTo`, `timeEntry.user` and
`project.members` are all NC user ids (verified at HEAD). There is therefore no
property portaliq can scope an external contractor's reads by. Adding one — a
distinct UUID ref that lives *alongside* the NC-uid field — is the minimum,
non-destructive precondition for any planix portal surface.

## Affected Projects

- [x] Project: `planix` — additive `contractorRef` (`task`, `timeEntry`) and `contractorRefs` (`project`) properties in `lib/Settings/planix_register.json`; register + touched-schema + `appinfo/info.xml` version bumps. No code, no routes, no UI.

## Scope

### In Scope

- `task.contractorRef` — `type: string`, `format: uuid`, title "Contractor",
  optional, sitting alongside `assignedTo`.
- `timeEntry.contractorRef` — same shape, alongside `user`.
- `project.contractorRefs` — `type: array` of uuid strings, title
  "Contractors", optional, alongside `members` (a contractor is visible on many
  projects and a project has many contractors).
- Version bumps so the version-gated OpenRegister import picks the properties
  up: `task` 0.1.3 → 0.1.4, `timeEntry` 0.1.2 → 0.1.3, `project` 0.1.2 → 0.1.3,
  register 0.2.5 → 0.2.6 (`info.version` + `registers.planix.version`),
  `appinfo/info.xml` 0.2.9 → 0.2.10.

### Out of Scope

- The provider class, the manifest, and any read/create scoping — that is the
  dependent `portal-contribution` change (`kind: code`).
- Removing, renaming or repurposing any NC-uid field — explicitly forbidden;
  internal teams keep `assignedTo` / `user` / `members`.
- Backfilling `contractorRef` onto existing objects, and any client
  project-view (no client reference exists in the planix model — not invented).
- Changing OpenRegister authorization blocks — portaliq performs the scoped
  read server-side; the register's NC-internal RBAC is untouched.

## Approach

Additive, non-destructive remap: rather than repurpose the NC-uid fields (which
would break every internal team that relies on them), a NEW uuid domain-ref
property is added next to each. The provider (next change) scopes portal reads
EXCLUSIVELY by the new property; a row whose `contractorRef` is unset is
invisible to the portal — fail-closed. Backfilling the refs onto live objects
is a documented follow-up, deliberately not in this change. The import is
version-gated, so every touched version is bumped in the same edit.

## Chain narration

This is link 1 of 2:

1. **portal-identity** (this change, `kind: config`) — adds the UUID scoping
   properties to the register.
2. **portal-contribution** (`kind: code`, `depends_on: [portal-identity]`) —
   ships `OCA\Planix\Portal\PortalContributionProvider`, which reads
   `contractorRef` / `contractorRefs`. It is meaningless until these properties
   exist, hence the dependency.

## New Dependencies

None. This is a JSON-only, additive schema change.

## Impact

- `lib/Settings/planix_register.json` — three additive properties + version
  bumps. Existing objects and seeds stay valid (`contractorRef` absent = valid).
- `appinfo/info.xml` — version bump so the repair-step import re-runs.
- No routes, controllers, services, frontend, or tests owned by this change
  (the property contract is pinned by the `portal-contribution` provider test's
  drift-pin, which `depends_on` this change).

## Cross-Project Dependencies

None. `portal-contribution` in this same repo depends on this change; no other
repo is affected at build or install time.

## Risks

### Risk 1: Version-gated import misses the new properties

**Severity:** Low — **Mitigation:** the register, all three touched schema
versions, and `appinfo/info.xml` are bumped in the same edit; JSON validity is
verified mechanically (`python3 -c "import json; json.load(...)"`).

### Risk 2: A future edit renames or drops a contractorRef property

**Severity:** Medium — **Mitigation:** the `portal-contribution` provider test
carries a register drift-pin that asserts each property exists with the right
type; a rename fails that suite loudly instead of silently scoping portal reads
to nothing.

## Rollback Strategy

Revert the register JSON and the `info.xml` bump. The properties are additive
and optional, so no object data is lost — existing rows simply keep no
`contractorRef`. Portaliq, finding the provider reading a now-absent property,
scopes every contractor read to nothing (fail-closed); planix itself is
unaffected.
