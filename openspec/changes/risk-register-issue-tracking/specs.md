# Specifications: Risk Register & Issue Tracking

**Status**: draft  
**Scope**: planix, openregister, mydash, decidesk, n8n-nextcloud  
**OpenSpec changes**: risk-register-issue-tracking (adds risk management system)

## Purpose

Enable gestandaardiseerde project risk management with probability × impact scoring, automated mitigation tracking through Planix Task integration, periodic mandatory reviews, automatic escalation on overdue mitigations, risk-to-issue conversion for materialized risks, and portfolio-level reporting (top-10 risks, heatmaps, trend analysis).

---

## Requirements

### REQ-001: Risk Identification with Mandatory Assessment

**Requirement Type:** Business Logic [MVP]

The system MUST enforce structured risk identification with validated assessment fields.

When a user with role projectleider or risk-officer creates a new Risk, the system MUST:
1. Require minimum fields: `titel` (string), `beschrijving` (≥50 chars), `categorie` (enum), `eigenaar` (user), `probability` (1-5), `impact` (1-5)
2. Auto-calculate `score` = `probability × impact`
3. Auto-set `status` = `"geidentificeerd"` (first state)
4. Auto-set `identificatieDatum` = today
5. Auto-set `volgendeReviewDatum` = today + 90 days
6. Prevent creation if `score ≥ project.risicobereidheid AND status ≠ "beoordeeld"` without explicit mitigation plan (REQ-002)

**Scenario: Create low-risk (below appetite)**
- GIVEN a project with `risicobereidheid: laag` (threshold = 6)
- AND a user with role projectleider
- WHEN the user submits a new Risk with `probability: 1`, `impact: 2` (score: 2)
- THEN the system creates the Risk with `status: geidentificeerd`
- AND allows transition to any state without requiring a MitigatieActie

**Scenario: Create high-risk (above appetite, requires mitigation)**
- GIVEN a project with `risicobereidheid: midden` (threshold = 12)
- AND a user with role risk-officer
- WHEN the user submits a new Risk with `probability: 4`, `impact: 4` (score: 16)
- THEN the system creates the Risk with `status: geidentificeerd`
- AND prevents transition to any status except `"beoordeeld"` until ≥1 MitigatieActie is linked (REQ-002)

**Scenario: Missing required field**
- GIVEN a risk creation form with missing `categorie`
- WHEN the user attempts to submit
- THEN the system rejects with validation error: "Categorie is verplicht"

**Scenario: Short description rejected**
- GIVEN a risk creation form with `beschrijving: "Test"` (4 chars)
- WHEN the user attempts to submit
- THEN the system rejects: "Beschrijving moet minimaal 50 tekens zijn"

---

### REQ-002: Mitigation Action Links to Planix Task

**Requirement Type:** System Integration [MVP]

The system MUST maintain bidirectional synchronization between MitigatieActie and Planix Task.

When a user creates or edits a MitigatieActie with `status: beoordeeld`:
1. If no `taakId` provided: system auto-creates a Planix Task with:
   - `title` = Risk title + " — Mitigatieactie"
   - `description` = MitigatieActie.actieType + "\n" + MitigatieActie.verwachteScoreReductie + " score-reductie expected"
   - `assignedTo` = MitigatieActie.verantwoordelijke
   - `dueDate` = MitigatieActie.streefDatum
   - `linkedResourceId` = `mitigation-{mitigationId}`
   - `linkedResourceType` = "mitigation"
2. If `taakId` provided: system validates Task exists and updates Task:
   - `assignedTo` = MitigatieActie.verantwoordelijke
   - `dueDate` = MitigatieActie.streefDatum
3. System maintains relation: MitigatieActie.taakId ↔ Task.linkedResourceId (bidirectional)
4. On Task status change (via TasksController), system updates MitigatieActie.status (mapped)

**Task Status → Mitigation Status Mapping:**
- Task.status = `open` → Mitigation.status = `open`
- Task.status = `in-progress` → Mitigation.status = `in-progress`
- Task.status = `completed` → Mitigation.status = `completed` AND populate `werkelijkeScoreReductie` workflow
- Task.status = `overdue` → Mitigation.status = `overdue` AND trigger escalation (REQ-003)

**Scenario: Create mitigation without task (auto-create)**
- GIVEN a Risk with `status: beoordeeld` and `score: 16`
- WHEN a user creates a MitigatieActie with `actieType: verminderen`, `verwachteScoreReductie: 8`, `streefDatum: 2026-06-30`, `verantwoordelijke: user-qa-001`
- THEN the system auto-creates a Planix Task with:
  - `title` includes risk title + "— Mitigatieactie"
  - `assignedTo: user-qa-001`
  - `dueDate: 2026-06-30`
