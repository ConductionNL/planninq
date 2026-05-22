# Specs: Portfolio Dashboard PMO

**Status**: draft  
**Standards**: PRINCE2 (RAG-status, risk-register), PMBoK (portfolio-management), MoP (portfolio-categorization), ISO 21500 (project-management), VNG-Handreiking PMO (Dutch public sector), GEMMA 2 (projectmatig werken)  
**Feature tier**: MVP

## Requirements

### REQ-001: Portfolio-Overzicht Traffic-Light Table

The system SHALL render a sortable and filterable table showing all projects in a portfolio, with per-project RAG-status indicators (overall, planning, budget, resources, quality) from the latest `project_status_report`.

#### Scenario 1: Portfolio opens with project list

- **GIVEN** portfolio "Strategische Projecten 2026" with 24 linked projects, each with a status-report from the last month
- **WHEN** PMO-manager opens `/planix/portfolio/{id}`
- **THEN** the system renders a table with 24 rows, sorted by `status_overall` descending (red first), displaying columns:
  - Project name (clickable → project-detail)
  - Five RAG-indicator circles (color: groen/amber/rood) for overall, planning, budget, resources, kwaliteit
  - Voortgang percentage (progress bar)
  - Project manager name
  - Next milestone and due date
  - RAG deltas vs previous month (↑ improving / ↓ degrading / → stable)
- AND the header shows: "24 projects | 14 green | 7 amber | 3 red"

#### Scenario 2: Filter to show only red projects

- **GIVEN** the same portfolio with status breakdown (3 red, 7 amber, 14 green)
- **WHEN** user applies filter `status_overall = rood`
- **THEN** table displays only 3 rows; header updates to "3 projects | 0 green | 0 amber | 3 red" (counts reflect filtered set)
- AND user can further sort by project-name (A-Z), voortgang (low-high), or milestone-date (nearest-first)

#### Scenario 3: Drill-down to project-detail

- **GIVEN** red project in the health-table
- **WHEN** user clicks project name
- **THEN** system navigates to `/planix/project/{project-id}` (existing planix project-detail page)
- AND the browser back button returns to the portfolio health-table

### REQ-002: Resource-Utilisatie Heatmap

The system SHALL render a heatmap visualization with users on the y-axis, weeks on the x-axis, and cells color-coded by utilization percentage (green ≤80%, yellow 80-100%, orange 100-120%, red >120%).

#### Scenario 1: Heatmap for PMO team

- **GIVEN** 14 team members with `resource_allocatie` records for weeks W12-W24 of 2026
- **WHEN** PMO-manager opens the "Resources" tab in the portfolio dashboard
- **THEN** system renders a 14×13 heatmap (14 users, 13 weeks)
- AND each cell is colored:
  - Green: ≤80% utilized (healthy)
  - Yellow: 80-100% (at-capacity)
  - Orange: 100-120% (over-capacity, warning)
  - Red: >120% (severely over-booked)
- AND cells are clickable to drill-down

#### Scenario 2: Over-allocation warning and drill-down

- **GIVEN** Janneke is allocated: Project A (16h) + Project B (16h) + Project C (12h) = 44 hours total for week W18, but her contract-hours = 32
- **WHEN** heatmap renders
- **THEN** Janneke's W18 cell is colored red (138% utilization)
- AND hovering shows tooltip: "44h / 32h (138%)"
- AND clicking cell opens ResourceConflictModal showing breakdown: "Project A: 16h | Project B: 16h | Project C: 12h | Total: 44h (32h contract)"
- AND PMO sees visually where conflicts occur and can reallocate (edit resource_allocatie records)

#### Scenario 3: Lazy-load weeks

- **GIVEN** heatmap initialized with 52-week view (too much to render efficiently)
- **WHEN** user scrolls horizontally
- **THEN** system loads additional weeks on-demand (paginate by date-range)
- AND initial load shows current week + 12 weeks ahead

### REQ-003: Milestone Radar 90 Dagen

The system SHALL visualize upcoming milestones in a 90-day forward-looking timeline, with milestone-importance (size), status (color: on-time green, at-risk orange, missed red), grouped by portfolio or program.

#### Scenario 1: Radar view with 47 milestones

