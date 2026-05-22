# Design: Portfolio Dashboard PMO

## Summary

Portfolio Dashboard PMO provides native cross-project visibility for PMO teams, program managers, and executive leadership. Eight configurable widgets (traffic-light table, resource heatmap, milestone radar, risk register, dependency graph, spend-vs-budget, status-trend, executive-summary) are rendered via a new dashboard route (`/planix/portfolio`). All data flows through OpenRegister schemas (6 new types) with live aggregation from Planix projects, financeq spend, and milestone data. Role-based access (pmo_manager, portfolio_eigenaar, program_manager) restricts views and mutations.

## Architecture

### Data Layer

**Six new OpenRegister schemas** (`lib/Settings/planix_register.json`):

1. **`Portfolio`** — Container for a cross-project view (e.g., "Strategische Projecten 2026", "Afdeling Digitaal", "Programma Mobiliteit")
   - `uuid` (PK)
   - `naam` (string, max 255)
   - `omschrijving` (text)
   - `eigenaar_id` (FK users.uid) — bestuurslid or directielid; responsible for executive oversight
   - `pmo_manager_id` (FK users.uid) — PMO lead who can publish rapportages and manage portfolio
   - `scope_filter` (JSON) — defines which projects fall into this portfolio:
     ```json
     {
       "programma_ids": ["uuid-1", "uuid-2"],
       "afdeling_ids": ["AD-001", "AD-002"],
       "tags": ["strategisch", "bbv-gerelateerd"],
       "project_ids": ["proj-uuid-3"]
     }
     ```
   - `kleurthema` (hex color, e.g. "#0066CC")
   - `actief` (bool, default true)
   - `gemaakt_op` (timestamp)
   - **Validations**: eigenaar_id MUST be Nextcloud user; scope_filter MUST be valid JSON

2. **`PortfolioProjectLink`** — Materialization of `scope_filter` for snapshot stability (explicit link table)
   - `uuid` (PK)
   - `portfolio_id` (FK Portfolio)
   - `project_id` (FK planix projects)
   - `weight` (decimal 0-1) — relative weight in portfolio rollup (e.g., strategic projects get 1.0, operational 0.5)
   - `is_strategisch` (bool) — flag for executive views
   - `toegevoegd_op` (timestamp)
   - **Validations**: portfolio_id + project_id must be unique (no duplicates); weight between 0-1
   - **Synchronization**: triggers on portfolio.scope_filter mutation; resync links (idempotent upsert by project_id)

3. **`ProjectStatusReport`** — Monthly RAG-status snapshot per project (created by projectmanager, published by pmo_manager)
   - `uuid` (PK)
   - `project_id` (FK planix projects)
   - `rapportage_datum` (date, e.g. "2026-05-31")
   - `status_overall` (enum: "groen", "amber", "rood")
   - `status_planning` (enum: "groen", "amber", "rood")
   - `status_budget` (enum: "groen", "amber", "rood")
   - `status_resources` (enum: "groen", "amber", "rood")
   - `status_kwaliteit` (enum: "groen", "amber", "rood")
   - `voortgang_percentage` (decimal 0-100)
   - `samenvatting` (text, max 1000 chars)
   - `successen` (text)
   - `issues` (text)
   - `volgende_mijlpaal` (string)
   - `volgende_mijlpaal_datum` (date, optional)
   - `opgesteld_door_id` (FK users.uid)
   - `opgesteld_op` (timestamp)
   - `gepubliceerd` (bool, default false)
   - `corrigeert_rapportage_id` (FK ProjectStatusReport, optional) — links correction to previous rapportage
   - **Validations**:
     - Max 1 rapportage per project per calendar-month (409 Conflict if inserting 2nd); system calculates month from rapportage_datum
     - voortgang_percentage between 0-100
     - samenvatting max 1000 chars (enforced in editor + API validation)
     - gepubliceerd=true is irreversible (edit only allowed if gepubliceerd=false)
   - **Concept workflow**: new rapportage starts with gepubliceerd=false; projectmanager drafts; pmo_manager reviews and publishes

