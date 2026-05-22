# Design: BBV Programma Tree (Programma → Doel → Indicator)

## Summary

Six-register data model implementing the Dutch BBV legal hierarchy with collapsible tree visualization, Gantt timeline, indicator tracking, and budget aggregation. The tree is the primary navigational structure for programma's (4-year coalition period bundles), doelen (strategic goals), activiteiten (operational tasks), and indicatoren (measurement KPI's).

## Architecture

### Register Schemas

#### bbv_programma
Root node of the tree. Represents a major program bundle for one coalition period (e.g. "2026-2030").

**Fields:**
- `uuid` — primary key
- `gemeente_orgaan_id` — FK to organisations; determines scope (gemeente, provincie, waterschap)
- `nummer` — e.g. "01", "03", uniquely identifies program within org + coalition_period
- `titel` — display name (e.g. "Veiligheid")
- `omschrijving` — long description (max 2000 char, BBV-required field)
- `portefeuillehouder_id` — FK users; portfolio owner (wethouder/gedeputeerde)
- `programma_manager_id` — FK users; day-to-day manager
- `coalition_period` — e.g. "2026-2030"; maps to 4-year governance cycle
- `status` — enum: `concept` | `vastgesteld` | `in_uitvoering` | `afgesloten`
- `vastgesteld_door_raad_op` — date when council approved (null until approved)
- `vastgesteld_in_raadsbesluit_id` — FK decidesk raadsbesluit that authorized it
- `kleur` — hex color for tree rendering (e.g. "#FF5733")
- `volgorde` — sort order within gemeente
- `gemma_taakgebied_codes` — array of CBS taakveld codes (e.g. ["0.1 Bestuur", "8.3 Wonen"]) for KPI matching

**Validation:**
- `nummer` unique within `gemeente_orgaan_id` + `coalition_period`
- `kleur` matches hex pattern `^#[0-9A-Fa-f]{6}$`
- If `status = "vastgesteld"`, require both `vastgesteld_door_raad_op` and `vastgesteld_in_raadsbesluit_id` to be non-null

#### bbv_doel
Strategic goal within a programma. Answers "what do we want to achieve?"

**Fields:**
- `uuid` — primary key
- `programma_id` — FK bbv_programma (required, cascade delete)
- `nummer` — e.g. "3.2" (must start with `programma.nummer + "."`)
- `titel` — goal statement (e.g. "Veilige openbare ruimte")
- `omschrijving` — elaboration (BBV field)
- `looptijd_start` — start date
- `looptijd_eind` — end date (goal achievement target)
- `eigenaar_id` — FK users; goal owner
- `status` — enum: `concept` | `lopend` | `behaald` | `niet_behaald` | `vervallen`
- `beleidsdoel_iri` — optional URI link to GEMMA beleidsdoel (for semantic alignment)
- `volgorde` — sort order within programma

**Validation:**
- `nummer` must match regex `^{programma.nummer}\.\d+$` — validator rejects otherwise
- `looptijd_eind` >= `looptijd_start`

#### bbv_activiteit
Operational activity: "what are we going to do to achieve the goal?"

**Fields:**
- `uuid` — primary key
- `doel_id` — FK bbv_doel (required, cascade delete)
- `nummer` — e.g. "3.2.1" (must start with `doel.nummer + "."`)
- `titel` — activity name (e.g. "Aanleg fietsstraat Hoofdstraat")
- `omschrijving` — details
- `start_datum` — activity start (must be >= doel.looptijd_start)
- `eind_datum` — planned completion (must be <= doel.looptijd_eind)
- `verantwoordelijke_id` — FK users; accountability
- `status` — enum: `gepland` | `in_uitvoering` | `gereed` | `uitgesteld` | `geannuleerd`
- `planix_project_ids` — array of FK planix projects; links to execution tasks
- `procest_zaaktype_codes` — array of procest zaaktype codes; links to case output
- `decidesk_raadsbesluit_ids` — array of FK raadsbesluiten that authorized this work
- `volgorde` — sort order within doel

**Validation:**
- `nummer` must match regex `^{doel.nummer}\.\d+$`
- `start_datum` >= `doel.looptijd_start` and `eind_datum` <= `doel.looptijd_eind`

#### bbv_indicator
KPI measurement node: "how do we measure progress toward the goal?"

