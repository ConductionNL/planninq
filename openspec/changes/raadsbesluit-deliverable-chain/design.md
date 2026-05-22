# Design: Raadsbesluit Deliverable Chain

## Summary

The deliverable chain is a graph-based system where a decidesk besluit (root node) connects to 0..n planix projects, procest cases, and external resources (leaf nodes). The chain system tracks overall execution progress via weighted rollup, milestones with escalation, and an event-driven audit trail. All data lives in planix OpenRegister as four interconnected schemas.

## Data Model

### Core Entities

**BesluitDeliverableChain** — Root container linking a decidesk besluit to its execution chain  
(schema.org: `schema:CreativeWork` + custom extensions for decision context)

```yaml
uuid: string @id (required)
decidesk_besluit_id: string (required, unique)
  # FK: reference to decidesk.Besluit via cross-app registry
  # Validation: MUST exist with status "vastgesteld" or "aangenomen"
  
besluit_titel: string (required, 255 chars, denormalized cache)
  # Extracted from decidesk.Besluit.titel at chain creation
  
besluit_type: enum (required)
  values: ["raadsbesluit", "motie", "amendement", "toezegging", "schriftelijke_vraag"]
  
vastgesteld_op: date (required)
  # When the decidesk besluit was approved by raad/college
  
uiterste_rapportage_datum: date (nullable)
  # Griffie-set deadline for college to report back; null = no fixed deadline
  
eigenaar_collegelid_id: string (required)
  # FK to users; the portfolio-holder responsible
  
eigenaar_ambtelijk_id: string (required)
  # FK to users; the program manager or team manager overseeing execution
  
status_overall: enum (required)
  values: ["niet_gestart", "in_uitvoering", "afgerond", "vertraagd", "on_hold", "afgewezen_door_college"]
  default: "niet_gestart"
  # Auto-set by rollup engine based on link states and milestone status
  
voortgang_percentage: decimal (0-100, required)
  default: 0
  # READ-ONLY: computed by rollup engine from chain_link progress
  
laatste_rapportage_op: datetime (nullable)
  # When eigenaar_ambtelijk last updated the textual rapportage_tekst
  
laatste_rapportage_tekst: text (nullable)
  # Owner's narrative update on status, blockers, etc.
  
volgers_user_ids: array<string> (nullable)
  # List of raadsled user IDs following this chain (for notifications)
  
bbv_doel_ids: array<string> (nullable)
  # FK array: link to strategic program goals in planix BBV tree
  
tags: array<string> (nullable)
  # User-added labels (e.g., ["budget-impact", "corona-related", "personeelszaak"])
  
created_at: datetime (required)
updated_at: datetime (required)
```

**ChainLink** — Edge connecting chain to a planix project, procest zaak, or external resource  
(schema.org: `schema:Thing` + custom relation type)

```yaml
uuid: string @id (required)
chain_id: string (required)
  # FK to BesluitDeliverableChain

link_type: enum (required)
  values: ["planix_project", "planix_task", "procest_zaak", "docudesk_document", "externe_url"]
  
linked_entity_id: string (required)
  # UUID of the target object in the foreign app
  # Validation: MUST exist in target app (via cross-app lookup service)
  
linked_entity_label: string (required, 255 chars, denormalized cache)
  # Human-readable label of the linked entity (e.g., project name, zaak type)
  # Denormalized for fast display; kept in sync via webhook on target update
  
volgorde: integer (required)
  # Display order in chain UI (1..50)
  
is_kritiek_pad: boolean (required)
  default: false
  # If true, this link's progress counts toward voortgang_percentage rollup
  # Critical path only; non-critical links inform context but not progress %
  
verwacht_gereed_op: date (nullable)
  # Expected completion date for this link
  
gereed_op: date (nullable)
  # Actual completion date (null while in progress)
  
toelichting: text (nullable)
  # Free-form context (why this link, connection to andere linken, assumptions)
  
gemaakt_door_id: string (required)
  # FK to users; who created this link
  
gemaakt_via: enum (required)
  values: ["handmatig", "ai_suggestie", "template", "auto_event"]
  # How the link was created (manual, LLM suggestion, template pattern, auto-created from event)
  
created_at: datetime (required)
updated_at: datetime (required)
```

**ChainMijlpaal** — Milestone with planned date, responsible party, and reporting cadence  
(schema.org: `schema:Event` — time-based event in execution)

