# Tasks: Risk Register & Issue Tracking

## Phase 1: Core Schemas & Integration (Sprint 1-2)

### OpenRegister Setup

- [ ] Define Risk schema in OpenRegister with fields: projectId, programmaId, risicoCode, titel, beschrijving, categorie (10-enum), probability (1-5), impact (1-5), score (computed), risicobereidheid, eigenaar, status (7-enum), identificatieDatum, volgendeReviewDatum, gerealiseerdDatum, linkedIssueId, reviewOverdueFlag
- [ ] Define MitigatieActie schema with fields: riskId, taakId, actieType (4-enum), verwachteScoreReductie, werkelijkeScoreReductie, streefDatum, verantwoordelijke, kosten, status (derived from Task)
- [ ] Define Issue schema with fields: projectId, issueCode, titel, beschrijving, bronRiskId, severity (4-enum), urgentie (3-enum), status (6-enum), meldingsDatum, streefDatum, oplossingsDatum, gemeldDoor, toegewezenAan, goedgekeurDoor, resolutionType (5-enum), oplossing, geleerdeLessen
- [ ] Define RiskReview schema with fields: riskId, reviewDatum, reviewer, nieuweProbability, nieuweImpact, wijzigingsRedenen, signedOff
- [ ] Create register manifest `lib/Settings/planix_register.json` with all 4 schemas + seed data (3-5 objects per schema, Dutch municipality examples)
- [ ] Create migration file for new register in repair step (idempotent, skip if version already imported)
- [ ] Verify all schemas pass validation against ADR-001 (no foreign keys, use OpenRegister relations, PascalCase, schema.org vocabulary)

### Backend: Score Calculation & Validation

- [ ] Create service `RiskScoringService` with methods:
  - `calculateScore(probability, impact): int` — returns probability × impact (1-25)
  - `validateAssessment(risk): ValidationResult` — ensures probability/impact in 1-5 range, returns error if invalid
  - `isHighRisk(score, risicobereidheid): bool` — compares score to appetite threshold
- [ ] Unit tests for all 25 score combinations (1×1 through 5×5)
- [ ] Unit tests for score boundary conditions (1, 5, 12, 20, 25)
- [ ] Create validation gate: prevent Risk status transition to `beoordeeld` if score ≥ appetite AND no MitigatieActie exists (REQ-001)

### Backend: Task Integration (REQ-002)

- [ ] Create service `MitigatieTaskSyncService` with methods:
  - `createTaskForMitigation(mitigation): Task` — auto-create Planix Task with risk title, score-reduction desc, assignee, due date
  - `updateTaskForMitigation(mitigation, updatedFields): Task` — sync mitigation changes to linked task (assignee, dueDate)
  - `mapTaskStatusToMitigationStatus(taskStatus): string` — return mitigation status (open/in-progress/completed/overdue)
- [ ] Integrate with TasksController webhook (existing): when Task.status changes, call `mapTaskStatusToMitigationStatus` and update MitigatieActie
- [ ] Unit tests: create task without task link, create task with existing task link, task status update propagates to mitigation
- [ ] Integration test: full flow (create mitigation → auto-create task → mark task done → mitigation status updates)

### Backend: Risk Score Recalculation on Review

- [ ] Modify Risk update flow: when RiskReview created with `nieuweProbability`, `nieuweImpact`:
  - Recalculate `Risk.score = nieuweProbability × nieuweImpact`
  - Update `Risk.probability`, `Risk.impact` to new values
  - Set `Risk.volgendeReviewDatum = reviewDatum + 90 days`
  - Clear `Risk.reviewOverdueFlag = false`
  - Create audit trail entry showing before/after values
- [ ] Unit test: verify score recalculation on review creation
- [ ] Unit test: verify review date advances 90 days from review date (not from original date)

### Backend: Risk-to-Issue Conversion (REQ-005)

- [ ] Create service `RiskToIssueConversionService` with methods:
  - `convertRiskToIssue(risk, issueStreefDatum): Issue` — create Issue with mapped severity, set bronRiskId, auto-create Task
  - `mapRiskImpactToIssueSeverity(impact): severity` — 1-2 → laag, 3 → midden, 4 → hoog, 5 → kritiek