- AND stores `taakId` in the new MitigatieActie

**Scenario: Create mitigation with existing task**
- GIVEN an existing Planix Task: `task-2026-567` with `assignedTo: user-dev-alex`
- WHEN a user creates a MitigatieActie with `taakId: task-2026-567`, `verantwoordelijke: user-qa-001`, `streefDatum: 2026-07-15`
- THEN the system updates Task to `assignedTo: user-qa-001`, `dueDate: 2026-07-15`
- AND stores the MitigatieActie.taakId = `task-2026-567`

**Scenario: Task completed, mitigation status updates**
- GIVEN a MitigatieActie with `taakId: task-2026-567`, `status: in-progress`
- WHEN a user marks Task as completed via TasksController
- THEN the system detects task completion webhook
- AND updates MitigatieActie.status → `completed`
- AND triggers workflow to populate `werkelijkeScoreReductie` (user-editable, not auto-set)

---

### REQ-003: Automatic Escalation on Overdue Mitigation

**Requirement Type:** Background Job + Notification [MVP]

The system MUST detect overdue mitigations and escalate notifications following a time-based protocol.

A daily scheduled n8n-nextcloud workflow (time: 07:00 UTC, configurable per org) MUST:
1. Query all MitigatieActies where `status in [open, in-progress, overdue]` AND `streefDatum < today`
2. For each overdue mitigation:
   - **Level 1 (1-7 days overdue):** Send notification to Risk.eigenaar + risk-officer user group
     - Subject: "Mitigatie vertraagd: {risicoCode} — {titel}"
     - Body: "Streefsdatum: {streefDatum}, Actueel: {today}, Dagen vertraagd: {daysOverdue}"
   - **Level 2 (8-14 days overdue):** Send notification to projectleider + risk-officer
   - **Level 3 (>14 days overdue):** Send notification to programmamanager (if `programmaId` set) OR stuurgroep-eigenaar

3. Mark MitigatieActie.status = `overdue` (if not already)
4. Set `Risk.reviewOverdueFlag = true` if underlying Risk status = `gemitigeerd-in-uitvoering` (force manual review)

**n8n Workflow Configuration:**
- Stored in OpenRegister config schema `escalation-rules`
- Configurable: escalation thresholds per risk appetite level, notification recipient groups, subject templates
- Dry-run mode for testing (no actual notifications, log what would be sent)

**Scenario: Single-day overdue, notify owner**
- GIVEN a MitigatieActie with `streefDatum: 2026-05-20` (yesterday)
- AND `status: in-progress`
- AND `Risk.eigenaar: user-pl-mueller`
- WHEN the daily escalation job runs on 2026-05-21
- THEN the system sends notification to user-pl-mueller:
  - Subject includes "Mitigatie vertraagd"
  - Body shows "Dias vertraagd: 1"
- AND updates MitigatieActie.status → `overdue`

**Scenario: Two-week overdue, escalate to PM and risk-officer**
- GIVEN a MitigatieActie with `streefDatum: 2026-05-08` (14 days ago)
- AND `status: in-progress`
- AND `Risk.eigenaar: user-pl-mueller`
- AND `Risk.programmaId: prog-2026-001` (programmaManager: user-pm-johan)
- WHEN the daily escalation job runs on 2026-05-22
- THEN the system sends notifications to:
  - user-pm-johan (programmamanager)
  - All users in risk-officer group
- AND sets `Risk.reviewOverdueFlag = true`

**Scenario: Escalation job respects opt-out**
- GIVEN organization has escalation disabled via config
- WHEN the daily job runs
- THEN no notifications are sent (logged in job history for audit)

---

### REQ-004: Periodic Risk Review Mandatory (90-Day Enforcement)

**Requirement Type:** Lifecycle Management [MVP]

The system MUST enforce periodic risk reassessment with notifications and status penalties.

**Review Trigger Logic:**
1. When Risk.volgendeReviewDatum approaches (within 7 days):
   - System sends notification to Risk.eigenaar: "Risicoherziening vervalt op {volgendeReviewDatum}"
2. When Risk.volgendeReviewDatum is exceeded without review:
   - System sets `Risk.reviewOverdueFlag = true`
   - System shows alert on risk detail page: "REVIEW OVERDUE — last review {daysAgo} days ago"
   - Portfolio reports flag risks with review overdue in separate "At-Risk" section

