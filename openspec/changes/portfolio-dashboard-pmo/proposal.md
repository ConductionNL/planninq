# Proposal: Portfolio Dashboard PMO (Project Management Office Rollup)

## Summary

Add a native Portfolio Dashboard to Planix that enables PMOs, program managers, and executive leadership to aggregate cross-project visibility: portfolio health (RAG-status traffic light table), resource utilization heatmap, milestone radar (90-day forward-looking), risk register with portfolio risk-scoring, inter-project dependency graph, spend-vs-budget analysis, and monthly snapshot tracking. This replaces the current spreadsheet-based PMO reporting workflows (Excel, Power BI, external SaaS) with live data from Planix and integrated governance systems (financeq, BBV-programma-tree, n8n).

## Motivation

Organizations using Planix with >10 parallel projects require a Project Management Office function to monitor portfolio health, coordinate resources, manage risks, and report to executive leadership. Today, Planix lacks cross-project rollup views — PMOs manually aggregate data in Excel/Power BI or deploy separate SaaS tools (Monday, ClickUp, Asana Portfolios) outside the organization's data governance. This introduces data duplication, stale reporting cycles, and breaks the single source of truth for project state.

The spec delivers eight standard widgets (traffic-light table, resource heatmap, milestone radar, risk register, dependency graph, spend-vs-budget, status-trend, executive-summary) plus a PMO role model (`pmo_manager`, `pmo_lid`, `portfolio_eigenaar`, `program_manager`) and read-only access for bestuur (executive board). This keeps PMO functions native to Planix and governance-coupled (financeq, BBV, raadsbesluiten, procest).

## Affected Projects

- [x] **planix** — Backend: register config, seeds (3-5 portfolio / project_status_report / risico / resource_allocatie objects); three materialized views (vw_portfolio_health, vw_resource_utilization, vw_milestone_radar). Frontend: `src/views/PortfolioDashboard.vue` + eight widget components + wizard for status-rapportage
- [x] **openregister** (read-only impact) — schema validation for the six new schemas (schemas ARE defined in planix register config, not as new openregister schemas — openregister stores the objects)
- [x] **mydash** — Portfolio widgets (same eight widgets) registered as mydash tile configs so executives can embed portfolio views in their personal dashboard
- [x] **financeq** — Webhook trigger on spend-realization change to update portfolio spend rollup
- [x] **n8n** — Scheduled flow (monthly 02:00 first of month) to create portfolio_snapshot records
- [x] **bbv-programma-tree** — Scope filter on portfolio can reference `bbv_doel_ids` for strategic context
- [ ] **docudesk** — Future: PDF stuur-rapportage generation from portfolio_snapshot
- [ ] **HR / peopleq** — Future: integration with resource capacity planning (contractor FTE, leave)

## Scope

### In Scope

**Data Model** (6 new OpenRegister schemas in `planix/portfolio-dashboard-pmo/`):
- `portfolio` — container for project grouping, ownership, color theme, scope filter
- `portfolio_project_link` — explicit link table materializing scope filter for snapshot stability
- `project_status_report` — monthly RAG-status (overall, planning, budget, resources, kwaliteit) + voortgang % + samenvatting
- `risico` — per-project risks with kans×impact matrix, auto-scoring, categorie, mitigatie
- `resource_allocatie` — per-user per-week FTE planned + actual (from time-tracking), per-project role
- `portfolio_snapshot` — immutable monthly KPI freeze (JSON payload) for trend tracking

**Views** (3 materialized or on-demand):
- `vw_portfolio_health` — per portfolio: project counts (groen/amber/rood), weighted RAG-score, delta vs previous snapshot
- `vw_resource_utilization` — per user per week: planned vs contract hours, %, projects list
- `vw_milestone_radar` — all milestones next 90 days + portfolio + status

**Routes & Pages**:
- `/planix/portfolio` — portfolio list + create (if pmo_manager)
- `/planix/portfolio/{id}` — dashboard with configurable layout (8 widgets, user-saveable per portfolio)
- `/planix/portfolio/{id}/exec` — executive summary view (read-only, bestuur-facing)