- [ ] On conversion: close Risk mitigations (mark completed if not already)
- [ ] Create Planix Task for Issue resolution (similar to REQ-002)
- [ ] Unit tests: conversion logic, severity mapping, task creation
- [ ] Integration test: full risk → issue flow

### Frontend: Risk CRUD (Planix)

- [ ] Create page `/risks` (Risk List):
  - Use `CnDataTable` with columns: risicoCode, titel, score, status, projectId.naam, eigenaar.naam, volgendeReviewDatum
  - Add filter bar: by status (7 enums), categorie (10 enums), score range (1-25), projectId
  - Add sorting: by score, status, dueDate
  - Color-code rows: score ≥ 20 = red, 12-19 = orange, < 12 = yellow; reviewOverdueFlag = red banner
  - Each row links to `/risks/:id`
- [ ] Create page `/risks/new` (Risk Create):
  - Use `CnFormDialog` auto-generated from Risk schema
  - Pre-populate `projectId` from context
  - Auto-set `identificatieDatum = today`, `volgendeReviewDatum = today + 90 days`
  - On save: calculate score, validate required fields, create Risk
- [ ] Create page `/risks/:id` (Risk Detail):
  - Use `CnDetailPage` with tabs:
    - **Overview** — Risk properties + audit trail link
    - **Mitigations** — List of MitigatieActies, +Add button, edit/delete inline
    - **Reviews** — List of RiskReviews, +Add Review button, display probability/impact deltas
    - **Linked Issue** — If linkedIssueId exists, link to Issue detail page
  - Actions: Edit, Delete, Convert to Issue (if status = gerealiseerd), Change Status
  - Status change dropdown with guards (high-risk → beoordeeld requires mitigation)
- [ ] Create component `RiskScoreMatrix` (probability × impact):
  - 5×5 grid, clickable cells to create risk in that score range
  - Show cell color coding (low = yellow, medium = orange, high = red)
  - Modal on click: pre-fill probability + impact, open create form
- [ ] Unit tests: form validation (required fields, min length), score calculation on save
- [ ] Integration test: full CRUD (create, read, update, delete risk)

### Frontend: Mitigation Form (Planix)

- [ ] Create component `MitigatieForm` (inline in Risk detail, Mitigations tab):
  - Fields: actieType (enum), verwachteScoreReductie (1-25), streefDatum, verantwoordelijke (user picker)
  - Task link section: search/select existing Task OR auto-create new
  - On save: create MitigatieActie, handle Task sync (create or update)
  - Submit button: "Add Mitigation" (creates MitigatieActie + Task)
- [ ] Mitigation list (in Risk detail):
  - Display: actieType, verwachteScoreReductie, streefDatum, verantwoordelijke, Task link (clickable), status (derived)
  - Actions: Edit, Delete, View linked Task
  - Highlight overdue mitigations (red background)
- [ ] Unit test: form validation (streefDatum > today, score reduction 1-25)
- [ ] Integration test: create mitigation without task (auto-create), create with existing task

### Frontend: Risk Review Form (Planix)

- [ ] Create component `RiskReviewForm` (modal, triggered from Risk detail):
  - Display previous assessment: `Risk.probability`, `Risk.impact`, `Risk.score`
  - Fields: `nieuweProbability` (1-5), `nieuweImpact` (1-5), `wijzigingsRedenen` (text)
  - Compute new score: `nieuweProbability × nieuweImpact`
  - Show delta: "Probability: 3 → 2 (↓1)", "Impact: 4 → 4 (no change)", "Score: 12 → 8 (↓4)"
  - Submit: "Opslaan en Update Risk" (creates RiskReview, updates Risk)
- [ ] Post-save notification: "Review saved; next review due {date}"
- [ ] Unit test: form validation (probability/impact 1-5), score delta calculation
- [ ] Integration test: review creation triggers Risk property update, volgendeReviewDatum advances

