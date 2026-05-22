# Exports Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds export of tree and indicators to legal reporting formats (iV3 XBRL, waarstaatjegemeente CSV, SISA Excel)

## Purpose

Export the BBV tree and measurements to Dutch legal accountability formats: iV3 XBRL (CBS), waarstaatjegemeente CSV (national KPI upload), and SISA Excel (BZK audit template). Exports MUST pass official validation before download.

## ADDED Requirements

### Requirement: iV3 XBRL Export [MVP]
The system MUST export a complete programma tree + measurements as an iV3 XBRL instance conforming to the CBS taxonomy.

**XBRL generation:**
- Input: All programma's, doelen, activiteiten, indicatoren, metingen for a gemeente + year
- Output: XBRL instance document (.xbrl file) with facts, dimensions, concepts per CBS iV3 taxonomy
- Mapping:
  - `bbv_programma` → Begroting Programma (concept)
  - `bbv_doel` → Doel (concept)
  - `bbv_indicator.streefwaarde` → Doelstelling Waarde (concept)
  - `bbv_meting.gemeten_waarde` → Realisatie Waarde (fact with dimension taakveld_code, jaar)
  - `bbv_budget_koppeling.bedrag_begroot` → Begrote Bedrag (fact)
  - `bbv_budget_koppeling.bedrag_realisatie` → Gerealiseerde Bedrag (fact)
- Validation: Generated XBRL run through CBS validator; report errors before download

#### Scenario: iV3 XBRL export
- **GIVEN** boekjaar 2026 closed with 12 programma's, frozen metingen, all budgets reconciled
- **WHEN** financieel beheerder clicks "Exporteer iV3 XBRL"
- **THEN** system generates XBRL instance conforming to CBS 2026 taxonomy; validates; returns download link `iv3-{gemeente-code}-2026.xbrl`
- **WHEN** file downloaded
- **THEN** file ready for upload to CBS Iv3 portal

#### Scenario: XBRL validation error handling
- **GIVEN** export process runs validation
- **WHEN** missing required concept (e.g. missing doel.nummer mapping)
- **THEN** export halts; user sees validation report: "Missing taggable concept for 'Doel 3.2' — ensure beleidsdoel_iri is populated"

### Requirement: Waarstaatjegemeente CSV Export [MVP]
The system MUST export the 39 BBV-verplichte KPI's as a CSV file conforming to waarstaatjegemeente.nl upload format.

**CSV structure:**
- Columns: `kpi_code, kpi_naam, peildatum, gemeten_waarde, eenheid, bron_url`
- Rows: All `bbv_indicator` with `bbv_verplicht=true` and their latest `bbv_meting`
- One row per KPI per year
- Format: UTF-8, semicolon-delimited, Dutch decimal (comma)

#### Scenario: Export KPI's to waarstaatjegemeente
- **GIVEN** 2026 year-end with 39 BBV-verplichte KPI's, all with measurements
- **WHEN** user clicks "Exporteer Waarstaatjegemeente CSV"
- **THEN** file generated: `waarstaatjegemeente-{gemeente-code}-2026.csv`
- **Example row:**
  ```
  WSJG-001;"Aantal woninginbraken";2026-12-31;238;"aantal";https://data.politie.nl/cijfers/2026
  ```

### Requirement: SISA Excel Export [MVP]
The system MUST export budget and realisatie data as SISA (Single information, Single audit) Excel template conforming to BZK specification.

**SISA template:**
- Input: All activiteiten with budget_koppeling for selected year
- Output: Excel workbook with sheets per paragraaf + index sheet
- Columns: Activity ID, Activity Title, Taakveld Code, Begroot (EUR), Realisatie (EUR), Variance (EUR), Variance %
- Formatting: BZK-specified font, color scheme, formulas for variance calculation
- BZK compliance check before download

#### Scenario: SISA Excel export for audit
- **GIVEN** boekjaar 2026 with all activiteiten budgeted
- **WHEN** finance team clicks "Exporteer SISA Excel"
- **THEN** system generates Excel with tabs for each paragraaf (lokale heffingen, weerstandsvermogen, etc.)
- **WHEN** file downloaded
- **THEN** ready for submission to BZK/accountant in audit process

### Requirement: Snapshot Export [MVP]
The system MUST export a frozen snapshot of the entire tree as-of year-end as a downloadable JSON or Excel document (for archive).

**Snapshot includes:**
- All programma's, doelen, activiteiten, indicatoren, metingen as-of snapshot date
- Audit metadata: who froze, when, which raadsbesluit approved final structure
- Immutable copy for historical reference