**Fields:**
- `uuid` — primary key
- `parent_type` — enum: `"doel"` | `"activiteit"` (which level it measures)
- `parent_id` — FK to bbv_doel or bbv_activiteit (required)
- `nummer` — e.g. "3.2.1" within parent scope
- `naam` — indicator name (e.g. "Aantal woninginbraken")
- `categorie` — enum: `"output"` | `"effect"` | `"uitkomst"` | `"input"`
- `bbv_verplicht` — boolean; true if one of the 39 national standard KPI's
- `bbv_kpi_code` — if `bbv_verplicht=true`, code from national KPI set (e.g. "WSJG-001")
- `eenheid` — enum: `"aantal"` | `"percentage"` | `"euro"` | `"fte"` | `"m2"` | `"index"`
- `baseline_waarde` — starting measurement (decimal, can be null for derived indicators)
- `baseline_jaar` — year of baseline (int, e.g. 2025)
- `streefwaarde` — target value at end of period (decimal)
- `streefwaarde_jaar` — target year (int; must be >= `baseline_jaar`)
- `meet_frequentie` — enum: `"maandelijks"` | `"kwartaal"` | `"jaarlijks"`
- `bron` — text description of data source (e.g. "CBS Statline", "Politie Open Data")
- `eigenaar_id` — FK users; person responsible for measurements

**Validation:**
- `streefwaarde_jaar` >= `baseline_jaar`
- If `bbv_verplicht=true`, then `bbv_kpi_code` must be non-null and match a code in `bbv_verplichte_kpis` reference list

#### bbv_meting
Time-stamped measurement of an indicator.

**Fields:**
- `uuid` — primary key
- `indicator_id` — FK bbv_indicator (required)
- `peildatum` — date of measurement (date only, no time component)
- `gemeten_waarde` — measured numeric value (decimal, aligned to `indicator.eenheid`)
- `bron_url` — optional link to source data (e.g. politie.nl/cijfers)
- `toelichting` — optional free-text annotation
- `ingevoerd_door_id` — FK users; who recorded this
- `bevroren` — boolean; true once included in year-end jaarrekening (immutable after)

**Validation:**
- `gemeten_waarde` must be numeric and align to unit (e.g. percentage 0-100)
- Validator warns if value > 3 sigma from historical mean (outlier detection)
- If `bevroren=true`, reject any PATCH/DELETE (read-only)

#### bbv_budget_koppeling
Link from activiteit to financeq budgetregel; aggregates costs.

**Fields:**
- `uuid` — primary key
- `activiteit_id` — FK bbv_activiteit (required)
- `financeq_budgetregel_id` — FK financeq budgetregel (nullable; may be filled post-facto)
- `taakveld_code` — CBS taakveld code (e.g. "2.1", "8.3") in pattern `^\d+\.\d+$`
- `bedrag_begroot` — budgeted amount (decimal EUR)
- `bedrag_realisatie` — actual spending (decimal EUR, mirrored from financeq)
- `jaar` — budget year (int, e.g. 2026)
- `mutatie_grondslag` — free-text reason for amount (budget explanation for councilors)

**Validation:**
- `taakveld_code` must match CBS pattern `^\d+\.\d+$`
- `jaar` must be within `programma.coalition_period`

### Reference Data

Three seed-data registers:

- **cbs_taakvelden** — 76 standard CBS task field codes (from "Iv3 informatie voor derden 2026"); used to tag programma's and budget koppelingen
- **bbv_verplichte_kpis** — 39 national standard KPI's per "Waarstaatjegemeente"; each has code, name, typical unit, taakgebied matches for auto-suggestion
- **bbv_paragrafen** — 7 BBV-required financial sections (lokale heffingen, weerstandsvermogen, onderhoud, financiering, bedrijfsvoering, verbonden partijen, grondbeleid); for future extension

## UI Flows

### Tree View
Collapsible vertical hierarchy:
```
Programma "03 Veiligheid" [color-coded badge, # doelen, # activiteiten, status icon]
├─ Doel "3.1 Veilige stad" [status, looptijd, 4 activiteiten, 2 indicatoren]
│  ├─ Activiteit "3.1.1 Meer politieagenten" [status, timeline, project link, 1 indicator]
│  └─ Activiteit "3.1.2 Cameratoezicht" [status, timeline, 2 indicatoren]
└─ Doel "3.2 Veilige openbare ruimte" [status, 3 activiteiten]
   └─ Activiteit "3.2.1 Schoonmaak buurten" [status, timeline]
```

On each node: expand/collapse chevron, color band, title, count badges, status icon, three-dot menu (edit, delete, add sub-nodes).

### Gantt Timeline
Horizontal timeline spanning coalition period (e.g. 2026-2030):
- Y-axis: grouped by programma → doel → activiteit
- X-axis: months from start to end of coalition period
- Each activiteit = horizontal bar with color (status-dependent: green=gereed, orange=in progress, red=vertraagd)
- Hover card: title, verantwoordelijke, % done (calculated from linked projects)
- Red overlay if eind_datum < today (late)

### Indicators Tab
Table of indicators with columns:
- Naam
- Categorie (output/effect/uitkomst/input)
- Eenheid
- Baseline (waarde / jaar)
- Streefwaarde (waarde / jaar)
- Laatste meting (waarde / peildatum)
- Status vs target (bar chart showing baseline → latest → target)
- Actions: view metingen, add meting, edit

