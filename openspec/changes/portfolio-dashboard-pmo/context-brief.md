---
status: draft
---
# Portfolio Dashboard PMO (Project Management Office Rollup)

## Purpose

Organisaties met meer dan een handvol parallelle projecten hebben binnen een paar maanden behoefte aan een Project Management Office (PMO). De PMO bestaat soms uit één persoon, soms uit een team, maar de kerntaak is altijd dezelfde: zicht houden op het hele portfolio van projecten — hoe staan we ervoor, waar lopen we vast, waar conflicteren resources, welke risico's nemen we, en passen we als organisatie binnen ons budget. In gemeentes, provincies, woningcorporaties, zorginstellingen en MKB-bedrijven die planix gaan gebruiken zal dezelfde behoefte ontstaan: een centraal, configureerbaar **portfolio-dashboard** dat over alle projecten heen aggregeert en de PMO + het bestuur in staat stelt om in één oogopslag de staat van de uitvoering te begrijpen.

Planix kent vandaag projecten, taken, time-tracking en een dashboard "my-work" per gebruiker. Wat ontbreekt is de cross-project rollup met de typische PMO-views: portfolio-overzicht met traffic-light per project, resource-utilisatie heatmap, risico-register, dependency-grafiek tussen projecten, milestone-radar voor de komende 90 dagen, en spend-vs-budget breakdown. Vandaag bouwen PMO's deze rapporten in Excel + Power BI of in losse SaaS-tools (Monday, ClickUp, Asana Portfolios) — buiten Conduction, dus zonder live data en zonder de governance-koppeling naar BBV, raadsbesluiten of GEMMA. Deze spec brengt die PMO-functionaliteit native in planix, gebruikmakend van mydash voor de widget-rendering, openregister voor de aggregatie, en de bestaande planix-data als bron.

Concreet levert de spec: (a) een **Portfolio Dashboard** als een eigen route in planix (`/planix/portfolio`) met configureerbare layout per gebruiker; (b) acht standaard-widgets (portfolio-health-table, resource-heatmap, milestone-radar, risk-register, dependency-graph, spend-vs-budget, status-trend, executive-summary) die ook losstaand op een mydash-pagina te tonen zijn; (c) een **PMO-rolmodel** met `pmo_manager`, `pmo_lid`, `portfolio_eigenaar` (bestuurslid), `program_manager`, en passende permissies; (d) een **risico-register** dat per risico kans, impact, mitigerende maatregelen en eigenaar bijhoudt en op project-niveau aggregateert tot risk-score; (e) **resource-management** waar elke FTE/medewerker geplande capaciteit per project per week heeft en het systeem oververdeling waarschuwt; (f) **portfolio-snapshots** voor maandelijkse stuur-rapportages; (g) filtering en drill-down vanaf bestuur-niveau ("alle projecten in programma Mobiliteit") tot project-detail.

Out of scope: detail project-management (blijft binnen planix project-spec), individuele taak-uitvoering (planix tasks), tijdschrijven per uur (planix time-tracking), HR-planning voor verlof/ziekte (eventueel toekomstige peopleq-app), financiële kostenboeking (financeq). Deze spec aggregateert, ze beheert geen brongegevens.

## Data Model

Zes schemas in `planix/portfolio-dashboard-pmo/`, plus drie afgeleide views (materialized of on-demand):

**portfolio**: `uuid`, `naam`, `omschrijving`, `eigenaar_id` (FK users — bestuurslid of directielid), `pmo_manager_id` (FK users), `scope_filter` (JSON — definieert welke projecten in dit portfolio vallen, bijv. `{"programma_ids":[...],"afdeling_ids":[...],"tags":["strategisch"]}`), `kleurthema` (hex), `actief` (bool), `gemaakt_op`.

