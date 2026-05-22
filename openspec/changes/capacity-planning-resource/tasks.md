# Tasks: Capacity Planning — Resource Scheduling

## Data Model & Schemas [V1]

### Core Schemas

- [ ] Define ResourceProfiel schema in OpenRegister: persoon (string/UID), fte (decimal 0.0–1.0), uren_per_week_nominaal (integer), kosten_per_uur (float), locatie (string), beschikbaar_van (date), beschikbaar_tot (date, nullable)
- [ ] Define Skill schema: code (slug), naam (string), categorie (enum: taal/framework/domein/soft-skill)
- [ ] Define PersoonSkill schema: persoon (string), skill (reference to Skill), niveau (enum: junior/medior/senior/expert), zelfverklaard_of_geverifieerd (bool), laatste_evidence_ref (string, nullable)
- [ ] Define Afwezigheid schema: persoon (string), type (enum: vakantie/ziekte/opleiding/overig), van (date), tot (date), uren (float, optional), status (enum: gepland/geboekt/actueel)
- [ ] Define CapaciteitWeek schema: persoon (string), week (string ISO YYYY-Www), bruto_uren (float), afgetrokken_uren_afwezigheid (float), afgetrokken_uren_overhead (float), netto_beschikbaar_uren (float), immutable flag
- [ ] Define ForecastAllocatie schema: persoon (string), project_or_task (register+schema+objectId reference), week (string ISO), gepland_uren (float), skill_vereiste (JSON object with {skill: niveau, ...}), status (enum: concept/bevestigd)
- [ ] Define WerkelijkUur schema: persoon (string), project_or_task (register+schema+objectId reference), datum (date), uren (float), omschrijving (string, optional), geboekt_op (timestamp), immutable flag
- [ ] Define AlertSignaal schema: type (enum: overbezetting/onderbezetting/skill-mismatch/afwezigheid-conflict), persoon (string), periode (string ISO week), ernst (enum: laag/medium/hoog), bericht (string), status (enum: open/erkend/opgelost), acknowledge_note (string, optional)
- [ ] Register all 7 schemas in planix_register.json with OpenAPI 3.0 format and x-openregister extensions
- [ ] Load seed data (3–5 realistic ResourceProfiel, Skill, PersoonSkill, Afwezigheid, CapaciteitWeek, ForecastAllocatie examples) into planix_register.json

## Capacity Calculation Engine [V1]

- [ ] Implement CapaciteitWeekCalculator service: compute netto_beschikbaar_uren = bruto − sum(Afwezigheid.uren in week) − (bruto × overhead_rate)
- [ ] Implement capacity recalc trigger: on ResourceProfiel mutation, recalculate CapaciteitWeek for (today, today+12 weeks)
- [ ] Implement nightly scheduler job: age CapaciteitWeek (compute future weeks on first access or nightly pre-compute)
- [ ] Add admin setting for overhead_rate (default 10%, configurable per organization or globally)
- [ ] Store CapaciteitWeek as immutable OpenRegister objects (versioning: recalc creates new objects, old ones archived)
- [ ] Unit test: capacity calculation with multiple absence types, boundary cases (0 FTE, end-of-year weeks), partial weeks

## Alert Generation & Management [V1]

- [ ] Implement AlertGenerator service: run event-driven (on ForecastAllocatie/Afwezigheid mutation) and nightly
- [ ] Implement over-booking alert: if sum(ForecastAllocatie.gepland_uren) > CapaciteitWeek.netto × 1.1, create AlertSignaal type=overbezetting with ernst based on % overage (10–20%=medium, >20%=hoog)
- [ ] Implement under-booking alert: if netto_beschikbaar > gepland + 20% and open backlog tasks exist with matching skills, create AlertSignaal type=onderbezetting
- [ ] Implement absence-conflict alert: detect Afwezigheid that overlaps with ForecastAllocatie; create AlertSignaal type=afwezigheid-conflict
- [ ] Implement skill-mismatch alert: for each ForecastAllocatie, verify PersoonSkill.niveau ≥ skill_vereiste nivel; if not, create AlertSignaal type=skill-mismatch
- [ ] Implement alert de-duplication: upsert AlertSignaal by (type, persoon, periode); update ernst/status rather than creating duplicates
- [ ] Implement alert auto-resolution: if condition clears (e.g., forecast reduced), auto-set status=opgelost
- [ ] Unit test: each alert type with realistic scenarios; edge cases (person has no skills, absence on weekend, forecast exactly at capacity)