- **GIVEN** portfolio "Digitale Dienstverlening" with 47 milestones in the next 90 days (various projects, various statuses)
- **WHEN** user opens "Milestone Radar" tab
- **THEN** system renders horizontal timeline with 90-day span (today → +90 days)
- AND 47 circle-bubbles plotted on timeline by milestone-due-date
- AND bubble colors: green (on-time, no tasks overdue), orange (at-risk, some tasks overdue), red (missed, past due)
- AND bubble sizes: proportional to milestone-importance or number-of-tasks
- AND hovering bubble shows: milestone-name, due-date, project-name, task-count
- AND clicking bubble shows milestone-detail (CnDetailPage)

#### Scenario 2: Filter to show only at-risk + missed

- **GIVEN** the same radar with status mix (38 green, 7 orange, 2 red)
- **WHEN** user clicks filter "Show only at-risk + missed"
- **THEN** radar displays only 9 bubbles (7 orange + 2 red)
- AND a sidebar list appears below timeline: "9 At-Risk Milestones" with drill-down per milestone → root cause (which task is overdue, which risico is affecting)

### REQ-004: Risico-Register and Portfolio Risk-Score

The system SHALL maintain a risk-register per project with 5×5 kans×impact matrix visualization, auto-computing `risico_score = kans_weight × impact_weight` (1-25 scale), and aggregate portfolio `risk_score` as weighted sum of project risks.

#### Scenario 1: Add risk to project

- **GIVEN** project "Vervangen ICT-werkplekken" with 0 risks
- **WHEN** projectmanager adds risk: `categorie='leveranciers', titel='Leverancier kan niet leveren binnen 8 weken', kans='midden', impact='hoog'`
- **THEN** system auto-calculates `risico_score = 3 × 4 = 12`
- AND risk is stored with status='open'
- AND risk appears in project's risk-register 5×5 matrix at cell (midden, hoog)
- AND portfolio's `vw_portfolio_health` risk-score updates (includes new risk in weighted rollup)

#### Scenario 2: Portfolio risk-score trend

- **GIVEN** portfolio with 6 projects having risico_scores (8, 12, 4, 16, 6, 9) weighted by project-budgets (40%, 25%, 10%, 15%, 5%, 5%)
- **WHEN** portfolio dashboard renders risk-tile
- **THEN** system calculates weighted-average: (8×0.4 + 12×0.25 + 4×0.1 + 16×0.15 + 6×0.05 + 9×0.05) = 9.45 → rounds to 9.5 or displays as "9.5 (9.3 prev-month)"
- AND tile shows "Portfolio Risk Score: 9.5" with trend-arrow (↑ ↓ →) vs previous snapshot
- AND clicking tile shows top-5 highest-risk-items org-wide (sorted by risico_score descending)

#### Scenario 3: Risk status transitions

- **GIVEN** risk with status='open'
- **WHEN** risk-eigenaar updates status to 'in_mitigation' (set mitigerende_maatregelen)
- **THEN** risk-status changes; notification sent to pmo_manager
- AND risk can transition: open → in_mitigation → geaccepteerd OR verholpen → gerealiseerd
- AND once status='gerealiseerd', risk is archived (no longer affects current risk-score)

### REQ-005: Dependency Graph Tussen Projecten

The system SHALL visualize inter-project dependencies as a directed acyclic graph (DAG), where an edge indicates that project A cannot deliver until project B reaches a specific milestone. Circular dependencies MUST be rejected with error.

#### Scenario 1: Add dependency

- **GIVEN** project "Klant Portaal Live" and project "Single Sign-on Implementatie", both in same portfolio
- **WHEN** PM of Klant Portaal adds dependency: "Klant Portaal → waits-for → SSO Milestone M-3"
- **THEN** system creates edge in the dependency DAG
- AND when M-3 status changes (e.g., from on-time to at-risk), at-risk flag propagates to Klant Portaal (upstream dependency)
- AND dependency is visible in portfolio dashboard DependencyGraph widget (D3/Cytoscape visualization)

#### Scenario 2: Reject circular dependency

- **GIVEN** DAG with: A → B → C (three projects, two edges)
- **WHEN** user attempts to add: C → waits-for → A (would create cycle)
- **THEN** API returns 422 Conflict: `{ "error": "circular_dependency", "path": ["C", "A", "B", "C"] }`
- AND dependency is NOT added; system is acyclic

#### Scenario 3: At-risk propagation

- **GIVEN** dependency graph with A → B → C
- **WHEN** project B's milestone becomes at-risk (status='at-risk')
- **THEN** project A receives notification: "Upstream dependency B is at-risk" (shown in status-rapportage wizard as suggestion)
- AND DependencyGraph widget highlights path from A → B (color: orange for at-risk)