4. **`Risico`** — Per-project risk with impact scoring (PRINCE2 5×5 matrix)
   - `uuid` (PK)
   - `project_id` (FK planix projects)
   - `code` (string, e.g. "PRJ-2026-014-R03") — human-friendly risk identifier
   - `titel` (string, max 255)
   - `omschrijving` (text)
   - `categorie` (enum: "planning", "budget", "scope", "resources", "kwaliteit", "extern", "compliance", "reputatie")
   - `kans` (enum: "zeer_laag", "laag", "midden", "hoog", "zeer_hoog")
   - `impact` (enum: "zeer_laag", "laag", "midden", "hoog", "zeer_hoog")
   - `risico_score` (int 1-25, auto-calculated: score_kans(kans) × score_impact(impact)) — SYSTEM-ONLY, not user-writable
   - `eigenaar_id` (FK users.uid) — risk owner responsible for mitigation
   - `status` (enum: "open", "in_mitigation", "geaccepteerd", "verholpen", "gerealiseerd")
   - `mitigerende_maatregelen` (text)
   - `gemeld_op` (timestamp)
   - `laatst_beoordeeld_op` (timestamp)
   - `volgende_review_datum` (date, optional)
   - **Validations**:
     - risico_score auto-calculated: `{ zeer_laag: 1, laag: 2, midden: 3, hoog: 4, zeer_hoog: 5 } × { zeer_laag: 1, laag: 2, midden: 3, hoog: 4, zeer_hoog: 5 }`
     - code MUST match pattern `PRJ-{projectId}-R\d{2}` (auto-generated on create from project + sequence)
     - eigenaar_id MUST be Nextcloud user
   - **Scoring matrix** (PRINCE2 standard):
     ```
                   zeer_laag(1)  laag(2)  midden(3)  hoog(4)  zeer_hoog(5)
     zeer_laag(1)       1          2         3         4         5
     laag(2)            2          4         6         8        10
     midden(3)          3          6         9        12        15
     hoog(4)            4          8        12        16        20
     zeer_hoog(5)       5         10        15        20        25
     ```

5. **`ResourceAllocatie`** — Weekly resource planning per user per project (geplande vs werkelijke uren)
   - `uuid` (PK)
   - `user_id` (FK users.uid)
   - `project_id` (FK planix projects)
   - `week_iso` (string, format "YYYY-Www", e.g. "2026-W14")
   - `geplande_uren` (decimal)
   - `werkelijke_uren` (decimal, populated from planix time-tracking)
   - `rol_op_project` (enum: "projectleider", "ontwikkelaar", "tester", "stakeholder", "overig")
   - `opmerking` (text, optional)
   - **Validations**:
     - geplande_uren >= 0 and <= 60 (validator warns if > 40)
     - werkelijke_uren auto-synced from time-tracking; read-only in UI (calculated field)
     - Soft constraint: sum of user's geplande_uren across all projects in week_iso should not exceed user's contract hours (Nextcloud user.contract_hours); warning shown, not blocked
   - **Unique constraint**: (user_id, project_id, week_iso)

6. **`PortfolioSnapshot`** — Immutable monthly KPI snapshot for trend tracking
   - `uuid` (PK)
   - `portfolio_id` (FK Portfolio)
   - `snapshot_datum` (date, e.g. "2026-05-01")
   - `payload` (JSON) — complete KPI freeze:
     ```json
     {
       "portfolio_id": "uuid",
       "datum": "2026-05-01",
       "projecten_totaal": 24,
       "projecten_groen": 14,
       "projecten_amber": 7,
       "projecten_rood": 3,
       "gewogen_rag_score": 7.2,
       "rag_delta_vs_vorige": -0.3,
       "risico_score_portfolio": 10.3,
       "risico_score_delta": 0.5,
       "milestone_30d_count": 8,
       "milestone_30d_at_risk": 2,
       "milestone_90d_count": 22,
       "spend_begroot_eur": 8400000,
       "spend_realisatie_eur": 5900000,
       "spend_percentage": 70,
       "resource_utilisation": { "total_uren_gepland": 4200, "total_uren_contract": 4320, "percentage": 97 },
       "top_issues": [
         { "project": "PRJ-001", "issue": "Budget overrun", "severity": "hoog" }
       ]
     }
     ```
   - `gemaakt_door_id` (FK users.uid)
   - `commentaar` (text, optional — PMO notes on this snapshot)
   - `gebruikt_in_rapportage` (string, optional, e.g. "maandrapportage MT april 2026")
   - **Validations**:
     - snapshot_datum + portfolio_id must be unique (1 snapshot per portfolio per date)
     - payload is IMMUTABLE after creation (locked column; PUT rejects with 409)
     - creation timestamp auto-set to current time