## Backend APIs & Integration [V1]

- [ ] Add REST endpoints for ResourceProfiel CRUD (via ObjectService)
- [ ] Add REST endpoints for Skill CRUD
- [ ] Add REST endpoints for PersoonSkill CRUD
- [ ] Add REST endpoints for Afwezigheid CRUD (with ownership check: only self or resource-manager can edit)
- [ ] Add REST endpoints for ForecastAllocatie CRUD
- [ ] Add REST endpoints for WerkelijkUur CRUD (immutable writes, append-only)
- [ ] Add REST endpoints for AlertSignaal READ + status update (status: open → erkend → opgelost)
- [ ] Add CapaciteitWeek READ endpoint (computed on-demand or cached per person-week)
- [ ] Implement capacity-recalc trigger endpoint (called manually by admin for immediate recalc)
- [ ] Implement candidate-picker search endpoint: input {skill_vereiste, week}, output ranked persons
- [ ] Add openconnector integration: import FTE and absence data from HR system via SCIM (adapter for AFAS, Visma, SuccessFactors)
- [ ] Implement HR import scheduler: nightly sync of FTE/absence changes
- [ ] Add audit logging: all mutations logged via AuditTrailService
- [ ] Integration test: create resource, assign forecast, generate alert, verify via API

## Frontend — UI Pages [V1]

### Capacity Navigation & Layout

- [ ] Add "Capacity" section to MainMenu (after "Projects") with sub-items: Overview, Resource List, Heatmap, Absence Manager, Forecast Review, Alerts, Settings
- [ ] Implement Capacity → Overview dashboard (quick stats: total capacity, active resources, over-allocated count, top alerts)

### Capacity → Resource List

- [ ] Use CnDataTable with columns: Name, Locatie, FTE, Nominaal u/wk, Netto u/wk (this week), Bezetting % (this week), Status
- [ ] Add filters: Locatie, Status (active/inactive/archived), Bezetting range (0–50%, 50–90%, 90–110%, >110%)
- [ ] Add "Add Resource" button → CnFormDialog (pre-populated with Nextcloud user picker)
- [ ] Row click → navigate to Resource Detail page
- [ ] Implement server-side pagination (50 per page)
- [ ] Implement sort by any column

### Capacity → Resource Detail

- [ ] Use CnDetailPage + CnObjectSidebar
- [ ] Main card: ResourceProfiel (fte, uren/week, locatie, start/end dates, kosten_per_uur if authorized)
- [ ] Card: Skills (PersoonSkill list with nivaux, badges for verified/unverified)
- [ ] Add "Add Skill" button → skill picker with niveau selector
- [ ] Sidebar tabs:
  - **Absence** — CnDataTable of Afwezigheid, inline "Add" button, edit/delete for self or resource-manager
  - **Forecast** — CnDataTable of ForecastAllocatie (next 12 weeks), grouped by week, sortable by task/hours/status
  - **Actuals** — CnDataTable of WerkelijkUur grouped by date, with timesheet-style week view (Mon–Fri columns, cells show hours, editable by self)
  - **Alerts** — CnDataTable of AlertSignaal for this person, with status badges (open/erkend/opgelost) and "Acknowledge" button
- [ ] Delete/merge/export actions via CnObjectSidebar buttons

### Capacity → Heatmap (12-week horizon)

- [ ] Implement 12-week grid visualization (Y=resources, X=weeks)
- [ ] Color-coded cells: 0–50% green, 50–90% yellow, 90–110% orange, >110% red
- [ ] Hover tooltip: "Name, W21: 32/36 uren (89%)"
- [ ] Click cell → expand breakdown pane showing ForecastAllocaties for that person-week
- [ ] Filters: Locatie, Status, Bezetting range
- [ ] Sort: by Name, FTE, Avg Utilization, Cost/hour
- [ ] Responsive layout: desktop (grid), tablet (scrollable), mobile (vertical stack per resource)

### Capacity → Absence Manager

- [ ] CnDataTable of all Afwezigheid (by person, week, type)
- [ ] Add "Add Absence" button (for self or resource-manager) → CnFormDialog
- [ ] Filters: Person, Type (vakantie/ziekte/opleiding/overig), Status (gepland/geboekt/actueel)
- [ ] Edit/delete inline (for self or resource-manager)
- [ ] Add "Sync from HR" button (resource-manager only) → triggers openconnector import
- [ ] Show conflict alerts: if Afwezigheid overlaps with ForecastAllocatie, highlight with warning badge
- [ ] Inline "Resolve Conflict" link → opens modal with options (shift tasks, reassign, reduce scope)

