# Griffie Dashboard Widget Specification (Delta)

**Status**: in-progress
**Scope**: mydash
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — griffie overview widget, KPI tiles, drill-down table

## Purpose

Specifies a mydash dashboard widget for griffie staff that provides a high-level summary of all active decision chains, highlighting overdue decisions and key metrics.

## ADDED Requirements

### Requirement: Griffie Chain Overview Widget [REQ-008]

The system SHALL provide a mydash dashboard widget titled "Raadsbesluiten Uitvoering" (Council Decision Execution) that displays:

**1. KPI Tiles** (top section, 3 tiles):
- **Actieve Besluiten**: Count of chains with `status_overall` in [niet_gestart, in_uitvoering, on_hold] (not afgerond or afgewezen)
- **Vertraagde Besluiten**: Count of chains with `status_overall = vertraagd`
- **Deze Week Deadline**: Count of chains where `uiterste_rapportage_datum` falls between today and today+7 days

Each tile displays:
- Large number (e.g., "47")
- Label (e.g., "Actieve Besluiten")
- Trend indicator (↑/↓/→) showing change from last week
- Click-through to filtered table (optional)

**2. Traffic-Light Table** (main section):
- Rows: All chains (or filtered subset), max 50 per page
- Columns (sortable/filterable):
  - **Status badge** (color: niet_gestart=gray, in_uitvoering=blue, afgerond=green, vertraagd=red, on_hold=orange)
  - **Besluit-ID & Titel** (truncated, click → chain detail)
  - **Eigenaar Collegelid** (portfolio holder name)
  - **Eigenaar Ambtelijk** (program manager name, clickable for contact)
  - **Voortgang %** (progress bar + numeric)
  - **Uiterste Rapportage** (date, with warning color if <14d away)
  - **Volgers** (count of raadsleden following)
  - **Laatste Rapportage** (date, click → show text in tooltip)
  - **Actions** (eye icon = view detail, envelope = notify volgers, pencil = edit)

**Default sorting**: Vertraagde chains first (status=vertraagd), then by uiterste_rapportage_datum ascending

**Filtering options** (sidebar or top filter bar):
- By status (multi-select: niet_gestart, in_uitvoering, afgerond, vertraagd, on_hold)
- By portfolio/commissie (if chains are tagged)
- By deadline urgency (1-7 days, >7 days, no deadline)
- By eigenaar ambtelijk (typeahead)

**Export**: Button to export current view to Excel (uses LTA-export endpoint)

#### Scenario: Griffie Opens Dashboard
- GIVEN 47 active chains (5 vertraagd, 12 in_uitvoering, 8 niet_gestart, 22 mixed)
- WHEN griffie opens mydash Raadsbesluiten widget
- THEN:
  - KPI tiles show: Actieve=45 (excluding afgerond/afgewezen), Vertraagde=5, Deze Week Deadline=8
  - Table shows all 47 chains, vertraagde ones first (sorted by status DESC, then by deadline)
  - Griffie clicks filter "Vertraagd", table shows only 5 chains
  - Griffie clicks on M-2026-014 row → navigates to chain detail page in new tab

#### Scenario: Deadline Urgency Highlighting
- GIVEN chain RB-2026-031 with `uiterste_rapportage_datum = 2026-05-28`, today is 2026-05-22
- WHEN widget displays the chain
- THEN deadline cell shows "28-05" in orange/red background (6 days away, <7d warning) with icon ⚠

### Requirement: Quick Actions on Widget

The system SHALL provide quick-action buttons on the widget for common griffie tasks:

**Actions**:
1. **View Detail** (eye icon): Navigates to chain detail page (read-write for griffie)
2. **Notify Volgers** (envelope icon): Opens dialog to send custom message to all raadsleden following the chain
3. **Edit Chain** (pencil icon): Opens inline edit dialog for chain fields (eigenaar, uiterste_rapportage_datum, tags, toelichting)
4. **Escalate** (⚡ icon, visible only if chain is vertraagd): Sends escalation e-mail to college + directie

#### Scenario: Notify Raadsleden
- GIVEN chain M-2026-014 is vertraagd with 2 raadsleden following
- WHEN griffie clicks envelope icon
- THEN:
  - Dialog opens: "Send notification to 2 followers of M-2026-014"
  - Griffie types message: "Onderzoekscommissie ronde uitgesteld tot juli i.v.m. zomervoorbereiding"
  - Clicks "Send" → message delivered via raadsleden's notify_kanaal (e-mail/Talk/app)
  - Event logged: `ChainEventLog.escalatie_notification_verzonden`

### Requirement: Drag-Drop Reorder (Optional)

The system MAY support drag-drop reordering of chains in the table for custom prioritization (saved per user in mydash preferences).

#### Scenario: Griffie Prioritizes Chains
- GIVEN griffie's custom order: [M-2026-014, RB-2026-031, RB-2026-009, ...]
- WHEN griffie drags RB-2026-009 above M-2026-014
- THEN:
  - Mydash saves custom order in user preferences (not stored in chain data)
  - Next time griffie opens widget, custom order is restored
  - Other griffie staff see default sort order (not affected by custom order)

## Non-Functional Requirements

- **Performance**: Widget loads in <2 seconds; filters execute in <500ms
- **Real-time updates**: Table refreshes on voortgang/status changes (via WebSocket or polling, max 5s stale)
- **Accessibility**: Colors used for status NOT the sole indicator; text labels present (WCAG 2.2 AA)
- **Responsiveness**: Widget adapts to tablet/mobile (table may collapse to cards on narrow screens)

## Acceptance Criteria

- [ ] Widget displays 3 KPI tiles: Actieve, Vertraagde, Deze Week Deadline
- [ ] KPI tiles show correct counts
- [ ] KPI tiles include trend indicators (↑/↓/→)
- [ ] Main table displays all chains with correct columns
- [ ] Table sorts by status (vertraagd first), then by deadline
- [ ] Status badges display with correct colors
- [ ] Voortgang% shown as progress bar + numeric
- [ ] Uiterste_rapportage_datum warnings appear for <14d deadlines
- [ ] Filters work: status, portfolio, deadline, eigenaar
- [ ] Quick actions: View Detail, Notify Volgers, Edit, Escalate
- [ ] Export to Excel works (uses LTA export)
- [ ] Widget updates in real-time (<5s latency on chain updates)
- [ ] Accessibility: color-blind-safe, screen-reader compatible

## Notes

- Widget is a mydash tile; part of standard griffie dashboard template
- Griffie access controlled by OpenRegister RBAC (role: griffier or secretaris)
- Widget data is read-only to external users (decidesk, procest); write actions restricted to planix/griffie
- Trend calculation: compares KPI counts from 7 days ago vs. today (requires historical snapshot log, optional MVP)