---

### Three Read-Only Views

**`vw_portfolio_health`** — Aggregated health per portfolio (query all ProjectStatusReport records for projects in portfolio):
```sql
SELECT
  portfolio_id,
  COUNT(*) as projecten_totaal,
  SUM(CASE WHEN status_overall = 'groen' THEN 1 ELSE 0 END) as projecten_groen,
  SUM(CASE WHEN status_overall = 'amber' THEN 1 ELSE 0 END) as projecten_amber,
  SUM(CASE WHEN status_overall = 'rood' THEN 1 ELSE 0 END) as projecten_rood,
  (
    SUM(CASE WHEN status_overall = 'groen' THEN 3 
             WHEN status_overall = 'amber' THEN 2 
             ELSE 1 END * weight) / SUM(weight)
  ) as gewogen_rag_score,
  -- delta requires previous snapshot (self-join on date)
FROM project_status_report
  JOIN portfolio_project_link ON ...
WHERE rapportage_datum = (SELECT MAX(rapportage_datum) FROM project_status_report)
GROUP BY portfolio_id
```

**`vw_resource_utilization`** — Per user per week, planned vs contract hours:
```sql
SELECT
  user_id,
  week_iso,
  SUM(geplande_uren) as uren_gepland,
  (SELECT contract_hours FROM users WHERE uid = user_id) as uren_contract,
  ROUND(100 * SUM(geplande_uren) / contract_hours, 0) as utilisatie_percentage,
  STRING_AGG(DISTINCT project_id, ', ') as projecten
FROM resource_allocatie
GROUP BY user_id, week_iso
```

**`vw_milestone_radar`** — All milestones in next 90 days:
```sql
SELECT
  milestone_id,
  milestone_naam,
  due_date,
  project_id,
  portfolio_id,  -- via portfolio_project_link
  milestone_status,  -- on-time | at-risk | vertraagd (computed from underlying task status)
  DATEDIFF(day, due_date, CURRENT_DATE) as giorni_da_scadenza
FROM planix_milestones
  JOIN portfolio_project_link ON planix_milestones.project_id = portfolio_project_link.project_id
WHERE due_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '90 days'
ORDER BY due_date
```

---

### Security & Authorization

**Role-Based Access Control** (RBAC; OpenRegister property-based):

| Role | Portfolio CRUD | ProjectStatusReport Create/Publish | Risico Edit | ResourceAllocatie Edit | Snapshot View | Comments |
|---|---|---|---|---|---|---|
| `pmo_manager` | ✓ CRUD assigned | ✓ publish | ✓ Edit own | ✓ Edit own | ✓ View+trend | Full access, can publish; escalates issues |
| `pmo_lid` | ✗ (read only) | ✓ Draft (not publish) | ✓ Edit own | ✓ Edit own | ✓ View | Helper; assists project managers; cannot publish |
| `portfolio_eigenaar` | ✗ (read + assign PM) | ✗ (read only) | ✗ (read only) | ✗ (read only) | ✓ View+exec-summary | Executive; read-only; add comments; sees executive-summary |
| `program_manager` | ✗ (read own programma) | ✗ (read only) | ✗ (read only) | ✗ (read only) | ✓ View (filtered) | Scoped to own BBV-programma; sees dependent projects only |
| `projectmanager` | ✗ (read portfolio context) | ✓ Create/Edit own | ✓ Own project only | ✓ Own team only | ✗ | Drafts rapportages; registers risks; allocates team FTE |
| Admin | ✓ All | ✓ All | ✓ All | ✓ All | ✓ All | Tenant admin; can assign PMO roles |

**Per-Object Checks**:
- All mutations checked via service authorization method: `authorizePortfolioAccess(portfolio, user)`, `authorizeStatusReportageEdit(rapportage, user)`, etc.
- Mutation endpoints return 403 Forbidden if unauthorized
- List endpoints filtered by user's accessible portfolios (Nextcloud RBAC scope)

---

### Frontend Architecture

**Route Map**:
```
/planix/portfolio                      → PortfolioListPage.vue
/planix/portfolio/:id                  → PortfolioDashboard.vue (configurable layout)
/planix/portfolio/:id/exec             → ExecutiveSummary.vue (bestuur-facing)
/planix/portfolio/:id/settings         → PortfolioSettingsPage.vue (scope_filter editor, pmo_manager only)
```