```yaml
uuid: string @id (required)
chain_id: string (required)
  # FK to BesluitDeliverableChain
  
volgorde: integer (required)
  # Sequence of milestones (1..n)
  
titel: string (required, 255 chars)
  # e.g., "Conceptnota gereed", "Commissie-behandeling", "Raad-stemming"
  
omschrijving: text (nullable)
  # Detailed description and success criteria
  
geplande_datum: date (required)
  # Planned date; MUST be >= chain.vastgesteld_op
  
werkelijke_datum: date (nullable)
  # Actual date when milestone was reached (null if not yet reached)
  
status: enum (required)
  values: ["gepland", "bereikt", "gemist", "opgeschoven"]
  default: "gepland"
  # Auto-set: gepland (future), bereikt (werkelijke_datum set), gemist (past geplande_datum, werkelijke not set), opgeschoven (user reschedules)
  
verantwoordelijke_id: string (required)
  # FK to users; owner responsible for reaching milestone
  
rapportage_aan: enum (required)
  default: "griffie"
  values: ["griffie", "commissie", "raad", "extern"]
  # Who should receive report on this milestone
  
rapportage_format: enum (required)
  default: "email"
  values: ["email", "brief", "commissie-stuk", "open-data"]
  # How to report on this milestone
  
created_at: datetime (required)
updated_at: datetime (required)
```

**ChainEventLog** — Audit trail of all status changes and propagation events  
(schema.org: `schema:Event` + custom action type)

```yaml
uuid: string @id (required)
chain_id: string (required)
  # FK to BesluitDeliverableChain
  
event_type: enum (required)
  values: [
    "chain_aangemaakt",
    "chain_status_gewijzigd",
    "link_toegevoegd",
    "link_verwijderd",
    "link_progress_bijgewerkt",
    "mijlpaal_bereikt",
    "mijlpaal_gemist",
    "mijlpaal_opgeschoven",
    "voortgang_bijgewerkt",
    "rapportage_verzonden",
    "escalatie_notification_verzonden",
    "eigen_update_ingediend",
    "eindrapportage_gegenereerd"
  ]
  
bron_app: enum (required)
  values: ["planix", "procest", "decidesk", "mydash", "systeem"]
  # Which app originated the event
  
bron_entity_id: string (nullable)
  # ID of the object that triggered the event (e.g., task uuid from planix)
  
timestamp: datetime (required)
  # When the event occurred (server clock)
  
actor_id: string (nullable)
  # FK to users; who triggered the event (null for system events)
  
payload: json (required)
  # Contextual data: before/after values, reason, change details
  # e.g., { "voortgang_old": 45, "voortgang_new": 62, "project_id": "...", "completed_task_count": 6 }
  
propagated_to_overall: boolean (required)
  default: false
  # true if this event caused a change to chain.status_overall or voortgang_percentage
  
created_at: datetime (required)
```

