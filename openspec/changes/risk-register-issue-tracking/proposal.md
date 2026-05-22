# Proposal: Risk Register & Issue Tracking

## Summary

Add a comprehensive risk management system to Planix with gestandaardiseerde probability × impact scoring, automated mitigation tracking through task integration, periodic risk reviews, and automated issue conversion when risks materialize. Enable portfolio-level reporting with top-10 risks, heatmaps, and trend analysis across all projects in the organisatie.

## Motivation

Dutch government project management (PRINCE2, IPMA, agile) mandates structured risk governance with NEN-ISO 31000 compliance. Current practice scattered across Excel files per project, SharePoint lists, and untracked mitigation actions — no escalation on overdue mitigations, no portfolio visibility, no link between risk (toekomstig) and issue (gerealiseerd).

This change consolidates risk management into Planix with:
- Standardized probability × impact scoring (1-5 scales, automated score calculation)
- Mitigation actions as Planix Tasks (bidirectional link tracking progress)
- Automatic escalation when mitigations exceed streefdatum
- Periodic mandatory reviews (RiskReview schema) preventing stale assessments
- Conversion of materialized risks into Issues with separate resolution workflow
- Portfolio-level top-10 risks, heatmaps, and mitigation-effectiviteit reporting

## Affected Projects

- [x] Project: `planix` — Backend (OpenRegister schemas + escalation jobs), Frontend (risk register CRUD, review workflow, issue conversion)
- [x] Project: `mydash` — Dashboard widgets for portfolio top-10 risks, heatmap, trend lines
- [x] Project: `decidesk` — Stuurgroep decision interface for high-impact risks and mitigation budgets
- [x] Project: `openregister` — 4 new schemas (Risk, MitigatieActie, Issue, RiskReview)
- [x] Project: `n8n-nextcloud` — Escalation workflows for overdue mitigations (REQ-003)

## Scope

### In Scope

**Backend (OpenRegister):**
- `Risk` schema: projectId, programmaId, risicoCode, titel, beschrijving, categorie (10 enums), probability/impact (1-5), score (computed), risicobereidheid, eigenaar, status (7 states), dates, linkedIssueId
- `MitigatieActie` schema: riskId, taakId, actieType (4 enums), verwachte/werkelijkeScoreReductie, streefDatum, verantwoordelijke, kosten
- `Issue` schema: projectId, issueCode, titel, beschrijving, bronRiskId, severity/urgentie (4+3 enums), status (6 states), dates, user refs, resolutionType, oplossing, geleerdeLessen
- `RiskReview` schema: riskId, reviewDatum, reviewer, newProbability/Impact, wijzigingsRedenen

**Backend Logic:**
- Automatic risk score calculation (probability × impact)
- Bidirectional Task linking for MitigatieActies (create/update Task on action save)
- Daily escalation job: overdue mitigations → notifications (risk-officer, projectleider, programmamanager)
- Review-overdue automation: RiskReview dates trigger notifications and status flags
- Risk-to-Issue conversion: copy data, generate Issue, create resolution Task

**Frontend (Planix):**
- Risk register CRUD (create, edit, delete, list, detail views)
- Risk scoring matrix (probability × impact visualization)
- MitigatieActie form with Task link picker
- RiskReview workflow (periodic review form, signature/approval)
- Risk-to-Issue conversion modal
- Status-based filtering and sorting

**Dashboard (MyDash):**
- Top-10 risks portfolio widget (score-ranked, drill-through to detail)
- Heatmap widget (probability × impact grid with count bubbles)
- Risk trend line (12-month rolling exposure)

**Compliance:**
- NEN-ISO 31000:2018 (risk management framework)
- NEN-ISO/IEC 27005 (information security risks — informatieveiligheid category)
- PRINCE2 Risk Theme adherence
- IPMA ICB 4.0 (risk management competence)

### Out of Scope

