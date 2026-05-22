# Tasks: BBV Programma Tree (Programma → Doel → Indicator)

## Backend Data Layer

- [ ] Create register schema `bbv_programma` with fields: uuid, gemeente_orgaan_id, nummer, titel, omschrijving, portefeuillehouder_id, programma_manager_id, coalition_period, status, vastgesteld_door_raad_op, vastgesteld_in_raadsbesluit_id, kleur, volgorde, gemma_taakgebied_codes
- [ ] Create register schema `bbv_doel` with fields: uuid, programma_id, nummer, titel, omschrijving, looptijd_start, looptijd_eind, eigenaar_id, status, beleidsdoel_iri, volgorde
- [ ] Create register schema `bbv_activiteit` with fields: uuid, doel_id, nummer, titel, omschrijving, start_datum, eind_datum, verantwoordelijke_id, status, planix_project_ids, procest_zaaktype_codes, decidesk_raadsbesluit_ids, volgorde
- [ ] Create register schema `bbv_indicator` with fields: uuid, parent_type, parent_id, nummer, naam, categorie, bbv_verplicht, bbv_kpi_code, eenheid, baseline_waarde, baseline_jaar, streefwaarde, streefwaarde_jaar, meet_frequentie, bron, eigenaar_id
- [ ] Create register schema `bbv_meting` with fields: uuid, indicator_id, peildatum, gemeten_waarde, bron_url, toelichting, ingevoerd_door_id, bevroren
- [ ] Create register schema `bbv_budget_koppeling` with fields: uuid, activiteit_id, financeq_budgetregel_id, taakveld_code, bedrag_begroot, bedrag_realisatie, jaar, mutatie_grondslag
- [ ] Create seed-data register `cbs_taakvelden` with 76 CBS task field codes
- [ ] Create seed-data register `bbv_verplichte_kpis` with 39 national standard KPI definitions
- [ ] Create seed-data register `bbv_paragrafen` with 7 BBV financial sections
- [ ] Add validators for all schemas: nummer sequence (doel/activiteit must match parent.nummer + ".X"), taakveld pattern, measurement unit alignment, outlier detection (>3 sigma)
- [ ] Create table `bbv_snapshot` with fields: id, gemeente_orgaan_id, coalition_period, snapshot_jaar, status, gemaakt_op, gemaakt_door_id, goedgekeurd_door_raad_op, data (JSONB), sha256_hash
- [ ] Create table `bbv_audit_trail` with fields: id, gemeente_orgaan_id, action_date, action_type, resource_type, resource_id, user_id, old_values (JSONB), new_values (JSONB), raadsbesluit_id, reason
- [ ] Implement trigger to log all mutations to audit trail on INSERT/UPDATE/DELETE of programma/doel/activiteit/indicator/meting

## Tree Visualization Backend

- [ ] Add API endpoint `GET /api/objects/planix/bbv/tree?gemeente_id={id}&coalition_period={period}` returning nested programma/doel/activiteit/indicator tree structure with counts
- [ ] Add API endpoint `GET /api/objects/planix/bbv/programma/{id}/summary` returning inline summary stats (doelen count, overdue activiteiten, total budget, variance)
- [ ] Implement tree reordering via `PATCH /api/objects/planix/bbv/{node_type}/{id}` with `volgorde` field update

## Tree Visualization Frontend

- [ ] Create Vue component `BBVTree.vue` with collapsible tree rendering (max 100 nodes without lag)
- [ ] Implement expand/collapse toggle with localStorage persistence per user
- [ ] Add color band rendering using `programma.kleur` (hex color from DB)
- [ ] Add status icon rendering (concept=blue outline, vastgesteld=green check, in_uitvoering=orange gear, afgesloten=grey)
- [ ] Add child count badges ("4 doelen", "11 activiteiten", "5 indicatoren")
- [ ] Create context menu (three-dot) per node with actions: Edit, Delete, Add Child, Archive, Change Status (if authorized)
- [ ] Add inline summary stats display on programma nodes (doelen count, overdue count, budget, variance)
- [ ] Implement search-as-you-type filtering across nummer/titel/omschrijving with match highlighting
- [ ] Add status filter dropdown (concept, vastgesteld, in_uitvoering, afgesloten)
- [ ] Implement drag-and-drop reordering with `volgorde` update via PATCH

## Gantt Timeline Backend