### Frontend: Risk-to-Issue Conversion Modal (Planix)

- [ ] Create component `RiskToIssueModal` (triggered from Risk detail when status = gerealiseerd):
  - Display summary: Risk title, impact → severity mapping
  - Fields: `streefDatum` (date picker for Issue due date)
  - Confirm button: "Converteer naar Issue"
  - On confirm: call conversion service, redirect to new Issue detail page
- [ ] Unit test: modal validation (streefDatum > today)
- [ ] Integration test: conversion creates Issue + Task + links Risk.linkedIssueId

### Frontend: Issue CRUD (Planix)

- [ ] Create page `/issues` (Issue List):
  - Use `CnDataTable` with columns: issueCode, titel, severity, urgentie, status, projectId.naam, toegewezenAan.naam
  - Add filter bar: by status (6 enums), severity (4 enums), urgentie (3 enums)
  - Color-code: severity kritiek = red, hoog = orange, midden = yellow, laag = gray
  - Each row links to `/issues/:id`
- [ ] Create page `/issues/:id` (Issue Detail):
  - Use `CnDetailPage` with tabs:
    - **Overview** — Issue properties
    - **Resolution** — oplossing (text), geleerdeLessen (text), resolutionType (enum)
    - **Related Risk** — If bronRiskId exists, link to originating Risk
  - Actions: Edit, Delete, Change Status
  - Status change logic: on status → `gesloten`, enforce validation (resolutionType, oplossing min 50 chars, geleerdeLessen if severity ≥ hoog)
- [ ] Create page `/issues/new` (Issue Create):
  - Form: titel, beschrijving, severity, urgentie, streefDatum, toegewezenAan
  - Optional: select bronRiskId (if converting from risk, pre-filled)
- [ ] Unit test: form validation (required fields, min length, severity-dependent fields on close)
- [ ] Integration test: create issue, close with resolution (pass/fail validation)

---

## Phase 2: Automation & Reviews (Sprint 3)

### Backend: Escalation Job (REQ-003)

- [ ] Create n8n-nextcloud workflow `escalate-overdue-mitigations`:
  - Trigger: scheduled daily at 07:00 UTC (configurable per org in OpenRegister config)
  - Query: all MitigatieActies where `status in [open, in-progress]` AND `streefDatum < today`
  - For each overdue mitigation:
    - Days overdue = `today - streefDatum`
    - **Level 1 (1-7 days):** notify Risk.eigenaar + risk-officer group
    - **Level 2 (8-14 days):** notify projectleider + risk-officer group
    - **Level 3 (>14 days):** notify programmamanager (if programmaId) OR stuurgroep-owner
    - Update MitigatieActie.status = `overdue`
    - Set Risk.reviewOverdueFlag = true (if Risk.status = gemitigeerd-in-uitvoering)
  - Notification subject template: "Mitigatie vertraagd: {riskCode} — {titel}"
  - Notification body: "Streefsdatum: {date}, Actueel: {today}, Dias vertraagd: {daysOverdue}"
- [ ] Create OpenRegister config schema `escalation-config` (per org):
  - `enabled` (bool), `scheduleTime` (time), `levelThresholds` (days), `notificationRecipients` (groups), `dryRun` (bool for testing)
- [ ] Integration test: overdue mitigation triggers escalation notifications at correct levels
- [ ] Load test: escalation job processes 5000+ overdue mitigations in < 30s

### Backend: Review-Overdue Automation (REQ-004)

- [ ] Create notification job `check-review-dates`:
  - Trigger: daily at 08:00 UTC
  - Query: all Risks where `volgendeReviewDatum` within next 7 days (and not yet reviewed)
  - Send notification: "Risicoherziening vervalt op {volgendeReviewDatum}"
  - Query: all Risks where `volgendeReviewDatum < today` AND no RiskReview since that date
  - Set `Risk.reviewOverdueFlag = true`
  - Log audit entry: "Review overdue flag set by system"
- [ ] Modify Risk detail page to display review-overdue banner if flag is true
- [ ] Unit test: review date notification logic, flag-setting logic
- [ ] Integration test: risk review date notification fires 7 days before, flag sets on overdue

