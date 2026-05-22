# Cross-App Integration & Event Propagation Specification (Delta)

**Status**: in-progress
**Scope**: planix + decidesk + procest + n8n
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — event listeners, cross-app links, event log propagation

## Purpose

Specifies how planix chain system integrates with decidesk (decision approval), procest (case handling), and other apps via CloudEvents. Defines the event schema, listener logic, and propagation rules.

## ADDED Requirements

### Requirement: Decidesk Event Listeners [REQ-009 Variant]

The system SHALL listen to three decidesk event types and react accordingly:

**1. `besluit.vastgesteld` — Decision Approved**

When decidesk publishes this event (raad or college votes on besluit and it passes):
- Planix chain-listener receives CloudEvent (MUST subscribe via Webhooks or event bus)
- Planix creates new `BesluitDeliverableChain` with:
  - `decidesk_besluit_id` from event `data.besluit_id`
  - `besluit_titel` from event `data.titel`
  - `besluit_type` from event `data.type`
  - `vastgesteld_op` from event `data.approved_date`
  - `eigenaar_collegelid_id` from event `data.portfolio_holder_id` (if available; else null)
  - `eigenaar_ambtelijk_id` → null (must be assigned by griffie within 48h)
  - All other fields initialized to defaults
- Planix creates `ChainEventLog.chain_aangemaakt` entry
- Planix sends Nextcloud notification to griffie: "Nieuwe raadsbesluit M-2026-014 vereist assignment van ambtelijk eigenaar. [Link]"

**CloudEvent schema** (decidesk → planix):
```json
{
  "specversion": "1.0",
  "type": "nl.conduction.decidesk.besluit.vastgesteld",
  "source": "https://decidesk/decisions",
  "id": "M-2026-014-vastgesteld-20260312T183000Z",
  "time": "2026-03-12T18:30:00Z",
  "datacontenttype": "application/json",
  "data": {
    "besluit_id": "M-2026-014",
    "titel": "Onderzoek inzet AED's wijkcentra",
    "type": "motie",
    "approved_date": "2026-03-12",
    "portfolio_holder_id": "user-wethouder-gezondheid",
    "raad_decision": true,
    "votes_for": 25,
    "votes_against": 8
  }
}
```

#### Scenario: Motie Approved
- GIVEN decidesk publishes `besluit.vastgesteld` for M-2026-014 on 2026-03-12
- WHEN planix chain-listener processes event
- THEN:
  - New `BesluitDeliverableChain` created with `decidesk_besluit_id = M-2026-014`, `status_overall = niet_gestart`, `voortgang_percentage = 0`
  - Griffie receives notification "Nieuwe raadsbesluit M-2026-014 vereist assignment van ambtelijk eigenaar"
  - Event logged as `ChainEventLog.chain_aangemaakt`

**2. `besluit.amendment` — Decision Amended**

When decidesk publishes this event (raad amends an existing decision; old version archived, new version created):
- Planix receives event with `old_besluit_id` and `new_besluit_id`
- Planix finds existing `BesluitDeliverableChain` where `decidesk_besluit_id = old_besluit_id`
- Planix updates the chain's `decidesk_besluit_id` to `new_besluit_id`
- Planix updates `besluit_titel` and other cached fields from new decision
- Planix does NOT recreate the chain (preserves all links, milestones, progress)
- All `ChainLink` records remain intact
- Planix logs `ChainEventLog.chain_status_gewijzigd` with reason "decidesk amendment"

#### Scenario: Amendment Preserves Chain
- GIVEN chain for RB-2026-009 with 3 links (45% voortgang)
- WHEN decidesk publishes `besluit.amendment` with `old_id = RB-2026-009`, `new_id = RB-2026-009-v2`
- THEN:
  - Chain's `decidesk_besluit_id` updated to RB-2026-009-v2
  - All 3 links preserved
  - Voortgang_percentage preserved (45%)
  - Event logged with amendment details

**3. `besluit.ingetrokken` — Decision Withdrawn**

When decidesk publishes this event (indiener withdraws decision before vote, or college formally rejects it post-vote):
- Planix receives event with `besluit_id`
- Planix finds `BesluitDeliverableChain` where `decidesk_besluit_id = besluit_id`
- Planix updates chain's `status_overall` to `afgewezen_door_college` (if withdrawal is formal rejection)
- Planix does NOT delete the chain (historical record kept)
- Planix logs `ChainEventLog` with type `chain_status_gewijzigd` reason "besluit ingetrokken"
- Planix sends notification to eigenaar and volgers: "Raadsbesluit M-2026-014 is ingetrokken; uitvoering stopgezet"

#### Scenario: Motie Withdrawn Before Vote
- GIVEN motie M-2026-015 (no chain yet, status still concept)
- WHEN indiener withdraws before raadsvergadering, decidesk publishes `besluit.ingetrokken`
- THEN no chain-related action (no chain was ever created)

### Requirement: Planix Task/Project Event Listeners [REQ-009 Variant]

The system SHALL listen to planix-internal events (task/project completion) and propagate to chain rollup.

**Events**:
- `project.status_changed` — When a project's overall status changes (e.g., 100% complete)
- `task.completed` — When an individual task is marked complete

