# Chain Lifecycle Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — chain creation, AI-based linking, template-based linking

## Purpose

Defines how a decision chain is created and populated with links. Covers automatic creation on besluit approval, AI-assisted link discovery from decision text, and manual/template-based linking mechanisms.

## ADDED Requirements

### Requirement: Automatic Chain Creation on Besluit Approval [REQ-001]

The system SHALL automatically create a `BesluitDeliverableChain` when a decidesk-besluit transitions to status `vastgesteld` or `aangenomen`.

**Chain initialization**:
- `decidesk_besluit_id` — set from decidesk event payload
- `besluit_titel` — denormalized copy of decidesk.Besluit.titel
- `besluit_type` — inferred from decidesk.Besluit.type (raadsbesluit|motie|amendement|toezegging|schriftelijke_vraag)
- `vastgesteld_op` — set from decidesk.Besluit.approved_date
- `eigenaar_collegelid_id` — set from decidesk.Besluit.portfolio_holder_id (if available; fallback to griffie assignment)
- `eigenaar_ambtelijk_id` — griffie must assign (via UI) within 48 hours or defaults to program manager for besluit-type
- `status_overall` — initialized to `niet_gestart`
- `voortgang_percentage` — initialized to 0
- All other optional fields null/empty

**Validation**:
- MUST NOT create chain for besluit with status `ingetrokken`, `verworpen`, or `concept`
- MUST NOT create duplicate chain (idempotent: re-receiving same event ignores)

#### Scenario: Motie Aangenomen
- GIVEN decidesk publishes `besluit.vastgesteld` event for motie M-2026-014 "AED's wijkcentra onderzoek", approved 2026-03-12, portfolio: Gezondheid
- WHEN planix chain-listener processes the event
- THEN a `BesluitDeliverableChain` is created with `decidesk_besluit_id = M-2026-014`, `besluit_type = motie`, `vastgesteld_op = 2026-03-12`, `eigenaar_collegelid_id = user-gezondheid`, `status_overall = niet_gestart`, and system sends griffie a task "Assign program manager for chain M-2026-014"

#### Scenario: Ingetrokken Motie Ignored
- GIVEN decidesk publishes `besluit.ingetrokken` event for motie M-2026-015
- WHEN planix chain-listener processes the event
- THEN NO chain is created; system logs event but takes no action

#### Scenario: Duplicate Event Idempotency
- GIVEN chain for M-2026-014 already exists
- WHEN decidesk event for M-2026-014 approval is re-delivered (network retry)
- THEN NO second chain is created; existing chain is left unchanged

### Requirement: AI-Suggested Linking from Besluit Text [REQ-002]

The system SHALL offer AI-based link suggestions by analyzing the besluit text and matching against existing planix projects and procest case types.

**Link suggestion workflow**:
1. User opens chain detail and clicks "Suggest links" button
2. System extracts besluit text + titel from decidesk
3. System calls `ChainAiService.suggestLinks(decision_text)`:
   - LLM analyzes text to identify actionable items (projects, cases, milestones)
   - Searches planix projects and procest case types for semantic matches
   - Returns ranked list: `[{entity_id, label, confidence_score, match_citation}, ...]`
4. UI displays suggestions with confidence score (0.0–1.0) and cite (excerpt from decision text)
5. User can accept, reject, or edit each suggestion
6. On accept: creates `ChainLink` with `gemaakt_via = ai_suggestie`

**Confidence thresholds**:
- Score ≥ 0.75: show as "High confidence" (green), pre-expanded
- Score 0.50–0.74: show as "Medium confidence" (yellow), collapsed by default
- Score < 0.50: show as "Low confidence" (gray), collapsed

**Validations**:
- Referenced `linked_entity_id` MUST exist in target app (verified server-side on accept)
- A link CANNOT be suggested twice; deduplicate by `linked_entity_id`

#### Scenario: Project Suggestion from Policy Decision
- GIVEN chain for raadsbesluit "Vaststellen Beleidsnota Mobiliteit 2026-2030" (contains text about "fietsstraten", "snelheidsremmers", "P+R-uitbreiding")
- WHEN user clicks "Suggest links"
- THEN system returns 4 suggestions: Project "Fietsstraat Hoofdweg" (conf 0.92, cite: "fietsstraten Hoofdweg als prioriteit"), Project "30km-zone wijk Noord" (conf 0.81, cite: "snelheidsremmers..."), Project "P+R uitbreiding De Mars" (conf 0.88, cite: "P+R De Mars uitbreiding"), Task "Commissie-voorbereiding mobiliteit" (conf 0.68, cite: "commissie over benaderingstechniek")