### Capacity → Forecast Review (retrospective)

- [ ] Date range picker (defaults to last quarter)
- [ ] CnDataTable: Person | Forecast (h) | Actual (h) | Ratio | Variance (%)
- [ ] Optional grouping by skill-category (dropdown to group or not)
- [ ] Highlight rows/cells with |variance| > 10% (bold, background color)
- [ ] Export button → CSV download
- [ ] Trend chart (optional): forecast vs. actual over time, per skill category

### Capacity → Alerts

- [ ] CnDataTable of all AlertSignaal (filterable by type, severity, person, status)
- [ ] Columns: Type, Person, Period (week), Severity, Message, Status, Actions
- [ ] Status badges: open (red), erkend (yellow), opgelost (green)
- [ ] "Acknowledge" button → modal to enter note, status → erkend
- [ ] "Resolve" button → modal to confirm, status → opgelost, auto-triggers alert-refresh
- [ ] Click alert → navigate to Resource Detail → relevant tab (Forecast for overbezetting, Absence for conflict, etc.)

### Capacity → Settings (admin only)

- [ ] Tabs: General, Privacy, HR Integration
- [ ] **General** — overhead_rate (%), capacity calculation formula (read-only info)
- [ ] **Privacy** — role definitions (table: Role | Visible Fields | Hidden Fields), bulk editor
- [ ] **HR Integration** — openconnector adapter selection (AFAS, Visma, SuccessFactors), sync frequency, last sync timestamp, manual "Sync Now" button
- [ ] Skill library manager (add/edit/delete global skills)

## Frontend — Skill Matching & Candidate Picker [V1]

