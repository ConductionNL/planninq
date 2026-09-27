# Design: project money, delivery terms, budget against actual cost, portfolio totals and the finance import

## Context

What exists at `de35541`:

- **Delivery terms on the schema.** `project` carries `client` (`lib/Settings/planninq_register.json:530`), `billable` (`:538`), `budgetHours` (`:544`), `budgetAmount` (`:550`), `hourlyRate` (`:556`), `startDate` and `endDate` (`:562-575`), specified by `openspec/specs/project-delivery/spec.md:25-51`. A zero budget means "no budget agreed" and is not rendered as a figure.
- **Nothing sets them.** `src/dialogs/ProjectCreationDialog.vue` has title, description, colour and icon. The Details tab (`src/components/ProjectSettingsSidebar.vue:16-64`) has the same plus the read-only case reference.
- **Display only.** `ProjectListItem.vue:38-48` shows a Billable badge and the budget when set, formatted in euro (`:120-142`). `CnProjectsWidget.vue:248-256` totals `budgetAmount` for the projects leaf that other apps render, through `budgetOf` in `src/integrations/projectScope.js:88`.
- **Time and rates.** `plannedTimeEntry` has `duration` in minutes, `billable`, `hourlyRate` and a denormalised `project` (`planninq_register.json:905-1058`). `projectPhase` has `budgetHours` but no money. Nothing multiplies hours by a rate.
- **Hours are moving.** The open change `plannedtimeentry-reads-humaniqs-hours` moves `date`, `duration`, `user` and the rest to humaniq's `TimeEntry`, and keeps `contractorRef` and `hourlyRate` on `plannedTimeEntry` (`openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md:8-15`).
- **Portfolio page.** `src/views/Portfolio.vue:29-69` has one row per project with members, open and overdue counts. No money.
- **Project key.** `project.key` (`planninq_register.json:524`) is declared and unused. Lane A's `tasks-readable-keys` sets it on create.

## Goals / non-goals

Goals:

- Set the terms that the schema already holds.
- Show budget against commitments, actual cost and forecast, per category and per phase.
- Total it per portfolio.
- Let the finance system fill actual cost and commitments without planninq import code.

Non-goals:

- Invoicing, currencies, budget alerts.

## Decisions

### Decision 1: a Finance tab owns the terms and the figures

A Finance tab at `/projects/:id/finance` joins `ProjectTabs`. Its top block edits the terms: billable, billing model (`none`, `fixedPrice`, `hourly`, a new `project.billingModel`), budget in hours and money, hourly rate and planned dates. Money figures are shown only to the owner, managers and portfolio readers; members and viewers get the tab without amounts, because a budget is not everyone's business.

Alternative considered: put the fields in the Details tab of the settings sidebar. The sidebar is narrow and has no room for the tables below, and the terms belong next to the figures they drive.

### Decision 2: every amount is a `financeLine`

`financeLine` has `project`, `phase` (optional), `category` (one of the admin's `finance_categories`, for example personnel, hired staff, materials, other), `kind` (`budget`, `commitment`, `actual`, `forecast`), `amount`, `date`, `description`, `source` (`manual` or `import`), `externalRef` (the finance system's line id), `projectKey` (the key the line came in with) and `portfolio` (denormalised, see Decision 4).

Budget lines break `project.budgetAmount` down by category and phase. The table on the Finance tab has a row per category and per phase, with the columns budget, commitments, actual, forecast and remaining. Remaining is budget minus actual minus open commitments.

Alternative considered: separate schemas per kind. Four schemas with the same fields would each need their own rights, import mapping and index.

### Decision 3: labour cost is computed, not stored

Booked time is its own row, "Labour (booked time)": the sum of hours times rate, where the rate is the entry's `hourlyRate`, else the project's. It is computed by one helper, `laborCost(projectId)`, from a single equality read on `project`, which the project-delivery spec already guarantees is possible (`openspec/specs/project-delivery/spec.md:80-92`). It is not written as `financeLine` objects, so an edited time entry never leaves a stale cost behind.

When `plannedtimeentry-reads-humaniqs-hours` lands, `laborCost` reads the hours from humaniq's `TimeEntry` and the rate from `plannedTimeEntry`. Nothing else on the Finance tab changes. When humaniq is not installed, the row reads "Hours are not available" rather than zero, which follows that change's rule that a missing owner hides data rather than showing it empty.

### Decision 4: portfolio totals read one aggregation per portfolio

`financeLine.portfolio` is a copy of its project's `portfolio`, written by the listener that `projects-grouping-hierarchy-fields` adds for `portfolioReaders`, whenever a line is created or a project moves. The portfolio finance page at `/portfolio/finance` then asks OpenRegister's aggregation for sums grouped by project and by kind with the single equality filter `portfolio = <id>`, the only filter shape the aggregation endpoint applies (`src/manifest.json:170`). Labour cost is added per project through `laborCost`.

The page has a portfolio picker, a row per project with budget, commitments, actual, forecast and remaining, and a totals row. It sits under Portfolio, as ADR-001 maps `portfolio-dashboard-pmo`.

### Decision 5: the finance import is an integriq mapping onto `financeLine`

Planninq writes no import code. Integriq reads the finance system's export or API and writes `financeLine` objects with `source: 'import'`, the finance line id in `externalRef` and the finance project number in `projectKey`. It resolves `project` by looking up the project whose `key` equals `projectKey`. The pair (`source`, `externalRef`) is unique, so a re-run updates lines instead of duplicating them.

A line whose key matches no project is still written, with `project` empty. Beheer gets a declarative index page "Unmatched finance lines" over `financeLine` with an empty `project`. An admin fixes the project's key or sets the line's project, and the line moves to that project's Finance tab.

Write rights for imported lines go to a Nextcloud group `planninq-finance-import`, which the admin gives to integriq's service account. Imported lines are read-only on the Finance tab; manual lines are editable by managers.

Alternative considered: a planninq import controller with a CSV upload. It would put integration plumbing in planninq, which ADR-001 rule 5 keeps in Beheer through the integration app, and every finance system would need its own parser here.

## Risks / trade-offs

- [The aggregation ignores a filter it cannot apply] -> Only equality on `portfolio` and on `project` is used, and the portfolio page cross-checks its total against the sum of the per-project rows it rendered.
- [Money shown to the wrong people] -> `financeLine` read rights are the owner, managers and portfolio readers, declared on the schema; the tab's amounts are hidden for members and viewers, and a member's GET on `financeLine` returns nothing.
- [An imported line is edited by hand] -> Imported lines carry `source: 'import'` and a property rule that only the import group may change `amount`; the next import would otherwise overwrite the edit.

## Open questions

- Is `project.key` always usable as the finance project number, or does an organisation need a separate `financeNumber`? To be settled with the first municipality that connects its finance system.