**portfolio_project_link**: `uuid`, `portfolio_id` (FK), `project_id` (FK planix projects), `weight` (decimal 0-1 — gewicht in portfolio-rollup), `is_strategisch` (bool — markering voor executive views), `toegevoegd_op`. Materializeert het `scope_filter` zodat snapshots stabiel zijn.

**project_status_report**: `uuid`, `project_id` (FK), `rapportage_datum` (date), `status_overall` (groen|amber|rood), `status_planning` (groen|amber|rood), `status_budget` (groen|amber|rood), `status_resources` (groen|amber|rood), `status_kwaliteit` (groen|amber|rood), `voortgang_percentage` (decimal), `samenvatting` (text, max 1000 char), `successen` (text), `issues` (text), `volgende_mijlpaal` (text), `volgende_mijlpaal_datum` (date), `opgesteld_door_id`, `opgesteld_op`, `gepubliceerd` (bool). Eén per project per rapportage-cyclus (typisch maandelijks).

**risico**: `uuid`, `project_id` (FK), `code` (e.g. "PRJ-2026-014-R03"), `titel`, `omschrijving`, `categorie` ('planning'|'budget'|'scope'|'resources'|'kwaliteit'|'extern'|'compliance'|'reputatie'), `kans` (zeer_laag|laag|midden|hoog|zeer_hoog), `impact` (zeer_laag|laag|midden|hoog|zeer_hoog), `risico_score` (auto: kans × impact, 1-25), `eigenaar_id`, `status` (open|in_mitigation|geaccepteerd|verholpen|gerealiseerd), `mitigerende_maatregelen` (text), `gemeld_op`, `laatst_beoordeeld_op`, `volgende_review_datum`.

**resource_allocatie**: `uuid`, `user_id` (FK), `project_id` (FK), `week_iso` (string "2026-W14"), `geplande_uren` (decimal), `werkelijke_uren` (decimal, gevuld vanuit time-tracking), `rol_op_project` (e.g. "projectleider", "ontwikkelaar", "tester", "stakeholder"), `opmerking`. Wekelijks granulair; week-niveau is goede middenweg tussen detail en onderhoudsdruk.

