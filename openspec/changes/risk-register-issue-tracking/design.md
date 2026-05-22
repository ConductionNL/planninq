# Design: Risk Register & Issue Tracking

## Summary

Four new OpenRegister schemas form the core data model:
1. **Risk** — project risk identification, probability/impact scoring, mitigation status tracking
2. **MitigatieActie** — mitigation actions linked to Planix Tasks, score-reduction tracking
3. **Issue** — materialized risks converted to issues, separate resolution workflow
4. **RiskReview** — periodic mandatory reviews preventing risk-assessment staleness

All schemas use OpenRegister relation mechanism (no foreign keys). Seed data includes realistic Dutch municipality project scenarios.

## Data Model

### Risk Schema

**Register:** `planix-risk`  
**Purpose:** Track identified project risks with probability × impact scoring and mitigation status

**Properties:**

| Property | Type | Required | Description | Example |
|----------|------|----------|-------------|---------|
| projectId | relation | true | Link to Project | project-2026-001 |
| programmaId | relation | false | Link to Programme (nullable) | prog-infra-2026 |
| risicoCode | string | true | Auto-generated unique ID | RISK-2026-0042 |
| titel | string | true | Risk title (max 255) | Vertraging bouwverlof |
| beschrijving | string | true | Detailed risk description (min 50) | Mogelijke procedurele vertraging in aanvraagbehandeling |
| categorie | enum | true | Risk category: scope, planning, budget, kwaliteit, resources, leverancier, juridisch, informatieveiligheid, privacy, stakeholder | planning |
| probability | integer | true | Likelihood 1-5: zeer-laag, laag, midden, hoog, zeer-hoog | 3 |
| impact | integer | true | Consequence 1-5: zeer-laag, laag, midden, hoog, zeer-hoog | 4 |
| score | integer | true | Computed (probability × impact) | 12 |
| risicobereidheid | enum | true | Risk appetite threshold: laag (score ≥ 6), midden (score ≥ 12), hoog (score ≥ 20) | midden |
| eigenaar | relation | true | Risk owner (User) | user-pl-001 |
| status | enum | true | Tracking state: geidentificeerd, beoordeeld, gemitigeerd-in-uitvoering, gemitigeerd-afgerond, gerealiseerd, geaccepteerd, vervallen | beoordeeld |
| identificatieDatum | date | true | Date risk identified | 2026-05-01 |
| volgendeReviewDatum | date | true | Next mandatory review (90-day max) | 2026-08-01 |
| gerealiseerdDatum | date | false | Date risk materialized (when status → gerealiseerd) | null |
| linkedIssueId | relation | false | Link to Issue if converted (nullable) | issue-2026-101 |
| reviewOverdueFlag | boolean | false | True if review exceeded 90 days without completion | false |

**Computed Fields:**
- `score` = `probability` × `impact` (1-25 range)
- `isHighRisk` = `score >= risicobereidheid_threshold`
- `daysUntilReview` = `volgendeReviewDatum - today()`
- `isReviewOverdue` = `daysUntilReview < 0`

---

### MitigatieActie Schema

**Register:** `planix-mitigation`  
**Purpose:** Track individual mitigation actions linked to Planix Tasks, measure effectiveness

**Properties:**

| Property | Type | Required | Description | Example |
|----------|------|----------|-------------|---------|
| riskId | relation | true | Link to Risk | risk-2026-0042 |
| taakId | relation | true | Link to Planix Task (bidirectional) | task-2026-567 |
| actieType | enum | true | Mitigation strategy: vermijden (avoid), verminderen (reduce), overdragen (transfer), accepteren (accept) | verminderen |
| verwachteScoreReductie | integer | true | Expected score reduction (1-25) | 6 |
| werkelijkeScoreReductie | integer | false | Actual reduction measured after completion (nullable) | null |
| streefDatum | date | true | Target completion date | 2026-07-15 |
| verantwoordelijke | relation | true | Action owner (User, distinct from risk eigenaar) | user-qa-001 |
| kosten | decimal | false | Budget for mitigation (optional, EUR) | 5000.00 |
| status | enum | false | Derived from linked Task status (open, in-progress, completed, overdue) | in-progress |

