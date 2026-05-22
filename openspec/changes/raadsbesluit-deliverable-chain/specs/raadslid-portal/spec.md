# Raadslid Portal Specification (Delta)

**Status**: in-progress
**Scope**: mydash + planix
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — raadslid follow portal, notification preferences

## Purpose

Specifies a raadslid-facing portal for following decisions, viewing execution status in real-time, and managing notification preferences per decision.

## ADDED Requirements

### Requirement: Raadslid Follow Portal [REQ-005]

The system SHALL provide a mydash portal tile (or separate page) titled "Mijn Beslissingen" (My Decisions) where a raadslid can:
1. View all chains they are following (via `RaadslidVolgVoorkeur`)
2. View all chains for which they are the `indiener` (submitter) of the original decidesk-besluit
3. See live `voortgang_percentage`, `status_overall`, `uiterste_rapportage_datum`, next milestone
4. Click through to chain detail (read-only view)
5. Manage follow status and notification preferences per chain

**Portal sections**:

**A. "Mijn Moties en Ingediende Besluiten"** — chains where user is listed in decidesk-besluit indieners
- Columns: Besluit-ID, Titel, Status-badge, Voortgang%, Volgers-count
- Sortable by: ID, datum, status, voortgang
- Filterable by: status (niet_gestart, in_uitvoering, afgerond, vertraagd)
- Action: Click row → open chain detail (read-only); "Follow this decision" button available

**B. "Waarop Ik Volg"** — chains with `user_id` in `volgers_user_ids`
- Same columns + additional "Volg sinds [date]" column
- Action: Click row → open chain detail; "Unfollow" button available; "Notification preferences" link

**Portal features**:
- **Real-time progress**: Voortgang% updates via WebSocket or polling (max 5s stale)
- **Status badges**: Color-coded (niet_gestart=gray, in_uitvoering=blue, afgerond=green, vertraagd=red, on_hold=orange)
- **Timeline preview**: Show next 3 upcoming milestones below chain summary (titles + planned dates)
- **Deadline warning**: If `uiterste_rapportage_datum < today + 14 days`, show warning badge
- **Notification bell**: Quick toggle for "mute all notifications for this decision"

