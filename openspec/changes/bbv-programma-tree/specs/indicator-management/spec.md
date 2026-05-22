# Indicator Management Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds indicator registration, measurement tracking, and BBV KPI auto-suggestion

## Purpose

Allow users to register indicators against doelen and activiteiten, track measurements over time with trend graphing, and auto-suggest BBV-verplichte KPI's (39 national standard indicators) based on programma's GEMMA taakgebied codes.

## ADDED Requirements

### Requirement: Indicator Registration and Measurement [MVP]
The system MUST allow registering indicators against a doel or activiteit and recording timestamped measurements.

**Indicator fields (all required unless noted):**
- `naam` — display name (e.g. "Aantal woninginbraken")
- `categorie` — enum: output | effect | uitkomst | input
- `eenheid` — enum: aantal | percentage | euro | fte | m2 | index
- `baseline_waarde` (optional) — starting measurement
- `baseline_jaar` (optional) — year of baseline
- `streefwaarde` — target value at end of goal period
- `streefwaarde_jaar` — target year
- `meet_frequentie` — enum: maandelijks | kwartaal | jaarlijks
- `bron` — data source description (e.g. "CBS Statline")
- `bbv_verplicht` (read-only after creation) — true if matches national KPI set

**Measurement fields (on `bbv_meting`):**
- `peildatum` — date of measurement
- `gemeten_waarde` — numeric value in indicator's unit
- `bron_url` (optional) — link to source data
- `toelichting` (optional) — annotation
- `bevroren` (read-only) — true once included in year-end jaarrekening

#### Scenario: Create indicator under doel
- **GIVEN** doel "3.2 Veilige openbare ruimte" with no indicators
- **WHEN** user clicks "Add Indicator" in doel detail
- **THEN** form opens with fields: naam, categorie, eenheid, baseline (value/year), streefwaarde (value/year), meet_frequentie, bron

#### Scenario: Register measurement
- **GIVEN** indicator "3.2.1 Aantal woninginbraken" with streefwaarde 120/jaar, bron "Politie Open Data"
- **WHEN** user clicks "Add Measurement" and enters peildatum=2026-12-31, waarde=147, source_url=politie.nl/...
- **THEN** measurement saves; trend graph updates; system warns "23% above target — consider jaarrekening note"

#### Scenario: Freeze measurement for year-end
- **GIVEN** measurement from 2025-12-31 with `bevroren=false`
- **WHEN** concept controleer clicks "Freeze measurements for 2025 jaarrekening"
- **THEN** all measurements with peildatum <= 2025-12-31 get `bevroren=true` and become read-only

### Requirement: Measurement Immutability [MVP]
Once a measurement is frozen (`bevroren=true`), it MUST be immutable.

#### Scenario: Edit frozen measurement fails
- **GIVEN** measurement with `bevroren=true` (in 2025 jaarrekening)
- **WHEN** user attempts PATCH `/api/objects/planix/bbv_meting/{id}` with new value
- **THEN** API returns 409 Conflict: `{"error": "meting_bevroren", "message": "Meting opgenomen in vastgestelde jaarrekening, niet meer wijzigbaar"}`

### Requirement: Trend Visualization and Variance Alerting [MVP]
The system MUST display a trend graph of historical measurements vs baseline and streefwaarde, and alert when latest measurement diverges significantly.

**Trend graph displays:**
- X-axis: peildatum (timeline)
- Y-axis: waarde (measurement scale)
- Baseline value as horizontal line
- Streefwaarde as horizontal line (or ramped line if linear target)
- Actual measurements as connected line plot (sparkline)
- Green zone if trending toward streefwaarde, red zone if away

**Variance alerts:**
- If latest measurement > streefwaarde by > 10%, yellow warning: "X% above target"
- If latest measurement < baseline and target is higher, red warning: "Regressing toward baseline"

#### Scenario: Measurement above target triggers warning
- **GIVEN** indicator with baseline 100, streefwaarde 80
- **WHEN** new measurement registered with waarde 95 (outlier, > 10% from target)
- **THEN** system shows warning: "95 is 18% above streefwaarde (80) — consider mitigation actions"