3. When a RiskReview is created:
   - System checks `nieuweProbability`, `nieuweImpact` against previous values
   - If changed: updates Risk.probability, Risk.impact, Risk.score (recalculated)
   - Sets `Risk.volgendeReviewDatum = review.reviewDatum + 90 days`
   - Clears `Risk.reviewOverdueFlag = false`

**Scenario: Review due in 7 days**
- GIVEN a Risk with `volgendeReviewDatum: 2026-05-29` (today = 2026-05-22)
- AND no RiskReview created since initial identification
- WHEN the review notification job runs
- THEN system sends notification to Risk.eigenaar:
  - Subject: "Risicoherziening vervalt spoedig"
  - Body includes exact date and link to review form

**Scenario: Review overdue, flag set**
- GIVEN a Risk with `volgendeReviewDatum: 2026-05-08` (today = 2026-05-22, 14 days overdue)
- AND no new RiskReview since then
- WHEN a user opens the risk detail page
- THEN the page displays:
  - Red banner: "REVIEW OVERDUE — 14 dagen zonder herziening"
  - CTA button: "Herziening starten"
  - Risk.reviewOverdueFlag = true in data model

**Scenario: Create review, update probability/impact**
- GIVEN a Risk with `probability: 3`, `impact: 4`, `score: 12`, `volgendeReviewDatum: 2026-05-22`
- AND a user (risk-officer) creates a RiskReview with `nieuweProbability: 2`, `nieuweImpact: 4`, `wijzigingsRedenen: "..."`
- WHEN the RiskReview is saved
- THEN the system:
  - Updates Risk.probability → 2, Impact → 4
  - Recalculates Risk.score → 8
  - Sets Risk.volgendeReviewDatum → 2026-08-20 (90 days from review date)
  - Clears Risk.reviewOverdueFlag → false
  - Creates audit trail entry showing old vs new values

**Scenario: No review in 90 days blocks escalation**
- GIVEN a Risk with `reviewOverdueFlag: true` for >90 days
- WHEN portfolio top-10 report is generated
- THEN the risk is flagged with "REVIEW OVERDUE" label
- AND a comment appears: "Risk assessment not validated since {date}; decision authority must review before further mitigation"

---

### REQ-005: Risk-to-Issue Conversion

**Requirement Type:** State Transition + Object Creation [MVP]

The system MUST support converting a realized risk into a tracking Issue with separate resolution workflow.

When a Risk transitions to `status: gerealiseerd`:
1. User may select "Converteer naar Issue" action
2. System creates a new Issue with:
   - `projectId` = Risk.projectId
   - `bronRiskId` = Risk.id (link back to originating risk)
   - `titel` = Risk.titel + " — Issue"
   - `beschrijving` = Risk.beschrijving
   - `severity` = mapped from Risk.impact: impact 1-2 → laag, 3 → midden, 4 → hoog, 5 → kritiek
   - `urgentie` = midden (default)
   - `status` = `open`
   - `meldingsDatum` = Risk.gerealiseerdDatum
   - `gemeldDoor` = Risk.eigenaar
   - `toegewezenAan` = Risk.eigenaar
3. System creates Planix Task for Issue resolution (similar to REQ-002):
   - `title` = "Issue Resolution: {issue titel}"
   - `assignedTo` = Issue.toegewezenAan
   - `dueDate` = Issue.streefDatum (user-set during conversion modal)
4. System closes Risk mitigations (mark status = completed if not already)
5. System links Issue → Risk via `Issue.bronRiskId`

**Scenario: Convert high-impact risk to issue**
- GIVEN a Risk:
  - `risicoCode: RISK-2026-0042`
  - `titel: "Vertraging bouwverlof"`
  - `status: gerealiseerd`
  - `gerealiseerdDatum: 2026-05-20`
  - `impact: 5` (kritiek)
  - `eigenaar: user-pl-mueller`
- WHEN user clicks "Converteer naar Issue"
- AND selects `streefDatum: 2026-06-30` in confirmation modal
- THEN the system:
  - Creates Issue:
    - `issueCode: ISSUE-2026-0101` (auto-generated)
    - `titel: "Vertraging bouwverlof — Issue"`
    - `severity: kritiek` (mapped from risk impact)
    - `meldingsDatum: 2026-05-20`
    - `gemeldDoor: user-pl-mueller`
    - `bronRiskId: RISK-2026-0042`
  - Creates Planix Task: "Issue Resolution: Vertraging bouwverlof — Issue"
  - Links Issue ↔ Task
  - Sets Risk.linkedIssueId = Issue.id
  - Returns Issue detail page with empty `oplossing` field awaiting resolution