#### Scenario: Raadslid Views Their Motions
- GIVEN raadslid Frank is the indiener of motie M-2026-014 and also following raadsbesluit RB-2026-031
- WHEN Frank opens "Mijn Beslissingen" portal
- THEN he sees 2 sections:
  - **Mijn Moties**: M-2026-014 (Status: In Uitvoering, 23%, 2 volgers), RB-2026-031 (Status: In Uitvoering, 67%, 3 volgers — actually RB not motie, but if he's indiener it appears here)
  - **Waarop Ik Volg**: any additional chains he follows but didn't submit
- Clicks M-2026-014 → chain detail page with progress bar, milestones, linked projects, last rapportage text (read-only)

#### Scenario: Follow a Decision
- GIVEN raadslid Frank views chain for RB-2026-031 (which he did not submit, but is curious about)
- WHEN Frank clicks "Volg deze besluit" and selects "E-mail notificaties op mijlpalen en afronding"
- THEN:
  - A `RaadslidVolgVoorkeur` record is created: `user_id = frank, chain_id = RB-2026-031, notify_op_mijlpaal = true, notify_op_afronding = true, notify_kanaal = email`
  - Frank's name is added to chain's `volgers_user_ids`
  - Frank sees RB-2026-031 in "Waarop Ik Volg" section with "Volg sinds [today]"

### Requirement: Notification Preferences per Chain [REQ-005 Variant]

The system SHALL allow each raadslid to configure **per-chain** notification preferences via `RaadslidVolgVoorkeur`.

**Notification preference fields**:
- `notify_op_mijlpaal` (bool, default true) — notify when milestone reached or missed
- `notify_op_vertraging` (bool, default true) — notify when chain status changes to `vertraagd`
- `notify_op_afronding` (bool, default true) — notify when chain status changes to `afgerond`
- `notify_kanaal` (enum: email | nextcloud-talk | app-only, default email) — delivery channel

**Preference dialog** (shown when clicking "Notification preferences" or when first following):
```
□ Notify when milestone is reached/missed
□ Notify when decision becomes overdue (vertraagd)
□ Notify when decision is completed (afgerond)

Delivery channel:
  ◯ E-mail
  ◯ Nextcloud Talk (@mention)
  ◯ In-app only (no external notification)
```

**Notification implementation**:
- E-mail: Sent via Nextcloud mail (with template subject/body)
- Nextcloud Talk: Bot mention to user (if Talk integration available)
- App-only: Nextcloud notification center only (no external delivery)

#### Scenario: Customize Notification Prefs
- GIVEN Frank is following RB-2026-031 with default prefs (all true, email channel)
- WHEN Frank clicks "Preferences" and unchecks "Notify on completion" + selects "App-only"
- THEN:
  - `RaadslidVolgVoorkeur.notify_op_afronding = false`
  - `RaadslidVolgVoorkeur.notify_kanaal = app-only`
  - Frank will NOT receive notification when chain reaches `afgerond` status
  - Frank WILL receive app notification on milestone/vertraging (if those remain checked)

### Requirement: Chain Detail View (Raadslid)

The system SHALL provide a read-only chain detail page accessible to raadsleden, showing:
- **Header**: Besluit-ID, Title, Status badge, Voortgang% (progress bar), Uiterste rapportage datum (with warning if <14d)
- **Timeline**: List of all milestones with status (gepland | bereikt | gemist | opgeschoven), planned vs. actual dates
- **Linked Projects/Cases**: List of all `ChainLink` records with:
  - Entity type + name + link to target app (planix project, procest case)
  - Is this link critical-path? (visual indicator)
  - Progress of this entity (% or status)
- **Last Rapportage**: Section showing `laatste_rapportage_op` timestamp + `laatste_rapportage_tekst` (owner's update)
- **Volgers**: Count of raadsleden following; raadslid can see only count, not names (privacy)
- **Actions**: "Follow/Unfollow" toggle, "Notification preferences" link

**Permissions**:
- Visible to: indiener, raadsleden following chain, griffie, eigenaar, college with appropriate role
- NOT visible to public if chain's decidesk-besluit has `openbaar = false`
- Returns 403 if user lacks permission

#### Scenario: Raadslid Opens Chain Detail
- GIVEN Frank opens chain for M-2026-014
- THEN he sees:
  - M-2026-014 | "Onderzoek AED's wijkcentra" | IN UITVOERING | 23% progress bar
  - Milestone 1: "Onderzoekscommissie onderzoek afgerond" | Geplaned: 30-06-26, Status: Gepland
  - Link 1: "Zaak: AED-plaatsingsprotocol" (procest) | 0% (open)
  - Last rapportage: "2026-05-15 — Onderzoekscommissie heeft 3 van 4 wijkcentra bezocht..."
  - Volgers: 2 raadsleden
  - "Volg deze besluit" button (if not following) OR "Ik volg deze besluit" + "Unfollow" (if following)

## Non-Functional Requirements

- **Real-time updates**: Raadslid portal updates voortgang% within 5 seconds of change (WebSocket or polling)
- **Performance**: Portal loads in <2 seconds (lazy-load large timeline if >20 milestones)
- **Privacy**: Raadsleden cannot see each other's identity in "volgers" list (count only)
- **Accessibility**: Portal meets WCAG 2.2 AA; status badges include text labels (not just colors); timeline is keyboard-navigable

## Acceptance Criteria

- [ ] Raadslid portal shows "Mijn Moties/Besluiten" section with indiener chains
- [ ] Raadslid portal shows "Waarop Ik Volg" section with followed chains
- [ ] Status badges display correctly (color + text)
- [ ] Voortgang% displayed as progress bar and numeric value
- [ ] Deadline warning appears when uiterste_rapportage_datum < 14 days
- [ ] Timeline shows upcoming 3 milestones in compact form
- [ ] "Follow/Unfollow" button toggles RaadslidVolgVoorkeur records
- [ ] Notification preferences dialog allows per-chain configuration
- [ ] Chain detail page is read-only (no edit buttons for raadsleden)
- [ ] Chain detail page respects `openbaar` permissions
- [ ] Real-time updates work (voortgang% updates within 5s of change)
- [ ] Accessibility: color-blind-safe status badges, keyboard navigation

## Notes

- Portal is a mydash tile (or standalone page accessible from mydash navigation)
- "Mijn Moties" list is derived from decidesk-besluit `indieners` field (cross-app query)
- Raadsleden cannot edit chain data (read-only view); only griffie/eigenaar can update chains
- Follow/Unfollow actions are user-driven; no auto-subscription