**Computed Fields:**
- `status` = derived from `taakId.status` (mapped: planix status → mitigation status)
- `isOverdue` = `streefDatum < today()` AND `status != "completed"`
- `daysOverdue` = `today() - streefDatum` (if overdue)
- `effectivenessRatio` = `werkelijkeScoreReductie / verwachteScoreReductie` (if completed, else null)

---

### Issue Schema

**Register:** `planix-issue`  
**Purpose:** Track issues (materialized risks) with separate resolution workflow and learned lessons

**Properties:**

| Property | Type | Required | Description | Example |
|----------|------|----------|-------------|---------|
| projectId | relation | true | Link to Project | project-2026-001 |
| issueCode | string | true | Auto-generated unique ID | ISSUE-2026-0101 |
| titel | string | true | Issue title (max 255) | Bouwverlof procedureel vertraagd |
| beschrijving | string | true | Issue description (min 50) | Grondige review door juridische afdeling vereist |
| bronRiskId | relation | false | Link to originating Risk (nullable, for traceability) | risk-2026-0042 |
| severity | enum | true | Issue importance: laag, midden, hoog, kritiek | hoog |
| urgentie | enum | true | Response speed required: laag, midden, hoog | midden |
| status | enum | true | Resolution state: open, in-behandeling, wachtend-op-info, opgelost, gesloten, heropend | open |
| meldingsDatum | date | true | Date issue first reported (or converted from risk) | 2026-05-20 |
| streefDatum | date | false | Target resolution date (optional) | 2026-06-15 |
| oplossingsDatum | date | false | Date issue closed (populated on status → gesloten) | null |
| gemeldDoor | relation | true | Reporter (User) | user-pl-001 |
| toegewezenAan | relation | true | Assignee responsible for resolution (User) | user-qa-001 |
| goedgekeurDoor | relation | false | Approver (User, validates resolution before close) | null |
| resolutionType | enum | false | Resolution category (required before closing): opgelost, workaround, niet-reproduceerbaar, afgewezen, duplicaat | null |
| oplossing | string | false | Resolution description (min 50 chars, required if resolutionType != null) | null |
| geleerdeLessen | string | false | Lessons learned (min 50 chars, required if severity ≥ hoog) | null |

**Computed Fields:**
- `isOpen` = `status in [open, in-behandeling, wachtend-op-info]`
- `isOverdue` = `streefDatum < today()` AND `status != gesloten`
- `daysOpen` = `today() - meldingsDatum`
- `resolutionPath` = `bronRiskId ? "converted-from-risk" : "direct-issue"`

---

### RiskReview Schema

**Register:** `planix-review`  
**Purpose:** Audit trail for risk re-assessments; enforces 90-day max interval without review

**Properties:**

| Property | Type | Required | Description | Example |
|----------|------|----------|-------------|---------|
| riskId | relation | true | Link to Risk | risk-2026-0042 |
| reviewDatum | date | true | Review completion date | 2026-08-01 |
| reviewer | relation | true | User who performed review (risk-officer, eigenaar, or projectleider) | user-officer-002 |
| nieuweProbability | integer | true | Re-assessed probability (1-5, may differ from original) | 2 |
| nieuweImpact | integer | true | Re-assessed impact (1-5, may differ from original) | 4 |
| wijzigingsRedenen | string | false | Why assessment changed (optional narrative, max 500) | Leverancier heeft stabiliteit guaranteed; probability laag now |
| signedOff | boolean | false | Formal approval/sign-off by risk-officer or stuurgroep | false |