#### Scenario: Download year-end snapshot
- **GIVEN** begroting 2026 frozen on 2027-02-15 after jaarrekening approval
- **WHEN** user clicks "Download Snapshot 2026"
- **THEN** file generated: `bbv-snapshot-2026-{gemeente}-{timestamp}.json`
- File contains complete tree state for future diff against 2027 draft

### Requirement: Export Validation Report [MVP]
Before any export is delivered, a validation report MUST be shown to user.

**Report format:**
- Summary: # programma's, # doelen, # activiteiten, # indicatoren, # metingen
- Validation checks:
  - [ ] All programma's vastgesteld (or explicit approval to include concept)
  - [ ] All doelen have looptijd (no nulls)
  - [ ] All activiteiten linked to at least one doel
  - [ ] All indicatoren have baseline or streefwaarde defined
  - [ ] All metingen frozen (or explicit approval to include unfrozen)
  - [ ] No missing required XBRL mappings
  - [ ] Budget totals match financeq reconciliation
- Warnings (non-blocking):
  - [ ] Indicators with no metingen for 2+ years
  - [ ] Activiteiten without linked projects (may be intentional)
  - [ ] Budget variance > 20%
- Errors (blocking): Missing required XBRL concepts, unmapped taakveld codes

#### Scenario: Validation report before export
- **GIVEN** user initiates iV3 XBRL export for 2026
- **WHEN** validation runs
- **THEN** report shows:
  ```
  ✅ 12 programma's vastgesteld
  ✅ 47 doelen with looptijd
  ⚠️  3 activiteiten without linked projects (review?)
  ✅ 39 verplichte KPI's with latest meting
  ✅ Budget reconciled with financeq (variance 0.8%)
  → Export ready to proceed?  [Download] [Cancel]
  ```

### Requirement: Export Audit Trail [MVP]
Financeq beheerder MUST be able to export an audit trail of all programma tree changes during a year, for accountant review.

**Audit trail export:**
- Format: PDF with chronological log of all mutations
- Content per mutation: Date, User, Action (added/deleted/modified node type), Old Value, New Value, Associated Raadsbesluit (if applicable)
- Sorting: By date, filterable by user/node-type/action
- Integrity: Signed with SHA-256 hash for authenticity

#### Scenario: Accountant requests audit trail
- **GIVEN** boekjaar 2026 audit starts
- **WHEN** accountant requests audit trail for programma tree
- **WHEN** finance beheerder clicks "Exporteer Audit Trail 2026"
- **THEN** PDF generated with all 287 mutations during 2026 (added doelen, changed activiteit status, etc.)
- PDF includes integrity hash

## Non-Functional Requirements

- **Performance:** Export with 1000+ activiteiten completes within 10 seconds; streaming to avoid timeout
- **File size:** XBRL < 10MB, CSV < 2MB, Excel < 5MB for typical gemeente
- **Charset:** All exports UTF-8 with BOM (for Excel import in non-English Windows)
- **Validation:** CBS XBRL validation run locally (offline) before delivery; BZK SISA template compliance checked
- **Accessibility:** PDF audit trail searchable and tagged for screen readers
- **Retention:** Exported files NOT stored on server; generated on-demand; no persistent export history

## Acceptance Criteria

- [ ] iV3 XBRL export generates valid XML conforming to CBS 2026 taxonomy
- [ ] XBRL validation report shows all required concepts and no errors before download
- [ ] Waarstaatjegemeente CSV contains all 39 KPI's with latest measurements
- [ ] CSV format matches waarstaatjegemeente.nl upload specification (semicolon, comma decimals)
- [ ] SISA Excel workbook includes all sheets, formulas, BZK formatting
- [ ] SISA variance calculations correct (Realisatie - Begroot)
- [ ] Snapshot export captures tree state accurately as JSON or Excel
- [ ] Validation report shows summary, checks, warnings, and errors
- [ ] Blocking errors prevent export; warnings allow proceeding
- [ ] Audit trail export includes all mutations with user/date/change details
- [ ] Audit trail PDF signed with SHA-256 hash for integrity
- [ ] Export with 1000+ activiteiten completes within 10 seconds
- [ ] All files UTF-8 encoded with BOM

## Notes

- CBS XBRL taxonomy updates yearly; export tool MUST be updated in Q1 each year to match new taxonomy version
- BZK SISA template also updates yearly; coordination with finance team needed for new template adoption
- Future iteration may add scheduled/automated export (e.g., weekly CSV to waarstaatjegemeente.nl portal)
- Excel exports may include charts (budget vs realisatie bar chart) in future iteration