- [ ] Add API endpoint `GET /api/objects/planix/bbv/gantt?programma_id={id}` returning activiteiten with start_datum, eind_datum, status, linked project progress %
- [ ] Implement progress rollup calculation: for each activiteit, fetch linked planix projects and calculate (sum of completed_tasks / sum of total_tasks)
- [ ] Implement status pessimism logic: if any linked project status = "vertraagd", activiteit gets "vertraagd" status
- [ ] Add webhook listener for planix `project.updated` events; trigger activiteit progress recalculation on task change

## Gantt Timeline Frontend

- [ ] Create Vue component `BBVGantt.vue` with horizontal timeline spanning coalition period
- [ ] Implement Y-axis grouping: programma → doel → activiteit (collapsible)
- [ ] Implement X-axis timeline: months from coalition start to end (e.g., Jan 2026 - Dec 2029)
- [ ] Render horizontal bars for each activiteit with status colors: green (gereed), orange (in_uitvoering/gepland), red (vertraagd/uitgesteld), grey (geannuleerd)
- [ ] Add "overdue" red hatching overlay if eind_datum < today and status != gereed; show "X days late" label
- [ ] Implement hover card: titel, verantwoordelijke, % done, eind_datum
- [ ] Add click handler to drill into activiteit detail
- [ ] Implement expand/collapse for programma and doel groups
- [ ] Add zoom controls to adjust timeline granularity (weeks, months, quarters)
- [ ] Render "today" vertical line marker
- [ ] Add PNG/PDF export button

## Indicator Management Backend

- [ ] Add API endpoint `POST /api/objects/planix/bbv_indicator` with validation: streefwaarde_jaar >= baseline_jaar
- [ ] Add API endpoint `POST /api/objects/planix/bbv_meting` with unit validation and outlier detection (warn if > 3 sigma)
- [ ] Add API endpoint `PATCH /api/objects/planix/bbv_meting/{id}` with check: reject if `bevroren=true` (return 409 Conflict)
- [ ] Add API endpoint `GET /api/objects/planix/bbv_indicator/{id}/trend` returning historical measurements for graphing
- [ ] Implement BBV KPI suggestion endpoint `GET /api/objects/planix/bbv_kpi/suggest?taakgebied_codes={codes}` matching `gemma_taakgebied_codes` to verplichte_kpis seed data
- [ ] Implement bulk-add KPI endpoint `POST /api/objects/planix/bbv_kpi/add-bulk` creating indicators from suggested list

## Indicator Management Frontend

- [ ] Create modal `AddIndicatorModal.vue` with form: naam, categorie, eenheid, baseline (value/year), streefwaarde (value/year), meet_frequentie, bron
- [ ] Create modal `AddMeasurementModal.vue` with form: peildatum, gemeten_waarde, bron_url, toelichting
- [ ] Implement trend chart using Chart.js: X=peildatum, Y=gemeten_waarde, overlay baseline line, overlay streefwaarde line, shaded green/red zones
- [ ] Add variance alerting: if measurement > 10% from target, show yellow warning; if regressing, show red warning
- [ ] Implement frozen measurement immutability: if `bevroren=true`, disable edit/delete buttons
- [ ] Create KPI suggestion modal: list matching KPI's from seed data, pre-checked, with bulk-add button
- [ ] Implement KPI import form: show prior-year programma's indicators, allow import with updated baseline/target for new period

## Project Linking Backend

- [ ] Add API endpoint `GET /api/objects/planix/bbv/projects?gemeente_id={id}` returning searchable planix projects for linking
- [ ] Implement project progress rollup: for each activiteit, query linked planix projects; calculate (sum completed_tasks / sum total_tasks)
- [ ] Add webhook listener for planix `task.updated` events; recalculate activiteit progress on change
- [ ] Implement status pessimism: if any linked project status = "vertraagd", activiteit gets pessimistic status

## Project Linking Frontend

- [ ] Add "Link Project" button to activiteit detail
- [ ] Create search modal for planix projects filtered to gemeente, status=active/planning
- [ ] Display project cards in activiteit detail: titel, status, % done, owner, "View in Planix" link
- [ ] Implement X button to unlink project
- [ ] Implement zaaktype linking: "Link Zaaktype" button, search modal, card display
- [ ] Implement raadsbesluit linking: "Link Raadsbesluit" button, search modal (from decidesk), card display
- [ ] In Gantt view, show progress % fill within activiteit bar (visual fill 0-100%)

## Budget Aggregation Backend