**Widgets** (8 total, render in PortfolioDashboard.vue + als mydash-tiles):
1. **Portfolio Health Table** — sortable, filterable; RAG columns; PRINCE2-style traffic lights
2. **Resource Utilization Heatmap** — user×week; green ≤80%, yellow 80-100%, orange 100-120%, red >120%
3. **Milestone Radar 90d** — timeline visualization; size = importance; color = on-time|at-risk|missed
4. **Risk Register** — 5×5 kans×impact matrix per project; portfolio risk-score (weighted sum)
5. **Dependency Graph** — gerichte graaf; detects cycles; highlights at-risk paths
6. **Spend-vs-Budget** — portfolio + drill-down per project; begroot/realisatie/%; financeq integration
7. **Status Trend** — sparklines of RAG-counts + spend% over 12 months (snapshot trend-view)
8. **Executive Summary** — one-page KPI-tiles + top-3 issues + top-3 successen + milestone-radar 30d; presentatie-modus

**Roles & Permissions** (5 new roles):
- `pmo_manager` — full CRUD on assigned portfolios, publish status-rapportages, create snapshots, edit PMO settings
- `pmo_lid` — read + help draft rapportages, cannot publish; annotate (commentaar) on issues/successen
- `portfolio_eigenaar` — assigned bestuurslid; read-only + commentaar-add; can assign portfolio to pmo_manager
- `program_manager` — read-only on own programma's projects (via BBV koppeling)
- (inherited) `projectmanager` — read own projects in portfolio context; draft + publish own project status-rapportage

**Workflows** (backend-driven):
- **Monthly snapshot** (n8n): 1st of month 02:00 → create portfolio_snapshot with payload = all KPI's snapshot
- **Status-rapportage wizard** (frontend): project managers draft rapportage monthly; system suggests issues based on data (overdue tasks, budget variance); publish → pmo_manager notified
- **Risk mitigation alert** (via webhook): risico.status changes to `in_mitigation` → escalate to pmo_manager
- **Resource conflict warning** (backend): when resource_allocatie sum > contract hours, flag for week for pmo_manager visibility

**Configuration**:
- Portfolio scope_filter (JSON) — programma_ids, afdeling_ids, tags, project_ids (explicit list)
- Risk categories — dropdown constrained to: planning, budget, scope, resources, kwaliteit, extern, compliance, reputatie (PRINCE2 + PMBoK alignment)
- Rapportage-cyclus — configurable per portfolio (monthly, biweekly, quarterly)

### Out of Scope

- **Detail project management** — stays in planix project-spec (tasks, columns, WIP limits, kanban)
- **Individuele time-tracking execution** — planix time-tracking already owns this; portfolio reads from it
- **HR capacity planning** — future peopleq/HR-app (this spec reads FTE from contract, not live absence)
- **Financial cost booking** — financeq owns GL entries; portfolio reads realisatie via webhook
- **Advanced financial analytics** — EVM (Earned Value Management), variance-at-completion forecasting (future V1 expansion)
- **Procest zaak integration** — scope shows zaak-completion as progress % (read-only link), no mutation

## Approach

**Backend** (PHP / OpenRegister):
1. Define 6 schemas in `lib/Settings/planix_register.json` (OpenAPI 3.0 format) with validation rules
   - risico.risico_score = auto-calculated (kans_weight × impact_weight), system-only
   - resource_allocatie constraint: geplande_uren ≤ 60 per record, validation warning if >40
   - project_status_report uniqueness: 1 per project per calendar-month
   - portfolio_snapshot immutability: locked after creation (immutable column)