**Computed Fields:**
- `previousProbability` = fetch from prior RiskReview or initial Risk.probability
- `previousImpact` = fetch from prior RiskReview or initial Risk.impact
- `probabilityDelta` = `nieuweProbability - previousProbability`
- `impactDelta` = `nieuweImpact - previousImpact`
- `scoreDelta` = `(nieuweProbability × nieuweImpact) - (previousProbability × previousImpact)`

---

## Reuse Analysis

**Existing OpenRegister Services Leveraged:**
- `ObjectService` — CRUD for all 4 schemas (create, read, update, delete, search)
- `RelationService` — manage projectId, taakId, eigenaar, reviewer relationships
- `AuditTrailService` — automatic change tracking for all schema updates
- `SearchService` + `IndexService` — full-text search on titel, beschrijving; facet by status, categorie
- `FileService` — attach risk assessments (PDFs, Excel exports) to Risk objects
- `NotificationService` — trigger alerts on review-overdue, escalation events
- `TasksController` — bidirectional link to Planix Task (existing system, extended)
- `ImportService` / `ExportService` — bulk risk import from Excel, CSV export for portfolio
- `CnDetailPage` + `CnDetailGrid` — risk/issue detail views (schema-driven)
- `CnFormDialog` — create/edit forms auto-generated from schema
- `CnDataTable` — risk list with sorting/filtering

**No custom components or services built** — all CRUD, search, file, audit, and notification logic reused.

---

## Seed Data

### Risk Seed Objects

```yaml
# Municipal Infrastructure Programme Project
Risk 1:
  @self:
    register: planix-risk
    schema: Risk
    slug: risk-2026-0001-vertraging-verlof
  projectId: proj-infra-2026
  programmaId: prog-mun-infra-2026
  risicoCode: RISK-2026-0001
  titel: Vertraging bouwverlof aanvraag
  beschrijving: Mogelijke procedurele vertraging in RO-behandeling of ingediende bezwaren tegen plannen
  categorie: planning
  probability: 3
  impact: 4
  score: 12
  risicobereidheid: midden
  eigenaar: user-pl-mueller
  status: beoordeeld
  identificatieDatum: 2026-01-15
  volgendeReviewDatum: 2026-04-15
  gerealiseerdDatum: null
  linkedIssueId: null
  reviewOverdueFlag: false

Risk 2:
  @self:
    register: planix-risk
    schema: Risk
    slug: risk-2026-0002-budget-overschrijding
  projectId: proj-infra-2026
  programmaId: prog-mun-infra-2026
  risicoCode: RISK-2026-0002
  titel: Budget overschrijding grondverwervingskosten
  beschrijving: Onvoorzien grondaankopen hoger dan begroot (3-4% overschrijding per m²)
  categorie: budget
  probability: 2
  impact: 5
  score: 10
  risicobereidheid: midden
  eigenaar: user-finance-johan
  status: gemitigeerd-in-uitvoering
  identificatieDatum: 2026-01-20
  volgendeReviewDatum: 2026-05-20
  gerealiseerdDatum: null
  linkedIssueId: null
  reviewOverdueFlag: false

Risk 3:
  @self:
    register: planix-risk
    schema: Risk
    slug: risk-2026-0003-leverancier-insolventie
  projectId: proj-infra-2026
  programmaId: prog-mun-infra-2026
  risicoCode: RISK-2026-0003
  titel: Insolventie primaire aannemerscombinatie
  beschrijving: Bouwmarkt volatiliteit; gespecialiseerde combinatie van technische eisen/bouwzone
  categorie: leverancier
  probability: 2
  impact: 5
  score: 10
  risicobereidheid: midden
  eigenaar: user-pm-anke
  status: gemitigeerd-afgerond
  identificatieDatum: 2026-02-01
  volgendeReviewDatum: 2026-05-01
  gerealiseerdDatum: null
  linkedIssueId: null
  reviewOverdueFlag: false

Risk 4:
  @self:
    register: planix-risk
    schema: Risk
    slug: risk-2026-0004-informatieveiligheid-dpia
  projectId: proj-infra-2026
  programmaId: prog-mun-infra-2026
  risicoCode: RISK-2026-0004
  titel: Privacy-gevoelige DPIA voor inwonersgegevens
  beschrijving: Inzagerechten op planbesluiten; onvoldoende anonimisering van aanvraaggegevens
  categorie: informatieveiligheid
  probability: 4
  impact: 4
  score: 16
  risicobereidheid: laag
  eigenaar: user-ciso-petra
  status: gemitigeerd-in-uitvoering
  identificatieDatum: 2026-02-10
  volgendeReviewDatum: 2026-05-10
  gerealiseerdDatum: null
  linkedIssueId: null
  reviewOverdueFlag: false

Risk 5:
  @self:
    register: planix-risk
    schema: Risk
    slug: risk-2026-0005-draagvlak-stakeholders
  projectId: proj-infra-2026
  programmaId: prog-mun-infra-2026
  risicoCode: RISK-2026-0005
  titel: Onvoldoende maatschappelijk draagvlak
  beschrijving: Buurtbewoners bezwaren tegen mobiliteitsmaatregelen; verwachte hoorzittingen
  categorie: stakeholder
  probability: 4
  impact: 3
  score: 12
  risicobereidheid: midden
  eigenaar: user-pl-mueller
  status: geidentificeerd
  identificatieDatum: 2026-03-01
  volgendeReviewDatum: 2026-06-01
  gerealiseerdDatum: null
  linkedIssueId: null
  reviewOverdueFlag: false
```

