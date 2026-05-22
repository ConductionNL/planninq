# Programma-Raadsbesluit Linking Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds linking of programma's to decidesk raadsbesluiten for legal authorization tracking

## Purpose

Track which raadsbesluit (council decision) authorized a programma and any subsequent amendments. Once a programma is `vastgesteld` (approved), its structure is locked; further changes require a new raadsbesluit.

## ADDED Requirements

### Requirement: Programma Approval via Raadsbesluit [MVP]
A bbv_programma in `concept` status MUST be linked to a decidesk raadsbesluit to transition to `vastgesteld` status.

**Fields:**
- `vastgesteld_in_raadsbesluit_id` — FK to decidesk raadsbesluit entity (required if status = "vastgesteld")
- `vastgesteld_door_raad_op` — date (usually raadsbesluit's `datum_vaststelling`)
- Approval flags locked fields: once vastgesteld, doelen/activiteiten cannot be added/removed without a new wijziging-raadsbesluit

#### Scenario: Approve programma via raadsbesluit
- **GIVEN** programma "04 Onderwijs" in status "concept" with 6 doelen
- **WHEN** user clicks "Approve" and selects decidesk raadsbesluit "RB-2025-189 Vaststellen programmabegroting 2026"
- **THEN** programma status flips to "vastgesteld"; `vastgesteld_door_raad_op` = RB's datum; header shows "Approved by RB-2025-189 on 2025-11-08"
- **AND** "Add Doel" button is disabled with tooltip "Requires wijziging-raadsbesluit"

#### Scenario: Modify vastgesteld programma requires new raadsbesluit
- **GIVEN** vastgesteld programma "04 Onderwijs"
- **WHEN** user attempts to add new doel "4.7" without linking a wijziging-raadsbesluit
- **THEN** API returns 422 Unprocessable Entity: `{"error": "needs_wijziging_raadsbesluit", "message": "Wijziging vastgesteld programma vereist koppeling aan een wijzigings-raadsbesluit"}`

### Requirement: Amendment Raadsbesluit Linking [MVP]
Users MUST be able to link programma amendments to separate raadsbesluiten (wijziging-besluiten).

**Amendment tracking:**
- New field `wijziging_raadsbesluit_ids` — array of FK raadsbesluiten that amended the programma after vastgestelling
- Amendment modal shows: which doelen/activiteiten changed, under which wijziging-RB, on which date

#### Scenario: Amend vastgesteld programma
- **GIVEN** vastgesteld programma "07 Mobiliteit"
- **WHEN** user clicks "Propose Amendment" and adds doel "7.5 E-mobility pilots"
- **THEN** form requires selecting a wijziging-raadsbesluit (e.g. "RB-2025-210 Programmabegroting wijziging")
- **WHEN** user submits
- **THEN** doel saves; `wijziging_raadsbesluit_ids` array updated; amendment logged with timestamp and wijziging-RB reference

### Requirement: Raadsbesluit Reference Display [MVP]
On programma detail view, raadsbesluit links MUST be prominently displayed.

**Display:**
- Header section shows: "Vastgesteld door raad op [date] via [RB number]"
- "Approved by RB-2025-189" clickable link → opens decidesk raadsbesluit detail in new tab
- List of wijziging-raadsbesluiten (if any) with dates

#### Scenario: Raadsbesluit links on programma header
- **GIVEN** programma "03 Veiligheid" vastgesteld via RB-2025-189
- **WHEN** user opens programma detail
- **THEN** header shows "Vastgesteld door raad op 2025-11-08 via RB-2025-189 (Vaststellen programmabegroting 2026)"
- **AND** user can click "RB-2025-189" to view decision in Decidesk

### Requirement: Decidesk Integration — Programma Link Visibility [MVP]
Decidesk raadsbesluiten MUST display linked programma's and doelen on the besluit detail view.

This is an outbound integration: planix publishes an event when programma status changes, decidesk subscribes and displays the link.

**Event structure:**
```json
{
  "event": "bbv.programma.status_changed",
  "programma_id": "uuid-prog-03",
  "gemeente_orgaan_id": "gm-0363",
  "status_old": "concept",
  "status_new": "vastgesteld",
  "vastgesteld_in_raadsbesluit_id": "rb-2025-189",
  "timestamp": "2025-11-08T14:30:00Z"
}
```

Decidesk listens and may display: "This decision authorizes BBV Programma '03 Veiligheid' (6 doelen, 18 activiteiten)".

#### Scenario: Decidesk shows linked programma
- **GIVEN** decidesk raadsbesluit "RB-2025-189 Vaststellen programmabegroting 2026"
- **WHEN** planix publishes event linking programma "03 Veiligheid"
- **AND** Decidesk user opens RB-2025-189
- **THEN** decision detail shows "Linked Programma's: 03 Veiligheid (doelen, activiteiten) [link to planix]"

### Requirement: Authorization Enforcement [MVP]
Only users with role `bbv_beheerder` or programma's `portefeuillehouder` MAY change programma status or link raadsbesluiten.

#### Scenario: Non-approved user cannot approve programma
- **GIVEN** user Janneke with role `beleidsmedewerker` (not bbv_beheerder)
- **WHEN** she clicks "Approve Programma"
- **THEN** button is disabled; tooltip says "Only BBV Administrator or Portfolio Owner can approve"

## Non-Functional Requirements

- **Audit trail:** Every status change and raadsbesluit link recorded with user, timestamp, and old/new values
- **Workflow state:** Programma statuses follow legal progression: concept → vastgesteld → in_uitvoering → afgesloten (no backward jumps allowed)
- **Constraint enforcement:** API validates that vastgesteld programma rejects structure changes without wijziging-RB
- **Decidesk webhook:** Listen for raadsbesluit deletions; if programma linked to deleted RB, mark as "onduidelijk" status in planix

## Acceptance Criteria

- [ ] Programma can transition from concept to vastgesteld only via linked raadsbesluit
- [ ] Status change triggers event published to Decidesk
- [ ] User cannot add doel to vastgesteld programma without wijziging-RB
- [ ] API returns 422 if user attempts unauthorized structure change
- [ ] Programma header displays raadsbesluit link and date
- [ ] User can click raadsbesluit link to open in Decidesk
- [ ] Amendment raadsbesluiten tracked in array with dates
- [ ] Audit trail records all status changes and RB links
- [ ] Role-based authorization enforced (bbv_beheerder or portefeuillehouder only)
- [ ] Decidesk receives event and can display linked programma
- [ ] Stale RB links (deleted in Decidesk) handled gracefully

## Notes

- Initial MVP does not support "draft amendment" workflow; amendments link to decisions immediately
- Reverse direction (Decidesk adds raadsbesluit to programma) may be added in future; currently one-way from Planix
- Bulk status changes (e.g., "close all programma's for 2022-2025 period") may be added in future planning change
