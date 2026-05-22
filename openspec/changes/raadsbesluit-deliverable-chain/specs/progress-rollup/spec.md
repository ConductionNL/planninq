# Progress Rollup Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — weighted progress aggregation, status inference

## Purpose

Defines how overall chain progress (`voortgang_percentage`) is computed from the progress of underlying planix projects, procest cases, and milestones. Specifies status transitions and rollup triggers.

## ADDED Requirements

### Requirement: Weighted Progress Calculation [REQ-003]

The system SHALL compute `voortgang_percentage` on a `BesluitDeliverableChain` as a **weighted average of critical-path links only**.

**Calculation algorithm**:

```
# For each ChainLink with is_kritiek_pad = true:
#   link_progress = progress_of_linked_entity
# voortgang_percentage = sum(link_progress) / count(kritiek_links)
# clamp to [0, 100]

# If no critical links exist: voortgang_percentage = 0
```

**Entity-specific progress calculation**:

- **planix_project**: `progress_percent` from project entity (based on task completion: completed_tasks / total_tasks)
- **planix_task**: `progress_percent` from task entity (0 if open, 100 if completed)
- **procest_zaak**: binary: 0 if zaak status in [open, in_bewerking], 100 if zaak status in [afgehandeld, gearchiveerd]
- **docudesk_document**: binary: 0 if document draft, 100 if document published/approved
- **externe_url**: binary: 0 (static reference, no progress tracked)

**Triggering recalculation**:
- Recalc fires whenever:
  - A `ChainLink.is_kritiek_pad` changes
  - A linked planix project/task changes status
  - A procest zaak changes status (via event)
  - Manual override (user clicks "Recalculate now" in UI)
- Recalc is **not** immediate; instead, enqueued in a background job queue (max 100ms latency for UI responsiveness)

**READ-ONLY constraint**: 
- `voortgang_percentage` is write-protected on the API
- Only `ChainRollupService.recalculateProgress()` may update it
- Direct PATCH/PUT attempts return 400 Bad Request

#### Scenario: Three Critical Links, Weighted Average
- GIVEN chain with 3 critical links:
  - Project A: 60% (6 of 10 tasks complete)
  - Project B: 100% (all 4 tasks complete)
  - Zaak C: 0% (still open)
- WHEN rollup-engine recalcs
- THEN `voortgang_percentage = (60 + 100 + 0) / 3 = 53.33` (rounded to 53 in UI)

#### Scenario: Non-Critical Links Ignored
- GIVEN chain with:
  - 2 critical links (85%, 100%)
  - 1 non-critical link (30%)
- WHEN rollup-engine recalcs
- THEN `voortgang_percentage = (85 + 100) / 2 = 92.5` (non-critical 30% link is ignored)

#### Scenario: No Critical Links
- GIVEN chain with 0 critical links (all `is_kritiek_pad = false`)
- WHEN rollup-engine recalcs
- THEN `voortgang_percentage = 0` (no data to average)

#### Scenario: Direct Write Rejection
- GIVEN user/API tries to PATCH `voortgang_percentage = 75`
- WHEN API receives request
- THEN returns 400: `{"error": "voortgang_percentage is read-only; set is_kritiek_pad on links to trigger recalc"}`

### Requirement: Status Inference and Transition

The system SHALL automatically infer `status_overall` based on the combined state of links, milestones, and `voortgang_percentage`.

**State machine**:

```
Initial: niet_gestart
  → in_uitvoering: when first critical link added OR first milestone created
  → afgerond: when all critical links at 100% AND all milestones bereikt OR opgeschoven to past
  → vertraagd: when any milestone status = gemist OR uiterste_rapportage_datum < today AND status_overall != afgerond
  → on_hold: when user explicitly sets status via UI + provides reason (toelichting)
  → afgewezen_door_college: when college formally rejects execution (decidesk event `besluit.ingetrokken` → chain status set)
```

