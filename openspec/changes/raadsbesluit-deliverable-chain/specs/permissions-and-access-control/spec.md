# Permissions & Access Control Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — permission checks on chain visibility, edit access, data filtering

## Purpose

Specifies permission and visibility rules for chains, ensuring that chains for private/beslotenheid decisions are accessible only to authorized parties, and that public chains are visible to intended audiences (raadsleden, citizens, etc.).

## ADDED Requirements

### Requirement: Permission-Based Chain Visibility [REQ-010]

The system SHALL control access to `BesluitDeliverableChain` records based on the visibility of the linked decidesk-besluit.

**Decision visibility states** (from decidesk):
- **openbaar = true, geheim = false**: Public chain, visible to all authenticated users + optionally citizens
- **openbaar = false, geheim = false**: Restricted chain, visible only to:
  - Chain eigenaren (collegelid, ambtelijk)
  - Griffie staff (role: griffier, secretaris)
  - Raadsleden who are listed in decidesk-besluit's `indieners` (submitters)
  - Fractie voorzitters / commissie voorzitters (if applicable)
- **openbaar = false, geheim = true**: Highly restricted (personnel, legal, commercial sensitive):
  - Visible ONLY to: eigenaren, griffie, college members, authorized security role

**API-level enforcement**:
- `GET /api/chains/{id}` — Returns 403 if user lacks permission
- `GET /api/chains?filter=...` — Filters chains by user's permission (private chains excluded)
- `PATCH /api/chains/{id}` — Returns 403 if user is not an eigenaar or griffie

**UI-level enforcement**:
- Raadslid portal does NOT list chains they cannot access (filtered at query time)
- Mydash griffie widget displays ALL chains (griffie can see all)
- Decidesk decision-detail page shows chain badge only if user can access chain

#### Scenario: Besluiten Chain Afgeschermd
- GIVEN raadsbesluit RB-2026-007 over personeelszaak with `openbaar = false, geheim = true`
- WHEN raadslid Pieterse (not in indieners, not griffie) tries to view the chain
- WHEN Pieterse visits `/chains` portal
- THEN RB-2026-007 is NOT listed in "Mijn Beslissingen"
- WHEN Pieterse tries direct URL `/chains/550e8400...` for RB-2026-007
- THEN returns 403 Forbidden: `{"error": "beslotenheid_chain", "reason": "This chain is not accessible to you"}`

#### Scenario: Griffie Sees All Chains
- GIVEN same RB-2026-007 (geheim=true)
- WHEN griffier opens mydash widget
- THEN RB-2026-007 appears in the table (griffie can see all)
- WHEN griffier clicks to view detail
- THEN returns 200 OK with full chain data (griffie has universal visibility)

#### Scenario: Indiener Can Access Restricted Chain
- GIVEN RB-2026-007 (openbaar=false) with indieners=[raadslid-Pieterse]
- WHEN Pieterse visits `/chains` portal
- THEN RB-2026-007 IS listed in "Mijn Beslissingen" (he is the indiener)
- WHEN Pieterse clicks detail
- THEN returns 200 OK (indiener has access)

### Requirement: Ownership & Edit Permissions [REQ-010 Variant]

The system SHALL restrict write-access (edit/delete chains, manage links) to authorized parties.

**Write permissions**:
- **View-only** (read):
  - Indiener of decision
  - Raadsleden who are following the chain
  - Citizens (public chains only)
- **Edit** (read + create/update links, edit milestone dates, write rapportage):
  - Chain's `eigenaar_collegelid_id` (portfolio holder)
  - Chain's `eigenaar_ambtelijk_id` (program manager)
  - Griffie staff (secretaris, griffier role)
- **Delete chain**:
  - Griffie only (only if chain is in `niet_gestart` state; no deletion after execution has begun)

#### Scenario: Ambtelijk Eigenaar Edits Chain
- GIVEN chain M-2026-014 with `eigenaar_ambtelijk_id = pm-gezondheid`
- WHEN pm-gezondheid opens chain detail
- THEN edit buttons are visible: "Add link", "Edit milestone", "Write rapportage", "Update status"
- WHEN pm-gezondheid clicks "Write rapportage" and saves text
- THEN chain's `laatste_rapportage_tekst` is updated, `laatste_rapportage_op` timestamp set, event logged

#### Scenario: Raadslid Cannot Edit
- GIVEN raadslid Pieterse following chain M-2026-014 (read access)
- WHEN Pieterse opens chain detail
- THEN NO edit buttons visible (read-only view)
- WHEN Pieterse tries PATCH `/api/chains/m-2026-014` to update a field
- THEN returns 403 Forbidden: `{"error": "insufficient_permissions", "required": "eigenaar or griffie"}`