- **Custom risk quantification models** — uses fixed 1-5 scales; probabilistic simulations deferred
- **Advanced analytics** — machine learning for risk prediction deferred
- **External risk feeds** — integration with third-party risk databases deferred
- **Scenario modeling** — "what-if" tools for sensitivity analysis deferred
- **Advanced audit trails** — beyond standard AuditTrailService (handled separately)
- **Budget optimization** — mitigation cost allocation algorithms deferred

## Approach

**Phase 1: Core schemas + integration (Sprint 1-2)**
- Define 4 OpenRegister schemas per ADR-001
- Seed data: 3-5 example objects per schema (Dutch municipality projects)
- Backend: Task integration, score calculation, conversion logic
- Frontend: CRUD interfaces (CnDetailPage + CnFormDialog)

**Phase 2: Automation + reviews (Sprint 3)**
- Escalation job (daily) via n8n-nextcloud
- RiskReview workflow (status guards, date notifications)
- Periodic review enforcement (90-day maximum without review)

**Phase 3: Portfolio reporting (Sprint 4)**
- MyDash widgets (top-10, heatmap, trend)
- DecideSk integration (stuurgroep approval surface)

**Phase 4: Compliance audit (Sprint 5)**
- Audit trail export (NEN-ISO 31000 compliance pack)
- IPMA assessment tool integration (read-only risk process maturity)

## New Dependencies

- No new packages — uses OpenRegister, existing TasksController, n8n-nextcloud for workflows

## Impact

### Data Model Impact
- 4 new OpenRegister schemas (Risk, MitigatieActie, Issue, RiskReview)
- New register: `planix-risk`, `planix-mitigation`, `planix-issue`, `planix-review`
- Cross-reference to Project, Task, User (via OpenRegister relation mechanism)

### Frontend Impact
- New views in Planix: `/risks` (list), `/risks/:id` (detail), `/risks/new` (create)
- New components: RiskForm, RiskScoreMatrix, MitigatieForm, RiskReviewForm, RiskToIssueModal
- New TaskCard integration: show linked-mitigation status

### API Impact
- New REST endpoints: `/projects/{id}/risks`, `/risks/{id}`, `/risks/{id}/reviews`, `/risks/{id}/convert-to-issue`
- New background job triggers: `escalate_overdue_mitigations`, `check_review_dates`

### Cross-Project Dependencies
- **planix base/projects**: Risk entity + register
- **planix tasks**: MitigatieActie bidirectional Task link
- **mydash**: Portfolio widgets
- **decidesk**: High-risk approval interface
- **openregister**: 4 new schemas + migration
- **n8n-nextcloud**: Escalation workflows

## Risks

### Risk 1: Score Calculation Complexity
**Severity:** Medium — **Mitigation:** Fixed 1-5 × 1-5 grid (no probabilistic models). Unit tests for all 25 score combinations.

### Risk 2: Bidirectional Task Sync
**Severity:** Medium — **Mitigation:** Transaction-wrapped create/update (both Risk and Task succeed or both rollback). Audit trail tracks link changes.

### Risk 3: Daily Escalation Job Scalability
**Severity:** Low — **Mitigation:** Batch queries by projectId, use indexed queries on status + volgendeReviewDatum. Monitor job duration in production.

### Risk 4: Review-Overdue False Positives
**Severity:** Low — **Mitigation:** 90-day window includes grace period for non-critical risks. Admin override via status flag bypass.

## Rollback Strategy

- Phase 1: Revert schemas and Planix CRUD (no production data impact yet)
- Phase 2+: Data exists — reverse migration to archive Risk/Issue/Review objects, keep Task orphans for audit trail
- No forced delete — compliance hold applies to risk records for 5 years post-project

## Success Criteria

- [ ] 4 OpenRegister schemas tested with 12+ scenarios per REQ
- [ ] Portfolio top-10 widget renders in < 2s for 1000+ risks
- [ ] Escalation job completes < 30s for 5000+ active risks
- [ ] 100% test coverage for score calculation, conversion logic, date comparisons
- [ ] NEN-ISO 31000 compliance audit completed (CISO sign-off)