**Scenario: User declines conversion**
- GIVEN a Risk with `status: gerealiseerd`
- WHEN user chooses NOT to convert to Issue
- THEN Risk remains with `status: gerealiseerd` and `linkedIssueId: null`
- AND portfolio reporting shows as "realized but not escalated to issue" (informational)

---

### REQ-006: Issue Resolution with Learned Lessons

**Requirement Type:** Workflow State Machine [MVP]

The system MUST enforce resolution validation before closing an Issue.

When a user attempts to change Issue.status → `gesloten`:
1. System MUST enforce required fields based on severity:
   - All issues: `resolutionType` (enum: opgelost, workaround, niet-reproduceerbaar, afgewezen, duplicaat)
   - All issues: `oplossing` (min 50 chars) if resolutionType ≠ duplicaat/afgewezen
   - severity ≥ hoog: `geleerdeLessen` (min 50 chars) mandatory
   - If missing: reject with validation error
2. On successful closure:
   - Set `oplossingsDatum = today`
   - Create AuditTrail entry with resolution snapshot
   - Trigger notification to Issue.goedgekeurDoor (if set) for approval
   - If goedgekeurDoor approves: Issue fully closed; if rejected: revert to `in-behandeling` + notify assignee

**Scenario: Closing high-severity issue without lessons**
- GIVEN an Issue:
  - `severity: hoog`
  - `status: in-behandeling`
  - `resolutionType: opgelost`
  - `oplossing: "Fixed via patch 3.14.2"` (short)
  - `geleerdeLessen: null`
- WHEN user attempts to save status → `gesloten`
- THEN the system rejects:
  - "Geleerdee lessen zijn verplicht voor issues met severity ≥ hoog (min 50 tekens)"

**Scenario: Closing low-severity issue with resolution**
- GIVEN an Issue:
  - `severity: laag`
  - `resolutionType: niet-reproduceerbaar`
  - `oplossing: "Unable to reproduce in testing environment; customer unable to replicate. Likely race condition. Monitoring in production."`
- WHEN user saves status → `gesloten`
- THEN the system:
  - Accepts closure (geleerdeLessen optional for low severity)
  - Sets `oplossingsDatum: today`
  - Creates audit trail
  - If `goedgekeurDoor` is set, sends approval notification

---

### REQ-007: Top-10 Portfolio Reporting

**Requirement Type:** Dashboard Widget [MVP]

The system MUST provide portfolio-level risk visibility across all projects.

When a user with role PMO or portfolio-manager opens the portfolio dashboard (MyDash):
1. System displays widget: "Top-10 Risks (Portfolio)"
   - Lists Risks sorted by score (descending)
   - Scope: all Risks across all projects in organization (unless user has project-scoped RBAC)
   - Columns: `risicoCode`, `titel`, `score`, `status`, `projectId.naam`, `eigenaar.naam`
   - Colors: score ≥ 20 = red, 12-19 = orange, < 12 = yellow
2. System displays widget: "Risk Heatmap (Portfolio)"
   - 5×5 grid: probability (Y-axis) × impact (X-axis)
   - Each cell shows count of risks + color intensity
   - Click on cell to drill down to risk list filtered by (probability, impact)
3. System displays widget: "Risk Exposure Trend (12-month)"
   - Line chart: X-axis = months, Y-axis = sum of all risk scores by month
   - Secondary line (optional): % of risks in `gerealiseerd` status (realization rate)
   - Hover tooltip shows count of high-risk (score ≥ 12) vs other by month

**Scenario: Executive views top-10 risks**
- GIVEN an organization with 47 active risks across 8 projects
- AND a user with role portfolio-manager
- WHEN the user opens portfolio dashboard
- THEN the Top-10 widget displays (in order):
  - RISK-2026-0042 (score 25, red)
  - RISK-2026-0035 (score 24, red)
  - ... (next 8 risks by score)
- AND each row is clickable → links to risk detail

**Scenario: Heatmap drill-down**
- GIVEN a 5×5 risk heatmap with 3 risks at (probability: 5, impact: 4)
- WHEN user clicks that cell
- THEN the system displays a filtered list showing those 3 risks

---

### REQ-008: Mitigation Effectiveness Reporting

**Requirement Type:** Analytics Query [MVP]