**Pages & Components** (Vue 2 + @conduction/nextcloud-vue):

1. **PortfolioListPage.vue** — CnIndexPage pattern
   - Table: portfolio list (name, owner, project-count, RAG-summary, actions)
   - Create button (pmo_manager only) → PortfolioFormDialog
   - Search by name
   - Filter by pmo_manager (show only assigned)
   - Actions: edit (scope_filter), delete (pmo_manager), view dashboard

2. **PortfolioDashboard.vue** — CnDashboardPage (GridStack layout)
   - Header: portfolio name, RAG-tile summary (groen/amber/rood counts), last-updated
   - Layout: 3-column grid, 8 widgets (user-configurable per portfolio, saved to localStorage or backend preference)
   - Widgets:
     - **PortfolioHealthTable** (CnDataTable) — sortable, filterable RAG-table; drill-down to project-detail
     - **ResourceUtilizationHeatmap** (custom SVG) — user × week heatmap; click → ResourceConflictModal
     - **MilestoneRadar** (custom SVG timeline) — 90-day forward-looking; click → milestone-detail
     - **RiskRegister** (custom 5×5 SVG grid) — risks plotted by kans×impact; click → RiskDetailModal
     - **DependencyGraph** (D3/Cytoscape) — DAG visualization; highlight at-risk paths
     - **SpendVsBudget** (CnChartWidget / ApexCharts bar chart) — begroot vs realisatie; drill-down per project
     - **StatusTrend** (ApexCharts sparklines) — 12-month snapshots; line chart RAG-counts, spend%, risk-score
     - **ExecutiveSummaryWidget** — KPI-tiles, top-3 issues/successen, mini-radar, presentatie-button
   - Settings button (top-right) → drag-to-reorder widgets (GridStack), save layout

3. **ExecutiveSummary.vue** — One-page executive view (bestuur-facing)
   - Header: portfolio name, datum
   - 4-6 KPI tiles: projecten-total, op-koers %, amber %, rood %, risk-score, milestone-status
   - Text sections: Top-3 Issues, Top-3 Successen (from latest ProjectStatusReport.issues/.successen)
   - Mini Milestone Radar (30 days, not 90)
   - Button: "Presentatie-modus" → fullscreen, hide nav, suitable for projector
   - Read-only except: "Commentaar toevoegen" → CommentModal (add comment to portfolio, notify pmo_manager)

4. **PortfolioSettingsPage.vue** (if pmo_manager)
   - Form: portfolio name, omschrijving, eigenaar_id (user-picker), kleurthema (color-picker), actief (toggle)
   - JSON editor for scope_filter (with validation / syntax-highlight)
   - Button: "Synchroniseer links" (resync portfolio_project_link from scope_filter, manual refresh)
   - Delete button (pmo_manager only) → confirm dialog

**Modals & Dialogs**:

1. **PortfolioFormDialog.vue** — Create/edit portfolio
   - Fields: naam, omschrijving, eigenaar_id, kleurthema, actief
   - Button: Create → POST /api/portfolios, Edit → PUT /api/portfolios/{id}

2. **StatusReportageWizard.vue** — Multi-step wizard
   - Step 1: Load previous rapportage as concept (if exists); show rapportage_datum input
   - Step 2: RAG-status inputs (5 dropdowns: overall, planning, budget, resources, kwaliteit)
   - Auto-suggestions: "3 tasks overdue → planning on amber?" (computed from planix task data)
   - Step 3: Narrative (samenvatting, successen, issues, volgende_mijlpaal + volgende_mijlpaal_datum)
   - Step 4: Review + Publish
     - Checkbox: "Ik bevestig dat deze rapportage juist is"
     - Button: "Publiceer naar PMO" (POST /api/projects/{id}/status-reports/{id}/publish, pmo_manager permission)
     - Notification to pmo_manager: "New status report from Project X"
   - Concept auto-saved on step-change (PUT /api/projects/{id}/status-reports/{id})

3. **RiskDetailModal.vue** — Edit/view risk
   - Fields: titel, omschrijving, categorie (dropdown), kans (picker), impact (picker), status (enum), mitigerende_maatregelen, eigenaar_id
   - Read-only: code, risico_score (auto-calculated)
   - Button: Save (PUT /api/risicos/{id}) or Delete (DELETE /api/risicos/{id})