### MitigatieActie Seed Objects

```yaml
Mitigation 1:
  @self:
    register: planix-mitigation
    schema: MitigatieActie
    slug: mitigation-2026-0001-vertraging-verlof
  riskId: risk-2026-0001-vertraging-verlof
  taakId: task-2026-001-ro-voorbereiding
  actieType: verminderen
  verwachteScoreReductie: 6
  werkelijkeScoreReductie: null
  streefDatum: 2026-06-30
  verantwoordelijke: user-ro-designer
  kosten: 8000.00
  status: in-progress

Mitigation 2:
  @self:
    register: planix-mitigation
    schema: MitigatieActie
    slug: mitigation-2026-0002-budget-reserve
  riskId: risk-2026-0002-budget-overschrijding
  taakId: task-2026-002-reserve-allocatie
  actieType: accepteren
  verwachteScoreReductie: 5
  werkelijkeScoreReductie: 5
  streefDatum: 2026-03-31
  verantwoordelijke: user-finance-johan
  kosten: 150000.00
  status: completed

Mitigation 3:
  @self:
    register: planix-mitigation
    schema: MitigatieActie
    slug: mitigation-2026-0003-aannemergarantie
  riskId: risk-2026-0003-leverancier-insolventie
  taakId: task-2026-003-garantie-onderhandeling
  actieType: verminderen
  verwachteScoreReductie: 7
  werkelijkeScoreReductie: 8
  streefDatum: 2026-04-15
  verantwoordelijke: user-contracts-emma
  kosten: 25000.00
  status: completed

Mitigation 4:
  @self:
    register: planix-mitigation
    schema: MitigatieActie
    slug: mitigation-2026-0004-dpia-uitvoering
  riskId: risk-2026-0004-informatieveiligheid-dpia
  taakId: task-2026-004-dpia-voltooid
  actieType: verminderen
  verwachteScoreReductie: 8
  werkelijkeScoreReductie: null
  streefDatum: 2026-06-15
  verantwoordelijke: user-ciso-petra
  kosten: 12000.00
  status: in-progress

Mitigation 5:
  @self:
    register: planix-mitigation
    schema: MitigatieActie
    slug: mitigation-2026-0005-communicatieplan
  riskId: risk-2026-0005-draagvlak-stakeholders
  taakId: task-2026-005-communicatie-inzet
  actieType: verminderen
  verwachteScoreReductie: 6
  werkelijkeScoreReductie: null
  streefDatum: 2026-07-01
  verantwoordelijke: user-comms-dirk
  kosten: 18000.00
  status: open
```

