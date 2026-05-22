# Proposal: BBV Programma Tree (Programma → Doel → Indicator)

## Summary

Add a first-class **BBV Programma Tree** data model and visualizations to planix that make the Dutch legal BBV hierarchy (Programma → Doel → Activiteit → Indicator → Meting) the canonical planning and reporting structure. Users can build and navigate the tree interactively, link activiteiten to projects and zaken, track indicators with measurements, aggregate budgets from financeq, and export to legal reporting formats (iV3 XBRL, waarstaatjegemeente CSV, SISA Excel).

This replaces static PDF programmabegrotingen with a living document that updates as projects close, indicators refresh, and budgets are spent.

## Motivation

Dutch decentral overheden (gemeenten, provincies, waterschappen) are legally bound by **Besluit Begroting en Verantwoording (BBV)** to structure annual programmabegrotingen around a programma-hierarchy with doelen (what we want to achieve), activiteiten (what we'll do), indicatoren (how we'll measure), and budgets. Today this hierarchy lives in PDF files and isolated P&C-tools with no link to the operational systems (planix projects, procest zaaktypes, decidesk decisions, financeq budgets) where the actual work happens. The result is the "begroting-realisatie kloof": councillors see the budget in November, then wait a year for the yearbook to learn what was delivered.

This spec adds a first-class BBV tree to planix that becomes the "wat-doen-we-en-waarom" navigational hub, with live links to projects, zaken, decisions, and budgets.

## Affected Projects

- [x] Project: `planix` — Full-stack: six new register schemas (programma, doel, activiteit, indicator, meting, budget_koppeling), tree and Gantt visualizations, cross-app integrations with financeq/decidesk/procest, exports to iV3/CSV/SISA.

## Scope

### In Scope

- **Data model:** Six register schemas aligned to BBV legal requirements:
  - `bbv_programma` — coalition period, status (concept/vastgesteld/in_uitvoering/afgesloten), portefeuillehouder, color, GEMMA taakgebied codes
  - `bbv_doel` — ownership, looptijd, beleidsdoel IRI, status
  - `bbv_activiteit` — responsibility, timeline, links to planix projects/procest zaaktypen/decidesk besluiten
  - `bbv_indicator` — category (output/effect/uitkomst/input), baseline and target, measurement frequency
  - `bbv_meting` — timestamped measurements with source URL and frozen flag for year-end closure
  - `bbv_budget_koppeling` — activity-to-financeq budgetregel link with CBS taakveld code
- **Visualizations:** Collapsible vertical tree + Gantt timeline across coalition period
- **Indicator management:** Register measurements, freeze/unfreeze, trend graphing vs target
- **Budget aggregation:** Live rollup from financeq budgetregels per taakveld/year, variance alerting
- **BBV-verplichte KPI's:** Auto-suggest 39 national standard KPI's based on taakgebied at programma creation
- **Cross-app links:** Programma ↔ raadsbesluit (decidesk), activiteit ↔ project/zaaktype/budget, indicator ↔ metadata
- **Exports:** iV3 XBRL (CBS), waarstaatjegemeente CSV, SISA Excel templates for legal reporting
- **Permissions:** Role-based: `bbv_beheerder` (structure), `bbv_indicator_eigenaar` (measurements), public read-only view
- **Audit trail & snapshots:** Immutable year-end snapshots per begroting-jaar, audit trail for accountant review

### Out of Scope

- Full financial accounting (stays in financeq)
- Raadsvergaderingen and motie-afhandeling (stays in decidesk)
- Zaakafhandeling (stays in procest)
- Configurable BBV reporting paragraphs or indicators (fixed set for this release)
- Historical comparison of multiple program years in single view (single year focus per spec)
- Mobile-optimized tree (desktop/tablet first)

## Approach

Six new register schemas in `planix/bbv-programma-tree/` following GEMMA data model alignment. Validation rules enforce BBV structure (programma.nummer + "." + doel.nummer, taakveld code pattern, measurment outlier detection). Collapsible tree view with expand/collapse, color coding, and status icons. Gantt visualization layered on timeline. Cross-app integrations via REST webhooks (financeq budget change → re-aggregate, decidesk decision → link programma, procest case closed → increment output indicator). Exports use register API to serialize to XBRL/CSV/Excel templates.

## New Dependencies

- **Register system:** Uses Nextcloud register architecture (existing planix-compatible)
- **Date libraries:** Via existing @conduction/vue-utils (no new npm deps)
- **Financeq webhook:** Publish integration for budget change events

## Impact

- **planix frontend:** New routes `/planix/bbv/`, tabs for tree/gantt/budget/indicators, modal dialogs for register management
- **planix backend:** Six new register schemas + validation, export formatters, cross-app webhook listeners
- **financeq integration:** Publish `budgetregel.changed` events on mutation; register reader for programma context
- **decidesk integration:** Reference `raadsbesluit` on programma; publish `programma.vaststeld` event so decidesk can show linked content
- **procest integration:** Link `zaaktype` codes; listen for `zaak.afgesloten` to increment output indicators
- **n8n:** Workflow to poll CBS Statline/Politie data monthly and push to `bbv_meting` API

## Cross-Project Dependencies

- **financeq** — GET endpoint for budgetregels per taakveld+jaar, webhook for realisatie mutations
- **decidesk** — raadsbesluit reference entity, event publish for vaststelling
- **procest** — zaaktype registry codes, event publish for zaak closure
- **mydash** — new widget `bbv-programma-tegel` for bestuurder dashboard
- **docudesk** — integration to generate programmabegroting/jaarrekening PDF from tree
- **openconnector** — optional ETL for legacy P&C-tool imports (LIAS, Pepperflow)

## Risks

### Risk 1: BBV Validation Complexity
**Severity:** Medium — Programmabegroting validation rules (taakveld codes, indicator categories, paragraph alignment) have edge cases. **Mitigation:** Comprehensive validator unit tests; validator warnings (not errors) for advisory checks; VNG-handreiking as reference.

### Risk 2: Financeq Integration Coupling
**Severity:** Medium — Budget rollup depends on financeq API stability and webhook reliability. **Mitigation:** Graceful degradation if financeq unavailable; cache rollup result with TTL; audit trail for discrepancies.

### Risk 3: Year-End Snapshot Immutability
**Severity:** Low — Deep copy of tree per year must remain immutable once frozen. **Mitigation:** Separate snapshot table with foreign keys to historical IDs; audit trail on any post-freeze mutation.

### Risk 4: Export Format Accuracy
**Severity:** High — XBRL/CSV/Excel must pass CBS validation and accountant review. **Mitigation:** Reference implementation against Iv3 test suite; export validation report before download; sample export review by finance controller.

## Rollback Strategy

- Revert six register schema migrations
- Remove routes and UI tabs
- Remove webhook listeners in financeq/decidesk/procest consumers
- Data loss: programma/doel/activiteit/indicator/meting tables drop (no dependent data elsewhere until integration)

## Success Criteria

- [ ] All six register schemas created and validated
- [ ] Tree and Gantt visualizations render 100+ nodes without lag
- [ ] Budget rollup matches financeq totals within rounding error
- [ ] iV3 XBRL export passes CBS validation suite
- [ ] Indicator measurements frozen after year-end snapshot
- [ ] Audit trail exportable for accountant review
- [ ] Public read-only view hides concept data and budget detail
