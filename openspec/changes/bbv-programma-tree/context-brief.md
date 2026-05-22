---
status: draft
---
# BBV Programma Tree (Programma → Doel → Indicator)

## Purpose

Dutch decentral overheden (gemeenten, provincies, waterschappen) are legally bound by the **Besluit Begroting en Verantwoording provincies en gemeenten (BBV)** to structure their annual programmabegroting and jaarrekening around a hierarchy of **programma's → doelen (wat willen we bereiken) → activiteiten (wat gaan we daarvoor doen) → indicatoren (hoe meten we het) → middelen (wat mag het kosten)**. Today this hierarchy lives in PDF programmabegrotingen, Excel-trees inside finance suites (iV3, Pepperflow, LIAS), and standalone P&C-tools that don't talk to the operational systems where the actual work happens. The result is the well-known "begroting-realisatie kloof": councillors see what was budgeted in November, then wait twelve months for the jaarrekening to learn what was delivered, with no live link to the projects (planix), zaken (procest), or raadsbesluiten (decidesk) that should be executing on those doelen.

This spec adds a first-class **BBV Programma Tree** to planix that makes the BBV hierarchy itself the navigational and planning structure of the app. Every programma is a node; every doel and (sub-)activiteit hangs underneath; every indicator records its baseline, target, and most recent measurement; every leaf can be linked to one or more planix projects/tasks, decidesk raadsbesluiten, procest zaaktypes, and financeq budget lines. The tree is the canonical "wat-doen-we-en-waarom" view of the organisation, replacing the static programmabegroting PDF with a living document that updates as projects close, indicators refresh, and money is spent.