**RaadslidVolgVoorkeur** — Per-user notification preferences for following chains  
(schema.org: `schema:UserInteraction` — user's preference for updates on a decision)

```yaml
uuid: string @id (required)
user_id: string (required)
  # FK to users; the raadslid
  
chain_id: string (required)
  # FK to BesluitDeliverableChain (user can follow many chains; unique constraint on (user_id, chain_id))
  
notify_op_mijlpaal: boolean (required)
  default: true
  # Send notification when a milestone is reached or missed
  
notify_op_vertraging: boolean (required)
  default: true
  # Send notification when chain status changes to "vertraagd"
  
notify_op_afronding: boolean (required)
  default: true
  # Send notification when chain status changes to "afgerond"
  
notify_kanaal: enum (required)
  default: "email"
  values: ["email", "nextcloud-talk", "app-only"]
  # Where to send notifications
  
gestart_op: datetime (required)
  # When the user started following this chain
  
updated_at: datetime (required)
```

## Reuse Analysis

The design leverages existing OpenRegister infrastructure (ADR-001, ADR-011):

- **ObjectService**: All CRUD operations use `ObjectService.saveObject()`, `findAll()`, `deleteObject()` — no custom persistence layer.
- **CnDetailPage + CnFormDialog**: Chain detail and edit views auto-generated from schemas; no custom form code.
- **CnDataTable**: Chain and link lists use `CnDataTable` with auto-columns from schema + custom filters.
- **NotificationService**: Escalation and follow notifications use existing `NotificationService` + activity feed.
- **AuditTrailService**: Automatic audit trail on all changes; `ChainEventLog` is supplemental domain audit (decision-specific events).
- **FileService**: Eindrapportage PDF stored as file attachment via existing file system.
- **CalendarEventService**: iCal export for milestones uses existing calendar integration.

**No custom services needed for**:
- User management (OpenRegister `users` register)
- Permission enforcement (OpenRegister RBAC + `_object_acl`)
- Search indexing (IndexService auto-indexes all fields)

**Custom services needed for decision-specific logic**:
- `BesluitDeliverableChainService` — lifecycle, create on decidesk event, permissions check
- `ChainRollupService` — voortgang % aggregation algorithm (weighted, critical-path-only)
- `ChainAiService` — LLM-based link suggestion (external API call)
- `ChainEscalationService` — nightly milestone sweep (scheduler trigger)
- `ChainReportService` — Markdown + PDF report generation

## Seed Data

### BesluitDeliverableChain Seed Objects

**Chain 1: "Motie AED's wijkcentra" (active, 23% complete)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "BesluitDeliverableChain",
    "slug": "motie-aeds-wijkcentra-2026"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440001",
  "decidesk_besluit_id": "M-2026-014",
  "besluit_titel": "Onderzoek inzet automatische externe defibrillatoren in wijkcentra",
  "besluit_type": "motie",
  "vastgesteld_op": "2026-03-12",
  "uiterste_rapportage_datum": "2026-09-30",
  "eigenaar_collegelid_id": "user-wethouder-gezondheid",
  "eigenaar_ambtelijk_id": "user-pm-publiekehealth",
  "status_overall": "in_uitvoering",
  "voortgang_percentage": 23,
  "laatste_rapportage_op": "2026-05-15T10:30:00Z",
  "laatste_rapportage_tekst": "Onderzoekscommissie heeft 3 van 4 wijkcentra bezocht. Sluitstuk: kostenraming en implementatieplan.",
  "volgers_user_ids": ["user-raadslid-frankv", "user-raadslid-annaK"],
  "tags": ["gezondheid", "preventie", "veiligheid"]
}
```

**Chain 2: "Raadsbesluit Beleidsnota Mobiliteit 2026-2030" (in progress, 67% complete)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "BesluitDeliverableChain",
    "slug": "rb-beleidsnota-mobiliteit-2026"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440002",
  "decidesk_besluit_id": "RB-2026-031",
  "besluit_titel": "Vaststellen Beleidsnota Mobiliteit 2026-2030 inclusief fietsstraten en P+R-strategie",
  "besluit_type": "raadsbesluit",
  "vastgesteld_op": "2026-02-01",
  "uiterste_rapportage_datum": "2026-12-31",
  "eigenaar_collegelid_id": "user-wethouder-mobiliteit",
  "eigenaar_ambtelijk_id": "user-pm-verkeerswezen",
  "status_overall": "in_uitvoering",
  "voortgang_percentage": 67,
  "laatste_rapportage_op": "2026-05-20T14:15:00Z",
  "laatste_rapportage_tekst": "Fietsstraat Hoofdweg fase 1 voltooid. P+R-uitbreiding in aanbestedingsfase. Snelheidsremmers gepland Q3.",
  "volgers_user_ids": ["user-fractie-vvd", "user-fractie-sp"],
  "tags": ["mobiliteit", "fietsstraten", "duurzaamheid"],
  "bbv_doel_ids": ["doel-duurzame-stad-2030"]
}
```

**Chain 3: "Amendement: Budgetverhoging Cultureel Centrum" (not started, 0%)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "BesluitDeliverableChain",
    "slug": "amendement-cultuurbudget-2026"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440003",
  "decidesk_besluit_id": "AMT-2026-008",
  "besluit_titel": "Budgetverhoging Cultureel Centrum 2026: € 150k extra voor tentoonstellingsprogramma",
  "besluit_type": "amendement",
  "vastgesteld_op": "2026-04-05",
  "uiterste_rapportage_datum": null,
  "eigenaar_collegelid_id": "user-wethouder-cultuur",
  "eigenaar_ambtelijk_id": "user-pm-cultuur",
  "status_overall": "niet_gestart",
  "voortgang_percentage": 0,
  "laatste_rapportage_op": null,
  "laatste_rapportage_tekst": null,
  "volgers_user_ids": [],
  "tags": ["cultuur", "budget"]
}
```

**Chain 4: "Toezegging: Dialoogavonden Jeugdbeleid" (delayed, 40%)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "BesluitDeliverableChain",
    "slug": "toezegging-dialoogavonden-jeugd-2026"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440004",
  "decidesk_besluit_id": "TOZ-2026-002",
  "besluit_titel": "Toezegging: 6 dialoogavonden met jongeren over jeugdbeleid 2026-2027",
  "besluit_type": "toezegging",
  "vastgesteld_op": "2025-11-20",
  "uiterste_rapportage_datum": "2026-06-30",
  "eigenaar_collegelid_id": "user-wethouder-jeugd",
  "eigenaar_ambtelijk_id": "user-pm-maatschappij",
  "status_overall": "vertraagd",
  "voortgang_percentage": 40,
  "laatste_rapportage_op": "2026-05-18T09:00:00Z",
  "laatste_rapportage_tekst": "Eerste 2 dialoogavonden afgerond (mei, april). Derde geplant 17 juni; locatie nog niet geboekt. Risico: achterstand door lokaal-beschikbaarheid.",
  "volgers_user_ids": ["user-raadslid-markusJ", "user-raadslid-priyaS"],
  "tags": ["jeugd", "participatie"]
}
```