**Listener behavior**:
- On event, planix finds all `ChainLink` records where `linked_entity_id = project_id` or `task_id`
- Updates the link's progress (fetches latest project/task state from database)
- Triggers `ChainRollupService.recalculateProgress(chain_id)` for all affected chains
- Logs `ChainEventLog.link_progress_bijgewerkt` with old/new progress values
- If rollup result changes `voortgang_percentage` or `status_overall`, logs those changes too

#### Scenario: Task Completion Triggers Rollup
- GIVEN chain with linked project P-2026-014 (currently 5/8 tasks done = 62.5% progress)
- WHEN a user completes the 6th task in planix
- THEN:
  - Planix publishes `task.completed` event (internal)
  - Chain-listener updates project progress to 75% (6/8 tasks)
  - `ChainRollupService.recalculateProgress()` fires
  - Chain's `voortgang_percentage` updates from 62% to 75%
  - `ChainEventLog.voortgang_bijgewerkt` entry created with old=62, new=75

### Requirement: Procest Case Event Listeners [REQ-009 Variant]

The system SHALL listen to procest events and propagate zaak status to chain.

**Event**: `zaak.status_changed` — When a procest case changes status (e.g., from `open` to `afgehandeld`)

**Listener behavior**:
- On event, planix finds all `ChainLink` records where `link_type = procest_zaak` and `linked_entity_id = zaak_id`
- Updates the link's progress: zaak `afgehandeld` = 100%, `open` or `in_bewerking` = 0%
- Triggers rollup recalc
- Logs link progress and rollup changes

#### Scenario: Zaak Closed Updates Chain Progress
- GIVEN chain with linked zaak Z-AED-001 (currently 0%, open)
- WHEN procest updates zaak status to `afgehandeld`
- THEN:
  - Chain-listener sees zaak is now 100% (afgehandeld)
  - Rollup recalc fires
  - Chain voortgang% increases (assuming other links remain same)
  - Event logged

### Requirement: Planix Event Publication [REQ-009 Variant]

The system (planix) SHALL publish events when chain state changes, enabling external apps to subscribe.

**Events published by planix**:
- `nl.conduction.planix.chain.voortgang_updated` — When voortgang_percentage changes
- `nl.conduction.planix.chain.status_changed` — When status_overall changes
- `nl.conduction.planix.chain.eindrapportage_klaar` — When final report is generated

**CloudEvent schema** (planix → external):
```json
{
  "specversion": "1.0",
  "type": "nl.conduction.planix.chain.voortgang_updated",
  "source": "https://planix/chains",
  "id": "chain-550e8400-voortgang-2026-05-20T154500Z",
  "time": "2026-05-20T15:45:00Z",
  "datacontenttype": "application/json",
  "data": {
    "chain_id": "550e8400-e29b-41d4-a716-446655440002",
    "decidesk_besluit_id": "RB-2026-031",
    "voortgang_percentage_old": 50,
    "voortgang_percentage_new": 67,
    "status_overall": "in_uitvoering",
    "changed_by": "system",
    "reason": "project P-2026-014 reached 85%"
  }
}
```

**External app subscriptions**:
- **Decidesk**: Subscribes to `chain.voortgang_updated`, `chain.status_changed` → displays chain status badge on besluit detail page
- **Mydash**: Subscribes to all chain events → updates griffie widget and raadslid portal in real-time
- **Procest**: May subscribe to `chain.voortgang_updated` → displays banner in zaak detail "This zaak contributes to [chain_id] ([voortgang]%)"

## Non-Functional Requirements

- **Event reliability**: CloudEvents are idempotent (same event delivered twice → handled gracefully, no side effects)
- **Event ordering**: Relative event ordering is preserved per chain (task-complete→rollup fires before next event on same chain)
- **Latency**: Chain state updates within 500ms of source event
- **Audit**: All event propagations logged in `ChainEventLog`

## Acceptance Criteria

- [ ] Planix subscribes to decidesk `besluit.vastgesteld`, `besluit.amendment`, `besluit.ingetrokken` events
- [ ] `besluit.vastgesteld` triggers automatic chain creation with correct initialization
- [ ] `besluit.amendment` updates `decidesk_besluit_id` and preserves chain state
- [ ] `besluit.ingetrokken` updates chain status to `afgewezen_door_college`
- [ ] Planix publishes internal events on task/project completion
- [ ] Planix subscribes to procest `zaak.status_changed` events
- [ ] Procest zaak updates propagate to linked ChainLink progress
- [ ] Rollup recalc fires on all relevant events, updates voortgang% and status
- [ ] Planix publishes `chain.voortgang_updated`, `chain.status_changed` events for external subscribers
- [ ] Event log contains trace of all propagations (event_type, source_app, actor, timestamp)
- [ ] All event listeners are idempotent (same event twice = same result)

## Notes

- CloudEvents format: RFC 9545, sent via HTTP POST to registered webhook URLs or via event bus (e.g., RabbitMQ, Kafka)
- Webhook registration: Each app (decidesk, procest, mydash) registers webhook URLs in a shared Webhook Registry (OpenRegister service)
- Event subscription logic: Apps declare subscribed event types in their manifest or app configuration
- Retry logic: Failed webhook deliveries retry with exponential backoff (up to 5 retries over 1 hour)