- [ ] Add API endpoint to fetch financeq budgetregels: `GET /api/integrations/financeq/budgetregels?gemeente_id={id}&jaar={year}` (integrate with financeq)
- [ ] Implement budget aggregation logic: match budgetregels to activiteiten via taakveld_code + jaar; rollup to doel → programma
- [ ] Add webhook listener for financeq `budgetregel.changed` and `realisatie.updated` events; re-aggregate on change
- [ ] Add API endpoint `GET /api/objects/planix/bbv/budget?programma_id={id}&jaar={year}` returning aggregated begroot/realisatie at all levels
- [ ] Add alert logic: if realisatie > begroot × 1.1 or < begroot × 0.9, flag variance
- [ ] Add endpoint to list unlinked realisatie: `GET /api/objects/planix/bbv/budget/unlinked?gemeente_id={id}&jaar={year}`

## Budget Aggregation Frontend

- [ ] Create budget tile component showing begroot/realisatie/restant with status indicator (green/orange/red)
- [ ] Implement variance alerting: display banner if variance > 10% (red or orange)
- [ ] Add unlinked realisatie banner with drill-down to filterable table
- [ ] Create budget breakdown table by taakveld
- [ ] Implement year-over-year budget comparison view: side-by-side 2026 vs 2027
- [ ] In programma header, show inline budget summary: "Budget 4.25M EUR | Spending 3.18M | Variance -25%"

## Export Backend

- [ ] Add API endpoint `POST /api/objects/planix/bbv/export/iv3-xbrl` generating XBRL instance conforming to CBS 2026 taxonomy
  - [ ] Map programma/doel/indicator/meting to XBRL concepts
  - [ ] Run local CBS XBRL validation; return validation report before download
  - [ ] Stream file to prevent timeout on large exports (1000+ activiteiten)
- [ ] Add API endpoint `POST /api/objects/planix/bbv/export/waarstaatjegemeente-csv` generating CSV with 39 KPI's
  - [ ] Columns: kpi_code, kpi_naam, peildatum, gemeten_waarde, eenheid, bron_url
  - [ ] Format: UTF-8, semicolon-delimited, Dutch decimals (comma)
- [ ] Add API endpoint `POST /api/objects/planix/bbv/export/sisa-excel` generating BZK SISA template Excel
  - [ ] Sheets per paragraaf (lokale heffingen, weerstandsvermogen, etc.)
  - [ ] Columns: Activity, Taakveld, Begroot, Realisatie, Variance, Variance %
  - [ ] BZK compliance formatting
- [ ] Add API endpoint `POST /api/objects/planix/bbv/export/validation` returning pre-export validation report
  - [ ] Checks: all programma's vastgesteld, all doelen have looptijd, all indicatoren have baseline or target, all metingen frozen
  - [ ] Warnings: unmapped taakveld codes, variance > 20%, indicators with no measurements
- [ ] Add API endpoint `POST /api/objects/planix/bbv/export/audit-trail` generating PDF audit trail with chronological mutations
  - [ ] Signature with SHA-256 hash for integrity

## Export Frontend

- [ ] Create export modal with format selection: iV3 XBRL, Waarstaatjegemeente CSV, SISA Excel, Snapshot, Audit Trail
- [ ] Display pre-export validation report; block export if errors, allow proceeding if warnings only
- [ ] Implement file download flow for each format
- [ ] Add scheduled export button (future: setup recurring weekly exports)

## Permissions Backend

- [ ] Add role definitions: `bbv_beheerder`, `bbv_indicator_eigenaar`, `bbv_portefeuillehouder`, `beleidsmedewerker`, authenticated_user, anonymous
- [ ] Implement permission checks on all API endpoints: check user role before DB query
- [ ] Return 403 Forbidden if user lacks permission; do not leak resource data in error
- [ ] Add audit logging: log all access attempts (allowed/denied) with user, timestamp, action, resource, reason
- [ ] Implement public share token generation: `POST /api/objects/planix/bbv/share` with expiry_days parameter
- [ ] Validate share tokens on public view access; return 404 if expired

## Permissions Frontend

- [ ] Add authorization checks on all UI actions (edit, delete, add, approve, export)
- [ ] Disable buttons and show tooltip if user lacks permission (e.g., "Only BBV Administrator can approve")
- [ ] Create public view route `/planix/bbv/public/{gemeente-slug}` accessible without authentication
- [ ] Implement token-based public view: `/planix/bbv/public/token/{token}`
- [ ] Public view filters: show only vastgesteld/in_uitvoering programma's; hide concept data, budget detail, unfrozen measurements
- [ ] Add "Share Public" button generating time-limited URL
- [ ] Implement per-programma share token scope option