### Backend: Periodic Review Enforcement (REQ-004)

- [ ] Add validation gate: when Risk.status = `gemitigiert-in-uitvoering` for >90 days without review, audit trail shows "REVIEW OVERDUE" marker
- [ ] Portfolio reporting (REQ-007, REQ-008) must filter/highlight overdue risks in separate "At-Risk" section
- [ ] Unit test: review interval enforcement, overdue detection

---

## Phase 3: Portfolio Reporting (Sprint 4)

### Frontend: MyDash Widgets (Portfolio)

- [ ] Create widget `Top-10 Risks (Portfolio)`:
  - Uses `CnTableWidget` or custom component
  - Query: all Risks, sorted by score (descending), limit 10
  - Columns: risicoCode, titel, score, status, projectId.naam, eigenaar.naam
  - Color-code: score ≥ 20 = red, 12-19 = orange, < 12 = yellow
  - Each row clickable → links to Risk detail
  - Scoped to user's organization (RBAC)
- [ ] Create widget `Risk Heatmap (Portfolio)`:
  - 5×5 grid: X-axis = impact (1-5), Y-axis = probability (1-5)
  - Each cell: count of risks + color intensity (darker = more risks)
  - Cell click: drill down to risk list filtered by (probability, impact)
  - Uses ApexCharts or custom SVG
- [ ] Create widget `Risk Exposure Trend (12-month)`:
  - Line chart: X-axis = months (last 12 months), Y-axis = sum of risk scores
  - Secondary line (optional): % of risks realized (status = gerealiseerd)
  - Hover tooltip: count of high-risk (≥12) vs other
  - Uses `CnChartWidget` (ApexCharts)
- [ ] Widget configuration: allow drag-drop onto dashboard, resize
- [ ] Unit test: query performance (< 3s for 1000 risks)
- [ ] Integration test: heatmap drill-down filters risk list correctly

### Backend: Portfolio Query Optimization

- [ ] Add database indexes on Risk table:
  - `(projectId, status)` — for portfolio filtering
  - `(score)` — for top-10 sorting
  - `(volgendeReviewDatum)` — for review-overdue queries
- [ ] Add caching layer (Redis or app-level):
  - Cache top-10 query for 1 hour (invalidate on Risk score/status change)
  - Cache heatmap aggregation for 1 hour
  - Cache trend aggregation for 1 day (less volatile)
- [ ] Load test: top-10 query < 3s, heatmap < 500ms

---

## Phase 4: Compliance & Audit (Sprint 5)

### Deduplication Check

- [ ] Search `openspec/specs/` and `openregister/lib/Service/` for overlap:
  - Risk → verify no existing risk management service (none found)
  - MitigatieActie → verify no existing mitigation tracking (none found)
  - Issue → verify no existing issue tracking (may overlap with incident tracking; cross-link as needed)
  - RiskReview → verify no existing periodic review system (none found)
- [ ] Document findings: confirm 4 new services, no overlap with existing functionality
- [ ] Output: deduplication audit report (committed to change artifacts)

### Compliance Audit

- [ ] Verify NEN-ISO 31000 alignment:
  - Risk identification (REQ-001) ✓
  - Assessment (probability/impact) ✓
  - Mitigation planning (REQ-002) ✓
  - Mitigation implementation (Task integration) ✓
  - Review/monitoring (REQ-004) ✓
  - Escalation (REQ-003) ✓
- [ ] Verify PRINCE2 Risk Theme:
  - Risk register ✓
  - Risk owner assignment ✓
  - Mitigation status tracking ✓
- [ ] Verify IPMA ICB 4.0:
  - Portfolio reporting demonstrates risk management competence ✓
- [ ] Generate compliance pack:
  - Audit trail export (PDF) with change history
  - Risk register snapshot (Excel export)
  - Escalation job log summary
- [ ] CISO sign-off: reviewed compliance pack, confirms alignment

### Seed Data Generation Task