### ChainLink Seed Objects

**Link 1: Raadsbesluit-2026-031 → Project "Fietsstraat Hoofdweg"**
```json
{
  "@self": {
    "register": "planix",
    "schema": "ChainLink",
    "slug": "link-rb-2026-031-fietsstraat"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440101",
  "chain_id": "550e8400-e29b-41d4-a716-446655440002",
  "link_type": "planix_project",
  "linked_entity_id": "p-fietsstraat-hoofdweg",
  "linked_entity_label": "Fietsstraat Hoofdweg - Fase 1",
  "volgorde": 1,
  "is_kritiek_pad": true,
  "verwacht_gereed_op": "2026-05-31",
  "gereed_op": "2026-05-20",
  "toelichting": "Eerste fase: asfaltering en markeringen; deel van het mobiliteitsbeleid fietsinfrastructuur",
  "gemaakt_door_id": "user-pm-verkeerswezen",
  "gemaakt_via": "ai_suggestie"
}
```

**Link 2: Raadsbesluit-2026-031 → Task "P+R-locatie afkoop"**
```json
{
  "@self": {
    "register": "planix",
    "schema": "ChainLink",
    "slug": "link-rb-2026-031-pr-afkoop"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440102",
  "chain_id": "550e8400-e29b-41d4-a716-446655440002",
  "link_type": "planix_task",
  "linked_entity_id": "t-pr-afkoop-onderhandeling",
  "linked_entity_label": "Onderhandeling P+R-locatie De Mars",
  "volgorde": 2,
  "is_kritiek_pad": true,
  "verwacht_gereed_op": "2026-07-15",
  "gereed_op": null,
  "toelichting": "Critical path item; afkoop moet voltrokken zijn voor bouw aanbesteding",
  "gemaakt_door_id": "user-pm-verkeerswezen",
  "gemaakt_via": "handmatig"
}
```

**Link 3: Motie-2026-014 → Procest Case "AED-plaatsingsprotocol"**
```json
{
  "@self": {
    "register": "planix",
    "schema": "ChainLink",
    "slug": "link-motie-2026-014-aed-zaak"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440103",
  "chain_id": "550e8400-e29b-41d4-a716-446655440001",
  "link_type": "procest_zaak",
  "linked_entity_id": "z-aed-plaatsingsprotocol",
  "linked_entity_label": "Zaak: AED-plaatsingsprotocol opstellen + implementatie",
  "volgorde": 1,
  "is_kritiek_pad": true,
  "verwacht_gereed_op": "2026-08-31",
  "gereed_op": null,
  "toelichting": "Samenhang tussen motie-onderzoek en operationele zaak-afhandeling",
  "gemaakt_door_id": "user-pm-publiekehealth",
  "gemaakt_via": "template"
}
```

### ChainMijlpaal Seed Objects