#### Scenario: Trend graph renders
- **GIVEN** indicator with 12 months of measurements
- **WHEN** user opens indicator detail
- **THEN** trend graph shows sparkline of all measurements, baseline line, streefwaarde line, green/red zones

### Requirement: BBV Verplichte KPI Auto-Suggestion [MVP]
When creating a new programma with `gemma_taakgebied_codes`, the system MUST suggest relevant BBV-verplichte KPI's from the national set (39 standard indicators).

**Auto-suggestion logic:**
- Match programma's `gemma_taakgebied_codes` against `bbv_verplichte_kpis` reference list
- Suggest all KPI's with matching taakgebied
- Show suggested KPI's in a modal with: code, naam, typical eenheid, baseline (if available from prior years)
- Pre-check all suggestions; user can uncheck to exclude
- "Bulk add" button creates all checked KPI's as indicators on the programma's doelen (distribute across doelen based on semantic grouping)

#### Scenario: KPI suggestion on programma creation
- **GIVEN** new programma "Sociaal Domein" with taakgebied_codes ["6.71 Maatwerkdienstverlening 18+", "6.72 Maatwerkdienstverlening 18-"]
- **WHEN** user clicks "Suggest required KPI's"
- **THEN** modal shows 7 KPI's:
  - WSJG-051 "Wmo-cliënten per 1000 inwoners" (aantal)
  - WSJG-052 "Jeugdhulp-trajecten" (aantal)
  - ... (pre-checked)
- **WHEN** user clicks "Bulk add"
- **THEN** system creates indicators on the programma (distributed to first doel, or to new "KPI's" grouping-doel if none exist)

#### Scenario: Filter KPI by taakgebied
- **GIVEN** suggestion modal with 39 available national KPI's
- **WHEN** user enters filter "Wmo" in search box
- **THEN** list narrows to 3 KPI's related to Wmo (Maatwerkdienstverlening)

### Requirement: Indicator Import from Prior Years [MVP]
When starting a new coalition period programma, users MUST be able to import indicator definitions (not measurements) from prior-year programma's with same nummer.

#### Scenario: Import indicators from 2022-2025 to 2026-2030
- **GIVEN** programma "05 Onderwijs" for 2022-2025 with 8 registered indicators
- **WHEN** user creates new programma "05 Onderwijs" for 2026-2030 and clicks "Import from prior programma"
- **THEN** system shows prior programma's indicators, user selects which to import, form pre-populates with naam/categorie/eenheid; user updates baseline_waarde/streefwaarde for new period

## Non-Functional Requirements

- **Performance:** Trend graph with 60+ measurements MUST render within 500ms
- **Data validation:** `gemeten_waarde` validated against `eenheid` (percentage 0-100, etc.); outlier warning if > 3 sigma from historical mean
- **Accessibility:** Trend graph alt-text describes latest value and direction; color + text used for alerts (WCAG 1.4.1)
- **Internationalization:** KPI names, units, alert messages translated per ADR-007 (Dutch/English)

## Acceptance Criteria

- [ ] Indicator form accepts all required fields (naam, categorie, eenheid, streefwaarde)
- [ ] Measurement registration accepts peildatum, waarde, source URL, toelichting
- [ ] Trend graph renders with baseline, streefwaarde, and actual measurements
- [ ] Variance alerts display when measurement > 10% from streefwaarde
- [ ] Frozen measurements (bevroren=true) reject PATCH with 409 Conflict
- [ ] BBV KPI suggestion modal shows matched KPI's based on programma's taakgebied
- [ ] Bulk add KPI's creates indicators on programma
- [ ] KPI filter narrows list correctly
- [ ] Prior-year indicator import pre-populates definition fields
- [ ] Outlier detection warns if measurement > 3 sigma from mean
- [ ] Trend graph with 60+ measurements renders within 500ms

## Notes

- Initial MVP does not support custom KPI definitions; all indicators inherit from national set or user defines one-off
- Measurement import/export to Excel for bulk update may be added in future (currently web form only)
- Automated data refresh from CBS Statline / Politie API via n8n is separate integration, outside this spec