- [ ] Verify seed data in `lib/Settings/planix_register.json`:
  - 3-5 Risk objects (Dutch municipality project context) ✓
  - 3-5 MitigatieActie objects linked to seed Risks ✓
  - 2-3 Issue objects (some from risk conversion, some direct) ✓
  - 1-2 RiskReview objects (showing historical reassessment) ✓
- [ ] Load seed data: run `importFromApp(planix, seedData)` and verify objects created
- [ ] Verify cross-references: MitigatieActio.taakId → existing Task, Issue.bronRiskId → existing Risk
- [ ] Integration test: seed data loads idempotently (re-import skips existing by slug)

---

## Phase 5: Testing & QA (Sprint 6)

### Unit Tests

- [ ] Risk scoring: all 25 combinations (1×1 through 5×5), boundary conditions
- [ ] Validation gates: high-risk without mitigation, missing required fields
- [ ] Task sync: create/update task on mitigation save, task status → mitigation status mapping
- [ ] Score recalculation: review update → Risk probability/impact/score change
- [ ] Risk-to-issue conversion: severity mapping, issue creation, task creation
- [ ] Escalation logic: overdue detection, notification levels, flag setting
- [ ] Review-overdue: notification on approach (7 days), flag on exceeded (0 days)
- [ ] Portfolio queries: top-10 sorting, heatmap aggregation, trend calculation

### Integration Tests

- [ ] Full risk lifecycle:
  - Create risk → auto-calc score → check appetite
  - Create mitigation → auto-create/link task
  - Update task status → mitigation status updates
  - Overdue mitigation → escalation notified
  - Schedule review date → notification sent
  - Create review → Risk score updates, volgendeReviewDatum advances
  - Realize risk → convert to issue → issue resolution
- [ ] Portfolio reporting:
  - Top-10 query returns correct risks sorted by score
  - Heatmap drill-down filters correctly
  - Trend chart aggregates month-by-month
- [ ] Concurrency:
  - Two users simultaneously edit same risk (optimistic lock or last-write-wins)
  - Task update concurrent with mitigation update (transactional sync)

### Browser Testing (Playwright)

- [ ] Risk list page: filters, sorting, row colors, drill-down
- [ ] Risk create: form validation, required fields, score calculation, auto-date-population
- [ ] Risk detail: tabs (Overview, Mitigations, Reviews, Linked Issue), actions (Edit, Delete, Convert, Status Change)
- [ ] Mitigation form: task link, auto-create, validation
- [ ] Risk review form: previous assessment display, new probability/impact input, delta calculation
- [ ] Issue list & detail: status change with validation, resolution form
- [ ] Portfolio widgets: top-10 load, heatmap render, trend chart, drill-down
- [ ] MyDash dashboard: widget drag-drop, resize, refresh

### Performance Testing

- [ ] Risk list (1000 risks): < 2s load time, sorting performance
- [ ] Portfolio top-10: < 3s query, < 500ms render
- [ ] Escalation job: 5000 overdue mitigations in < 30s
- [ ] Heatmap: 500+ risks aggregation in < 500ms
- [ ] Risk detail (20 mitigations + 5 reviews): < 1s load

### Accessibility Testing

- [ ] Keyboard navigation: Risk forms, tabs, modals
- [ ] Screen reader: status labels, color indicators (text backup), buttons
- [ ] Heatmap alt-text: numeric table alternative
- [ ] WCAG 2.1 AA compliance check

---

## Notes

- All OpenRegister services (ObjectService, SearchService, AuditTrailService, etc.) are pre-existing; no custom service implementations needed for core CRUD
- Task integration with Planix TasksController requires webhook implementation (already exists in Planix; this change extends usage)
- Escalation rules stored in OpenRegister config allow per-organization customization without code changes
- Seed data uses realistic Dutch municipality names, addresses, and person names (fictional but believable)
- All dates use `date` type (no time component) to avoid timezone complexity
- Audit trail is automatic via AuditTrailService; no custom logging needed
- Notifications use existing NotificationService (extended with new event types for risk/mitigation/review events)