#### Scenario: Suggestion Accept and Validation Failure
- GIVEN suggestion for project-id "proj-x" with confidence 0.85
- WHEN user clicks "Accept"
- THEN system verifies project exists; if project deleted since suggestion was generated, server returns 404 and shows error "Project is no longer available; suggestion is stale"

### Requirement: Template-Based Linking per Besluit-Type [REQ-002 Variant]

The system SHALL support templates: per `besluit_type`, pre-configured lists of typical projects and case types that should be linked.

**Template structure** (stored in app config):
```
BesluitType: raadsbesluit
  TypicalProjects: 
    - project_slug: "policy-implementation-{domain}"
    - project_slug: "stakeholder-engagement"
  TypicalCases:
    - zaak_type: "advies_commissie"
    - zaak_type: "implementatie_rapportage"
```

**Usage**:
- When user creates new chain (via UI or API), system offers: "Create standard links based on [raadsbesluit] template?" (yes/no/custom)
- On "yes": automatically creates `ChainLink` records with `gemaakt_via = template` for each matching entity in the template

#### Scenario: Template-Based Creation
- GIVEN user opens chain-create dialog for a new raadsbesluit
- WHEN dialog shows "Use template links for raadsbesluit? (Yes/No)" and user clicks Yes
- THEN system searches planix for projects tagged `policy-implementation-*` and procest for case type `advies_commissie`; creates links for all found entities (e.g., 2 policy projects + 1 case type found → creates 3 links)

### Requirement: Manual Link Management

The system SHALL allow manual creation, reordering, and deletion of links on a chain.

**Manual link creation**:
- User clicks "Add link" on chain detail
- Dialog opens with dropdowns: Link Type (planix_project | planix_task | procest_zaak | docudesk_document | externe_url)
- If planix_project: typeahead search in planix projects
- If procest_zaak: typeahead search in procest zaak types
- If externe_url: text input for URL
- User optionally sets `is_kritiek_pad`, `verwacht_gereed_op`, `toelichting`
- On save: creates `ChainLink` with `gemaakt_via = handmatig`

**Link reordering**: Drag-and-drop in UI updates `volgorde` field

**Link deletion**: Soft-delete (mark inactive) or hard-delete with confirmation (no undo); creates event log entry `link_verwijderd`

#### Scenario: Manual Linking
- GIVEN chain for motie M-2026-014
- WHEN user clicks "Add link", selects "procest_zaak", searches for "AED", finds "Zaak: AED-plaatsingsprotocol", clicks "Create link"
- THEN a `ChainLink` is created with `gemaakt_via = handmatig`, `link_type = procest_zaak`, `linked_entity_id = z-aed-plaatsingsprotocol`

## Non-Functional Requirements

- **Performance**: AI link suggestion (LLM call) completes in <5s; dedupe/validation in <1s
- **Reliability**: Chain creation is synchronous (event → chain created before listener returns); no orphaned decisions without a chain
- **API Idempotency**: Creating chain twice with same `decidesk_besluit_id` returns same UUID (upsert semantics)

## Acceptance Criteria

- [ ] Automatic chain creation on decidesk `vastgesteld` event fires within 2 seconds
- [ ] Chain initialization correctly copies titel, type, dates, portfolio holder from decidesk
- [ ] Duplicate chain-creation events are safely idempotent (no duplicates)
- [ ] AI suggestion engine calls LLM with decision text and returns ranked matches
- [ ] Suggestions include confidence score and match citation
- [ ] User can accept/reject suggestions; accepted suggestions create ChainLink records
- [ ] Template-based linking creates correct links per besluit-type template
- [ ] Manual linking CRUD fully functional (create, read, reorder, delete)
- [ ] Cross-app referential integrity checks (LinkedEntityId must exist in target app)
- [ ] All linking creates appropriate ChainEventLog entries

## Notes

- AI LLM backend initially uses (TBD: OpenAI API, local LLaMA, or Anthropic); may change with contract negotiations
- Templates stored as YAML in `lib/Settings/raadsbesluit_deliverable_chain_templates.yaml` (part of app config, not OpenRegister data)
- Template updates are manual (griffie updates config); future: UI for template management