### REQ-006: Spend-vs-Budget Breakdown

The system SHALL render portfolio-level spend vs budget from financeq (begroting + realisatie) filtered by project-cost-center or BBV-doel-koppeling, with drill-down per project.

#### Scenario 1: Portfolio spend tile

- **GIVEN** portfolio "Strategische Projecten 2026" with 24 projects, total budget EUR 8.4M for 2026
- **WHEN** user opens portfolio dashboard in October 2026 (8 months into year)
- **THEN** spend-tile displays:
  - "Begroot: EUR 8.4M"
  - "Gerealiseerd: EUR 5.9M"
  - "Bestedingsgraad: 70%"
  - "Prognose Q4: EUR 8.1M (5% under-budget)"
- AND tile shows bar chart (budget vs realized) or progress-bar (70% filled)
- AND chart colored: green if under-budget, orange if 80-100%, red if over-budget

#### Scenario 2: Drill-down to project breakdown

- **GIVEN** user clicks spend-tile
- **WHEN** SpendVsBudget widget expands or modal opens
- **THEN** table displays 24 projects with columns:
  - Project name
  - Begroot (EUR)
  - Gerealiseerd (EUR)
  - Restant (EUR)
  - Bestedingsgraad (%)
- AND rows sorted by bestedingsgraad descending (highest-spend first)
- AND each row clickable → project-detail

#### Scenario 3: Spend mutation from financeq

- **GIVEN** financeq records a new spend-realization for a project (via accounting entry)
- **WHEN** financeq POSTs webhook to `/apps/planix/api/webhooks/financeq-spend-change`
- **THEN** planix backend updates internal spend-cache (or queries financeq on-demand)
- AND next time portfolio dashboard loads, spend-numbers reflect latest financeq data
- AND if spend-overage detected (realized > budget), flag shown as warning

### REQ-007: Status-Rapportage Wizard

The system SHALL provide projectmanagers a multi-step wizard for monthly status-rapportage, with concept-auto-loading, data-driven issue-suggestions, and publish-flow to PMO.

#### Scenario 1: Wizard concept-load and suggestions

- **GIVEN** projectmanager Maria has project "PRJ-2026-019", and last month's rapportage with samenvatting="Project on track, 3 tasks completed"
- **WHEN** Maria clicks "Nieuwe rapportage opstellen" for April 2026
- **THEN** wizard loads concept with:
  - rapportage_datum = "2026-04-30"
  - Previous month's samenvatting as template (editable)
  - System-suggested issues:
    - "3 tasks overdue → suggest planning=amber?"
    - "Budget spend 92% vs expected 70% → suggest budget=rood?"
    - "Resource utilization 115% → suggest resources=amber?"
- AND Maria can edit/dismiss suggestions and proceed

#### Scenario 2: Publish-flow to PMO

- **GIVEN** concept-rapportage with RAG-statuses selected, samenvatting filled in
- **WHEN** Maria clicks "Publiceer naar PMO"
- **THEN** wizard shows review screen: "Confirm this report is accurate" (checkbox)
- AND button "Publiceer" submits POST /api/projects/{id}/status-reports/{id}/publish
- AND rapportage.gepubliceerd set to true, timestamp recorded
- AND PMO-manager receives notification: "New status report: PRJ-2026-019 (amber planning, rood budget)"
- AND rapportage becomes read-only (cannot edit; only view + add comment)

#### Scenario 3: Correction rapportage

- **GIVEN** Maria published rapportage with status=rood
- **WHEN** later she realizes status should be amber (error in data)
- **WHEN** Maria starts new rapportage for same project+month
- **THEN** system blocks duplicate (409) with option: "Create corrected rapportage?" 
- AND new rapportage can set `corrigeert_rapportage_id=previous-id` (linking chain)
- AND PMO sees "Correction to April report (replaces previous)" in UI

### REQ-008: Maandelijkse Portfolio-Snapshot

The system SHALL automatically create a monthly `portfolio_snapshot` (1st of month 02:00) containing the complete KPI freeze (RAG-counts, risk-score, milestone-counts, spend-numbers), and notify PMO that the snapshot is ready for MT-rapportage.

#### Scenario 1: Nightly snapshot creation

