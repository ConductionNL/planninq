# Audit Trail and Snapshots Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds immutable year-end snapshots and audit trail for compliance auditing

## Purpose

Maintain immutable snapshots of the complete BBV tree per begroting-jaar and a chronological audit trail of all mutations. Snapshots and trails are required for accountant review during jaarrekening audit and for defending changes to provincial/BZK oversight.

## ADDED Requirements

### Requirement: Year-End Snapshot Creation [MVP]
When a begroting year is formally closed, the system MUST create an immutable deep-copy snapshot of the entire tree, preserving structure and data as-of that moment.

**Snapshot scope:**
- All programma's, doelen, activiteiten, indicatoren, metingen for `coalition_period` (e.g., 2026-2030 snapshot from 2026 year-end)
- All frozen measurements (`bevroren=true`)
- Budget koppelingen and realisatie amounts (mirrored from financeq on freeze date)
- Metadata: who froze, when, which raadsbesluit approved final structure

**Snapshot storage:**
- New table `bbv_snapshot` with fields:
  - `id` — UUID
  - `gemeente_orgaan_id` — FK
  - `coalition_period` — e.g. "2026-2030"
  - `snapshot_jaar` — the year being closed (int, e.g. 2026)
  - `status` — "concept" | "vastgesteld" | "afgesloten"
  - `gemaakt_op` — timestamp of snapshot creation
  - `gemaakt_door_id` — FK users
  - `goedgekeurd_door_raad_op` — raadsbesluit approval date (if applicable)
  - `data` — complete JSON blob of tree + metingen + budgets (JSONB field)
  - `sha256_hash` — immutable integrity hash of data

#### Scenario: Freeze begroting 2026 into snapshot
- **GIVEN** begroting 2026 year-end (2027-02-15 after jaarrekening approval)
- **WHEN** concern controller clicks "Freeze 2026 Begroting"
- **THEN** system creates snapshot record with:
  - `snapshot_jaar = 2026`
  - `data = { programma's, doelen, activiteiten, indicatoren, metingen (all frozen), budget_koppelingen }`
  - `gemaakt_op = 2027-02-15T10:30:00Z`
  - `gemaakt_door_id = user-controller-01`
  - `sha256_hash = SHA256(JSON.stringify(data))`
- **AND** all measurements with `peildatum <= 2026-12-31` set to `bevroren=true`
- **AND** notifications sent to portefeuillehouders: "2026 begroting frozen; cannot amend without wijziging-raadsbesluit"

#### Scenario: Snapshot immutability
- **GIVEN** snapshot for 2026 exists
- **WHEN** user attempts to view historical snapshot
- **THEN** can view data in read-only interface; cannot edit or export as mutable JSON
- **AND** integrity hash displayed for audit: "SHA256: a3b2c1d4e5f6..."

### Requirement: Snapshot Diff View [MVP]
Users MUST be able to compare current working version (2027 draft) against previous year's snapshot (2026 closed).

**Diff features:**
- Side-by-side view: "Snapshot 2026" vs "Current 2027"
- Highlights: Added nodes (green), deleted nodes (red), modified nodes (orange)
- Drill into node: shows old vs new values for all fields

#### Scenario: Compare 2026 closed vs 2027 draft
- **GIVEN** closed snapshot for 2026 and new draft for 2027
- **WHEN** user clicks "Compare with 2026 Snapshot"
- **THEN** side-by-side tree view shows:
  - "✅ Programma '03 Veiligheid' (unchanged)"
  - "✏️  Doel '3.2 Veilige openbare ruimte' (looptijd_eind changed: 2029-12-31 → 2028-06-30)"
  - "➕ Activiteit '3.2.4 new activity' (added)"
  - "➖ Activiteit '3.1.5' (deleted)"
- **WHEN** user clicks doel "3.2"
- **THEN** detail pane shows: "looptijd_eind: 2029-12-31 → 2028-06-30"

### Requirement: Audit Trail Recording [MVP]
Every mutation to programma/doel/activiteit/indicator/meting MUST be logged chronologically in an audit trail.

**Audit trail entry fields:**
- `id` — UUID
- `gemeente_orgaan_id` — FK
- `action_date` — timestamp of mutation
- `action_type` — "CREATE" | "UPDATE" | "DELETE" | "FREEZE"
- `resource_type` — "bbv_programma" | "bbv_doel" | "bbv_activiteit" | "bbv_indicator" | "bbv_meting"
- `resource_id` — FK to affected node
- `user_id` — FK users; who made change
- `old_values` — JSON object of previous field values (null for CREATE)
- `new_values` — JSON object of new field values (null for DELETE)
- `raadsbesluit_id` — FK decidesk raadsbesluit if mutation authorized by decision (optional)
- `reason` — free-text explanation (optional)

#### Scenario: Audit trail captures all mutations
- **GIVEN** activiteit "3.2.1" created on 2026-03-15
- **WHEN** title edited from "Straatverlichting" to "LED-verlichting zuidoost" on 2026-05-20
- **AND** status changed from "gepland" to "in_uitvoering" on 2026-08-10
- **THEN** audit trail records 3 entries:
  1. CREATE bbv_activiteit, 2026-03-15, user=Janneke, new_values={titel: "Straatverlichting", status: "gepland", ...}
  2. UPDATE bbv_activiteit, 2026-05-20, user=Janneke, old_values={titel: "Straatverlichting"}, new_values={titel: "LED-verlichting zuidoost"}
  3. UPDATE bbv_activiteit, 2026-08-10, user=Janneke, old_values={status: "gepland"}, new_values={status: "in_uitvoering"}