4. **ResourceConflictModal.vue** — Drill-down from heatmap cell
   - Header: "Janneke — Week 18 — 44u / 32u (138%)"
   - Table: projects for that user-week, with allocated uren per project
   - Chart: stacked-bar showing hours breakdown

5. **DependencyAddModal.vue** — Add inter-project dependency
   - From-project (selected), To-project (selector), milestone-name (link-to), description
   - Button: Add → POST /api/dependencies; validation checks for cycles (422 if cycle detected, returns path)

6. **CommentModal.vue** — Add comment to portfolio (portfolio_eigenaar + pmo_manager only)
   - Textarea: free-form comment
   - Button: Save → POST /api/portfolios/{id}/comments; notifies pmo_manager

**Store** (`src/store/modules/portfolioStore.js`):
```javascript
createObjectStore('portfolio', {
  crud: true,
  relations: {
    portfolio_project_link: 'many',
    project_status_report: 'many',
    risico: 'many',
    resource_allocatie: 'many',
    portfolio_snapshot: 'many'
  }
})
```

**Composables**:
- `usePortfolioDashboard(portfolioId)` — Load portfolio + health-view + all widgets in parallel (Promise.all)
- `useStatusReportageWizard(projectId, month)` — Load previous rapportage, compute issue-suggestions from planix task data
- `useDependencyGraph(portfolioId)` — Load all inter-project dependencies, detect cycles on add

**Utilities**:
- `src/utils/riskScoring.js` — riskScore(kans, impact) → 1-25 (lookup table)
- `src/utils/dateHelpers.js` — isWithin90Days, weekIsoString, etc.
- `src/utils/resourceValidation.js` — validateResourceAllocatie(allocatie, contractHours) → { valid: bool, warning: string }

---

### API Endpoints

**Portfolios** (OpenRegister CRUD via ObjectService):
- `GET /apps/planix/api/portfolios` — List all (paginated, filtered by pmo_manager if not admin); returns `[{ uuid, naam, projecten_count, status_overall_counts, pmo_manager_id }]`
- `POST /apps/planix/api/portfolios` — Create (pmo_manager) → 201 + portfolio object
- `GET /apps/planix/api/portfolios/{id}` — Detail + vw_portfolio_health rollup
- `PUT /apps/planix/api/portfolios/{id}` — Edit (pmo_manager)
- `DELETE /apps/planix/api/portfolios/{id}` — Delete (pmo_manager)

**Dashboard Widgets** (aggregation endpoints):
- `GET /apps/planix/api/portfolios/{id}/health-table?sort=status_overall&page=1&per_page=20` — Paginated, filterable RAG-table
- `GET /apps/planix/api/portfolios/{id}/risk-register` — 5×5 matrix raw data; returns `{ data: [[risk, ...], ...], matrix_counts: { groen, amber, rood } }`
- `GET /apps/planix/api/portfolios/{id}/resource-heatmap?week_start=2026-W12&week_count=13` — Heatmap data; returns `[{ user_id, week, uren_gepland, uren_contract, percentage, projects: [{ project_id, uren }] }]`
- `GET /apps/planix/api/portfolios/{id}/milestone-radar?days=90` — All milestones next 90 days; returns `[{ milestone_id, due_date, project_id, status, is_at_risk }]`
- `GET /apps/planix/api/portfolios/{id}/dependencies` — All inter-project dependencies; returns `[{ from_project, to_project, milestone, is_at_risk }]`
- `GET /apps/planix/api/portfolios/{id}/spend-vs-budget` — Budget + spend aggregate; returns `{ begroot, realisatie, percentage, projects: [{ project_id, begroot, realisatie, % }] }`
- `GET /apps/planix/api/portfolios/{id}/snapshots?limit=12` — Last 12 monthly snapshots for trend-view; returns `[{ snapshot_datum, payload }]`