**Milestone 1: "Onderzoek afgerond" (Motie-2026-014)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "ChainMijlpaal",
    "slug": "milestone-motie-2026-014-onderzoek"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440201",
  "chain_id": "550e8400-e29b-41d4-a716-446655440001",
  "volgorde": 1,
  "titel": "Onderzoekscommissie onderzoek afgerond",
  "omschrijving": "Alle wijkcentra bezocht, interviews afgerond, aanbevelingen geformuleerd",
  "geplande_datum": "2026-06-30",
  "werkelijke_datum": null,
  "status": "gepland",
  "verantwoordelijke_id": "user-pm-publiekehealth",
  "rapportage_aan": "commissie",
  "rapportage_format": "commissie-stuk"
}
```

**Milestone 2: "College-advies klaar" (Raadsbesluit-2026-031)**
```json
{
  "@self": {
    "register": "planix",
    "schema": "ChainMijlpaal",
    "slug": "milestone-rb-2026-031-college-advies"
  },
  "uuid": "550e8400-e29b-41d4-a716-446655440202",
  "chain_id": "550e8400-e29b-41d4-a716-446655440002",
  "volgorde": 2,
  "titel": "College-advies implementatieplan mobiliteitsbeleid",
  "omschrijving": "College stelt detailadvies op ten aanzien van fasering en budgettering",
  "geplande_datum": "2026-10-15",
  "werkelijke_datum": null,
  "status": "gepland",
  "verantwoordelijke_id": "user-pm-verkeerswezen",
  "rapportage_aan": "raad",
  "rapportage_format": "brief"
}
```

### ChainEventLog Seed Objects (example entries)

```json
[
  {
    "@self": {
      "register": "planix",
      "schema": "ChainEventLog",
      "slug": "event-rb-2026-031-created-2026-02-01"
    },
    "uuid": "550e8400-e29b-41d4-a716-446655440301",
    "chain_id": "550e8400-e29b-41d4-a716-446655440002",
    "event_type": "chain_aangemaakt",
    "bron_app": "decidesk",
    "bron_entity_id": "RB-2026-031",
    "timestamp": "2026-02-01T18:30:00Z",
    "actor_id": "user-decidesk-system",
    "payload": {
      "trigger": "besluit.vastgesteld",
      "eigenaar_collegelid": "user-wethouder-mobiliteit"
    },
    "propagated_to_overall": true
  },
  {
    "@self": {
      "register": "planix",
      "schema": "ChainEventLog",
      "slug": "event-rb-2026-031-voortgang-2026-05-20"
    },
    "uuid": "550e8400-e29b-41d4-a716-446655440302",
    "chain_id": "550e8400-e29b-41d4-a716-446655440002",
    "event_type": "voortgang_bijgewerkt",
    "bron_app": "planix",
    "bron_entity_id": "p-fietsstraat-hoofdweg",
    "timestamp": "2026-05-20T15:45:00Z",
    "actor_id": null,
    "payload": {
      "voortgang_old": 50,
      "voortgang_new": 67,
      "reason": "project status updated to 85%, task 3 completed",
      "link_ids": ["550e8400-e29b-41d4-a716-446655440101", "550e8400-e29b-41d4-a716-446655440102"]
    },
    "propagated_to_overall": true
  }
]
```

## Integration Architecture

**Event flow**:
1. Decidesk publishes `besluit.vastgesteld` (CloudEvents) → Planix listener creates `BesluitDeliverableChain` with status `niet_gestart`
2. User/AI suggests `ChainLink` records; each creates `ChainEventLog.link_toegevoegd`
3. User marks `ChainMijlpaal` dates; marked in event log
4. Planix internal: `project.status_changed` or `task.completed` → triggers `ChainRollupService.recalculateProgress()`
5. Procest publishes `zaak.status_changed` → Planix listener updates relevant `ChainLink` and retriggers rollup
6. Nightly n8n workflow: queries upcoming milestones, sends escalation emails, marks missed
7. Raadslid follows chain → adds to `volgers_user_ids` + creates `RaadslidVolgVoorkeur`
8. On chain status change to `afgerond` → `ChainReportService` generates PDF, stores as file, sends notification

## Non-Functional Requirements

- **Performance**: Rollup recalc must complete in <500ms (max 50 links per chain); milestone escalation query in <2s (nightly batch).
- **Consistency**: Event log is append-only; all state changes traceable.
- **Scalability**: Assumes ~1000 active chains per municipality; indices on `decidesk_besluit_id`, `eigenaar_ambtelijk_id`, `uiterste_rapportage_datum` for query performance.
- **Accessibility**: Mydash widgets and raadslid portal follow WCAG 2.2 AA (ADR-010, nl-design).
- **Internationalization**: Dutch UI labels in messages, emails, reports; UI strings i18n-marked (ADR-007).

## Notes

- Seed data uses realistic Dutch municipality context (raadsled names, budget notes, date formats).
- Cross-app references (decidesk_besluit_id, linked_entity_id) resolved via `OpenRegisterCrossAppService` — no direct API calls across apps.
- Chain data is read-only to external apps (decidesk, procest, mydash only consume status); all writes happen in planix.