**Automatic transitions** (no user action):
- `niet_gestart` → `in_uitvoering`: when first `ChainLink.gemaakt_via` is committed (auto/template/suggestie/handmatig)
- `in_uitvoering` → `afgerond`: when `voortgang_percentage >= 99` AND all open milestones have `werkelijke_datum` set
- `any` → `vertraagd`: when nightly escalation sweep finds overdue milestone (status = gemist)

**Manual transitions** (user action):
- `*` → `on_hold`: user clicks "Pause execution" with reason (e.g., budget freeze, legal review pending)
- `on_hold` → `in_uitvoering`: user clicks "Resume" (resets reason)
- `*` → `afgewezen_door_college`: driven by decidesk ingetrokken event

#### Scenario: Status Transitions on Link Addition
- GIVEN chain status is `niet_gestart`, no links exist
- WHEN user adds first critical link
- THEN chain status auto-transitions to `in_uitvoering`

#### Scenario: Afgerond Trigger
- GIVEN chain with 2 critical links, both at 100%, status = `in_uitvoering`
- WHEN last link reaches 100%
- THEN rollup-engine checks milestones; if all milestones have `werkelijke_datum` OR are opgeschoven past today, auto-transitions status to `afgerond`

#### Scenario: Vertraagd Marking
- GIVEN chain with milestone "College-advies" planned 2026-06-30, today is 2026-07-02, `werkelijke_datum` not set
- WHEN nightly escalation sweep runs
- THEN milestone status = `gemist`, and chain status auto-transitions to `vertraagd`

#### Scenario: Manual On-Hold
- GIVEN chain status = `in_uitvoering`
- WHEN user clicks "Pause" and enters reason "Awaiting court decision on legal challenge"
- THEN status → `on_hold`, reason stored in `toelichting` or new audit-log field; raadsleden following chain receive notification

### Requirement: Milestone-Driven Status Constraints

Milestones feed into status inference:
- If **any** milestone's `status = gemist` (past due, not completed), chain status must be `vertraagd` OR `afgerond` (final milestone past due is acceptable if chain is done).
- If **all** milestones have `status = bereikt`, chain may auto-transition to `afgerond` (assuming all critical links also 100%).

#### Scenario: One Missed Milestone Forces Vertraagd
- GIVEN chain with milestones:
  - Milestone 1: `status = bereikt` (completed on time)
  - Milestone 2: `status = gemist` (was due 2026-06-15, today 2026-07-10, still open)
- WHEN rollup-engine runs
- THEN chain must have `status = vertraagd` (or `afgerond` if somehow alle links are 100%, but milestone 2 still open — contradiction prevents afgerond)

## Non-Functional Requirements

- **Performance**: Rollup recalc (load links, compute %, infer status) completes in <200ms
- **Consistency**: Rollup result deterministic (given same input state, always same output)
- **Atomicity**: A chain update (voortgang_percentage + status_overall) is atomic — both committed or both rolled back
- **Audit**: Every rollup recalc creates a `ChainEventLog` entry of type `voortgang_bijgewerkt` (if % changed) and `chain_status_gewijzigd` (if status changed)

## Acceptance Criteria

- [ ] Voortgang_percentage correctly averages critical links
- [ ] Non-critical links are excluded from rollup
- [ ] Voortgang_percentage field is read-only (rejects direct PATCH)
- [ ] Status auto-transitions: niet_gestart → in_uitvoering on first link
- [ ] Status auto-transitions: in_uitvoering → afgerond when all critical links 100% + all milestones bereikt
- [ ] Status auto-transitions: * → vertraagd when any milestone gemist
- [ ] Status auto-transitions: * → afgewezen on decidesk ingetrokken event
- [ ] Manual pause/resume (on_hold) works correctly
- [ ] Rollup recalc created ChainEventLog entries for progress and status changes
- [ ] Rollup recalc performance <200ms

## Notes

- Milestone states (`bereikt`, `gemist`, `opgeschoven`) are partially manual (user sets date when milestone reached, nightly sweep marks missed). Future: automatic milestone reaching when linked project reaches 100%.
- Status inference rules are hardcoded in `ChainRollupService`; future: configurable via template for gemeente-specific rules