- **GIVEN** scheduled task triggered at 2026-05-01 02:00
- **WHEN** n8n flow executes POST `/apps/planix/api/portfolios/{portfolio-id}/snapshots`
- **THEN** planix backend:
  1. Queries all project_status_reports for latest rapportage per project in portfolio
  2. Aggregates vw_portfolio_health (projecten counts, RAG-score)
  3. Queries risico table, sums weighted risk-score
  4. Queries vw_milestone_radar for 30 + 90 day counts
  5. Fetches spend-data from financeq
  6. Generates payload (JSON):
     ```json
     {
       "portfolio_id": "uuid",
       "snapshot_datum": "2026-05-01",
       "projecten_totaal": 24,
       "projecten_groen": 14,
       "projecten_amber": 7,
       "projecten_rood": 3,
       "gewogen_rag_score": 7.2,
       "rag_delta_vs_vorige_month": -0.3,
       "risico_score_portfolio": 10.3,
       "milestone_30d_count": 8,
       "milestone_30d_at_risk": 2,
       "milestone_90d_count": 22,
       "spend_begroot": 8400000,
       "spend_realisatie": 5900000,
       "spend_percentage": 70,
       "resource_utilisatie_avg_percentage": 97,
       "top_5_issues": [
         { "project": "PRJ-001", "issue": "Budget overrun 20%", "severity": "rood" },
         { "project": "PRJ-005", "issue": "Resource conflict week 19", "severity": "amber" }
       ]
     }
     ```
  7. Creates portfolio_snapshot object with immutable payload
- AND PMO-managers receive e-mail: "April-snapshot ready for MT-rapportage"

#### Scenario 2: Snapshot trend-comparison

- **GIVEN** snapshots for Jan, Feb, Mar, Apr 2026 in database
- **WHEN** user opens "Trend-view" in portfolio dashboard
- **THEN** system displays multi-line chart with 4 data-points (one per month):
  - RAG-count trends (line: groen count, line: amber count, line: rood count)
  - Risk-score trend (single line)
  - Spend % trend (single line)
  - Resource-utilization % trend (single line)
- AND sparkline shows direction (↑ ↓ →) next to each metric
- AND hovering point shows exact values for that month

#### Scenario 3: Manual snapshot

- **GIVEN** PMO-manager wants to create snapshot outside scheduled time (e.g., for emergency MT meeting)
- **WHEN** PMO clicks "Create snapshot now" button (top-right of portfolio dashboard)
- **THEN** system generates snapshot with current KPI's (same logic as nightly task)
- AND snapshot is stored immediately (not scheduled)
- AND system checks: if snapshot already exists for today's date, returns 409 (reject duplicate)

### REQ-009: Executive Summary Widget voor Bestuur

The system SHALL render a one-page "Executive Summary" optimized for board/MT presentation, with 4-6 KPI-tiles, top-3 issues, top-3 successen, and 30-day milestone-radar. Optional presentatie-modus (fullscreen, no nav).

#### Scenario 1: Executive-summary view

- **GIVEN** wethouder Pieter is `portfolio_eigenaar` of portfolio "Stedelijke Vernieuwing"
- **WHEN** Pieter opens `/planix/portfolio/{id}/exec` (or embeds widget on mydash)
- **THEN** system renders one-page summary:
  - Header: "Stedelijke Vernieuwing | Mei 2026"
  - KPI tiles (4-6 metrics):
    - "Projecten: 12 (8 groen, 3 amber, 1 rood)"
    - "Portfolio Risk: 9.2 (↓ improved)"
    - "Budget: 70% bestedingsgraad (on-track)"
    - "Milestones 30d: 8 (2 at-risk)"
    - "Resources: 97% utilization (3 conflicts)"
  - Text sections:
    - "Top-3 Issues" (extracted from latest project_status_reports.issues)
    - "Top-3 Successen" (from .successen)
    - Bulleted lists, each with project-name + brief description
  - Milestone-radar (30 days, not 90; bubbles same as REQ-003 but narrower scope)
- AND tiles are color-coded: green if healthy, orange if warning, red if critical

#### Scenario 2: Presentatie-modus

- **GIVEN** executive-summary view rendered
- **WHEN** Pieter clicks "Presentatie-modus" button
- **THEN** browser switches to fullscreen
- AND navigation, header, footer disappear
- AND content scales for large-screen projection (suitable for beamer in MT-meeting)
- AND ESC key or back-button exits presentatie-modus

#### Scenario 3: Bestuurder commentaar

