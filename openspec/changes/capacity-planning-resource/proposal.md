# Proposal: Capacity Planning — Resource Scheduling

## Summary

Planix lacks visibility into who has capacity to take on new work. Teams today plan projects with Jira, Excel, and post-its, leading to situations where the same senior developer is 100% allocated across three projects while a junior developer sits at 40% utilization. Capacity Planning introduces a first-class resource layer: configure each person's capacity (hours/week adjusted for FTE, part-time %, vacation, sick leave, overhead); forecast hours per task, sprint, or project; continuously compare actual vs. forecast; and alert early when someone is under- or over-allocated. Skill matching ensures tasks are assigned only to people with the right competencies. Post-project actual hours close the forecast loop and improve planning accuracy.

## Motivation

Resource allocation is the #1 bottleneck in project delivery. Capacity Planning shifts visibility from ad-hoc spreadsheets into Planix, using the same OpenRegister data layer. Teams will answer in real time:
- Who has capacity for new work? (underbooking signals)
- Who is overallocated? (escalate weeks before deadline)
- Do we have someone with the right skills? (skill matching)
- How accurate was our forecast? (forecast-vs-actual analysis)

## Affected Projects

- [x] **Project: planix** — Frontend + backend integration with OpenRegister for resource data; alert rules engine; capacity dashboard

## Scope

### In Scope

- Resource profiles per person (FTE, hours/week, start/end dates, cost/hour, location)
- Skill inventory with proficiency levels (junior/medior/senior/expert) and verification status
- Absence tracking (vacation, sick, training, other) with automatic capacity recalculation
- Weekly capacity calculation: bruto_uren − absence − overhead = netto_beschikbaar
- Forecast allocations per task/project/sprint with skill requirements
- Over/under booking alerts with Ernst (severity) based on overage %
- Absence-conflict detection when leave overlaps with forecasted hours
- Multi-week heatmap (12-week horizon) showing capacity utilization per person (0-50% green, 50-90% yellow, 90-110% orange, >110% red)
- Skill-matching candidate ranking by match score, available capacity, cost
- Forecast-vs-actual analysis for retrospectives (accuracy per skill category)
- What-if scenarios (local mutations, no live data change)
- Privacy controls: resource managers see full data, others see only availability status
- HR system integration via openconnector (import FTE, absence data)

### Out of Scope

- Capacity resource leveling (automatic reallocation) — decision tool, not automated solver
- Integration with financeq billing/throughput (separate change)
- External contractor/ZZP onboarding (purchaseq integration, separate change)
- Mobile app — starts as desktop-only planix feature
- Custom capacity formulas per organization — hardcoded calculation for MVP

## Approach

- Data: 7 new OpenRegister schemas (ResourceProfiel, Skill, PersoonSkill, Afwezigheid, CapaciteitWeek, ForecastAllocatie, WerkelijkUur, AlertSignaal)
- Backend: ConfigurationService imports schemas; ScheduledWorkflowController runs capacity recalc (event-driven + nightly)
- Frontend: New app section "Capacity" with list, heatmap, and detail views; alerts shown in dashboard sidebar
- Skill matching: candidate picker dialog with ranking algorithm
- Scenarios: toggle mode that forks live data for local mutations

## New Dependencies

- None (uses existing OpenRegister, openconnector, @conduction/nextcloud-vue)

## Impact

- App config: 7 new schemas registered
- New views: Capacity → Resource List, Capacity Heatmap, Resource Detail, Absence Manager, Forecast Review, Scenario Mode
- New API: AlertSignal CRUD, capacity-recalc trigger
- Integration: openconnector HR adapter for FTE/absence import
- Database: 7 new OpenRegister object types, 100s of forecast/actual records per project

## Cross-Project Dependencies

- **openregister** — schemas for all resource entities
- **openconnector** — adapters for HR system FTE/absence import (AFAS, Visma, SuccessFactors)
- **financeq** — future integration for cost tracking (out of scope for MVP)
- **purchaseq** — future integration for external capacity (out of scope for MVP)

## Risks

### Risk 1: Capacity calculation accuracy
**Severity:** Medium — **Mitigation:** Seed capacity recalc with realistic test data; unit tests cover all deduction scenarios (vacation overlap, overhead policies); manual QA with HR team to validate import mappings.

### Risk 2: Performance at scale
**Severity:** Medium — **Mitigation:** Heatmap queries indexed on (person, week); alerts table pruned nightly; caching of 12-week heatmap in browser (refresh on mutation only).

### Risk 3: Privacy — sensitive salary data in capacity view
**Severity:** High — **Mitigation:** Field-level RBAC via OpenRegister PropertyRbacHandler; cost_per_hour visible only to resource-manager role or the person themselves; team leads see only "available/limited/full" status (not %).

### Risk 4: Forecast vs actual discrepancy
**Severity:** Low — **Mitigation:** Actual hours booked separately by each person; audit trail tracks changes; weekly reconciliation report for finance review.

## Rollback Strategy

Archive all 7 resource schemas from OpenRegister via ConfigurationService; remove Capacity section from sidebar navigation. No data deletion — historical forecasts/actuals remain for audit. Restore by re-importing schema config.