### Issue Seed Objects

```yaml
Issue 1:
  @self:
    register: planix-issue
    schema: Issue
    slug: issue-2026-0101-verlof-bezwaar-indiend
  projectId: proj-infra-2026
  issueCode: ISSUE-2026-0101
  titel: Bezwaar tegen ruimtelijk plan ingediend
  beschrijving: Buurtbewoner heeft bezwaar ingediend tegen planbestemming; hoorzitting gepland
  bronRiskId: risk-2026-0001-vertraging-verlof
  severity: hoog
  urgentie: hoog
  status: in-behandeling
  meldingsDatum: 2026-05-10
  streefDatum: 2026-06-15
  oplossingsDatum: null
  gemeldDoor: user-pl-mueller
  toegewezenAan: user-ro-designer
  goedgekeurDoor: null
  resolutionType: null
  oplossing: null
  geleerdeLessen: null

Issue 2:
  @self:
    register: planix-issue
    schema: Issue
    slug: issue-2026-0102-systeemfout-formulier
  projectId: proj-infra-2026
  issueCode: ISSUE-2026-0102
  titel: Webformulier timing bug bij grote uploads
  beschrijving: Indieners krijgen timeout-fout bij upload bestanden > 50MB; formulier reset niet
  bronRiskId: null
  severity: midden
  urgentie: midden
  status: opgelost
  meldingsDatum: 2026-04-22
  streefDatum: 2026-05-15
  oplossingsDatum: 2026-05-08
  gemeldDoor: user-help-desk
  toegewezenAan: user-dev-alex
  goedgekeurDoor: user-pl-mueller
  resolutionType: opgelost
  oplossing: Verhoging nginx upload timeout naar 5min; progressive chunked upload 100MB support
  geleerdeLessen: Requirements testing team moet grote-file-scenario's in UAT opnemen

Issue 3:
  @self:
    register: planix-issue
    schema: Issue
    slug: issue-2026-0103-archief-ontoegankelijk
  projectId: proj-infra-2026
  issueCode: ISSUE-2026-0103
  titel: Gearchiveerde plannen niet doorzoekbaar
  beschrijving: Zoekopdracht op plannen >2 jaar oud geeft lege resultaten
  bronRiskId: null
  severity: laag
  urgentie: laag
  status: gesloten
  meldingsDatum: 2026-03-15
  streefDatum: 2026-04-30
  oplossingsDatum: 2026-04-25
  gemeldDoor: user-archivist
  toegewezenAan: user-dev-alex
  goedgekeurDoor: user-cto-marc
  resolutionType: afgewezen
  oplossing: Archief-zoekopdrachten vereisen aparte Solr index; gesteld tot Q3-2026 optimization sprint
  geleerdeLessen: null
```

### RiskReview Seed Objects

```yaml
Review 1:
  @self:
    register: planix-review
    schema: RiskReview
    slug: review-2026-0001-v2
  riskId: risk-2026-0001-vertraging-verlof
  reviewDatum: 2026-04-20
  reviewer: user-pl-mueller
  nieuweProbability: 2
  nieuweImpact: 4
  wijzigingsRedenen: Indiening vervroegd; nu in FastTrack procedure zonder bezwaren verwacht
  signedOff: true

Review 2:
  @self:
    register: planix-review
    schema: RiskReview
    slug: review-2026-0002-v1
  riskId: risk-2026-0002-budget-overschrijding
  reviewDatum: 2026-05-15
  reviewer: user-finance-johan
  nieuweProbability: 1
  nieuweImpact: 5
  wijzigingsRedenen: Reserve budget gefinancierd; leveranciers hebben prijzen gegarandeerd t/m Q4
  signedOff: false
```

---

## Architecture Decisions