### Requirement: Audit Trail Export for Accountant [MVP]
Users with role `bbv_beheerder` MUST be able to export audit trail as a PDF for accountant review.

**Export format:**
- PDF with chronological table: Date, User, Action, Resource Type, Resource ID/Name, Old Value, New Value, Associated Raadsbesluit
- Searchable/filterable by date range, user, action type, resource type
- Includes summary: "Total 287 mutations in 2026 (102 CREATE, 145 UPDATE, 40 DELETE, 0 FREEZE)"
- Signed with SHA-256 hash of entire trail for integrity

#### Scenario: Export audit trail 2026
- **GIVEN** boekjaar 2026 audit starts
- **WHEN** concern controller clicks "Exporteer Audit Trail 2026"
- **THEN** PDF generated with:
  ```
  Audit Trail: Gemeente Amsterdam | Boekjaar 2026
  =========================================
  Total mutations: 287 (CREATE: 102, UPDATE: 145, DELETE: 40, FREEZE: 0)
  
  Date       | User      | Action | Type       | ID     | Old Value | New Value | RB
  -----------+-----------+--------+------------+--------+-----------+-----------+-----
  2026-01-10 | Janneke   | CREATE | programma  | prog-  | n/a       | '03 Veil' | RB-25-189
  2026-01-15 | Janneke   | CREATE | doel       | doel-  | n/a       | '3.1'     | —
  2026-03-20 | Pietje    | UPDATE | activiteit | act-32 | "gepland" | "in_uit"  | —
  ...
  
  Integrity Hash: SHA256(audit_trail_json) = a3b2c1d4e5f6...
  ```

### Requirement: Immutable Historical References [MVP]
Once a snapshot is frozen, if referenced entities (doelen, activiteiten) are deleted from current version, snapshot MUST retain historical IDs for audit purposes.

**Historical tracking:**
- Foreign keys in snapshot use historical IDs (not live table keys)
- If current version deletes a doel, snapshot's reference to that doel remains intact (no cascade)
- Audit trail links to snapshot for accountability

#### Scenario: Delete activiteit after snapshot
- **GIVEN** snapshot 2026 includes activiteit "3.2.1"
- **WHEN** activiteit "3.2.1" deleted from 2027 draft
- **THEN** snapshot 2026 still contains activiteit "3.2.1" data; diff view shows "❌ Activiteit '3.2.1' deleted in 2027"
- **AND** audit trail records: "2027-03-10 | DELETE | bbv_activiteit | act-321 | old_values={titel:'LED-vl',...} | new_values=null"

### Requirement: Frozen Measurement Immutability [MVP]
Measurements with `bevroren=true` (included in year-end snapshot) MUST NOT be editable or deletable.

API enforcement:
- GET `/api/objects/planix/bbv_meting/{id}` — returns 200 with data; `bevroren` field visible
- PATCH `/api/objects/planix/bbv_meting/{id}` — if `bevroren=true`, returns 409 Conflict
- DELETE `/api/objects/planix/bbv_meting/{id}` — if `bevroren=true`, returns 409 Conflict

#### Scenario: Cannot edit frozen measurement
- **GIVEN** measurement with `bevroren=true` (included in 2025 jaarrekening)
- **WHEN** user attempts PATCH with new value
- **THEN** API returns 409: `{"error": "meting_bevroren", "message": "Meting opgenomen in vastgestelde jaarrekening, niet meer wijzigbaar"}`

## Non-Functional Requirements

- **Snapshot integrity:** SHA256 hash of snapshot data immutable; can be re-verified by accountant
- **Audit trail retention:** Minimum 7 years per Dutch tax law; deletion requires formal audit approval
- **Performance:** Snapshot creation with 1000+ nodes completes within 30 seconds; diff calculation within 5 seconds
- **Backup:** Snapshots automatically backed up to secure storage (separate from operational DB)
- **Compliance:** Audit trail and snapshots exported as machine-readable (JSON) and human-readable (PDF) formats

## Acceptance Criteria

- [ ] Snapshot creation freezes all relevant data into immutable record
- [ ] Snapshot includes programma's, doelen, activiteiten, indicatoren, metingen, budgets
- [ ] SHA256 integrity hash calculated and stored with snapshot
- [ ] Snapshot data not editable after creation
- [ ] Diff view compares snapshot vs current with ADD/DELETE/MODIFY highlighting
- [ ] Diff shows field-level changes (old → new values)
- [ ] Audit trail records all mutations with timestamp, user, action, old/new values
- [ ] Audit trail associable with raadsbesluiten (if authorized by decision)
- [ ] Export audit trail as PDF with chronological table
- [ ] PDF includes summary and integrity hash
- [ ] Frozen measurements reject PATCH/DELETE with 409 Conflict
- [ ] Historical IDs retained in snapshot when entities deleted from current
- [ ] Snapshot with 1000+ nodes created within 30 seconds
- [ ] Diff calculated within 5 seconds

## Notes

- Initial MVP stores snapshots in same database; separate archival DB may be added for long-term retention
- Audit trail export to PDF requires signature/encryption for formal compliance (may be added in future)
- Automatic snapshot creation on raadsbesluit approval (via webhook) may be added in future