## Snapshots and Audit Trail Backend

- [ ] Add API endpoint `POST /api/objects/planix/bbv/snapshot/freeze?jaar={year}` creating immutable snapshot
  - [ ] Deep-copy all programma/doel/activiteit/indicator/meting/budget_koppeling data to `bbv_snapshot` table
  - [ ] Generate SHA256 hash of snapshot data
  - [ ] Set all measurements with peildatum <= year-end to `bevroven=true`
- [ ] Add API endpoint `GET /api/objects/planix/bbv/snapshot/{snapshot_id}` returning snapshot data (read-only)
- [ ] Add API endpoint `GET /api/objects/planix/bbv/diff?snapshot_id={id}&current=true` computing diff between snapshot and current version
  - [ ] Identify added nodes (green), deleted nodes (red), modified nodes (orange) with old→new field values
- [ ] Implement mutation logging: every INSERT/UPDATE/DELETE on programma/doel/activiteit/indicator/meting tables triggers audit trail entry
- [ ] Add API endpoint `GET /api/objects/planix/bbv/audit-trail?gemeente_id={id}&jaar={year}` returning chronological audit entries
- [ ] Add API endpoint `POST /api/objects/planix/bbv/audit-trail/export-pdf` generating signed PDF for accountant review

## Snapshots and Audit Trail Frontend

- [ ] Create snapshot modal: show list of available year snapshots; allow download/view/compare
- [ ] Implement diff view component: side-by-side tree comparison with ADD/DELETE/MODIFY highlighting
- [ ] Create audit trail browser: table with columns Date, User, Action, Resource, Old Value, New Value, Raadsbesluit
- [ ] Add filters: date range, user, action type, resource type
- [ ] Add summary: "Total X mutations (Y CREATE, Z UPDATE, ...)"
- [ ] Add download button for audit trail PDF export

## Integration with Financeq

- [ ] Register webhook listener endpoint `POST /api/webhooks/financeq/budgetregel-changed`
- [ ] On webhook: update `bbv_budget_koppeling.bedrag_realisatie`; recalculate programma/doel rollups
- [ ] Test webhook with financeq team; handle retries on failure

## Integration with Decidesk

- [ ] Register event publisher for `bbv.programma.status_changed` events
- [ ] On programma status change to `vastgesteld`: publish event to decidesk with programma details
- [ ] Decidesk team subscribes to event and displays linked programma on raadsbesluit detail (out-of-scope for this spec; noted for cross-project coordination)
- [ ] Add webhook listener for raadsbesluit `status_changed` event (future: auto-approve programma when raadsbesluit approved)

## Integration with Procest

- [ ] Add webhook listener endpoint `POST /api/webhooks/procest/zaak-closed`
- [ ] On zaak closure: find all indicatoren linked to that zaaktype; increment output indicator measurement count by 1
- [ ] Aggregate daily counts into single meting per indicator per day

## Integration with n8n (Future)

- [ ] Design n8n workflow to poll CBS Statline / Politie open data monthly
- [ ] Create POST endpoint `/api/objects/planix/bbv_meting/bulk-import` accepting array of meting objects
- [ ] Test workflow with sample data (out-of-scope for this spec; noted for future planning)

## Testing

- [ ] Write unit tests for all validators (nummer sequence, taakveld pattern, outlier detection)
- [ ] Write integration tests for all API endpoints (CRUD operations, permission checks, rollup calculations)
- [ ] Write UI component tests for tree, Gantt, indicators, budget tiles
- [ ] Write tests for export format validation (XBRL, CSV, Excel)
- [ ] Write tests for snapshot creation and diff calculation
- [ ] Write tests for audit trail logging on all mutations
- [ ] Performance test: tree with 100 nodes renders within 2 seconds
- [ ] Performance test: Gantt with 200+ bars renders within 3 seconds
- [ ] Performance test: Snapshot with 1000+ nodes created within 30 seconds

## Documentation

- [ ] Add API documentation for all new endpoints (OpenAPI spec)
- [ ] Create user guide for BBV tree management (Dutch)
- [ ] Create guide for programma approval workflow (vastgesteld via raadsbesluit)
- [ ] Create guide for indicator registration and measurement entry
- [ ] Create guide for budget reconciliation with financeq
- [ ] Create guide for year-end snapshot and audit trail export