2. Seed data: 3 portfolios + 2 projects each + sample status-reports, risico's, resource-allocatie, snapshots (Dutch realistic values: gemeentes, woningcorporaties, MKB)
3. Import schemas on install via ConfigurationService::importFromApp() repair step (existing pattern)
4. Three read-only views (SQL or on-demand aggregation queries):
   - vw_portfolio_health via SettingsLoadService or on-demand computation in PageController
   - vw_resource_utilization — query resource_allocatie grouped by user+week, join contract-hours from Nextcloud Users
   - vw_milestone_radar — query planix milestones, join project→portfolio links, compute due-date status
5. API endpoints (READ-only, per-portfolio scoped authorization):
   - GET `/apps/planix/api/portfolios` (list, pmo_manager view only)
   - GET `/apps/planix/api/portfolios/{id}` (detail + vw_portfolio_health rollup)
   - GET `/apps/planix/api/portfolios/{id}/health-table` (paged, filtered for table widget)
   - GET `/apps/planix/api/portfolios/{id}/risk-register` (5×5 matrix data)
   - GET `/apps/planix/api/portfolios/{id}/resource-heatmap` (vw_resource_utilization grouped)
   - GET `/apps/planix/api/portfolios/{id}/milestone-radar` (vw_milestone_radar filtered 90d)
   - GET `/apps/planix/api/portfolios/{id}/spend-vs-budget` (financeq integration, cost-center per project)
   - GET `/apps/planix/api/portfolios/{id}/snapshots/{date}` (trend-comparison)
   - GET `/apps/planix/api/projects/{id}/status-reports` (list for project)
   - POST `/apps/planix/api/projects/{id}/status-reports` (create draft, pmo_lid+ permission)
   - PUT `/apps/planix/api/projects/{id}/status-reports/{id}` (edit draft, only if not published)
   - POST `/apps/planix/api/projects/{id}/status-reports/{id}/publish` (set published=true, pmo_manager only)
6. WebhookService listener for financeq spend mutations — triggers portfolio spend rollup update

**Frontend** (Vue 2 + @conduction/nextcloud-vue):
1. Route: `src/router/index.js` → `/portfolio` (list page), `/portfolio/:id` (dashboard), `/portfolio/:id/exec` (executive view)
2. Pages:
   - `PortfolioListPage.vue` — CnIndexPage pattern (search, filter by pmo_manager, create button)
   - `PortfolioDashboard.vue` — CnDashboardPage (GridStack layout, user-configurable per portfolio, 8 widget slots)
   - `ExecutiveSummary.vue` — one-page KPI layout + presentatie-modus button (fullscreen)
3. Widgets (each a Vue component, reusable in both dashboard + mydash):
   - `PortfolioHealthTable.vue` — CnDataTable over health-table endpoint
   - `ResourceUtilizationHeatmap.vue` — custom canvas/SVG heatmap component, cell-click → drill-down modal
   - `MilestoneRadar.vue` — custom timeline SVG, plotted bubble-chart for milestones
   - `RiskRegister.vue` — 5×5 grid, cell click → risk-detail sidebar
   - `DependencyGraph.vue` — custom D3/Cytoscape diagram (gerichte acyclic DAG with cycle-detection on add)
   - `SpendVsBudget.vue` — bar/pie chart (ApexCharts) over spend-vs-budget endpoint
   - `StatusTrendWidget.vue` — sparklines (ApexCharts) pulling snapshots over time
   - `ExecutiveSummaryWidget.vue` — KPI tiles, top-3 lists, mini-radar, presentatie button
4. Modals / Dialogs:
   - `PortfolioFormDialog.vue` (create/edit portfolio, scope-filter JSON editor)
   - `StatusReportageWizard.vue` (monthly rapportage: concept-load, suggest-issues, publish flow)
   - `RiskDetailModal.vue` (edit risico, kans/impact picker, mitigation textarea)
   - `ResourceConflictModal.vue` (drill-down from heatmap cell → projects + hours breakdown)
5. Store:
   - `src/store/modules/portfolioStore.js` (createObjectStore for portfolios, project_status_report, risico, resource_allocatie, portfolio_snapshot)
   - Plugins: relationsPlugin (portfolio → projects, risico → project)