### 1. No Embedded Risk in Task
MitigatieActie links to both Risk and Task via relations, but does NOT embed the task object in Risk. This decouples domain models and prevents bidirectional object nesting. Task updates propagate via event (WebhookService) to update Mitigation status.

### 2. Computed Score vs Stored Score
`Risk.score` is stored (not computed on read) for:
- Portfolio query performance (filter by score ranges without multiplying on each query)
- Audit trail (historical score visible in change snapshots)
- Report generation (no need to recalculate closed risks)

### 3. RiskReview is Append-Only
Reviews never update existing records; each periodic review creates a new RiskReview object. This preserves audit trail of assessment changes and enables trend analysis (probability/impact delta over time).

### 4. Issue from Risk vs Direct Issue
`Issue.bronRiskId` is optional — not all issues originate from risks. Issues can be created directly (defects, support requests) and never converted from risks. This supports portfolio use cases where both risks and issues need unified reporting.

### 5. Escalation via n8n, Not Backend Cron
Overdue mitigation notifications (REQ-003) are triggered by n8n-nextcloud scheduled workflow, not a PHP cron job. This:
- Centralizes notification rules (configurable per org via n8n UI)
- Avoids task table locks during daily escalation
- Enables per-project escalation thresholds (stored in OpenRegister config)
- Allows async notification dispatch (no request blocking)

---

## Frontend Architecture

### Views
- `/risks` — List all risks (CnDataTable, filterable by status/categorie/score range)
- `/risks/:id` — Detail view (CnDetailPage, tabs: Overview, Mitigations, Reviews, linked Issue)
- `/risks/new` — Create form (CnFormDialog pre-filled with project context)
- `/issues` — List all issues (CnDataTable)
- `/issues/:id` — Detail view with resolution form (approval workflow)

### Components
- `RiskScoreMatrix` — probability × impact grid visualization (click to create risks in each cell)
- `MitigatieForm` — inline task creation + link picker
- `RiskReviewForm` — triggered by `volgendeReviewDatum` notification
- `RiskToIssueModal` — conversion dialog (confirmation, pre-populated fields)
- `StatusBadges` — inline risk/issue status indicators (color-coded enums)

### State Management
- `createObjectStore('risks')` + `createObjectStore('issues')` (Pinia)
- Store plugins: relations, auditTrails, search
- No custom HTTP layer — ObjectService handles all CRUD

---

## Compliance Mapping

| Standard | Artifact | Mapping |
|----------|----------|---------|
| NEN-ISO 31000 | Risk, RiskReview, AuditTrail | Risk identification → probability/impact → mitigation → review → audit trail (core 31000 loop) |
| PRINCE2 Theme | Risk status enum (7 states) | Identified → Assessed → Mitigated → Realized → Accepted/Closed |
| IPMA ICB 4.0 | Portfolio top-10 widget, trend reports | Competence: "manage project risks" (reporting demonstrates capability) |
| AVG art. 35 DPIA | `informatieveiligheid` + `privacy` categories | Privacy risks tracked separately; DPIA links via docudesk integration |
| BIO (gemeenten) | Information security risk category | Subset of `informatieveiligheid` risks mapped to ENSIA via shillinq-ensia bridge |

---

## Performance Targets

- Risk list (1000 objects): < 2s load with faceted filtering
- Top-10 portfolio report: < 3s (cached, invalidated on risk status/score change)
- Daily escalation job (5000 overdue mitigations): < 30s
- Risk detail page (with 20 mitigations + 5 reviews): < 1s
- Risk creation (with auto-generated task): < 2s transaction

---

## Success Metrics

- [ ] Risk register adoption (% of active projects with ≥ 1 risk identified)
- [ ] Mitigation task link rate (% of mitigations with active task)
- [ ] Review adherence (% of risks reviewed within 90 days)
- [ ] Portfolio visibility (% of executives using top-10 dashboard)
- [ ] Escalation effectiveness (% of escalated mitigations resolved within 14 days)
