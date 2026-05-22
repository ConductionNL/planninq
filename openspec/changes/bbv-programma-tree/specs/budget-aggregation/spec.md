# Budget Aggregation Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds budget aggregation from financeq into programma tree with live rollup and variance reporting

## Purpose

Aggregate budgeted and realized amounts from financeq budgetregels into the BBV tree at activiteit → doel → programma → organisatie levels. Display budget summary tiles and variance alerting.

## ADDED Requirements

### Requirement: Budget Aggregation from Financeq [MVP]
The system MUST fetch budgetregels from financeq and aggregate them by `taakveld_code` and `jaar` to `bbv_budget_koppeling` records, enabling budget rollup.

**Data flow:**
- Financeq endpoint: `GET /api/integrations/planix-bbv/budgetregels?gemeente_id={id}&jaar={year}`
- Returns: array of budgetregels with `taakveld_code`, `bedrag_begroot`, `bedrag_realisatie`
- Planix matches each to activiteit via `bbv_budget_koppeling.taakveld_code` + `jaar`
- Rollup: sum activiteit → doel → programma → organisatie

**Aggregation levels:**
- **Activiteit:** sum of all budget_koppeling's bedrag_begroot/realisatie for activiteit
- **Doel:** sum of activiteiten
- **Programma:** sum of doelen
- **Organisatie:** sum of programma's

#### Scenario: Budget summary on programma
- **GIVEN** programma "07 Mobiliteit" with 18 activiteiten linked to financeq budgetregels
- **WHEN** user opens programma detail in November 2026
- **THEN** budget tile shows:
  - "Begroot 4.250.000 EUR | Gerealiseerd 3.180.000 EUR | Restant 1.070.000 EUR (25%)"
  - Status indicator: green if on-budget, orange if ±10%, red if >10% overrun
  - Breakdown by taakveld (2.1, 8.3, etc.)

#### Scenario: Activiteit-level budget view
- **GIVEN** activiteit "7.3.1 Aanleg fietsstraat" linked to budgetregel 8.3-2026 (bedrag_begroot=485K, bedrag_realisatie=420K)
- **WHEN** user opens activiteit detail
- **THEN** shows budget card: "Begroot 485.000 EUR | Gerealiseerd 420.000 EUR | Restant 65.000 EUR"

### Requirement: Budget Variance Alerting [MVP]
When actual spending diverges from budget by >10%, the system MUST display a warning banner.

**Alert triggers:**
- Realisatie > Begroot × 1.1 → red "10% over budget" warning
- Realisatie < Begroot × 0.9 → orange "10% under budget" (may indicate scope reduction)

#### Scenario: Over-budget warning
- **GIVEN** programma "06 Sport" with begroot 2.5M EUR, realized 2.85M EUR (114% of budget)
- **WHEN** user opens programma detail
- **THEN** orange/red banner: "Spending 14% over budget (2.85M EUR vs 2.5M begroot) — review with finance"

#### Scenario: Under-budget warning
- **GIVEN** activiteit with begroot 100K EUR, realized 80K EUR (80% of budget)
- **WHEN** user opens activiteit detail
- **THEN** orange banner: "Spending 20% under budget — confirm scope still on track"

### Requirement: Financeq Webhook Integration [MVP]
When financeq publishes a `budgetregel.changed` or `realisatie.updated` event, planix MUST re-aggregate budget totals within 5 minutes.

**Event listener:**
- Webhook endpoint: `POST /api/webhooks/financeq/budgetregel-changed`
- Payload: `{ budgetregel_id, gemeente_id, taakveld_code, jaar, bedrag_begroot, bedrag_realisatie }`
- Action: Find all `bbv_budget_koppeling` matching taakveld_code + jaar; update bedrag_realisatie; recalculate rollups

#### Scenario: Live budget update from financeq
- **GIVEN** activiteit "5.1.3" with bedrag_realisatie 420K EUR
- **WHEN** financeq publishes realisatie update: 425K EUR
- **THEN** planix webhook receives event; updates bedrag_realisatie to 425K; recalculates programma rollup within 1 min

### Requirement: Unlinked Realisatie Alerting [MVP]
If financeq reports spending on a taakveld that has no matching `bbv_budget_koppeling` in planix, alert the user.

**Alert:**
- Banner on programma detail: "47.000 EUR realisatie zonder activiteit-koppeling — [Click] bekijk lijst"
- Filterable table showing unmatched budgetregels with options to create/link missing activiteiten

#### Scenario: Unlinked spending discovery
- **GIVEN** financeq reports 47K EUR realized on taakveld 2.1 (Openbare orde)
- **AND** no activiteit in programma "03 Veiligheid" is linked to taakveld 2.1
- **WHEN** programma detail loads
- **THEN** banner appears: "47.000 EUR realisatie zonder activiteit-koppeling"
- **WHEN** user clicks banner
- **THEN** table shows unlinked budgetregel; user can click "Create Activity for 2.1" or "Link to existing activity"

### Requirement: Budget Line Item (Mutatie Grondslag) [MVP]
On each `bbv_budget_koppeling`, a free-text field `mutatie_grondslag` captures the reason/justification for the amount.

This field is used in council budget explanations and jaarrekening notes.

#### Scenario: Budget explanation for council
- **GIVEN** activiteit "7.3.1 Aanleg fietsstraat" with budget_koppeling
- **WHEN** programma budget report is generated for council
- **THEN** shows: "Taakveld 8.3 — 485.000 EUR — LED-verlichting zuidoost; vervanger oude gaskost. Besparing energiekosten ca. 85K EUR/jaar."

### Requirement: Year-over-Year Budget Comparison [MVP]
Timeline view MUST allow switching between budget years (e.g., 2026 vs 2027) to compare projected budgets.

#### Scenario: Compare 2026 vs 2027 budgets
- **GIVEN** multi-year activiteiten with budget_koppeling for 2026 and 2027
- **WHEN** user clicks "Compare Years" and selects 2026 vs 2027
- **THEN** side-by-side view shows budget comparison: "2026: 485K begroot, 420K realisatie | 2027: 510K begroot, — (projected)"

## Non-Functional Requirements

- **Cache & Refresh:** Budget aggregation cached in-memory with TTL=5min; webhook triggers immediate refresh
- **Consistency:** Rollup calculation is idempotent; can be re-run safely without data loss
- **Rounding:** All EUR amounts rounded to nearest 100 EUR for display (no cents in report)
- **Precision:** Internal calculation uses 2 decimal places; rounding only for display
- **Webhook robustness:** Failed webhook delivery retried 3 times with exponential backoff; alerts on persistent failure

## Acceptance Criteria

- [ ] Programma budget tile displays begroot/realisatie/restant with correct amounts
- [ ] Budget status indicator shows green (<10% variance), orange (10-20%), red (>20%)
- [ ] Over-budget and under-budget warnings display on programma/activiteit details
- [ ] Financeq webhook received and processed within 5 minutes
- [ ] Budget aggregation recalculates correctly after webhook
- [ ] Unlinked realisatie alert appears when spending has no matching activiteit
- [ ] Unlinked detail table shows budgetregels with link/create options
- [ ] Mutatie grondslag displays in budget explanation for council
- [ ] Year-over-year comparison renders side-by-side
- [ ] Rounding to 100 EUR applied consistently
- [ ] Webhook retry on failure; alert if persistent

## Notes

- Initial MVP assumes financeq budgetregels are pre-created and linked via taakveld_code matching; no two-way budget creation flow
- Multi-year budget projection may be added in future (currently only actual years with realized spending)
- Budget forecasting (based on YTD spending) may be added in separate planning change