6. Composables:
   - `usePortfolioDashboard()` — load portfolio + vw_portfolio_health + all 8 widgets in parallel
   - `useStatusReportageWizard()` — load previous rapportage as concept, compute issue-suggestions
7. Styling: NL Design CSS variables, responsive (320px-1920px), tablet-critical at 768px

**Integrations**:
- **financeq**: Webhook receiver → portfolio spend-update mutation when realisatie changes
- **n8n**: Scheduled snapshot flow (cron 1st of month 02:00) → POST `/apps/planix/api/portfolios/{id}/snapshots` (auto-create, immutable)
- **mydash**: Register 8 widgets via `IWidgetRegistry` (OCP\Dashboard\IWidget implementation per widget component)
- **BBV-programma-tree**: portfolio.scope_filter can reference bbv_doel_ids; link shown in portfolio-detail
- **procest**: project.zaakUuid exists (via existing planix integration); progress % includes zaak-completion

## New Dependencies

- **Vue 2 + Pinia** — already in planix; no new deps
- **@conduction/nextcloud-vue** — already in planix
- **ApexCharts** — for charts (spend-vs-budget, status-trend sparklines); already common in Nextcloud apps
- **D3.js** or **Cytoscape.js** — for dependency-graph visualization; lightweight, ~50KB minified
- Optional: **date-fns** — for date utilities (is-same-day, is-within-range, sub, add); lightweight

No new backend PHP deps beyond existing (openregister, symfony, guzzle, n8n webhook client already present).

## Impact

### Planix

- **New schemas**: 6 new register objects (portfolio, portfolio_project_link, project_status_report, risico, resource_allocatie, portfolio_snapshot)
- **New views**: 3 read-only aggregation queries
- **New routes**: 6 new pages/modals
- **New API endpoints**: 10 GET + 4 POST/PUT endpoints (all scoped to portfolio-owner or pmo_manager)
- **Schema extend**: `Project` schema gains optional `weight` field (for portfolio-weighting in rollup); time-compatible, non-breaking
- **Database**: ~200MB schema storage for 100-portfolio org (6 schemas × ~30 objects/portfolio × 1KB/object avg)

### Nextcloud

- **Notifications**: Uses NotificationService for pmo_manager alerts (new rapportage ready, risk mitigation, resource-conflict)
- **Calendar**: Optional: milestone-radar could sync to Nextcloud calendar as all-day events per milestone
- **Activity feed**: Status-report publish logged to ActivityService
- **Files**: Portfolio snapshots can include attachments (notes, PDFs) via FileService

### Cross-App

- **financeq**: Webhook listener added (trigger on spend-realization); no schema changes
- **n8n**: New scheduled flow template provided (install-time)
- **mydash**: 8 new widget registration entries
- **BBV-programma-tree**: read-only link in scope-filter; no mutation
- **procest**: read-only zaak-completion lookup; no mutation

## Cross-Project Dependencies

- **openregister** — for ObjectService, schema validation, views (READ-only impact; no modification to openregister codebase needed)
- **financeq** — webhook contract: expects portfolio spend-realization endpoint on planix
- **n8n** — scheduled snapshot task: template provided in `docs/n8n/portfolio-snapshot-monthly-template.json`
- **mydash** — dashboard widget registry: 8 widgets exported as OCP\Dashboard\IWidget

## Risks

### Risk 1: Materialization of scope_filter into portfolio_project_link

**Severity**: Medium — **Impact**: If scope_filter JSON changes, portfolio_project_link stales; snapshot is then based on outdated link set.

**Mitigation**:
- Validation: before publish(status-rapportage), re-sync portfolio_project_link from portfolio.scope_filter (idempotent update or event-driven on scope-filter mutation)
- Documentation: include "Update portfolio scope" in monthly PMO checklist
- Future: auto-resync on scope-filter mutation (trigger in openregister or webhook)

### Risk 2: Circular dependency detection in dependency-graph

**Severity**: Low — **Impact**: User attempts to add circular dependency; system must reject with 422 + path explanation.