### Budget Tab
Tree-like aggregation:
```
Programma "Veiligheid"
├─ Taakveld "2.1 Openbare orde en veiligheid" — 2026
│  ├─ Begroot: 3.250.000 EUR
│  ├─ Gerealiseerd: 2.180.000 EUR (67%)
│  └─ Restant: 1.070.000 EUR (33%)
└─ Taakveld "2.1" — 2027 [projected]
```

Green/yellow/red status based on % variance.

## Seed Data (Example Objects in Dutch)

### bbv_programma
```json
{
  "id": "uuid-prog-03",
  "gemeente_orgaan_id": "gm-0363",
  "nummer": "03",
  "titel": "Veiligheid",
  "omschrijving": "Een veilige en gezonde woonomgeving waarin inwoners en ondernemers zich vrij en veilig kunnen bewegen.",
  "portefeuillehouder_id": "user-wethouder-01",
  "programma_manager_id": "user-manager-veiligheid",
  "coalition_period": "2026-2030",
  "status": "vastgesteld",
  "vastgesteld_door_raad_op": "2025-11-08",
  "vastgesteld_in_raadsbesluit_id": "rb-2025-189",
  "kleur": "#DC143C",
  "volgorde": 3,
  "gemma_taakgebied_codes": ["2.1 Openbare orde en veiligheid", "0.1 Bestuur"]
}
```

### bbv_doel
```json
{
  "id": "uuid-doel-32",
  "programma_id": "uuid-prog-03",
  "nummer": "3.2",
  "titel": "Veilige openbare ruimte",
  "omschrijving": "Inbraken, overvallen en vernielingen terugdringen door betere straatverlichting en toezicht.",
  "looptijd_start": "2026-01-01",
  "looptijd_eind": "2029-12-31",
  "eigenaar_id": "user-beleidsmedewerker-01",
  "status": "lopend",
  "beleidsdoel_iri": "https://gemma.nl/beleidsdoel/veilige-openbare-ruimte",
  "volgorde": 2
}
```

### bbv_activiteit
```json
{
  "id": "uuid-act-321",
  "doel_id": "uuid-doel-32",
  "nummer": "3.2.1",
  "titel": "Vervangen verouderde straatverlichting zuidoost",
  "omschrijving": "Uitfasering van 120-jaar-oude gaslampen in stadsdeel Zuidoost; vervangen door LED met schemersensor.",
  "start_datum": "2026-03-01",
  "eind_datum": "2026-10-31",
  "verantwoordelijke_id": "user-projectleider-infra",
  "status": "in_uitvoering",
  "planix_project_ids": ["prj-2026-0052"],
  "procest_zaaktype_codes": ["zaaktype-vergunning-werken"],
  "decidesk_raadsbesluit_ids": ["rb-2025-189"],
  "volgorde": 1
}
```

### bbv_indicator
```json
{
  "id": "uuid-ind-321-a",
  "parent_type": "doel",
  "parent_id": "uuid-doel-32",
  "nummer": "3.2.1",
  "naam": "Aantal woninginbraken",
  "categorie": "effect",
  "bbv_verplicht": true,
  "bbv_kpi_code": "WSJG-001",
  "eenheid": "aantal",
  "baseline_waarde": 247,
  "baseline_jaar": 2024,
  "streefwaarde": 180,
  "streefwaarde_jaar": 2029,
  "meet_frequentie": "jaarlijks",
  "bron": "Politie Open Data (CBS Veiligheid)",
  "eigenaar_id": "user-beleidsmedewerker-01"
}
```

### bbv_meting
```json
{
  "id": "uuid-met-ind321a-2025",
  "indicator_id": "uuid-ind-321-a",
  "peildatum": "2025-12-31",
  "gemeten_waarde": 238,
  "bron_url": "https://data.politie.nl/cijfers/2025",
  "toelichting": "Daling van 9 inbraken ten opzichte van 2024; positieve trend gaat door.",
  "ingevoerd_door_id": "user-beleidsmedewerker-01",
  "bevroren": false
}
```

### bbv_budget_koppeling
```json
{
  "id": "uuid-bud-act321",
  "activiteit_id": "uuid-act-321",
  "financeq_budgetregel_id": "fg-2026-8301",
  "taakveld_code": "8.3",
  "bedrag_begroot": 485000,
  "bedrag_realisatie": 420000,
  "jaar": 2026,
  "mutatie_grondslag": "LED-verlichting zuidoost wijken; vervanger oude gaskost. Besparing energiekosten ca. 85K EUR/jaar."
}
```

## Integration Points

- **financeq webhook:** When budgetregel `bedrag_realisatie` updates, re-aggregate activiteit → doel → programma totals
- **decidesk event:** Publish `programma.vaststeld` when status flips to `vastgesteld` so decidesk can link in decision detail
- **procest event:** Listen for `zaak.afgesloten` on subscribed zaaktype codes; increment output indicator count
- **n8n:** Monthly poll CBS Statline + Politie open data APIs; POST new metingen to `bbv_meting` endpoint
