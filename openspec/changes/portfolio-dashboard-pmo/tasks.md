# Tasks: Portfolio Dashboard PMO

## Task Breakdown

### Backend — Data Layer & Schemas

- [ ] **Define 6 OpenRegister schemas** in `lib/Settings/planix_register.json` (OpenAPI 3.0 format)
  - [ ] Portfolio (UUID, naam, omschrijving, eigenaar_id, pmo_manager_id, scope_filter JSON, kleurthema, actief, gemaakt_op)
  - [ ] PortfolioProjectLink (UUID, portfolio_id FK, project_id FK, weight decimal, is_strategisch bool, toegevoegd_op)
  - [ ] ProjectStatusReport (UUID, project_id FK, rapportage_datum date, 5× status enum, voortgang_percentage, samenvatting text, successen text, issues text, volgende_mijlpaal, volgende_mijlpaal_datum, opgesteld_door_id, opgesteld_op, gepubliceerd bool, corrigeert_rapportage_id FK)
  - [ ] Risico (UUID, project_id FK, code string, titel, omschrijving, categorie enum, kans enum, impact enum, risico_score auto-calculated, eigenaar_id, status enum, mitigerende_maatregelen, gemeld_op, laatst_beoordeeld_op, volgende_review_datum)
  - [ ] ResourceAllocatie (UUID, user_id FK, project_id FK, week_iso string, geplande_uren decimal, werkelijke_uren decimal, rol_op_project enum, opmerking)
  - [ ] PortfolioSnapshot (UUID, portfolio_id FK, snapshot_datum date, payload JSON immutable, gemaakt_door_id, commentaar, gebruikt_in_rapportage)
  - [ ] Add validation rules per schema (uniqueness, enum constraints, immutability, max-length)
  - [ ] Test schema validation with OpenRegister SchemaValidator

- [ ] **Create three read-only views** (SQL queries or on-demand aggregation)
  - [ ] vw_portfolio_health — SELECT portfolio_id, project counts (groen/amber/rood), weighted RAG-score, delta vs previous snapshot
  - [ ] vw_resource_utilization — SELECT user_id, week_iso, planned_hours, contract_hours, utilization_percentage, projects_list
  - [ ] vw_milestone_radar — SELECT milestone data, portfolio_id, due_date, status, 90-day filter

- [ ] **Seed data generation** — Create 3 realistic portfolios with linked data
  - [ ] Portfolio 1: "Strategische Projecten 2026" (4 projects)
    - [ ] Add 2 ProjectStatusReport per project (Feb, Mar, Apr 2026) with realistic RAG-values
    - [ ] Add 5-8 Risico objects across projects (various categories, kans/impact combinations)
    - [ ] Add 8-10 ResourceAllocatie records (for 2-3 team members, weeks W12-W20)
    - [ ] Add 3 PortfolioSnapshot records (Jan, Feb, Mar 2026) with realistic payload
  - [ ] Portfolio 2: "Afdeling Digitaal" (3 projects, filtered by afdeling_id)
  - [ ] Portfolio 3: "Programma Mobiliteit" (2 projects, filtered by bbv_doel_id)
  - [ ] Use realistic Dutch values: gemeente names (Amsterdam, Rotterdam, Utrecht), user names (Jan van der Berg, Annemarie de Vries), budget amounts (EUR 500K—8.4M), risico categories (planning, budget, resources, compliance)
  - [ ] Ensure cross-register consistency: user_ids valid Nextcloud users, project_ids valid planix projects, dates within 2026
  - [ ] Seed data marked with `@self` envelope for idempotent import