**Mitigation**:
- Topological sort on every add/edit (DFS cycle detection)
- Unit test: test both valid DAGs and circular attempts
- API response includes `cycleDetection: { detected: true, path: ["PRJ-A", "PRJ-B", "PRJ-C", "PRJ-A"] }`

### Risk 3: Resource-utilisatie heatmap performance at scale

**Severity**: Medium — **Impact**: 200+ users × 52 weeks = 10K+ cells; rendering lags.

**Mitigation**:
- Lazy-load weeks (initially show current + 12 weeks ahead; paginate older)
- Backend: vw_resource_utilization filtered by date-range query param
- Frontend: virtualization (vue-virtual-scroller) if heatmap grows beyond 500 cells
- Caching: portfolio dashboard caches endpoint responses for 1 hour

### Risk 4: PMO role creep

**Severity**: Low — **Impact**: 5 new roles (pmo_manager, pmo_lid, portfolio_eigenaar, program_manager, projectmanager-extended) may conflict with existing role model.

**Mitigation**:
- Roles are opt-in (added during portfolio creation by tenant admin)
- Projectmanagers get no new permissions (just new UI view of own projects in portfolio context)
- Config: per-portfolio PMO role assignment (not org-wide), allowing multiple portfolio structures
- Document: DEPLOYMENT.md — step-by-step role assignment workflow

### Risk 5: Monthly snapshot creation timing

**Severity**: Low — **Impact**: n8n task fails silently; no snapshot created for month.

**Mitigation**:
- Scheduled task logs to planix.log + alerts n8n admin on failure
- Manual fallback: PMO-manager can trigger snapshot via UI button "Create snapshot now"
- Validation: snapshot creation endpoint checks date (reject if already exists for that date)

## Rollback Strategy

1. **Database**: Snapshot payloads are immutable but deletes are reversible. If snapshot-generation is wrong, simply don't use that snapshot for stuur-rapportage; create corrected snapshot manually.
2. **API**: All new endpoints are additive; removing them leaves no orphaned data in planix existing objects.
3. **Frontend**: New routes are isolated in `/portfolio/*`; removing breaks link only to new UI, not to existing project/task workflows.
4. **Reverse**: Delete `lib/Settings/planix_register.json` portfolio-dashboard-pmo section (re-run repair); existing project/task/time-tracking data unaffected.

### Deployment Checklist

- [ ] Install openregister (if not present) — verify via `/ocs/v2.php/apps/openregister/api/v1/settings`
- [ ] Run repair step: import 6 schemas + seed 3 portfolios
- [ ] Assign pmo_manager roles to PMO team members (via Nextcloud user admin panel)
- [ ] Install n8n flow template (docs/n8n/portfolio-snapshot-monthly-template.json)
- [ ] Configure financeq webhook (financeq admin → Nextcloud Settings → Webhooks → add planix portfolio-spend endpoint)
- [ ] Test: PMO-manager opens `/planix/portfolio`, creates test portfolio, publishes test status-rapportage, verifies snapshot creation

---

## Related Specs

- **planix** — Project, Task, TimeEntry, Milestone (parent specs)
- **openregister** — Object storage, schema validation, views
- **financeq** — Budget, spend-realization (data source)
- **BBV-programma-tree** — Programme classification (scope-filter context)
- **procest** — Case management (zaak-completion progress link)
- **mydash** — Dashboard widget framework

## Standards & Governance

- **PRINCE2** — RAG-status traffic-light (Rood/Amber/Groen); risk-register kans×impact matrix
- **PMBoK** — Portfolio-management terminology, KPI definitions
- **MoP (Management of Portfolios)** — Portfolio categorization and governance
- **ISO 21500** — Project-management processes
- **VNG-Handreiking PMO** — Dutch public sector PMO best practices
- **GEMMA 2** — "Projectmatig werken" koppelvlakken

---

**OpenSpec Change**: `portfolio-dashboard-pmo`  
**Created**: 2026-05-22  
**Status**: proposal