**Status Rapportages** (OpenRegister CRUD + publish):
- `GET /apps/planix/api/projects/{project_id}/status-reports` — List rapportages for project (paginated); returns `[{ uuid, rapportage_datum, status_overall, gepubliceerd, opgesteld_door_id, voortgang_percentage }]`
- `POST /apps/planix/api/projects/{project_id}/status-reports` — Create draft (projectmanager) → 201 + rapportage object
- `GET /apps/planix/api/projects/{project_id}/status-reports/{id}` — Detail
- `PUT /apps/planix/api/projects/{project_id}/status-reports/{id}` — Edit (only if gepubliceerd=false; projectmanager or pmo_manager)
- `POST /apps/planix/api/projects/{project_id}/status-reports/{id}/publish` — Publish (pmo_manager only) → set gepubliceerd=true, notify pmo_manager
- `DELETE /apps/planix/api/projects/{project_id}/status-reports/{id}` — Delete (only if gepubliceerd=false; projectmanager or pmo_manager)

**Risks** (OpenRegister CRUD):
- `GET /apps/planix/api/projects/{project_id}/risicos` — List risicos for project (paginated)
- `POST /apps/planix/api/projects/{project_id}/risicos` — Create (projectmanager)
- `GET /apps/planix/api/projects/{project_id}/risicos/{id}` — Detail
- `PUT /apps/planix/api/projects/{project_id}/risicos/{id}` — Edit (projectmanager or risk eigenaar)
- `DELETE /apps/planix/api/projects/{project_id}/risicos/{id}` — Delete (projectmanager or risk eigenaar)

**Resource Allocaties** (OpenRegister CRUD):
- `GET /apps/planix/api/projects/{project_id}/resource-allocaties?week_iso=2026-W14` — List allocaties for project in week (paginated)
- `POST /apps/planix/api/projects/{project_id}/resource-allocaties` — Create (pmo_manager or projectmanager)
- `PUT /apps/planix/api/projects/{project_id}/resource-allocaties/{id}` — Edit
- `DELETE /apps/planix/api/projects/{project_id}/resource-allocaties/{id}` — Delete

**Portfolio Snapshots** (OpenRegister CRUD + immutability):
- `GET /apps/planix/api/portfolios/{id}/snapshots` — List (paginated, ordered by snapshot_datum desc)
- `POST /apps/planix/api/portfolios/{id}/snapshots` — Create snapshot now (pmo_manager or automated by n8n); generates payload from current KPI's; returns 409 if snapshot already exists for that date
- `GET /apps/planix/api/portfolios/{id}/snapshots/{date}` — Get snapshot for date; payload is immutable
- `PUT /apps/planix/api/portfolios/{id}/snapshots/{date}` — Edit metadata only (commentaar, gebruikt_in_rapportage); payload stays immutable; returns 422 if attempting to edit payload

**Webhooks** (inbound from financeq, outbound to n8n):
- `POST /apps/planix/api/webhooks/financeq-spend-change` — Received when financeq spend-realization changes; triggers portfolio spend rollup update
- `POST /apps/planix/api/webhooks/snapshot-ready` — (n8n → planix) Triggers automatic snapshot creation

**Issue Suggestions** (for rapportage wizard):
- `GET /apps/planix/api/projects/{project_id}/status-suggestions` — Returns list of data-driven suggestions for status-rapportage (e.g., "3 tasks overdue")

---

### Integration Points

**financeq** (spend data):
- Webhook listener: financeq POST to `/apps/planix/api/webhooks/financeq-spend-change` on spend-realization mutation
- Planix backend: update portfolio spend-cache (or compute on-demand from financeq API) and update portfolio-snapshots