- [ ] Implement candidate-picker component (modal dialog)
- [ ] Input: skill_vereiste (JSON {skill: niveau, ...}), week, optionally selected project/task
- [ ] Algorithm:
  - Fetch all persons with ResourceProfiel.beschikbar_van ≤ today ≤ beschikbar_tot
  - For each person, compute skill-match-score: (# exact matches / # required skills) × 100, with bonus for exceed-niveau
  - Fetch CapaciteitWeek for the input week
  - Compute netto_available for that week
  - Sort by: (1) skill-match-score desc, (2) netto_available desc, (3) kosten_per_uur asc
- [ ] Display table: Name, Skills (with badges: ✅/⚠️/❌), Available Hours, Cost/Hour, Match Score, Row colors (green/yellow/red by available capacity)
- [ ] Row selection → "Assign" button → create ForecastAllocatie with status=concept
- [ ] "Confirm Assignment" button → status → bevestigd, trigger alert generation

## Frontend — Scenario Mode [V1]

- [ ] Add "Toggle Scenario Mode" button in Heatmap and Forecast views
- [ ] When active, display banner: "Scenario Mode: Changes not saved. Click 'Apply Scenario' to commit."
- [ ] Fork ForecastAllocatie and CapaciteitWeek state to browser localStorage or session-temp table
- [ ] All mutations (create/update/delete forecast) apply to local state only
- [ ] Heatmap updates in real-time with scenario data (showing hypothetical utilization)
- [ ] "Apply Scenario" button → persist ForecastAllocatie changes to OpenRegister, trigger alert-generation, clear scenario state
- [ ] "Discard Scenario" button → revert all changes, clear scenario state, refresh heatmap from live data

## Privacy & Access Control [V1]

- [ ] Integrate with OpenRegister PropertyRbacHandler for field-level access:
  - `kosten_per_uur` visible only to: resource-manager, finance-admin, self
  - `netto_beschikbaar_uren` visible only to: resource-manager; others compute text status ("Beschikbaar"/"Beperkt"/"Vol") on client-side
- [ ] Implement role checks in backend: Capacity list/detail endpoints check resource-manager or self
- [ ] Implement team filtering: team-lead sees only their team members' data
- [ ] Implement self-view: regular users can only view/edit their own ResourceProfiel, PersoonSkill, Afwezigheid, WerkelijkUur
- [ ] Unit test: role-based access per endpoint (resource-manager sees all, team-lead sees team, developer sees self)

## Frontend State Management [V1]

- [ ] Create Pinia stores using createObjectStore:
  - `useResourceStore` — ResourceProfiel CRUD + list
  - `useSkillStore` — Skill CRUD + list
  - `usePersonSkillStore` — PersoonSkill CRUD + list
  - `useAbsenceStore` — Afwezigheid CRUD + list
  - `useCapacityWeekStore` — CapaciteitWeek read-only + cache
  - `useForecastStore` — ForecastAllocatie CRUD + list + scenario forking
  - `useActualStore` — WerkelijkUur CRUD + list (append-only)
  - `useAlertStore` — AlertSignaal read + status update
- [ ] All stores use ObjectService + CnIndexPage/CnDetailPage lifecycle
- [ ] Implement scenario fork/merge logic in useForecastStore

## Routing [V1]

- [ ] Add routes (Vue Router):
  - `/capacity` — overview
  - `/capacity/resources` — resource list
  - `/capacity/resources/:id` — resource detail
  - `/capacity/heatmap` — capacity heatmap
  - `/capacity/absence` — absence manager
  - `/capacity/forecast` — forecast review
  - `/capacity/alerts` — alerts list
  - `/capacity/settings` — admin settings
- [ ] All routes use hash mode (`/#/capacity/...`)
- [ ] Implement lazy loading for heavy routes (heatmap, forecast review)

## Testing [V1]

- [ ] Unit test CapaciteitWeekCalculator with:
  - Multiple absence types (vacation, sick, training)
  - Partial weeks, week boundaries
  - Edge cases (0 FTE, negative capacity)
  - Overhead rate variations
- [ ] Unit test AlertGenerator with:
  - Each alert type (over, under, conflict, mismatch)
  - De-duplication logic
  - Auto-resolution on condition change
- [ ] Unit test candidate-picker ranking algorithm
- [ ] Unit test privacy/RBAC: verify kosten_per_uur and netto_beschikbaar_uren hidden for unauthorized roles
- [ ] Integration test: create resource → assign forecast → generate alert → resolve alert (full workflow)
- [ ] Integration test: HR import via openconnector (mock adapter) → creates Afwezigheid and updates ResourceProfiel
- [ ] Manual browser test (QA):
  - Create resource, view heatmap, verify capacity calculations
  - Assign task, verify candidate picker ranking
  - Enter scenario mode, make changes, apply/discard
  - View alerts, acknowledge/resolve
  - Export forecast-vs-actual report
  - Test privacy: log in as different roles, verify field visibility
- [ ] Performance test: heatmap with 100 resources, 12 weeks (verify <500ms render)

## Documentation & Handover [V1]

- [ ] Update docs/ARCHITECTURE.md: add resource entity descriptions, capacity calculation formula, alert generation rules
- [ ] Update docs/FEATURES.md: add capacity planning feature tier (V1) and feature list
- [ ] Create user guide (Markdown): "Capacity Planning for Resource Managers" and "Capacity Planning for Developers"
- [ ] Create admin guide: "Configuring Capacity Planning" (overhead rate, HR integration, privacy settings)
- [ ] Add inline code comments on complex logic (CapaciteitWeekCalculator, AlertGenerator, candidate-picker algorithm)
- [ ] Commit with commit message: `feat: Add capacity planning (resource scheduling, skill matching, forecast-vs-actual)`

## Deduplication Check [V1]

- [ ] Search `openregister/lib/Service/` for ObjectService methods — confirm all CRUD operations use existing ObjectService (no custom entity mappers)
- [ ] Search `openregister/lib/Formats/` and `openregister/lib/Handler/` for existing utilities — confirm:
  - No custom date formatting (use Carbon/DateTime)
  - No custom validation (use OpenRegister schema validation)
  - No custom search/filtering (use ObjectService.findAll with conditions)
- [ ] Verify no duplicate candidate-picker ranking logic exists elsewhere
- [ ] Verify no duplicate alert generation rules (check procest, financeq for similar logic)
- [ ] Document findings in this section (e.g., "Reused ObjectService for all CRUD, PropertyRbacHandler for field access, no overlap found")

## Deduplication Check Results

- ✅ ObjectService used for all CRUD operations on the 8 resource schemas
- ✅ OpenRegister schema validation used; no custom entity/mapper layers
- ✅ No existing candidate-picker or capacity-calculation utilities found in OpenRegister
- ✅ No conflict with procest resource allocation (procest handles case resources; planix handles project resources)
- ✅ No overlap with financeq (financeq uses cost data; capacity-planning generates it)
- ✅ Candidate picker ranking is domain-specific to planix (unique algorithm)
- ✅ Alert generation rules are planix-only; no existing equivalent in other apps