- **GIVEN** Pieter viewing executive-summary
- **WHEN** he clicks a red KPI tile (e.g., "1 rood project")
- **THEN** tooltip or detail-card appears with drill-down option
- AND button "Add comment" opens CommentModal (textarea)
- AND Pieter enters: "Let's discuss this red project at next MT"
- AND submits → comment saved, PMO-manager notified
- AND comment visible in portfolio-detail for PMO context

### REQ-010: PMO Permissies en Read-only Voor Bestuur

The system SHALL enforce role-based access control: `pmo_manager` full CRUD on assigned portfolios, `pmo_lid` draft/read but no publish, `portfolio_eigenaar` read-only + commentaar, `program_manager` scoped to own programma, `projectmanager` own projects only.

#### Scenario 1: PMO-lid cannot publish

- **GIVEN** PMO-medewerker Henk is role=pmo_lid, assigned to portfolio X
- **WHEN** Henk drafts status-rapportage for project in portfolio X
- **WHEN** Henk clicks "Publiceer naar PMO"
- **THEN** API POST /publish returns 403 Forbidden: `{ "error": "requires_pmo_manager_role" }`
- AND concept stays in draft state
- AND PMO-manager must review + publish

#### Scenario 2: Portfolio_eigenaar read-only + commentaar

- **GIVEN** wethouder Pieter is portfolio_eigenaar of portfolio "Stedelijke Vernieuwing"
- **WHEN** Pieter opens portfolio-detail
- **THEN** all widgets render in read-only mode:
  - No "edit" buttons on risicos, no "publish" on rapportages
  - Health-table rows not draggable
  - Trend-charts not editable
- BUT "Add comment" button visible (on tiles, on issues)
- AND Pieter can add comment → notification to pmo_manager

#### Scenario 3: Program-manager scoped access

- **GIVEN** program-manager Roos assigned to BBV-programma "Mobiliteit"
- **WHEN** Roos opens `/planix/portfolio`
- **THEN** portfolio-list filtered to show only portfolios containing projects tagged with `bbv_doel_id` = "Mobiliteit"
- AND Roos can view health-table, risks, resources only for her programma-projects
- AND cannot create/edit portfolios (not pmo_manager)

#### Scenario 4: Projectmanager own-project-only

- **GIVEN** projectmanager Sandra assigned to project "PRJ-001"
- **WHEN** Sandra opens `/planix/portfolio/portfolio-xyz`
- **THEN** health-table shows all projects in portfolio (for context)
- BUT status-rapportage create/edit restricted to PRJ-001 only
- AND risico create/edit restricted to PRJ-001 only
- AND resource-allocatie edit restricted to own team (users assigned to PRJ-001)

#### Scenario 5: Admin has full access

- **GIVEN** Nextcloud admin Bas
- **WHEN** Bas opens any portfolio
- **THEN** all CRUD operations enabled; can create/edit/publish/delete any object
- AND can assign PMO roles to other users (Nextcloud admin panel)

---

## User Stories

- As a PMO-manager, I want to see at a glance which projects are red so that I can escalate issues to leadership
- As a PMO-manager, I want to see resource-conflicts across my team so that I can rebalance capacity before bottlenecks occur
- As a PMO-manager, I want to track risk-trends over months so that I can report portfolio health to the executive board
- As a program-manager, I want to see dependencies across my program's projects so that I can identify at-risk milestones
- As a projectmanager, I want a guided wizard for monthly status-reporting so that I don't forget important details
- As an executive, I want a one-page summary of my portfolio so that I can present status in MT-meeting in 5 minutes
- As a bestuurslid, I want to see budget trends so that I can make informed decisions on budget reallocation
- As a financieel director, I want portfolio spend-vs-budget so that I can forecast year-end expenditure
- As a PMO-medewerker, I want to help projectmanagers draft reports so that quality is consistent across portfolio
- As an auditor, I want immutable snapshots so that I have historical evidence of portfolio state for compliance

---

## Cross-Functional Integration

- **financeq** — read spend-realization (begroting + realisatie per project-cost-center) via webhook + query
- **BBV-programma-tree** — read bbv_doel_ids for scope-filtering and programme-manager scoping
- **procest** — read zaak-completion % if project links to procest zaak (via project.zaakUuid)
- **n8n** — scheduled monthly snapshot creation (cron 1st of month 02:00)
- **mydash** — register 8 widgets as OCP\Dashboard\IWidget for board embedding
- **docudesk** — future: PDF stuur-rapportage generation from snapshot
- **openregister** — object storage + schema validation (dependency, no code change)

---

**OpenSpec Change**: `portfolio-dashboard-pmo`  
**Status**: specs