### Requirement: Audit Log Permission Filtering [REQ-010 Variant]

The system SHALL filter `ChainEventLog` entries based on user permissions.

**Event log filtering**:
- Public chains: all events visible to all users
- Restricted chains: only visible to users with view access to the chain
- Events are NOT separately permission-controlled; if you can't see the chain, you can't see its event log

#### Scenario: Event Log Hidden from Unauthorized User
- GIVEN RB-2026-007 (geheim=true) with event log containing "Escalatie notification verzonden"
- WHEN unauthorized user tries to access event log
- THEN 403 Forbidden (cascade from chain permission)

### Requirement: OpenRegister RBAC Integration

The system SHALL use Planix's OpenRegister RBAC system (ADR-001) to define permissions.

**Permission model**:
- Standard OpenRegister `_rbac` field on BesluitDeliverableChain
- Roles: `owner`, `editor`, `viewer`
- Default roles inherited from decision's `openbaar` flag + decidesk-besluit indieners list
- Griffie has implicit `owner` role on all chains (via role-based rule)

**Implementation**:
- Chain creation: set `_rbac.owner = [eigenaar_collegelid_id, eigenaar_ambtelijk_id]`
- Chain creation: add indieners from decidesk-besluit to `_rbac.viewer` (if openbaar=false)
- Griffie staff: granted via app-level role, no per-chain ACL needed
- Raadsleden followers: added to `_rbac.viewer` when they click "Follow"

#### Scenario: Automatic RBAC Setup on Chain Creation
- GIVEN decidesk-besluit RB-2026-031 with `indieners=[raadslid-frank, raadslid-anna]`, `openbaar=false`
- WHEN chain is created
- THEN:
  - `chain._rbac.owner = [user-wethouder-mobiliteit, user-pm-verkeerswezen]` (eigenaren)
  - `chain._rbac.viewer = [raadslid-frank, raadslid-anna]` (indieners, since openbaar=false)
  - Griffie role grants universal owner access (via app-level role)

### Requirement: Data Minimization for Public Chains

The system SHALL minimize data exposure for public chains that might contain sensitive details.

**Public chain data exposure**:
- Exposed: Besluit-ID, title, status, voortgang%, milestones (public dates only), project names (if public)
- NOT exposed: Owner names, last rapportage text (if contains sensitive info), escalation notes
- Rapportage text: only exposed if explicitly marked `rapportage_public = true` by eigenaar

#### Scenario: Public Raadsbesluit with Sensitive Rapportage
- GIVEN RB-2026-031 (openbaar=true) with `laatste_rapportage_tekst = "Budget allocation to contractor X, pending security vetting"`
- WHEN citizen opens chain detail
- THEN rapportage section shows "Last rapportage [date]" but NO text (flagged as internal-only)
- WHEN eigenaar wants to expose the rapportage
- THEN eigenaar checks "Make rapportage public in chain" → `rapportage_public = true` → text becomes visible

## Non-Functional Requirements

- **Performance**: Permission checks complete in <100ms (cached RBAC lookups)
- **Consistency**: All visibility rules enforced uniformly (API + UI + exports)
- **Audit**: All permission-denied attempts logged (with user, resource, timestamp)

## Acceptance Criteria

- [ ] Public chains (openbaar=true) visible to all authenticated users
- [ ] Restricted chains (openbaar=false) visible only to eigenaren, griffie, indieners
- [ ] Geheim chains (openbaar=false, geheim=true) visible only to eigenaren, griffie, college
- [ ] GET /api/chains returns 403 if user lacks permission
- [ ] GET /api/chains?filter=... filters out inaccessible chains
- [ ] PATCH /api/chains returns 403 if user is not eigenaar or griffie
- [ ] Raadslid portal does NOT list inaccessible chains
- [ ] Mydash griffie widget shows ALL chains (griffie has universal access)
- [ ] Event log entries not visible if user cannot access chain
- [ ] OpenRegister RBAC correctly initialized on chain creation
- [ ] Griffie staff granted via app-level role (no per-chain setup)
- [ ] Raadsleden following a chain are added to `_rbac.viewer`
- [ ] Permission-denied attempts logged in audit trail

## Notes

- Permission enforcement uses Planix's `AuthorizationService` (existing OpenRegister service)
- All permission checks are centralized in `ChainAuthorizationHandler` (service)
- UI respects permission state and hides edit buttons/actions from non-editors
- Cross-app chains (references to decidesk-besluit) inherit visibility from the source decision