The system MUST measure mitigation action success rates and ROI.

When a user with role PMO generates the "Mitigation Effectiveness" report:
1. System queries all completed MitigatieActies where `werkelijkeScoreReductie` is populated
2. System calculates per categorie and per actieType (vermijden, verminderen, overdragen, accepteren):
   - **Metrics:**
     - Count: # of completed actions
     - Avg Expected Reduction: mean `verwachteScoreReductie`
     - Avg Actual Reduction: mean `werkelijkeScoreReductie`
     - Variance: (actual - expected) / expected (%)
     - Total Cost: sum `kosten`
     - Cost per Risk Reduction Point: `sum kosten / sum werkelijkeScoreReductie`
3. System cross-references Issues converted from risks:
   - For each Issue with `bronRiskId`, estimate avoided impact (Euro value)
   - Calculate: Total mitigation cost vs Total impact avoided (rough ROI)
4. System presents as:
   - Table per categorie (7 categories)
   - Barchart: expected vs actual reduction by actieType
   - Narrative insights: "Verminderen actions exceeded expectations by 12%", etc.

**Scenario: Mitigation report for Infrastructure Program**
- GIVEN completed MitigatieActies:
  - Action 1: expected 6, actual 8, cost €25,000
  - Action 2: expected 5, actual 5, cost €15,000
  - Action 3: expected 4, actual 3, cost €8,000
- WHEN PMO generates report filtered to `categorie: planning`
- THEN the report shows:
  - Count: 3 completed actions
  - Avg Expected: 5.0 score reduction
  - Avg Actual: 5.33 score reduction
  - Variance: +6.6% (exceeded by 6.6%)
  - Total Cost: €48,000
  - Cost/Point: €3,000 per score reduction point

---

## Non-Functional Requirements

### Performance
- **Risk list (1000 risks):** < 2s load with filtering/sorting
- **Top-10 portfolio query:** < 3s (cached, invalidation on Risk status/score change)
- **Daily escalation job (5000 risks):** < 30s
- **Risk detail page (20 mitigations, 5 reviews):** < 1s
- **Heatmap rendering:** < 500ms
- **Mitigation effectiveness report (1000 completed actions):** < 5s

### Availability
- Background escalation job must complete without blocking project creation
- Portfolio widgets must gracefully degrade if query exceeds timeout (show cached data + "last updated" timestamp)

### Audit & Compliance
- All Risk/MitigatieActie/Issue/RiskReview changes logged to AuditTrail (AuditTrailService auto-captures)
- Audit export (PDF) must be exportable by CISO for NEN-ISO 31000 compliance pack
- Risk data retention: 5 years post-project closure (legal hold applies)

### Internationalization
- All enums and strings support Dutch (NL) and English (EN) translations (ADR-007)
- Date formatting respects locale (Dutch: DD-MM-YYYY)

### Accessibility
- Risk detail form (CnFormDialog) must be keyboard-navigable
- Score matrix visualization must have text alternative (table view)
- Heatmap must have accessible alt-text per cell count

---

## Acceptance Criteria

- [ ] 4 OpenRegister schemas (Risk, MitigatieActie, Issue, RiskReview) validated against ADR-001
- [ ] All 8 requirements pass scenario testing (REQ-001 through REQ-008)
- [ ] Score calculation verified: probability × impact in range [1, 25]
- [ ] Task integration: bidirectional sync tested (create mitigation → auto-create task, update task status → update mitigation status)
- [ ] Escalation job completes < 30s for 5000 overdue mitigations
- [ ] Portfolio top-10 queries < 3s
- [ ] Risk detail page loads < 1s (with 20 mitigations, 5 reviews)
- [ ] Mitigation effectiveness report generated < 5s
- [ ] All date comparisons (review-overdue, escalation thresholds) use date-only (ignore time)
- [ ] AuditTrail captures all state changes with before/after snapshots
- [ ] Dutch translations provided for all enums, status labels, notifications
- [ ] Accessibility checks pass (WCAG 2.1 AA): keyboard navigation, screen reader compatibility

---

## Notes

- Periodic review enforcement (REQ-004) is critical to NEN-ISO 31000 compliance; audit this monthly
- Escalation rules (REQ-003) must be configurable per organization in OpenRegister config to support variability across municipalities and provinces
- Risk-to-Issue conversion (REQ-005) is unidirectional; converting back to risk is out-of-scope
- Portfolio reporting (REQ-007, REQ-008) assumes all risks have projectId; orphan risks (without project) are filtered from portfolio widgets
