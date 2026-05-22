# Permissions and Public View Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds role-based permissions and public read-only view of BBV tree

## Purpose

Enforce role-based access to programma tree management and measurements; provide a public read-only view of vastgesteld programma's and KPI trends for citizens, media, and external stakeholders (Woo compliance).

## ADDED Requirements

### Requirement: Role-Based Access Control [MVP]
The system MUST enforce role-based permissions on BBV tree operations.

**Roles and permissions:**

| Role | Permission | Operations |
|------|-----------|------------|
| `bbv_beheerder` | Full Admin | Create/edit/delete programma's, doelen, activiteiten; approve status changes; freeze measurements; export audits |
| `bbv_indicator_eigenaar` | Measurement Entry | Register/edit metingen for assigned indicators only |
| `bbv_portefeuillehouder` | Portfolio Owner | Edit own programma's; approve doelen/activiteiten within portfolio; cannot delete or export |
| `beleidsmedewerker` | Read + Limited Edit | View full tree; add activiteiten/doelen within assigned doel; cannot approve/freeze |
| `authenticated_user` | Read-Only | View tree (except concept nodes); view measurements; read-only access to filters/search |
| `anonymous` | No access | Redirected to public view |

#### Scenario: Indicator owner scope enforcement
- **GIVEN** user Janneke is `bbv_indicator_eigenaar` for 4 indicators in programma "06 Sport"
- **WHEN** she attempts `POST /api/objects/planix/bbv_meting` for an indicator outside her scope
- **THEN** API returns 403 Forbidden: `{"error": "not_indicator_owner", "message": "You are not authorized to edit measurements for this indicator"}`

#### Scenario: Portfolio owner limited edit
- **GIVEN** user Wethouder is `bbv_portefeuillehouder` for programma "03 Veiligheid"
- **WHEN** she edits doel "3.2" and adds activiteit "3.2.5"
- **THEN** edit succeeds (within portfolio); cannot delete the programma itself

#### Scenario: Authenticated user reads concept data
- **GIVEN** programma "11 Duurzaamheid" with status "concept"
- **WHEN** raadslid (authenticated but no admin role) opens BBV tree
- **THEN** concept programma is hidden; only "vastgesteld" programma's visible

### Requirement: Public Read-Only View [MVP]
A shareable, unauthenticated URL MUST provide read-only access to vastgesteld programma's and KPI trends without concept data or budget detail.

**Public view rules:**
- Show ONLY programma's with status `"vastgesteld"` or `"in_uitvoering"`
- Show doelen, activiteiten, indicatoren (no budget detail below aktiviteit level)
- Hide fields: `mutatie_grondslag`, `bedrag_begroot` (activiteit-level budgets), `bedrag_realisatie`, user names
- Frozen measurements only (no draft/unfrozen metingen visible)
- No "Edit" / "Add" / "Delete" buttons; completely read-only UI

**URL format:**
- `/planix/bbv/public/{gemeente-slug}` — e.g. `/planix/bbv/public/amsterdam`
- URL shareable; no authentication required
- Optional: Restrict via share token with expiry date

#### Scenario: Public views vastgesteld programma's only
- **GIVEN** gemeente Amsterdam with 3 vastgesteld programma's ("03 Veiligheid", "07 Mobiliteit", "05 Onderwijs") and 1 concept ("11 Duurzaamheid")
- **WHEN** burger opens `/planix/bbv/public/amsterdam`
- **THEN** sees 3 vastgesteld programma's; "11 Duurzaamheid" hidden entirely

#### Scenario: Public view hides budget detail
- **GIVEN** vast gesteld programma "03 Veiligheid" with activiteiten and budgets
- **WHEN** burger opens public view and expands activiteit "3.2.1 Straatverlichting"
- **THEN** sees activiteit title, looptijd, status, linked projects; DOES NOT see budget amounts or mutatie_grondslag

#### Scenario: Public view shows frozen KPI's
- **GIVEN** indicator "3.2.1 Aantal inbraken" with measurements from 2024 (frozen), 2025 (frozen), 2026 (draft, not frozen)
- **WHEN** burger views public trend chart
- **THEN** chart shows 2024 + 2025 data only; 2026 draft measurement hidden