- [ ] **Implement ConfigurationService integration** for auto-import on install
  - [ ] Register import handler in repair step (`src/Settings/Migration/PlanixPortfolioDashboardPMOMigration.php` or similar)
  - [ ] Import schemas + seed data via `ConfigurationService::importFromApp('planix', {...})`
  - [ ] Verify idempotency (re-running repair doesn't create duplicates)
  - [ ] Test: uninstall app, delete schemas, reinstall → verify schemas + seeds re-created

### Backend — API Endpoints

- [ ] **Portfolio CRUD endpoints** (OpenRegister ObjectService + custom pagination/filtering)
  - [ ] GET `/apps/planix/api/portfolios` → list all (paginated, filter by pmo_manager if not admin)
  - [ ] POST `/apps/planix/api/portfolios` → create (pmo_manager only)
  - [ ] GET `/apps/planix/api/portfolios/{id}` → detail + vw_portfolio_health rollup
  - [ ] PUT `/apps/planix/api/portfolios/{id}` → edit (pmo_manager)
  - [ ] DELETE `/apps/planix/api/portfolios/{id}` → delete (pmo_manager)
  - [ ] Validate authorization: pmo_manager must be eigenaar or have global pmo_manager role
  - [ ] Test: unauthorized user gets 403; pmo_manager CRUD works; admin overrides

- [ ] **Dashboard widget data endpoints** (aggregation queries)
  - [ ] GET `/apps/planix/api/portfolios/{id}/health-table?sort=status_overall&page=1&per_page=20` → paginated RAG-table
  - [ ] GET `/apps/planix/api/portfolios/{id}/risk-register` → 5×5 matrix data + cell counts
  - [ ] GET `/apps/planix/api/portfolios/{id}/resource-heatmap?week_start=2026-W12&week_count=13` → heatmap data (user, week, percentage, projects breakdown)
  - [ ] GET `/apps/planix/api/portfolios/{id}/milestone-radar?days=90` → milestones next N days (due_date, status, project_id, is_at_risk)
  - [ ] GET `/apps/planix/api/portfolios/{id}/dependencies` → all inter-project dependencies (from_project, to_project, milestone, is_at_risk)
  - [ ] GET `/apps/planix/api/portfolios/{id}/spend-vs-budget` → budget + spend aggregate (queries financeq or internal cache)
  - [ ] GET `/apps/planix/api/portfolios/{id}/snapshots?limit=12` → last N monthly snapshots for trend-view
  - [ ] Each endpoint: authorize (portfolio_owner or pmo_manager or admin), cache for 1 hour, handle errors gracefully

- [ ] **Project Status-Report endpoints**
  - [ ] GET `/apps/planix/api/projects/{project_id}/status-reports` → list rapportages (paginated, newest first)
  - [ ] POST `/apps/planix/api/projects/{project_id}/status-reports` → create draft (projectmanager)
  - [ ] GET `/apps/planix/api/projects/{project_id}/status-reports/{id}` → detail (read-only if published)
  - [ ] PUT `/apps/planix/api/projects/{project_id}/status-reports/{id}` → edit (only if not published; projectmanager or pmo_manager)
  - [ ] POST `/apps/planix/api/projects/{project_id}/status-reports/{id}/publish` → publish (pmo_manager only, sets gepubliceerd=true)
  - [ ] DELETE `/apps/planix/api/projects/{project_id}/status-reports/{id}` → delete (only if not published)
  - [ ] Validate: max 1 rapportage per project per calendar-month (409 if duplicate)
  - [ ] On publish: send NotificationService notification to pmo_manager, log to ActivityService

- [ ] **Risico endpoints**
  - [ ] GET `/apps/planix/api/projects/{project_id}/risicos` → list (paginated)
  - [ ] POST `/apps/planix/api/projects/{project_id}/risicos` → create (projectmanager)
  - [ ] GET `/apps/planix/api/projects/{project_id}/risicos/{id}` → detail
  - [ ] PUT `/apps/planix/api/projects/{project_id}/risicos/{id}` → edit (projectmanager or risk eigenaar)
  - [ ] DELETE `/apps/planix/api/projects/{project_id}/risicos/{id}` → delete (projectmanager or risk eigenaar)
  - [ ] Validate: auto-calculate risico_score (kans_weight × impact_weight per PRINCE2 matrix); code auto-generated pattern PRJ-{projectId}-R{seq}
  - [ ] Test: risico-score updates correctly on kans/impact change

- [ ] **ResourceAllocatie endpoints**
  - [ ] GET `/apps/planix/api/projects/{project_id}/resource-allocaties?week_iso=2026-W14` → list (paginated)
  - [ ] POST `/apps/planix/api/projects/{project_id}/resource-allocaties` → create (pmo_manager or projectmanager)
  - [ ] PUT `/apps/planix/api/projects/{project_id}/resource-allocaties/{id}` → edit
  - [ ] DELETE `/apps/planix/api/projects/{project_id}/resource-allocaties/{id}` → delete
  - [ ] Validate: geplande_uren 0-60 (warning if >40); unique (user_id, project_id, week_iso)
  - [ ] werkelijke_uren auto-synced from planix time-tracking (read-only in API, computed field)
  - [ ] Test: soft-warn on over-capacity without blocking

- [ ] **Portfolio Snapshot endpoints**
  - [ ] GET `/apps/planix/api/portfolios/{id}/snapshots` → list (paginated, ordered by snapshot_datum desc)
  - [ ] POST `/apps/planix/api/portfolios/{id}/snapshots` → create snapshot (pmo_manager or automated by n8n); generates payload; 409 if exists for date
  - [ ] GET `/apps/planix/api/portfolios/{id}/snapshots/{date}` → get snapshot (immutable payload)
  - [ ] PUT `/apps/planix/api/portfolios/{id}/snapshots/{date}` → edit metadata only (commentaar, gebruikt_in_rapportage); 422 if modifying payload
  - [ ] Payload auto-generation: query vw_portfolio_health, risico scores, milestone counts, financeq spend, aggregate

- [ ] **Webhook endpoints** (financeq, n8n)
  - [ ] POST `/apps/planix/api/webhooks/financeq-spend-change` → inbound from financeq on spend-realization change; trigger portfolio spend-rollup update
  - [ ] Validate webhook signature (if financeq provides)
  - [ ] POST `/apps/planix/api/webhooks/snapshot-ready` → inbound from n8n; triggers snapshot creation (safety fallback)

- [ ] **Issue suggestion endpoint** (for rapportage wizard)
  - [ ] GET `/apps/planix/api/projects/{project_id}/status-suggestions?month=2026-04` → data-driven suggestions
    - [ ] Count overdue tasks → suggest planning=amber
    - [ ] Calculate budget variance vs spend → suggest budget=rood if >10% over
    - [ ] Calculate resource utilization → suggest resources=amber if >100%
    - [ ] Check upstream dependencies for at-risk status → suggest overall=amber if dependency at-risk
    - [ ] Return array of suggestions: `[{ issue: "3 tasks overdue", suggestion: "Consider planning=amber" }, ...]`

### Backend — Authorization & Roles

- [ ] **Define 5 new roles** in role-manager or config
  - [ ] pmo_manager — full CRUD assigned portfolios, publish rapportages, create snapshots
  - [ ] pmo_lid — read + draft rapportages (no publish), edit resources/risks, view snapshots
  - [ ] portfolio_eigenaar — read-only + add comments, assign pmo_manager
  - [ ] program_manager — read scoped to own BBV-programma
  - [ ] (existing projectmanager extended) — can draft own project rapportages + register risks

- [ ] **Implement authorization service** (`src/Service/AuthorizationService.php` or extend existing)
  - [ ] authorizePortfolioAccess(portfolio, user, action) → returns bool or throws OCSForbiddenException
  - [ ] authorizeStatusReportagePublish(rapportage, user) → pmo_manager only
  - [ ] authorizeRiscoEdit(risico, user) → projectmanager or risk eigenaar or pmo_manager
  - [ ] authorizeResourceAllocatieEdit(allocatie, user) → pmo_manager or projectmanager (own team)
  - [ ] Called from every mutation endpoint before ObjectService.saveObject()

- [ ] **Test authorization** with unit tests
  - [ ] pmo_lid cannot publish (403)
  - [ ] portfolio_eigenaar cannot edit (403 but can comment)
  - [ ] projectmanager cannot edit others' risks (403)
  - [ ] admin overrides all (200)

### Backend — Integration with financeq & n8n

- [ ] **WebhookService listener for financeq**
  - [ ] Create listener class listening for financeq spend-realization changes
  - [ ] On webhook: parse financeq data, extract project-cost-center, fetch/cache spend-amount
  - [ ] Update internal portfolio spend-rollup (or mark for refresh on next dashboard load)
  - [ ] Log to activity feed

- [ ] **n8n flow template** (docs/n8n/portfolio-snapshot-monthly-template.json)
  - [ ] Cron trigger: 1st of month, 02:00 UTC
  - [ ] For each portfolio: POST `/apps/planix/api/portfolios/{id}/snapshots` with empty body
  - [ ] Planix generates payload from current KPI's
  - [ ] n8n logs result; on failure, alert n8n admin
  - [ ] Provide template file with instructions for n8n admin to import + configure

### Frontend — Pages & Layout

- [ ] **Portfolio List page** (`src/views/Portfolio/PortfolioListPage.vue`)
  - [ ] Use CnIndexPage pattern (search, filter, create, list)
  - [ ] Table columns: name, owner, project-count, RAG-summary (groen/amber/rood), actions
  - [ ] Create button → PortfolioFormDialog (pmo_manager only)
  - [ ] Row actions: View (navigate to dashboard), Edit (scope_filter, pmo_manager only), Delete (pmo_manager only)
  - [ ] Filter by pmo_manager (show only assigned portfolios if not admin)
  - [ ] Search by portfolio name
  - [ ] Test: unauthorized user doesn't see Create button; pmo_manager sees all portfolios assigned

- [ ] **Portfolio Dashboard page** (`src/views/Portfolio/PortfolioDashboard.vue`)
  - [ ] Use CnDashboardPage (GridStack layout, user-configurable)
  - [ ] Header: portfolio name, last-updated timestamp, RAG-summary tiles (count groen/amber/rood)
  - [ ] 8 widget slots (drag-to-reorder, save layout to localStorage per portfolio)
  - [ ] Load all 8 widgets in parallel (Promise.all) with error-boundaries
  - [ ] Settings button (top-right) → PortfolioSettingsPage (pmo_manager only) or scope-filter editor
  - [ ] Test: widgets load within 2s; layout persists; read-only user can't drag-reorder

- [ ] **Executive Summary page** (`src/views/Portfolio/ExecutiveSummary.vue`)
  - [ ] One-page view: header, KPI-tiles (4-6), top-3 issues, top-3 successen, mini-milestone-radar
  - [ ] Read-only except: "Add comment" buttons
  - [ ] Button: "Presentatie-modus" → fullscreen, hide nav
  - [ ] Responsive for large-screen projection (scale text for 2m distance readability)
  - [ ] Test: presentatie-modus works; ESC key exits; mobile view degrades gracefully

- [ ] **Portfolio Settings page** (`src/views/Portfolio/PortfolioSettingsPage.vue`)
  - [ ] Form: naam, omschrijving, eigenaar_id (user-picker), kleurthema (color-picker), actief (toggle)
  - [ ] JSON editor for scope_filter with syntax-highlight + validation
  - [ ] Button: "Synchroniseer links" (resync portfolio_project_link from scope_filter)
  - [ ] Delete button (pmo_manager only) → confirm dialog
  - [ ] Save → PUT /api/portfolios/{id}
  - [ ] Test: scope-filter JSON validation; resync works; unauthorized gets 403

### Frontend — Widgets (8 total)

- [ ] **PortfolioHealthTable widget** (`src/components/Portfolio/PortfolioHealthTable.vue`)
  - [ ] CnDataTable over `/api/portfolios/{id}/health-table` endpoint
  - [ ] Columns: project-name (clickable), 5× RAG-circles (overall, planning, budget, resources, kwaliteit), voortgang %, project-manager, next-milestone, RAG-deltas (↑ ↓ →)
  - [ ] Sortable: by RAG-status (red first), voortgang, next-milestone-date
  - [ ] Filterable: by status_overall = groen/amber/rood
  - [ ] Header: counts (24 projects | 14 green | 7 amber | 3 red) reflecting visible filter
  - [ ] Row click → navigate to project-detail (existing planix route)
  - [ ] Test: filters work; sort works; counts update on filter; row click navigates

- [ ] **ResourceUtilizationHeatmap widget** (`src/components/Portfolio/ResourceUtilizationHeatmap.vue`)
  - [ ] Custom SVG heatmap (users y-axis, weeks x-axis)
  - [ ] Color-coding: green ≤80%, yellow 80-100%, orange 100-120%, red >120%
  - [ ] Lazy-load weeks (initial: current + 12 weeks ahead; paginate older/future on scroll)
  - [ ] Cell click → ResourceConflictModal (drill-down)
  - [ ] Hover → tooltip "name, week, Xh / Yh (Z%)"
  - [ ] Test: heatmap renders; colors correct; drill-down works; virtualization handles 200+ cells

- [ ] **MilestoneRadar widget** (`src/components/Portfolio/MilestoneRadar.vue`)
  - [ ] Custom SVG timeline (90 days horizontal)
  - [ ] Circles (bubbles) for milestones: position by due_date, size by importance, color by status (green on-time, orange at-risk, red missed)
  - [ ] Click bubble → milestone-detail
  - [ ] Filter toggle: "Only at-risk + missed"
  - [ ] Sidebar: list of visible milestones with drill-down (why at-risk → which task overdue / which risico)
  - [ ] Test: timeline renders; bubbles positioned correctly; filter works; drill-down shows cause

- [ ] **RiskRegister widget** (`src/components/Portfolio/RiskRegister.vue`)
  - [ ] 5×5 kans×impact grid (SVG or canvas)
  - [ ] Cells colored by density (or dots per cell)
  - [ ] Click cell → RiskDetailModal (edit/view risk)
  - [ ] Hover cell → tooltip "N risks in this cell"
  - [ ] Legend: score scale (1-25)
  - [ ] Test: grid renders; cells clickable; color scale correct

- [ ] **DependencyGraph widget** (`src/components/Portfolio/DependencyGraph.vue`)
  - [ ] D3.js or Cytoscape.js DAG visualization
  - [ ] Nodes: projects; edges: dependencies
  - [ ] Edge color: green (healthy), orange (at-risk upstream), red (blocked)
  - [ ] Click node → project-detail
  - [ ] Button: "Add dependency" → DependencyAddModal
  - [ ] Test: DAG renders; colors reflect at-risk; add-dependency modal validates cycles

- [ ] **SpendVsBudget widget** (`src/components/Portfolio/SpendVsBudget.vue`)
  - [ ] ApexCharts bar chart: budget vs realisatie
  - [ ] Summary: "Begroot: EUR XM | Gerealisatie: EUR YM | %%"
  - [ ] Tile colored: green if under-budget, orange if 80-100%, red if over-budget
  - [ ] Click tile → drill-down table (24 projects with begroot/realisatie/%)
  - [ ] Test: chart renders; summary accurate; drill-down shows project breakdown

- [ ] **StatusTrendWidget** (`src/components/Portfolio/StatusTrendWidget.vue`)
  - [ ] ApexCharts multi-line chart (last 12 snapshots)
  - [ ] Lines: groen-count, amber-count, rood-count, risk-score, spend-%
  - [ ] Sparklines next to each metric (↑ ↓ →)
  - [ ] Hover point → tooltip with exact values for that month
  - [ ] Test: chart renders; data correct; 12 data-points visible; sparklines accurate

- [ ] **ExecutiveSummaryWidget** (`src/components/Portfolio/ExecutiveSummaryWidget.vue`)
  - [ ] KPI-tiles: projecten, on-koers, amber, rood, risk-score, milestones-30d
  - [ ] Tile colors: green/orange/red based on health
  - [ ] Text sections: "Top-3 Issues" and "Top-3 Successen" (bulleted, from latest rapportages)
  - [ ] Mini-milestone-radar (30 days, not 90)
  - [ ] Button: "Presentatie-modus" → fullscreen
  - [ ] Tile click → drill-down card (e.g., click red project count → list of red projects)
  - [ ] Test: tiles render; text extracted correctly; mini-radar shows 30d; presentatie-button works

### Frontend — Modals & Dialogs

- [ ] **PortfolioFormDialog** (`src/components/Portfolio/PortfolioFormDialog.vue`)
  - [ ] Form: naam, omschrijving, eigenaar_id (user-picker), kleurthema (color-picker), actief (toggle)
  - [ ] Create mode: POST /api/portfolios → close on success
  - [ ] Edit mode: PUT /api/portfolios/{id}
  - [ ] Cancel button
  - [ ] Test: form validates; create/edit works; unauthorized gets 403; color-picker works

- [ ] **StatusReportageWizard** (`src/components/Portfolio/StatusReportageWizard.vue`)
  - [ ] Step 1: Load previous rapportage as concept (if exists); rapportage_datum input
  - [ ] Step 2: RAG-status inputs (5 dropdowns: overall, planning, budget, resources, kwaliteit); show data-driven suggestions
  - [ ] Step 3: Narrative (samenvatting textarea, successen, issues, volgende_mijlpaal, volgende_mijlpaal_datum)
  - [ ] Step 4: Review + publish (checkbox "I confirm", button "Publiceer")
  - [ ] Auto-save concept on step-change (debounced PUT)
  - [ ] Button: "Publiceer" → POST /publish (pmo_manager permission required)
  - [ ] On publish: notification to pmo_manager, close wizard
  - [ ] Test: concept-load works; suggestions accurate; publish-flow works; auto-save works

- [ ] **RiskDetailModal** (`src/components/Portfolio/RiskDetailModal.vue`)
  - [ ] Form: titel, omschrijving, categorie (dropdown), kans (picker: zeer_laag-zeer_hoog), impact (picker), status (enum), mitigerende_maatregelen (textarea), eigenaar_id (user-picker)
  - [ ] Read-only fields: code (auto-generated), risico_score (auto-calculated)
  - [ ] Save → PUT /api/risicos/{id}
  - [ ] Delete button → DELETE /api/risicos/{id}
  - [ ] Test: form validates; risico-score displays correctly; delete works

- [ ] **ResourceConflictModal** (`src/components/Portfolio/ResourceConflictModal.vue`)
  - [ ] Header: "User — Week — XYh / ABh (Z%)"
  - [ ] Table: projects allocated to user in week, hours per project
  - [ ] Stacked-bar chart showing hours breakdown
  - [ ] Can edit resource_allocatie records (inline edit or form)
  - [ ] Test: modal shows correct data; edit saves; chart updates

- [ ] **DependencyAddModal** (`src/components/Portfolio/DependencyAddModal.vue`)
  - [ ] From-project (pre-selected or selector), To-project (selector), milestone-name (input), description (textarea)
  - [ ] Button: "Add" → POST /api/dependencies
  - [ ] Validation: check for cycles (DFS); 422 if cycle detected with path explanation
  - [ ] Test: cycle-detection works; 422 returns path; valid dependency created

- [ ] **CommentModal** (`src/components/Portfolio/CommentModal.vue`)
  - [ ] Textarea: comment text
  - [ ] Button: "Save" → POST /api/portfolios/{id}/comments
  - [ ] Notifies pmo_manager
  - [ ] Test: comment saved; pmo_manager notified

### Frontend — Store & Utilities

- [ ] **Portfolio store** (`src/store/modules/portfolioStore.js`)
  - [ ] createObjectStore('portfolio') with relations:
    - [ ] portfolio_project_link (many)
    - [ ] project_status_report (many)
    - [ ] risico (many)
    - [ ] resource_allocatie (many)
    - [ ] portfolio_snapshot (many)
  - [ ] Custom getters: getPortfolioHealth(id), getProjectsInPortfolio(id), getRiscosByPortfolio(id)
  - [ ] Test: store actions work; relations loaded; getters return correct data

- [ ] **Composables**
  - [ ] usePortfolioDashboard(portfolioId) — loads portfolio + health-view + all 8 widgets in parallel
  - [ ] useStatusReportageWizard(projectId, month) — loads previous rapportage, computes suggestions
  - [ ] useDependencyGraph(portfolioId) — loads dependencies, detects cycles on add
  - [ ] Test: composables load data; return correct structure

- [ ] **Utility functions** (`src/utils/`)
  - [ ] riskScoring.js → riskScore(kans, impact) → 1-25 (PRINCE2 matrix)
  - [ ] dateHelpers.js → isWithin90Days(), weekIsoString(), daysUntil()
  - [ ] resourceValidation.js → validateResourceAllocatie(allocatie, contractHours) → { valid, warning }
  - [ ] Test: utilities return correct values for edge cases

### Frontend — Routing

- [ ] **Router configuration** (`src/router/index.js`)
  - [ ] /portfolio → PortfolioListPage
  - [ ] /portfolio/:id → PortfolioDashboard
  - [ ] /portfolio/:id/exec → ExecutiveSummary
  - [ ] /portfolio/:id/settings → PortfolioSettingsPage (pmo_manager only, route-guard)
  - [ ] Route guards: check authentication + role (pmo_manager for settings)

- [ ] **MainMenu integration** (`src/components/MainMenu.vue`)
  - [ ] Add "Portfolio" navigation item (icon: briefcase or chart)
  - [ ] Badge: show "X red projects" if portfolio count > 0
  - [ ] Click → /portfolio

### Frontend — Styling & i18n

- [ ] **CSS styling** (`src/styles/portfolio-dashboard.scss` or in components)
  - [ ] Use Nextcloud CSS variables: `var(--color-primary-element)`, `var(--color-success)`, etc.
  - [ ] No hardcoded colors (except: groen #4DBF47, amber #FFBB00, rood #FF1D15 override via Nextcloud theming)
  - [ ] Responsive: test at 320px (mobile), 768px (tablet), 1920px (desktop)
  - [ ] WCAG AA compliance: color is not sole method of conveying info (use icons + text + color)

- [ ] **Translations** (`l10n/en.json`, `l10n/nl.json`)
  - [ ] All user-facing strings via `t()` function
  - [ ] Translation keys in English (source), Dutch translations in l10n/nl.json
  - [ ] Common terms: "portfolio" (nl: "portfolio"), "rapportage" (nl: "rapportage"), "risico" (nl: "risico"), "RAG-status" (nl: "RAG-status")
  - [ ] Button labels, error messages, tooltips, placeholders
  - [ ] Test: UI renders in Dutch; no hardcoded English

### Testing

- [ ] **Unit tests — Backend**
  - [ ] Authorization service: pmo_manager can edit, pmo_lid cannot publish, etc.
  - [ ] Risk scoring: kans='midden' + impact='hoog' → score=12
  - [ ] Resource validation: geplande_uren capped at 60, warn if >40
  - [ ] Snapshot payload generation: KPI's correctly aggregated
  - [ ] View queries: vw_portfolio_health returns correct RAG-counts
  - [ ] Uniqueness constraints: max 1 rapportage per project per month
  - [ ] File: `tests/Unit/Service/PortfolioAuthorizationServiceTest.php`, etc.

- [ ] **Integration tests — Backend**
  - [ ] Create portfolio → verify schemas imported, seed objects created
  - [ ] Publish status-rapportage → verify pmo_manager notified, rapportage locked
  - [ ] Add risk → verify risico_score auto-calculated, portfolio risk-score updates
  - [ ] financeq webhook → verify portfolio spend-rollup updates
  - [ ] Create snapshot → verify payload immutable, duplicates rejected (409)
  - [ ] File: `tests/Integration/PortfolioDashboardTest.php`

- [ ] **Frontend unit tests — Components**
  - [ ] PortfolioHealthTable: filters work, sort works, counts update
  - [ ] ResourceUtilizationHeatmap: colors correct (green ≤80%, etc.), drill-down works
  - [ ] MilestoneRadar: bubbles positioned correctly, filter works
  - [ ] RiskRegister: 5×5 grid renders, cell click opens detail
  - [ ] DependencyGraph: DAG renders, cycles detected
  - [ ] Executive summary: tiles render, text sections populated, presentatie-modus works
  - [ ] File: `tests/unit/components/Portfolio/PortfolioHealthTableTest.spec.js`, etc.

- [ ] **Frontend integration tests — Pages**
  - [ ] PortfolioListPage: create portfolio, list shows new portfolio, delete works
  - [ ] PortfolioDashboard: 8 widgets load, layout drag-reorder works, settings accessible
  - [ ] ExecutiveSummary: tiles display, presentatie-modus toggles, comments work
  - [ ] Status-rapportage wizard: concept-load, suggestions, publish-flow, pmo_manager notified
  - [ ] File: `tests/integration/Portfolio/PortfolioDashboardTest.spec.js`, etc.

- [ ] **E2E tests** (optional, higher value if mydash integration included)
  - [ ] Flow: PMO-manager creates portfolio → adds projects → publishes status-rapportage → snapshot created
  - [ ] Flow: Executive opens portfolio → embeds widget on mydash → views exec-summary
  - [ ] Flow: financeq webhook triggers spend-update → portfolio dashboard reflects new spend
  - [ ] File: `tests/e2e/portfolio-dashboard.spec.js` (if E2E framework available)

- [ ] **Manual QA checklist**
  - [ ] Browser: Chrome, Firefox, Safari (latest 2 versions)
  - [ ] Devices: mobile (iPhone 12), tablet (iPad), desktop (1920×1200)
  - [ ] Accessibility: keyboard navigation (Tab, Enter, Arrow keys), screen-reader (NVDA, JAWS)
  - [ ] Performance: dashboard loads <2s on broadband, heatmap with 200+ cells renders smoothly
  - [ ] Data: seed data visible in all views, filters work, drill-downs navigate correctly
  - [ ] Permissions: unauthorized user gets 403, pmo_manager can CRUD, portfolio_eigenaar can comment only
  - [ ] Integrations: financeq webhook triggers (manual test with financeq app), n8n snapshot task runs
  - [ ] Offline: dashboard gracefully handles network errors (show cached data or empty state)

### Documentation

- [ ] **README update** (openspec/README.md)
  - [ ] Add section: "Portfolio Dashboard PMO — Cross-project visibility for PMO teams"
  - [ ] Link to design.md, specs.md, context-brief.md

- [ ] **Deployment guide** (`docs/DEPLOYMENT.md` or app docs)
  - [ ] Prerequisites: OpenRegister installed
  - [ ] Install & enable planix app
  - [ ] Run repair step to import schemas + seed data
  - [ ] Assign PMO roles (admin UI)
  - [ ] Configure financeq webhook
  - [ ] Install n8n flow template (link + instructions)
  - [ ] Verify: `/ocs/v2.php/apps/openregister/api/v1/status` returns openregister enabled
  - [ ] Test: create test portfolio, publish test rapportage

- [ ] **Architecture documentation** (`docs/ARCHITECTURE.md` or in app)
  - [ ] Data model: 6 schemas, 3 views
  - [ ] Authorization: 5 roles, per-object checks
  - [ ] Integrations: financeq (webhook), n8n (snapshot), mydash (widgets), BBV (scope-filter)
  - [ ] Widget architecture: 8 reusable components, shared store
  - [ ] Performance considerations: caching, lazy-load, virtualization

- [ ] **API documentation** (auto-generated from OpenAPI schema or hand-written)
  - [ ] Endpoint reference: all CRUD + aggregation endpoints
  - [ ] Request/response examples (JSON)
  - [ ] Error codes: 400 (validation), 403 (authorization), 404 (not found), 409 (conflict), 422 (validation or cycle-detected)
  - [ ] Webhook contracts (financeq → planix)

- [ ] **User guide** (for PMO team, bestuur)
  - [ ] Portfolio creation & configuration (scope-filter, color-theme)
  - [ ] Status-rapportage monthly workflow
  - [ ] Risk registration & mitigation
  - [ ] Resource-allocation & conflict-resolution
  - [ ] Executive-summary & presentatie-modus for MT meetings
  - [ ] Snapshot & trend-analysis

- [ ] **n8n template** (`docs/n8n/portfolio-snapshot-monthly-template.json`)
  - [ ] Cron trigger (1st of month, 02:00 UTC)
  - [ ] Loop: for each portfolio, POST `/apps/planix/api/portfolios/{id}/snapshots`
  - [ ] Error handling: log failures, alert admin
  - [ ] Documentation: how to import + configure in n8n UI

### Deduplication Check

- [ ] **Search openspec/specs/** for similar capabilities (portfolio-rollup, RAG-status, risk-register)
  - [ ] No existing portfolio-rollup spec found
  - [ ] No existing risk-management schema in openregister (only PMBoK standard adopted here)
  - [ ] No existing resource-heatmap visualization in planix
  - [ ] **Result**: ✓ No overlap; all new functionality specific to portfolio-dashboard-pmo

- [ ] **Search openregister/lib/Service/** and **openregister/lib/Handler/** for overlap
  - [ ] ObjectService CRUD reused (no duplication)
  - [ ] RelationsPlugin reused (no duplication)
  - [ ] SearchService reused (for portfolio search in list page)
  - [ ] **Result**: ✓ Leverage existing OpenRegister patterns; build domain-specific logic on top

- [ ] **Document findings** in design.md "Reuse Analysis" section
  - [ ] Already done in design.md

---

## Seed Data Definition

**Portfolio 1: "Strategische Projecten 2026"** (4 projects)
- Eigenaar: Jan van der Berg (wethouder)
- PMO-manager: Annemarie de Vries
- Projects: PRJ-2026-001, PRJ-2026-002, PRJ-2026-003, PRJ-2026-004
- Budget: EUR 8.4M
- Status-reports: 3 per project (Feb, Mar, Apr 2026) with varied RAG values
- Risks: 8 total (planning, budget, resources, compliance)
- Resources: 3 team members, weeks W12-W20
- Snapshots: Jan, Feb, Mar 2026

**Portfolio 2: "Afdeling Digitaal"** (3 projects)
- Eigenaar: Henk Smit (director)
- PMO-manager: (same or different)
- Projects: filtered by afdeling_id
- Budget: EUR 2.1M
- Status-reports: 2 per project
- Risks: 5 total
- Resources: 2 team members, weeks W14-W18
- Snapshots: Feb, Mar 2026

**Portfolio 3: "Programma Mobiliteit"** (2 projects)
- Eigenaar: Pieter van Dijk (bestuurslid)
- PMO-manager: (same or different)
- Projects: filtered by bbv_doel_id (reference to BBV-programma)
- Budget: EUR 1.2M
- Status-reports: 2 per project
- Risks: 3 total
- Resources: 2 team members, weeks W16-W20
- Snapshots: Mar 2026

**Team Members**: Jan van der Berg, Annemarie de Vries, Henk Smit, Pieter van Dijk, Maria Jansen, Johan Wijnstra (6 total)

**Risk Categories**: planning, budget, resources, kwaliteit, extern, compliance, reputatie

**Organization Context**: Gemeente Amsterdam (large city)

---

**OpenSpec Change**: `portfolio-dashboard-pmo`  
**Status**: tasks  
**Feature tier**: MVP  
**Estimated effort**: 60-80 dev days (backend 25 days, frontend 40 days, testing 10 days, docs 5 days)
