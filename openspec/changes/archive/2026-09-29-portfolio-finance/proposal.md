---
kind: code
---

# Project money: delivery terms, budget against actual cost, portfolio totals and the finance import

## Why

The `project` schema already carries the delivery terms: `billable`, `budgetHours`, `budgetAmount` and `hourlyRate` (`lib/Settings/planninq_register.json:538-561`), specified in `openspec/specs/project-delivery/spec.md:25-51`. No planninq screen sets them. `ProjectCreationDialog.vue` asks for title, description, colour and icon, and the Details tab of the settings sidebar adds nothing more (`src/components/ProjectSettingsSidebar.vue:16-64`). The values show up as badges when an import or the API has set them (`src/components/ProjectListItem.vue:38-48`), and only then.

A project manager cannot compare the budget with what the project costs. Time is booked with a duration and a rate (`plannedTimeEntry`), but nothing multiplies them, and there is no place for other costs, commitments or a forecast. The Portfolio page (`src/views/Portfolio.vue:29-69`) counts members and tasks and has no money column. There is no link with the finance system: `project.key` is declared (`planninq_register.json:524`) and used nowhere, and spend has no property to land in.

Gemeente Sittard-Geleen asks for all of it in tender 365739 (https://www.tenderned.nl/aankondigingen/overzicht/365739): financial tables per project, per cost category and per phase, totalled per portfolio (requirements 3998, 3999, 4000), and an automated link with the financial system on the project number (requirements 4001, 4023, 4127). OpenProject tracks a budget against booked time and costs.

Parity rows: `prj-delivery-terms`, `prt-budget`, `prt-portfolio-finance`, `int-finance-import` in planninq's `openspec/parity/capabilities.json`.
Decision: build. The delivery terms are stored but cannot be set, which is a core projects gap. The budget against actual cost is the first half of the tender's financial tables, so it is specified in the same change. The portfolio totals and the finance import answer the tender.

## What changes

- A project manager sets the delivery terms on a Finance tab: billable or not, fixed price or hourly, budget in hours and money, hourly rate, and planned dates.
- The Finance tab compares budget with commitments, actual cost and forecast, per cost category and per phase, with time booked on the project counted as labour cost.
- A project manager adds cost lines by hand. The finance system adds them automatically through integriq, matched on the project key.
- Lines from the finance system that match no project wait in a list in Beheer until an admin fixes the key.
- A Portfolio sub-page totals budget, commitments, actual cost and forecast across the projects of a portfolio.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-delivery-terms`, `prt-budget`, `prt-portfolio-finance`, `int-finance-import`.

### `prj-delivery-terms`: Record a project's delivery and billing terms, such as budget and fixed price or hourly billing.

- Area `projects`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "lib/Settings/planninq_register.json declares billable/budgetHours/budgetAmount/hourlyRate on the project schema. They are read-only displayed in src/components/ProjectListItem.vue:41,127,138 and src/integrations/CnProjectsWidget.vue:45-52 (badges), but neither src/dialogs/ProjectCreationDialog.vue nor src/components/ProjectSettingsSidebar.vue (Details tab, lines 16-64) exposes any field to set them."
- Defect: "src/components/ProjectSettingsSidebar.vue:16-64 Details tab has no fields for billable/budgetHours/budgetAmount/hourlyRate"
- Note: "the fields exist and are shown when already set (presumably via import/direct API), but the app itself provides no way to record them, which is the capability the row asks about."
- Demand: none recorded on the row.
- Competitors rated yes: none.

### `prt-budget`: Track a project's budget against actual cost.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "lib/Settings/planninq_register.json:544-561 project.budgetHours/budgetAmount/hourlyRate exist as schema properties; no planninq form sets them (src/dialogs/ProjectCreationDialog.vue, src/components/ProjectSettingsSidebar.vue have no budget field); src/integrations/CnProjectsWidget.vue:17-19,47-51 only DISPLAYS budgetAmount on the leaf in other apps"
- Note: "Budget is stored and shown (on the cross-app projects leaf) but never set from a planninq screen and never compared with logged time or its cost."
- Demand: none recorded on the row.
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/budgets/README.md:15 'If you log time or costs to this work package the costs will booked to this budget and show the percentage spent for a project budget' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/budgets/config/routes.rb:31-37 budgets; modules/budgets/app/models/budget.rb:120-123 budget_ratio and :150-151 spent from material and labor; modules/budgets/app/views/budgets/show.html.erb:47-48 spent ratio bar and :79 'Spent'"

### `prt-portfolio-finance`: See budget, spend, commitments and forecast totalled across all projects of a portfolio.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Budget is schema plus display only: lib/Settings/planninq_register.json:544-561 budgetHours/budgetAmount/hourlyRate, shown on src/components/ProjectListItem.vue:44-49 and totalled in src/integrations/CnProjectsWidget.vue:254-256 (a leaf rendered on OTHER apps' pages). No planninq page sets a budget, and spend, commitments and forecast have no property at all. The Portfolio page (src/views/Portfolio.vue:29-69) carries no money column."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirements 3998, 3999, 4000 (financial tables per cost category and phase, totals at portfolio level)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

### `int-finance-import`: Import actual spend and commitments from the finance system into each project by its project number.

- Area `integration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No finance import and no project number to match on: project.key is declared at lib/Settings/planninq_register.json:524 and unused in src/ and lib/. lib/Service/RegisterImportService.php loads planninq's own register JSON (schemas and seed objects), not external spend. Spend and commitments have no schema property to land in."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirements 4001, 4023, 4127 (automated link with the financial system, project number as key)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

## Scope

### In scope

- Screens that set the existing delivery terms, plus a billing model.
- A `financeLine` schema for budget, commitment, actual and forecast amounts per category and phase.
- Labour cost from booked time.
- The Finance tab, a portfolio finance page, and the unmatched lines list.
- The contract integriq writes to. The mapping itself lives in integriq.

### Out of scope

- Import code in planninq. An external import runs through integriq, not planninq code (ADR-001 rule 5: plumbing in Beheer, results inline).
- Invoicing and sending bills.
- Currencies other than euro. `budgetAmount` is described as the reporting currency, and every figure uses it.
- Budget alerts (Enterprise in FEATURES.md, "Overtime / budget alerts").

## Impact

- Schema: `project` gains `billingModel`; a new `financeLine` schema with a denormalised `portfolio`.
- Settings: `finance_categories` in Beheer.
- Views: a Finance tab (`/projects/:id/finance`) in `ProjectTabs`, `src/dialogs/FinanceLineDialog.vue`, a portfolio finance page (`/portfolio/finance`), and an unmatched lines index in Beheer.
- Store: a `laborCost(projectId)` helper that reads booked time and rates in one place.
- Extends the capability folder `project-delivery` and the flat spec `openspec/specs/portfolio-dashboard-pmo.md`.
- Depends on: `tasks-readable-keys` (lane A) for the project key the import matches on. `projects-grouping-hierarchy-fields` for the portfolio and the listener that keeps `portfolio` in step. `projects-overview-logs-risks` for `ProjectTabs`. `projects-members-and-roles` for the manager role.

## Risks

### Risk 1: hours are moving to humaniq

**Severity**: High
**Mitigation**: The open change `plannedtimeentry-reads-humaniqs-hours` moves `duration`, `date` and `user` to humaniq's `TimeEntry` and keeps `hourlyRate` on `plannedTimeEntry`. Labour cost is read by one helper, `laborCost`, which is the only code that changes with that move. When humaniq is absent after the move, the labour row says "Hours are not available" instead of showing zero.

### Risk 2: two budgets disagree

**Severity**: Medium
**Mitigation**: `project.budgetAmount` stays the agreed total. Budget lines per category are a breakdown of it, and the Finance tab shows any difference as "Not yet assigned to a category" rather than picking one of the two.

### Risk 3: a finance number is not a short key

**Severity**: Medium
**Mitigation**: The import matches on `project.key`, which `tasks-readable-keys` sets. When an organisation's finance numbers do not fit that format, lines stay unmatched and visible in Beheer. Whether a separate finance number is needed is an open question in design.md.

### Risk 4: another schema changes the schema count

**Severity**: Low
**Mitigation**: Task 1.4 updates `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72`.