The spec covers (a) the data model — Programma, Doel, Activiteit, Indicator, Meting, BudgetKoppeling — aligned to BBV-verplichte velden plus the GEMMA-aligned begrip "beleidsdoel"; (b) two visualisations — a collapsible vertical tree and a Gantt-style timeline that lays activiteiten across the four-year coalition period; (c) the cross-app koppelingen to decidesk (raadsbesluit autoriseert programma-wijziging), procest (zaaktype levert output van activiteit), financeq (budgetregel financiert activiteit), mydash (programma-tegel op bestuurder-dashboard); (d) the verplichte BBV-indicatoren set (the 39 indicatoren uit "Besluit BBV waarstaatjegemeente" — kpi's als jeugdwerkloosheid, woninginbraken, demografische druk — die elke gemeente jaarlijks moet opnemen); en (e) een rapportage-export naar iV3 / SISA / waarstaatjegemeente.nl formats zodat de tree ook als bron voor de wettelijke verantwoordingsketen kan dienen.

Out of scope for this spec: full **financiële boekhouding** (dat blijft financeq), **raadsvergaderingen en motie-afhandeling** (decidesk), **operationele zaakafhandeling** (procest). Deze spec orchestreert hun output naar de BBV-hierarchie, ze blijven authoritative bron.

## Data Model

Six register schemas in `planix/bbv-programma-tree/`:

**bbv_programma**: `uuid`, `gemeente_orgaan_id` (FK organisations), `nummer` (e.g. "01"), `titel`, `omschrijving` (max 2000 char, BBV-veld), `portefeuillehouder_id` (FK users), `programma_manager_id`, `coalition_period` (e.g. "2026-2030"), `status` (concept/vastgesteld/in_uitvoering/afgesloten), `vastgesteld_door_raad_op` (date), `vastgesteld_in_raadsbesluit_id` (FK decidesk), `kleur` (hex, voor visualisatie), `volgorde` (int), `gemma_taakgebied_codes` (array van CBS-taakvelden, e.g. "0.1 Bestuur", "8.3 Wonen en bouwen").

**bbv_doel**: `uuid`, `programma_id` (FK), `nummer` (e.g. "1.2"), `titel` ("Wat willen we bereiken"), `omschrijving`, `looptijd_start`, `looptijd_eind`, `eigenaar_id`, `status` (concept/lopend/behaald/niet_behaald/vervallen), `beleidsdoel_iri` (optionele link naar GEMMA-gegevenscatalogus beleidsdoel-URI), `volgorde`.

**bbv_activiteit**: `uuid`, `doel_id` (FK), `nummer` ("1.2.3"), `titel` ("Wat gaan we daarvoor doen"), `omschrijving`, `start_datum`, `eind_datum`, `verantwoordelijke_id`, `status` (gepland/in_uitvoering/gereed/uitgesteld/geannuleerd), `planix_project_ids` (array, FK projects), `procest_zaaktype_codes` (array), `decidesk_raadsbesluit_ids` (array), `volgorde`.

**bbv_indicator**: `uuid`, `parent_type` ('doel'|'activiteit'), `parent_id` (FK), `nummer`, `naam`, `categorie` ('output'|'effect'|'uitkomst'|'input'), `bbv_verplicht` (bool — true als één van de 39 standaard kpi's), `bbv_kpi_code` (e.g. "WSJG-001"), `eenheid` ('aantal'|'percentage'|'euro'|'fte'|'m2'|'index'), `baseline_waarde` (decimal), `baseline_jaar` (int), `streefwaarde` (decimal), `streefwaarde_jaar`, `meet_frequentie` ('maandelijks'|'kwartaal'|'jaarlijks'), `bron` (text, e.g. "CBS Statline", "interne registratie procest"), `eigenaar_id`.

**bbv_meting**: `uuid`, `indicator_id` (FK), `peildatum` (date), `gemeten_waarde` (decimal), `bron_url`, `toelichting`, `ingevoerd_door_id`, `bevroren` (bool — true zodra meegenomen in jaarrekening). Eén indicator heeft typisch baseline + 0..n metingen; trend grafiek leest hieruit.

**bbv_budget_koppeling**: `uuid`, `activiteit_id` (FK), `financeq_budgetregel_id` (FK financeq, nullable), `taakveld_code` (CBS), `bedrag_begroot` (decimal, EUR), `bedrag_realisatie` (decimal, gespiegeld vanuit financeq), `jaar` (int), `mutatie_grondslag` (text — vrije omschrijving waarom dit bedrag, ten behoeve van begroting-toelichting).

Validation rules:
- `bbv_programma.nummer` uniek binnen `gemeente_orgaan_id` per `coalition_period`.
- `bbv_doel.nummer` MUST start met `programma.nummer + "."` (e.g. programma "03" → doelen "3.1", "3.2"); validator weigert anders.
- `bbv_indicator.streefwaarde_jaar` >= `baseline_jaar`.
- `bbv_meting.gemeten_waarde` MUST be numeric en in de unit van `indicator.eenheid` (percentage 0-100, etc.); validator waarschuwt bij outlier > 3 sigma t.o.v. eerdere metingen.
- `bbv_budget_koppeling.taakveld_code` MUST match het reguliere expressie patroon `^\d+\.\d+$` (CBS-taakveld notatie).

Referentielijsten (alle als seed-data registers): `cbs_taakvelden` (76 codes, vanuit "Iv3 informatie voor derden 2026"), `bbv_verplichte_kpis` (39 indicatoren), `bbv_paragrafen` (de 7 verplichte paragrafen: lokale heffingen, weerstandsvermogen, onderhoud kapitaalgoederen, financiering, bedrijfsvoering, verbonden partijen, grondbeleid — voor latere uitbreiding maar nu al referenceerbaar vanuit programma).

## Requirements

### REQ-001: Programma Tree Visualisatie

The system SHALL render a collapsible vertical tree of programma's → doelen → activiteiten → indicatoren met expand/collapse op elke node, kleur-codering per programma, en status-icoon per node.

#### Scenario 1: openen van programma-overzicht
- **GIVEN** een gemeente heeft 12 programma's vastgesteld voor coalition_period 2026-2030
- **WHEN** een gebruiker navigeert naar `/planix/bbv/`
- **THEN** zijn alle 12 programma's zichtbaar als collapsed root nodes met nummer, titel, portefeuillehouder, en aantal onderliggende doelen/activiteiten

#### Scenario 2: uitklappen tot indicator-niveau
- **GIVEN** programma "03 Veiligheid" met 4 doelen en 11 activiteiten
- **WHEN** de gebruiker klapt programma "03" uit en daarna doel "3.2 Veilige openbare ruimte"
- **THEN** verschijnen 3 onderliggende activiteiten met hun streefdatums en de 5 indicatoren van doel "3.2" met huidige meetwaarde + sparkline trend

### REQ-002: Gantt Timeline van Activiteiten

The system SHALL provide a Gantt-style timeline weergave die alle activiteiten van een programma (of de hele organisatie) plot op een tijdas van de coalition_period, met visuele indicatie van overlap, vertraging, en afhankelijkheden.

#### Scenario 1: programma-gantt
- **GIVEN** programma "07 Mobiliteit" loopt 2026-2030 met 18 activiteiten
- **WHEN** de gebruiker schakelt naar de Gantt-view voor programma "07"
- **THEN** worden 18 horizontale balken getoond, gegroepeerd per doel, met statuskleur en hovercard met verantwoordelijke + voortgang

#### Scenario 2: vertraging gemarkeerd
- **GIVEN** activiteit "7.3.1 Aanleg fietsstraat" met eind_datum 2026-09-01 en status "in_uitvoering" op peildatum 2026-11-01
- **WHEN** de Gantt-view rendert
- **THEN** wordt deze activiteit rood gemarkeerd met label "61 dagen over de planning" en geeft de tooltip de gekoppelde planix-projecten met hun openstaande taken

### REQ-003: Indicator-meting Registratie en Trend

The system SHALL allow registering metingen tegen een indicator, een chronologische lijst tonen, en automatisch een trendlijn vs streefwaarde renderen.

#### Scenario 1: meting toevoegen
- **GIVEN** indicator "3.2.1 Aantal woninginbraken" met streefwaarde 120/jaar, bron "Politie Open Data"
- **WHEN** de eigenaar een meting toevoegt met peildatum 2026-12-31, waarde 147, bron_url naar politie.nl/cijfers
- **THEN** wordt de meting opgeslagen, de trendgrafiek bijgewerkt, en de gebruiker krijgt een waarschuwing "23% boven streefwaarde — overweeg toelichting in jaarrekening"

#### Scenario 2: bevroren meting kan niet meer gewijzigd worden
- **GIVEN** meting van 2025-12-31 met `bevroren=true` (al opgenomen in jaarrekening 2025)
- **WHEN** een gebruiker probeert deze meting te editen via PATCH `/api/objects/planix/bbv_meting/{id}`
- **THEN** retourneert de API 409 Conflict met body `{"error": "meting_bevroren", "message": "Meting opgenomen in vastgestelde jaarrekening, niet meer wijzigbaar"}`

### REQ-004: BBV-verplichte KPI's Auto-injectie

The system SHALL bij het aanmaken van een nieuw programma automatisch de relevante verplichte BBV-KPI's voorstellen op basis van het GEMMA-taakgebied, met één-klik-bulk-toevoegen.

#### Scenario 1: KPI-suggestie bij taakveld
- **GIVEN** nieuw programma "Sociaal Domein" met `gemma_taakgebied_codes` ["6.71 Maatwerkdienstverlening 18+", "6.72 Maatwerkdienstverlening 18-"]
- **WHEN** de gebruiker klikt op "Stel verplichte KPI's voor"
- **THEN** toont de UI 7 voorgestelde KPI's (Wmo-cliënten per 1000 inwoners, jeugdhulp-trajecten, etc.) met BBV-code en bron, allemaal vooraf-aangevinkt

### REQ-005: Koppeling Activiteit ↔ Planix Project

The system SHALL allow linking een bbv_activiteit aan één of meerdere planix projects, waarbij projectvoortgang automatisch reflecteert in de activiteit-status.

#### Scenario 1: project-koppeling
- **GIVEN** activiteit "5.1.3 Vervangen klimaatinstallatie zwembad De Plons" zonder gekoppelde projecten
- **WHEN** de verantwoordelijke een bestaand planix project "PRJ-2026-0142 Klimaat zwembad" koppelt
- **THEN** verschijnt de project-card in de activiteit-detail, en de activiteit-voortgang (%) wordt berekend als gewogen gemiddelde van project-task-completion

#### Scenario 2: status-rollup
- **GIVEN** activiteit met 2 gekoppelde projecten, één status "in_uitvoering" en één "vertraagd"
- **WHEN** de Gantt-view rendert
- **THEN** krijgt de activiteit de pessimistische status "vertraagd" en een tooltip "1 van 2 projecten vertraagd"

### REQ-006: Koppeling Programma ↔ Raadsbesluit (decidesk)

The system SHALL link programma's en doelen aan decidesk raadsbesluiten, zodat de autorisatie-keten (welke besluit dekt welke uitgave / activiteit) traceerbaar blijft.

#### Scenario 1: programma-vaststelling
- **GIVEN** decidesk bevat raadsbesluit "RB-2025-189 Vaststellen programmabegroting 2026"
- **WHEN** een ambtenaar het programma "04 Onderwijs 2026-2030" markeert als `vastgesteld_door_raad_op = 2025-11-08` en koppelt aan RB-2025-189
- **THEN** worden de status naar "vastgesteld" gezet, het besluit-snippet in de programma-header getoond, en kan niemand meer doelen toevoegen zonder nieuwe wijzigings-RB

#### Scenario 2: ongeautoriseerde wijziging geblokkeerd
- **GIVEN** vastgesteld programma met 6 doelen
- **WHEN** een ambtenaar probeert doel "4.7" toe te voegen zonder `wijziging_raadsbesluit_id`
- **THEN** retourneert de API 422 Unprocessable Entity met melding "Wijziging vastgesteld programma vereist koppeling aan een wijzigings-raadsbesluit"

### REQ-007: Budget-rollup uit financeq

The system SHALL aggregate begroting- en realisatiebedragen vanuit financeq-budgetregels naar activiteit-, doel-, programma- en organisatie-niveau, met live rollup en variance-indicatie.

#### Scenario 1: budget-tegel op programma
- **GIVEN** programma "07 Mobiliteit" met 18 activiteiten, totaal begroot 4.250.000 EUR voor jaar 2026
- **WHEN** de gebruiker opent de programma-detail in november 2026
- **THEN** toont de budget-tegel "Begroot 4.250.000 / Gerealiseerd 3.180.000 / Restant 1.070.000 (25%)" met groene/oranje/rode indicator

#### Scenario 2: niet-gekoppelde realisatie waarschuwing
- **GIVEN** financeq bevat 47.000 EUR realisatie op taakveld "2.1 Verkeer en vervoer" zonder bbv_budget_koppeling
- **WHEN** de programma-detail laadt
- **THEN** verschijnt een banner "47.000 EUR realisatie zonder activiteit-koppeling — bekijk lijst" met klik-door naar een filterbare table

### REQ-008: Export naar iV3 / Waarstaatjegemeente / SISA

The system SHALL export de tree + metingen naar verplichte landelijke verantwoordingsformaten: iV3 XBRL (CBS), waarstaatjegemeente.nl CSV, en SISA (Single information, Single audit) Excel-template.

#### Scenario 1: iV3 XBRL export
- **GIVEN** afgerond boekjaar 2026 met 12 programma's en bevroren metingen
- **WHEN** de financieel beheerder klikt "Exporteer iV3 XBRL"
- **THEN** wordt een XBRL-instantie gegenereerd conform de geldende CBS-taxonomie, met validatie-rapport, en gedownload als `iv3-{gemeente-code}-2026.xbrl`

#### Scenario 2: KPI-set naar waarstaatjegemeente
- **GIVEN** 39 BBV-verplichte KPI's met meting voor 2026
- **WHEN** export "Waarstaatjegemeente CSV" wordt gestart
- **THEN** krijgt de gebruiker een CSV met kolommen `kpi_code, waarde, peildatum, bron_url` exact conform het waarstaatjegemeente.nl-uploadformaat

### REQ-009: Permissions en Read-only Publieksversie

The system SHALL enforce role-based access: alleen `bbv_beheerder` mag structuur wijzigen, `bbv_indicator_eigenaar` mag metingen invoeren voor toegewezen indicatoren, alle ingelogde gebruikers mogen lezen, en er is een publieke read-only versie via shareable URL.

#### Scenario 1: indicator-eigenaar scope
- **GIVEN** gebruiker Janneke is eigenaar van 4 indicatoren binnen programma "06 Sport"
- **WHEN** zij `POST /api/objects/planix/bbv_meting` doet voor een indicator buiten haar scope
- **THEN** retourneert de API 403 Forbidden met `{"error": "not_indicator_owner"}`

#### Scenario 2: publieksversie zonder concept-data
- **GIVEN** programma "03 Veiligheid" met status "vastgesteld" en programma "11 Duurzaamheid" met status "concept"
- **WHEN** een burger de publieke URL `/planix/bbv/public/{gemeente-slug}` opent
- **THEN** ziet de burger alleen "03 Veiligheid" met bevroren metingen, zonder concept-programma's en zonder bedragen onder activiteit-niveau

### REQ-010: Audit Trail en Versie-snapshot per Begrotingsjaar

The system SHALL maintain een immutable snapshot van de complete tree per begrotingsjaar (versie 2026, 2027...) en alle wijzigingen na vaststelling loggen in een audit trail die exporteerbaar is voor de accountantscontrole.

#### Scenario 1: jaar-snapshot
- **GIVEN** programmabegroting 2026 wordt door de raad vastgesteld op 2025-11-08
- **WHEN** de actie "Bevries begroting 2026" wordt uitgevoerd
- **THEN** wordt een complete deep-copy van de tree opgeslagen als `bbv_snapshot/2026/vastgesteld`, raadpleegbaar in elke latere fase, met diff-view t.o.v. huidige werkversie

#### Scenario 2: audit-trail export voor accountant
- **GIVEN** boekjaar 2026 wordt afgesloten en de accountant vraagt om de wijzigings-historie
- **WHEN** de financieel beheerder klikt "Exporteer audit trail 2026"
- **THEN** wordt een PDF gegenereerd met chronologisch overzicht van alle structuur-wijzigingen (wie, wanneer, welk raadsbesluit, voor/na-waarde) ondertekend met een digitale handtekening (SHA-256 hash) voor integriteit

## Standards & Sources

- **Besluit Begroting en Verantwoording provincies en gemeenten (BBV)** — wettelijk kader, art. 7 t/m 25 (programma-indeling, paragrafen, indicatoren, taakvelden); de spec implementeert deze structuur als data-model en valideert tegen verplichte vormvereisten.
- **Gemeentewet titel IV (financiën)** en **Provinciewet titel V** — kader rondom begroting, jaarrekening, art. 12-toezicht; bepalen welke documenten formeel uit deze tree moeten kunnen rollen.
- **Iv3 voorschriften CBS** — informatie voor derden, jaarlijks taxonomie-update, bron voor `cbs_taakvelden` seed; de XBRL-taxonomie waar export tegen valideert.
- **Besluit "Waarstaatjegemeente" beleidsindicatoren** — de 39 verplichte KPI's per gemeente (BZK-publicatie, jaarlijks geüpdatet) plus de aanvullende set voor specifieke domeinen (jeugd, Wmo, veiligheid).
- **GEMMA 2 referentiegegevensmodel** — `bbv_doel.beleidsdoel_iri` linkt naar GEMMA-gegevenscatalogus; consistent gebruik van begrippen "beleidsdoel", "taakgebied", "bedrijfsfunctie".
- **GEMMA Procesarchitectuur "P&C-cyclus"** — referentie-proces dat de timing van bevriezing, rapportage en herziening van de tree dicteert.
- **SISA (Single information, Single audit)** — verantwoording specifieke uitkeringen, jaarlijkse Excel-template van BZK; export-formaat target.
- **Wet financiering decentrale overheden (Wet fido)** — randvoorwaarden voor de financierings-paragraaf.
- **VNG-handreiking "BBV in de praktijk"** — best-practices voor doel-indicator-koppeling, gebruikt als seed voor template-doelen.
- **VNG-uitvoeringsinformatie "Iv3" en "BBV in een notendop"** — toelichting bij taakvelden, materiële vaste activa, voorzieningen.
- **XBRL Nederland taxonomie (NT)** — voor Iv3-export, https://www.nltaxonomie.nl, jaarlijks bijgewerkt door Logius/CBS.
- **CBS Statline open data API** — voor automatische refresh van demografische KPI's (bevolkingsopbouw, banen, woningvoorraad).
- **Politie open data** — bron voor veiligheids-KPI's (woninginbraken, geweldsdelicten, jeugdoverlast).
- **DUO open data** — bron voor onderwijs-KPI's (leerlingenaantallen, schoolverlaters).
- **Atlas Leefomgeving (RIVM)** — bron voor leefomgeving-KPI's (luchtkwaliteit, geluidhinder).
- **Forum Standaardisatie "pas-toe-of-leg-uit"** — XBRL, RDF/Turtle, NL API Strategie, DCAT-AP voor data-publicatie van programma-data als open data.
- **DCAT-AP-NL 2.0** — metadata-standaard voor publicatie op data.overheid.nl.
- **Wet open overheid (Woo)** — categorie "vergaderstukken decentrale overheden" en "begrotingen en jaarrekeningen" zijn actief openbaar.
- **NL Design System** — voor publieksversie-styling conform overheid-huisstijl.
- **WCAG 2.2 AA** — toegankelijkheid voor publieksversie en raadsleden-portaal.

## Cross-app Integration

- **financeq** (`/api/integrations/planix-bbv/budgetregels`): GET-endpoint dat alle budgetregels per `taakveld_code + jaar` levert; webhook bij nieuwe realisatie-mutatie zodat de programma-rollup re-aggregateert. Twee-richtings: planix consumeert begroot/realisatie, financeq leest activiteit-omschrijving voor begrotings-toelichting.
- **decidesk** (raadsbesluiten): `bbv_programma.vastgesteld_in_raadsbesluit_id` en `bbv_activiteit.decidesk_raadsbesluit_ids` zijn referenties; decidesk publiceert event `raadsbesluit.vastgesteld` waarop planix kan reageren door programma-status te flippen. Reverse: decidesk toont op besluit-detail welke programma-onderdelen het besluit dekt.
- **procest** (zaaktypen → activiteit-output): `bbv_activiteit.procest_zaaktype_codes` koppelt; procest publiceert `zaak.afgesloten` event, planix telt automatisch output-indicatoren op (e.g. "aantal afgegeven vergunningen").
- **mydash** (bestuurdersdashboard): widget `bbv-programma-tegel` toont per programma traffic-light status + restant-budget; klikt door naar planix tree.
- **opencatalogi** (GEMMA-publicatie): elk vastgesteld programma + doel + indicator is publiceerbaar als opencatalogi-publicatie type "beleidsdoel" — feeds open data portalen.
- **docudesk** (programmabegroting-PDF): elk jaar genereert docudesk de wettelijke programmabegroting-PDF en jaarrekening-PDF uit de tree, met de zeven verplichte paragrafen.
- **n8n** (auto-refresh KPI's): n8n-workflow polt maandelijks CBS Statline / Politie open data en pusht naar `bbv_meting` API.
- **openconnector** (externe systemen): voor gemeenten met legacy P&C-tooling (LIAS, Pepperflow) — eenmalige ETL-koppeling om bestaande tree te importeren.

## Target Users

- **Concerncontrollers / financieel beheerders**: primaire beheerders van de structuur, valideren cijfers vóór jaarrekening, draaien exports naar Iv3/SISA/waarstaatjegemeente, beheren de bevriezings-momenten in de begrotings- en verantwoordings-cyclus.
- **Programmamanagers**: bewaken voortgang van hun programma, koppelen projecten en zaken, escaleren vertragingen naar portefeuillehouder, leveren maandelijkse stuur-input voor concernrapportage.
- **Beleidsmedewerkers / indicator-eigenaren**: voeren periodiek metingen in voor toegewezen indicatoren, plannen interventies wanneer KPI's afwijken van streefwaarden, leveren context voor commissie-presentaties.
- **Portefeuillehouders (wethouders / gedeputeerden / DB-leden)**: gebruiken mydash-tegel + drill-down voor wekelijkse status, gebruiken de tree voor commissie-presentaties en burgerparticipatie-sessies, sturen op effectindicatoren binnen eigen portefeuille.
- **Griffie**: koppelt raadsbesluiten aan programma-wijzigingen, bewaakt motie-uitvoering, faciliteert de begrotings- en verantwoordingsraad met live data in plaats van papieren stukken.
- **Raadsleden / Provinciale Statenleden / Algemeen Bestuur**: read-only toegang tot tree + Gantt + indicator-trends om collegiaal toezicht uit te oefenen, vragenuren onderbouwen, eigen moties koppelen aan programma-context.
- **Accountants (externe controle)**: exporteren audit-trail en bevroren snapshots tijdens jaarrekening-controle, verifiëren dat realisatie aan begroting koppelt en dat indicator-bron-data verifieerbaar is.
- **Rekenkamer / lokale rekenkamercommissie**: gebruiken historische snapshots en indicator-trends voor doelmatigheids- en doeltreffendheids-onderzoek.
- **Provinciaal financieel toezicht (PFT) / BZK interbestuurlijk toezicht**: bij preventief of artikel-12 toezicht raadpleegbaar inzicht in begrotingsdiscipline en realisatiepatronen.
- **Burgers / journalistiek / lokale media**: publieksversie raadpleegbaar zonder login, voor lokale democratische verantwoording, voor burger-initiatieven die op programma-doelen aansluiten.
- **Onderzoek / universiteiten / planbureaus (SCP, CPB)**: via open-data publicatie van geanonimiseerde KPI-tijdreeksen onderzoek naar effectiviteit van decentraal beleid.