### Requirement: Share Token with Expiry [MVP]
Users MUST be able to generate time-limited shareable links to public view, optionally scoped to single programma.

**Share token fields:**
- `token` — random slug (e.g. "abc123def456")
- `expires_at` — date/time (e.g. 30 days from creation)
- `scope` — "all" (all vastgesteld programma's) or programma_id (single programma)
- `created_by` — user who generated token

#### Scenario: Generate share token for public view
- **GIVEN** portefeuillehouder wants to share programma "07 Mobiliteit" with townhall visitors
- **WHEN** she clicks "Share Public" and selects "30-day token"
- **THEN** generates URL: `https://planix.gemeente.nl/bbv/public/token/abc123def456`
- **WHEN** token expires after 30 days
- **THEN** URL returns 404 (or shows expiry message)

### Requirement: Audit Logging for Permission Checks [MVP]
Every access attempt (successful or denied) to protected resources MUST be logged for compliance auditing.

**Audit log entry:**
- User, timestamp, action (VIEW, CREATE, EDIT, DELETE, EXPORT), resource type, resource ID, result (ALLOWED / DENIED), reason (if denied)

#### Scenario: Failed access attempt logged
- **GIVEN** user Janneke tries to delete programma "03 Veiligheid" without bbv_beheerder role
- **WHEN** she clicks "Delete"
- **THEN** API returns 403; audit log records: `Janneke | 2026-05-22T14:30:00Z | DELETE | bbv_programma | prog-03 | DENIED | Requires bbv_beheerder role`

### Requirement: Raadslid and Burger Access [MVP]
Raadsleden (council members) and burgers MUST have different access levels.

| User Type | View | Edit | Download | Comments |
|-----------|------|------|----------|----------|
| Raadslid (authenticated) | Full tree (all doelen/activiteiten) | Read-only | Download reports (freeze snapshots, audit trail on request) | Collegiaal toezicht; view own motions linked to tree |
| Burger (unauthenticated) | Public view only (vastgesteld + frozen data) | None | None | Via `/bbv/public/{gemeente}` URL |

#### Scenario: Raadslid views full tree with read-only
- **GIVEN** raadslid opens planix BBV section (authenticated via saml2)
- **WHEN** she navigates tree
- **THEN** sees ALL programma's (concept + vastgesteld); can read activiteit/budget detail; no edit buttons

#### Scenario: Burger views public programma
- **GIVEN** burger opens public view
- **WHEN** she searches for "Veiligheid"
- **THEN** sees only vastgesteld "Veiligheid" programma; limited detail; no login option

## Non-Functional Requirements

- **Authorization:** All API endpoints check permission before DB query (fail fast); no data leaked in error messages
- **Audit trail:** Permission checks logged with O(1) overhead (async to avoid blocking API response)
- **Session security:** Public share tokens not tied to user sessions; expires based on timestamp
- **Caching:** Public view HTML cached per gemeente (5-min TTL); cache cleared on programma status change
- **Rate limiting:** Public view not rate-limited (shared with external users); authenticated API endpoints use standard rate limit

## Acceptance Criteria

- [ ] `bbv_beheerder` can create/edit/delete all nodes
- [ ] `bbv_indicator_eigenaar` can edit measurements for assigned indicators only
- [ ] `bbv_portefeuillehouder` can edit own programma's; cannot delete
- [ ] Unauthenticated user cannot access authenticated tree; redirected to public
- [ ] Public view shows only vastgesteld/in_uitvoering programma's
- [ ] Public view hides concept programma's, budget detail, unfrozen measurements
- [ ] Share token with expiry works and returns 404 after expiry
- [ ] Share token can be scoped to single programma
- [ ] Audit log records all access attempts (allowed/denied)
- [ ] Failed permission checks return 403 (no data leakage)
- [ ] Raadslid sees full tree read-only; no edit buttons
- [ ] Burger sees public view only; cannot access authenticated tree
- [ ] Public view renders without authentication
- [ ] Public view cached with 5-min TTL

## Notes

- Initial MVP does not support fine-grained field-level permissions (e.g., hide budget_realisatie from raadsleden); all-or-nothing per node type
- Role assignment currently manual (admin configures in user profile); SAML/AD group mapping may be added in future
- Public share tokens stored in memory (not persistent); restart clears tokens (acceptable for MVP)