**portfolio_snapshot**: `uuid`, `portfolio_id` (FK), `snapshot_datum` (date), `payload` (JSON — complete state van alle KPI's op moment X), `gemaakt_door_id`, `commentaar`, `gebruikt_in_rapportage` (text — bijv. "maandrapportage MT april 2026"). Read-only na creatie, voor stuurcyclus + audit.

Afgeleide views (read-only via aggregation):
- **vw_portfolio_health**: per portfolio het aantal projecten per status (groen/amber/rood per dimensie), gewogen score, deltas tov vorige snapshot.
- **vw_resource_utilization**: per user per week totale geplande uren vs contracturen, %, lijst gekoppelde projecten.
- **vw_milestone_radar**: alle planix milestones komende 90 dagen + portfolio waar ze in vallen + status (op-tijd, at-risk, vertraagd).

Validaties:
- `project_status_report` mag max 1x per project per rapportage-cyclus; tweede insert in dezelfde maand → 409.
- `risico.risico_score = score_van_kans(kans) * score_van_impact(impact)` waar `score_van_*` mapt naar 1..5; systeem-berekend, niet schrijfbaar.
- `resource_allocatie.geplande_uren` MUST ≥ 0 en ≤ 60 per record (validator waarschuwt boven 40); som over alle projecten per `user_id + week_iso` zou ≤ contracturen moeten zijn maar wordt zacht-gehandhaafd met waarschuwing in plaats van blokkering.
- `portfolio_snapshot.payload` is immutable na creatie.

## Requirements

### REQ-001: Portfolio-overzicht Traffic-light Table

The system SHALL toon een sorteerbare en filterbare tabel met alle projecten in een portfolio, met per project de vijf RAG-statusindicatoren (overall, planning, budget, resources, kwaliteit) uit het laatste `project_status_report`.

#### Scenario 1: portfolio openen
- **GIVEN** portfolio "Strategische Projecten 2026" met 24 gekoppelde projecten waarvan elk een rapportage van afgelopen maand
- **WHEN** de PMO-manager opent `/planix/portfolio/{id}`
- **THEN** verschijnt een tabel met 24 rijen, gesorteerd op `status_overall` desc (rood eerst), kolommen voor de vijf RAG-indicatoren met kleur-bolletjes, voortgang%, projectmanager, volgende mijlpaal, en deltas tov vorige maand

#### Scenario 2: filteren op rood
- **GIVEN** dezelfde portfolio met 3 rode, 7 amber, 14 groene projecten
- **WHEN** de gebruiker filter `status_overall = rood` toepast
- **THEN** worden 3 rijen getoond en de header-tegels updaten naar (3 rood, ...) op de zichtbare selectie

### REQ-002: Resource-utilisatie Heatmap

The system SHALL render een heatmap van resource-allocaties met op de y-as users, op de x-as weken, en cellen ingekleurd op basis van utilisatie-percentage (groen ≤80%, geel 80-100%, oranje 100-120%, rood >120%).

#### Scenario 1: heatmap voor PMO
- **GIVEN** 14 medewerkers met `resource_allocatie` records voor weken W12-W24 van 2026
- **WHEN** PMO opent de "Resources"-tab
- **THEN** verschijnt heatmap 14×13, met klikbare cellen → drill-down naar welke projecten die week voor die user beslaan

#### Scenario 2: oververdeling-rapport
- **GIVEN** Janneke is voor W18 ingepland op project A (16u), B (16u), C (12u) — totaal 44u tegen 32 contracturen
- **WHEN** heatmap rendert
- **THEN** is de cel Janneke×W18 rood, hovercard toont "44u / 32u (138%)", drill-down toont breakdown per project; PMO ziet visueel waar conflicts ontstaan

### REQ-003: Milestone Radar 90 Dagen

The system SHALL een mijlpaal-radar visualiseren met komende 90 dagen op een tijdas, mijlpalen als bolletjes (grootte = belang, kleur = at-risk-status), gegroepeerd op portfolio of programma.

#### Scenario 1: radar-view
- **GIVEN** portfolio "Digitale Dienstverlening" met 47 mijlpalen in de komende 90 dagen
- **WHEN** gebruiker opent "Milestone Radar"
- **THEN** verschijnt horizontale tijdslijn met 47 bolletjes, gekleurd groen (op tijd), oranje (at-risk = onderliggende taken vertraagd), rood (al gemist)

#### Scenario 2: filter op risk-status
- **GIVEN** dezelfde radar
- **WHEN** filter `alleen at-risk + vertraagd` aan
- **THEN** verschijnen alleen oranje + rode bolletjes; een sidebar lijst toont per mijlpaal de oorzaak (welke taak / risico)

### REQ-004: Risico-register en Portfolio Risk-score

The system SHALL een risico-register beheren per project met 5×5 kans×impact matrix-visualisatie, en op portfolio-niveau geaggregeerde **portfolio risk-score** = gewogen som van risico-scores van projecten.

#### Scenario 1: risico toevoegen
- **GIVEN** project "Vervangen ICT-werkplekken" zonder risico's
- **WHEN** projectleider voegt risico toe `categorie='leveranciers', titel='Leverancier kan niet leveren binnen 8 weken', kans='midden', impact='hoog'`
- **THEN** wordt `risico_score = 3 × 4 = 12` berekend, opgeslagen als status `open`, project's risk-rollup updates, en het verschijnt in de 5×5 matrix in cel (midden, hoog)

#### Scenario 2: portfolio risk-score
- **GIVEN** portfolio met 6 projecten met risico-scores resp. (8, 12, 4, 16, 6, 9) gewogen naar project-budget
- **WHEN** de portfolio risk-tegel rendert
- **THEN** wordt portfolio risk-score gepresenteerd als 10.3 (gewogen gemiddelde) met trend-indicator vs vorige maand en lijst van top-5 risico's organisatie-breed

### REQ-005: Dependency Graph Tussen Projecten

The system SHALL inter-project dependencies visualiseren als een gerichte graaf, waar een dependency aangeeft dat project A pas kan opleveren als project B een bepaalde mijlpaal bereikt heeft.

#### Scenario 1: dependency toevoegen
- **GIVEN** project "Klant Portaal Live" en project "Single Sign-on Implementatie"
- **WHEN** PM van Klant Portaal voegt dependency toe: "wacht op SSO mijlpaal M-3"
- **THEN** wordt een edge in de graaf gemaakt; bij verandering van M-3 status propageert at-risk-flag naar Klant Portaal

#### Scenario 2: circulaire dependency afgewezen
- **GIVEN** project A → wacht op B → wacht op C
- **WHEN** iemand probeert dependency C → wacht op A toe te voegen
- **THEN** retourneert API 422 `{"error":"circular_dependency"}` en geeft het pad terug

### REQ-006: Spend-vs-Budget Breakdown

The system SHALL spend-vs-budget tonen op portfolio-, programma- en project-niveau, met data uit financeq (begroting + realisatie) gefilterd op project-cost-center of bbv_doel-koppeling.

#### Scenario 1: portfolio-spend tegel
- **GIVEN** portfolio "Strategische Projecten 2026" met 24 projecten en totaal begroot 8.4 M EUR voor 2026
- **WHEN** de gebruiker opent het portfolio-overzicht in oktober 2026
- **THEN** toont de spend-tegel "8.4 M begroot, 5.9 M gerealiseerd, 70% bestedingsgraad, prognose Q4 op 8.1 M"

#### Scenario 2: drill-down per project
- **GIVEN** klik op spend-tegel
- **WHEN** drill-down open
- **THEN** tabel met 24 projecten, kolommen begroot/realisatie/restant/%, klikbaar naar project-detail

### REQ-007: Status-rapportage Wizard

The system SHALL projectmanagers een wizard bieden voor maandelijkse status-rapportage met geheugen (laatste rapportage als concept), suggesties op basis van data (overschreden deadlines → automatische "issue"), en publish-flow naar PMO.

#### Scenario 1: maandelijkse rapportage opstellen
- **GIVEN** projectmanager Maria heeft project "PRJ-2026-019" en vorige maand een rapportage met `samenvatting`
- **WHEN** Maria klikt "Nieuwe rapportage opstellen" voor maand april
- **THEN** wordt een concept gemaakt met vorige tekst als startpunt, en de wizard suggereert: "3 taken over deadline → markeer planning op amber?", "budget bestedingsgraad 92% vs verwacht 70% → markeer budget op rood?"

#### Scenario 2: publish-flow
- **GIVEN** concept-rapportage met groene status
- **WHEN** Maria klikt "Publiceer naar PMO"
- **THEN** wordt `gepubliceerd=true`, PMO-manager krijgt notificatie, dashboard updates met nieuwe data; daarna kan rapportage niet meer geëdit worden (alleen via nieuwe correctie-rapportage met `corrigeert_rapportage_id`)

### REQ-008: Maandelijkse Portfolio-snapshot

The system SHALL elke maand (configureerbaar) automatisch een `portfolio_snapshot` maken per portfolio met de volledige rollup-state, en de PMO een notificatie sturen dat de snapshot klaar is voor de MT-rapportage.

#### Scenario 1: nightly snapshot taak
- **GIVEN** datum is 2026-05-01 02:00 (eerste van de maand)
- **WHEN** scheduled task draait
- **THEN** wordt per actieve portfolio een `portfolio_snapshot` gemaakt met `payload` bestaande uit alle KPI's op die datum (RAG-tellingen, risk-score, mijlpaal-tellingen, spend-numbers), PMO-managers ontvangen e-mail "April-snapshot klaar"

#### Scenario 2: snapshot-vergelijking
- **GIVEN** snapshots voor jan, feb, maa, apr van portfolio X
- **WHEN** gebruiker opent "trend-view"
- **THEN** verschijnt grafiek met 4 datapunten per KPI (RAG-counts, spend%, risk-score) met sparkline, voor strategische "hoe staan we ervoor over tijd"

### REQ-009: Executive Summary Widget voor Bestuur

The system SHALL een one-page "Executive Summary" rendering bieden, geoptimaliseerd voor presentatie aan bestuur/MT: 4-6 KPI-tegels, top-3 issues, top-3 successen, milestone-radar 30 dagen.

#### Scenario 1: bestuurder opent zijn portfolio
- **GIVEN** wethouder Pieter is `portfolio_eigenaar` van portfolio "Stedelijke Vernieuwing"
- **WHEN** hij `/planix/portfolio/{id}/exec` opent (of widget op mydash)
- **THEN** ziet hij 5 KPI-tegels (Projecten 12 / Op koers 8 / Amber 3 / Rood 1 / Risk-score 9.2), tekstuele top-3 issues uit gepubliceerde rapportages, gefilterde milestone-radar

#### Scenario 2: presentatie-modus
- **GIVEN** executive-summary view
- **WHEN** klik "presentatie-modus"
- **THEN** schakelt over naar fullscreen layout zonder navigatie, geschikt voor beamer in MT-vergadering

### REQ-010: PMO Permissies en Read-only Voor Bestuur

The system SHALL het rolmodel handhaven: `pmo_manager` mag alles in toegewezen portfolios, `pmo_lid` mag rapportages lezen + helpen opstellen maar niet publiceren, `portfolio_eigenaar` heeft full read + commentaar, projectmanagers zien alleen eigen projecten in portfolio-context.

#### Scenario 1: PMO_lid zonder publish
- **GIVEN** PMO-lid Henk werkt mee aan portfolio rapportage
- **WHEN** Henk klikt "Publiceer"
- **THEN** API 403 met `{"error":"requires_pmo_manager_role"}`; concept blijft staan voor manager-review

#### Scenario 2: bestuurder commentaar
- **GIVEN** wethouder Pieter ziet executive-summary van zijn portfolio
- **WHEN** hij op een rode tegel klikt en "commentaar toevoegen"
- **THEN** wordt een commentaar opgeslagen, zichtbaar voor PMO, met notificatie aan PMO-manager voor opvolging

## Standards & Sources

- **PRINCE2 (PRojects IN Controlled Environments)** — RAG-status (Rood/Amber/Groen) is PRINCE2-praktijk; risico-register kans×impact matrix idem.
- **PMBoK (PMI)** — portfolio-management terminologie, KPI's, EVM (Earned Value Management) — basis voor toekomstige uitbreiding `spend-vs-budget` met EV/PV/AC.
- **MoP (Management of Portfolios)** — UK Cabinet Office standard, portfolio-categorisering.
- **PMI Risk Management Standard** — 5×5 matrix, mitigation-categorieën.
- **ISO 21500 / ISO 21502** — projectmanagement processen.
- **MSP (Managing Successful Programmes)** — programma-portfolio relatie.
- **VNG-Handreiking PMO bij gemeenten** — Nederlandse publieke sector-specifieke best practices.
- **GEMMA 2 procesarchitectuur "Projectmatig werken"** — koppelvlakken.
- **Forum Standaardisatie BPMN 2.0** — voor dependency-graph notatie (toekomstige uitbreiding).

## Cross-app Integration

- **planix base** (bron): projecten, taken, milestones, time-tracking; deze spec leest data uit deze schemas en aggregateert.
- **mydash** (rendering): alle widgets uit deze spec zijn óók registreerbaar als mydash-widgets met expliciete tile-config (portfolio-filter, breedte, hoogte) zodat bestuurders ze in eigen dashboard plaatsen.
- **financeq** (spend-data): leest begroting + realisatie per project-cost-center; bidirectional via webhook bij realisatie-mutatie.
- **bbv-programma-tree** (strategische context): portfolio kan gefilterd worden op `bbv_doel_ids`, en spend-rollup koppelt aan BBV-budgetkoppelingen.
- **raadsbesluit-deliverable-chain**: chains die actief zijn voor projecten in portfolio worden getoond als sub-context op project-cards.
- **procest** (zaken als deliverables): waar projecten via procest-zaaktypes lopen, telt zaak-completion mee in voortgang.
- **opencatalogi** (publicatie): portfolio-snapshot met publish-flag publiceerbaar als open data dataset "projectportfolio gemeente X stand maand Y".
- **n8n** (auto-snapshot + notificaties): scheduled flows voor maand-snapshot, e-mailrapportage, escalatie bij rood-status > 2 maanden.
- **docudesk** (rapportage-PDF): genereert maandelijkse stuur-rapportage PDF uit snapshot voor MT-vergadering.
- **openconnector** (legacy import): voor organisaties met bestaande PPM-tools (Planview, Sciforma) — initiële import van projectportfolio.

## Target Users

- **PMO-manager**: dagelijkse gebruiker, beheert portfolio's, valideert rapportages, escaleert issues, presenteert aan MT, draait wekelijkse stand-up over rode/amber projecten, coördineert resource-conflictresolutie tussen projectmanagers.
- **PMO-medewerker / portfolio-controller**: helpt rapportages opstellen, onderhoudt resource-allocaties, beheert risico-register, draait analyses voor ad-hoc bestuurs-vragen, verzorgt maandelijkse snapshot-bevriezing.
- **Projectmanagers**: leveren maandelijkse rapportage, registreren en mitigeren risico's, plannen resources voor eigen team, zien hun project in portfolio-context, vragen escalatie naar PMO bij dependency-issues.
- **Programmamanagers**: zien portfolio gefilterd op eigen programma, signaleren cross-project issues binnen programma, bewaken programma-budget en programma-risk-rollup, koppelen naar bbv-programma-tree voor beleidscontext.
- **Bestuurders (wethouders, directieleden, MT-leden)**: gebruiken executive-summary widget voor wekelijkse status, vragen om drill-down in MT-vergadering, leggen commentaren bij rode tegels, gebruiken presentatie-modus voor stuurgroep-vergaderingen.
- **Concerncontroller / financieel directeur**: gebruikt spend-vs-budget views voor financial governance, koppelt aan financeq-data, signaleert dreigende overschrijdingen op portfolio-niveau, levert input voor begrotingswijziging-voorstellen.
- **Directie / CEO / gemeentesecretaris**: krijgen periodieke executive-summary per e-mail (n8n-flow); top-down zicht; gebruiken portfolio-trend voor strategische heroriëntatie.
- **Auditors / accountants**: hebben read-only inzage in snapshots voor verklaring projectbeheersing in management letter, gebruiken audit-log voor verklaring inzake interne beheersing.
- **Lokale rekenkamer / Algemene Rekenkamer**: gebruiken historische snapshots voor doelmatigheid- en doeltreffendheids-onderzoek naar grote investeringsprojecten.
- **Resource-managers / teamleads (HR-functioneel)**: bekijken resource-heatmap voor capaciteitsplanning, signaleren burn-out-risico's bij chronisch oververdeelde medewerkers.
- **Risicomanagers / compliance-officers**: gebruiken risico-register voor enterprise risk management (ERM) bredere agenda, koppelen project-risico's aan organisatie-risk appetite.
- **Inkoopadviseurs / contractmanagers**: zien project-portfolio voor planning van inkoopvolume, signaleren bundelingskansen tussen projecten.