**n8n** (scheduled snapshot):
- Cron schedule: 1st of month, 02:00 UTC
- Action: POST to `/apps/planix/api/portfolios/{portfolio-id}/snapshots` with empty body (system generates payload from current KPI's)
- Error handling: n8n logs failures; fallback: manual snapshot creation via UI button

**mydash** (dashboard widgets):
- Register 8 widgets via `IWidgetRegistry` (OCP\Dashboard\IWidget)
- Each widget implements `getId()`, `getTitle()`, `getOrder()`, `getIconClass()`, `getUrl()`
- Widget tiles receive config (portfolio_id, filter, tile-size) from mydash layout
- Frontend: same 8 Vue components, but wrapped in mydash `CnWidgetRenderer` component (loads data from planix API)

**BBV-programma-tree** (strategic context):
- Scope filter can reference `bbv_doel_ids` (program UUIDs from BBV)
- Planix portfolio-detail shows "Part of BBV Programma: [list]" as context
- Read-only link; no mutation

**procest** (case completion):
- If project.zaakUuid is set (existing planix integration), projectmanager can include zaak-completion % in status-rapportage voortgang_percentage
- Read-only lookup; no mutation

---

### Seed Data

**3 Portfolios**:
1. "Strategische Projecten 2026" — 4 projects, own status-reports, risks, resources
2. "Afdeling Digitaal" — 3 projects (filtererd by afdeling_id)
3. "Programma Mobiliteit" — 2 projects (filtered by bbv_doel_id)

**Per Portfolio**:
- 4-6 ProjectStatusReport objects (past months + current draft)
- 5-8 Risico objects (various categories, kans/impact scores)
- 8-10 ResourceAllocatie objects (for 2-3 team members, various weeks)
- 3 PortfolioSnapshot objects (Jan, Feb, Mar 2026 — for trend demo)

**Realistic Dutch Values**:
- User names: Jan van der Berg, Annemarie de Vries, Henk Smit, Pieter van Dijk
- Organization: Gemeente Amsterdam, Woningcorporatie De Sleutel, MKB-bedrijf TechCo
- Budget amounts: EUR 500K—8.4M (scale to organization)
- Risk categories: Lokale zaken (planning, resources), BBV-compliance (compliance, extern)

---

## Reuse Analysis

**OpenRegister Services** (leveraged):
- `ObjectService::saveObject()` — create/update/delete portfolio, risico, resource_allocatie, snapshot
- `ObjectService::findAll()` — list with pagination, sorting, filtering
- `RegisterService::getSchema()` — validate against 6 new schemas
- `ConfigurationService::importFromApp()` — import schemas + seed data on install
- `RelationsPlugin` — link portfolio → projects via portfolio_project_link

**@conduction/nextcloud-vue Components** (reused):
- `CnIndexPage` — portfolio list
- `CnDashboardPage` — dashboard layout (GridStack)
- `CnDataTable` — health-table widget
- `CnChartWidget` — spend-vs-budget, status-trend charts
- `CnDetailPage` — project-detail (existing; no changes)
- `CnObjectSidebar` — files, notes, audit for portfolios (existing pattern)
- `CnFormDialog` — portfolio create/edit, risk edit
- `CnEmptyState` — when no portfolios or projects

**Nextcloud Services** (reused):
- `IUserManager::search()` — user picker for eigenaar_id, pmo_manager_id, eigenaar_id
- `IGroupManager::inGroup()` — check if user in 'pmo' group (role assignment)
- `NotificationService` — alerts to pmo_manager when status-rapportage published
- `ActivityService` — log portfolio changes, snapshot creation
- `CalendarEventService` — optional: sync milestones to calendar

**No Overlap / New Logic**:
- Risico-score auto-calculation (kans × impact matrix)
- Dependency-graph cycle detection (DFS topological sort)
- Resource-utilization heatmap visualization (custom SVG)
- Milestone-radar timeline (custom SVG + data aggregation)
- Status-suggestion engine (data-driven insights from task state)

---

## Deduplication Check

✓ Portfolio/project grouping → No existing similar in planix (first cross-project view)
✓ RAG-status matrix → PRINCE2 standard; not in OpenRegister shared libs; new to planix
✓ Risico register → PMBoK standard; not in openregister RiskManagement schema; new to planix
✓ Resource heatmap — No existing visualization; new to planix
✓ Snapshot versioning → Immutable history; not in openregister audit-trails (different purpose)
✓ Dependency graph → New capability; not in planix

**Existing Open Register Patterns Reused**:
- ObjectStore CRUD (existing, no duplication)
- Relation system (existing, no duplication)
- File/note/audit tabs (existing, reused via CnObjectSidebar)

---

## Deployment & Configuration

**Installation**:
1. Verify OpenRegister installed: `curl {nextcloud}/ocs/v2.php/apps/openregister/api/v1/status`
2. Enable planix app
3. Run repair step: `occ repair:run --app=planix` → imports 6 schemas + 3 seed portfolios
4. Verify schemas: `occ openregister:list-schemas | grep portfolio`
5. Assign PMO roles (Nextcloud admin UI) to PMO team members (users → edit → PMO role)
6. Install n8n flow template (docs/n8n/portfolio-snapshot-monthly-template.json)
7. Configure financeq webhook: financeq admin → Settings → Webhooks → add `{nextcloud}/apps/planix/api/webhooks/financeq-spend-change`

---

**OpenSpec Change**: `portfolio-dashboard-pmo`  
**Status**: design
