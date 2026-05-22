status: draft

# Risk Register & Issue Tracking

## Placement & Information Architecture

**Placement type:** `SUB_PAGE` — Sub-page beneath a top-level menu entry. Renders as a page inside the parent surface (usually reachable via a router child route or a tab on the parent index page).

**Lives at:** Projecten > Risico-register

**Rationale:** risks scoped to projects  
_Source: /tmp/ia-small5.md_

> **Implementation note for builders:** Respect the placement above. Do not promote this spec to a top-level menu item, sub-page, or new route unless the placement type explicitly says so. If the placement is `DETAIL_TAB`, `WIDGET`, `ACTION`, `SETTING`, or `INFRA`, the feature must NOT introduce a new entry in the app sidebar. When in doubt, ask before creating a new top-level surface.

## Purpose

Planix is een project- en programma-managementtool gericht op gemeentelijke en provinciale uitvoeringsorganisaties. Een kernfunctie die in de huidige set ontbreekt is een gestructureerd risk register met issue-tracking: het identificeren, kwantificeren, mitigeren en monitoren van projectrisico's, en het opvolgen van risico's die zich realiseren tot daadwerkelijk issue. Voor PRINCE2-, IPMA- en agile-projecten is dit verplicht onderdeel van de project-governance.

De huidige praktijk in veel overheidsorganisaties: een Excel-bestand per project, een ander format per programma, en (in best-case) een SharePoint-lijst voor het portfolio. Geen consistentie in risico-score-methodiek, geen automatische escalatie als mitigatieacties hun streefdatum overschrijden, geen samenhang tussen risico (toekomstig) en issue (gerealiseerd). Bij portfolio-rapportage moet een PMO handmatig top-10-risks samenstellen uit verspreide bronnen.

Deze spec voegt aan planix toe: per project een risk register met gestandaardiseerde probability × impact = score-methodiek, mitigation-acties als planix-tasks (cross-link naar bestaande task-functionaliteit), automatische status-tracking (open / in-behandeling / gemitigeerd / gerealiseerd / geaccepteerd), conversie van een gerealiseerd risico naar een actief issue met aparte resolution-workflow, en portfolio-niveau rapportages (top-10 risks per organisatie, risico-trendlijnen, mitigatie-effectiviteit). Volledig conform NEN-ISO 31000 risk management en NEN-ISO/IEC 27005 voor informatiebeveiligingsrisico's.

## Data Model

**Risk** (nieuw schema, register `planix-risk`):
- `projectId` (relatie naar Project)
- `programmaId` (relatie, nullable)
- `risicoCode` (string, auto-generated bv. "RISK-2026-0042")
- `titel`, `beschrijving` (string)
- `categorie` (enum: scope / planning / budget / kwaliteit / resources / leverancier / juridisch / informatieveiligheid / privacy / stakeholder)
- `probability` (integer 1-5, schaal: zeer-laag / laag / midden / hoog / zeer-hoog)
- `impact` (integer 1-5, zelfde schaal)
- `score` (integer 1-25, computed: probability × impact)
- `risicobereidheid` (enum: laag / midden / hoog - drempel waarboven mitigatie verplicht is)
- `eigenaar` (user-reference)
- `status` (enum: geidentificeerd / beoordeeld / gemitigeerd-in-uitvoering / gemitigeerd-afgerond / gerealiseerd / geaccepteerd / vervallen)
- `identificatieDatum`, `volgendeReviewDatum` (date)
- `gerealiseerdDatum` (date, nullable)
- `linkedIssueId` (relatie naar Issue, nullable)

**MitigatieActie** (nieuw schema):
- `riskId` (relatie)
- `taakId` (relatie naar Planix Task, primary actie-tracking)
- `actieType` (enum: vermijden / verminderen / overdragen / accepteren)
- `verwachteScoreReductie` (integer, hoe veel score-punten daalt naar verwachting)
- `werkelijkeScoreReductie` (integer, nullable, ingevuld bij afronding)
- `streefDatum` (date)
- `verantwoordelijke` (user)
- `kosten` (decimal, optioneel)

**Issue** (nieuw schema):
- `projectId` (relatie)
- `issueCode` (string, auto-generated)
- `titel`, `beschrijving`
- `bronRiskId` (relatie naar Risk, nullable - bij conversie uit risico)
- `severity` (enum: laag / midden / hoog / kritiek)
- `urgentie` (enum: laag / midden / hoog)
- `status` (enum: open / in-behandeling / wachtend-op-info / opgelost / gesloten / heropend)
- `meldingsDatum`, `streefDatum`, `oplossingsDatum`
- `gemeldDoor`, `toegewezenAan`, `goedgekeurDoor` (user-references)
- `resolutionType` (enum, bij sluiting: opgelost / workaround / niet-reproduceerbaar / afgewezen / duplicaat)
- `oplossing` (string)
- `geleerdeLessen` (string, voor portfolio-leereffect)

**RiskReview** (nieuw schema, voor periodieke herbeoordeling):
- `riskId` (relatie)
- `reviewDatum` (date)
- `reviewer` (user)
- `nieuweProbability`, `nieuweImpact` (integer)
- `wijzigingsRedenen` (string)

## Requirements

### REQ-001: Risico identificeren met verplichte beoordeling

GIVEN een gebruiker met projectrol projectleider of risk-officer
WHEN deze "Nieuw risico" registreert binnen een project
THEN eist het systeem minimaal: titel, beschrijving, categorie, eigenaar, en initiele probability + impact - en berekent automatisch de `score` waarmee de risicostatus wordt afgeleid (score >= 12 = "hoog risico", verplicht mitigatieplan binnen 5 werkdagen).

### REQ-002: Mitigatieactie koppelt aan planix-task

GIVEN een Risk met status `beoordeeld` en `score` boven projectspecifieke risicobereidheid-drempel
WHEN de risico-eigenaar een MitigatieActie aanmaakt
THEN genereert het systeem automatisch een Planix Task met de actiebeschrijving, kent deze toe aan `verantwoordelijke`, plant op `streefDatum`, en houdt de bidirectionele link in stand zodat taakvoortgang direct de mitigatie-status in het risk register reflecteert.

### REQ-003: Automatische escalatie bij overschreden streefdatum

GIVEN een MitigatieActie waarvan de gekoppelde Task de streefdatum heeft overschreden en nog open is
WHEN de dagelijkse risk-monitoring-job draait
THEN stuurt het systeem een escalatie-notificatie naar de risico-eigenaar en projectleider, markeert de mitigatie als `vertraagd`, en bij twee weken vertraging escaleert door naar de programmamanager (indien `programmaId` aanwezig) of stuurgroep-eigenaar.

### REQ-004: Periodieke risk-review verplicht

GIVEN een Risk met status `gemitigeerd-in-uitvoering` of hoger
WHEN de `volgendeReviewDatum` nadert (binnen 7 dagen)
THEN notificeert het systeem de eigenaar, en bij overschrijding zonder review zet het systeem de risk-status automatisch op een "review-overdue"-flag die in alle rapportages wordt getoond - geen risico mag langer dan 90 dagen zonder review blijven.

### REQ-005: Risico-naar-issue-conversie

GIVEN een Risk dat zich realiseert (status wordt gezet op `gerealiseerd`)
WHEN de risico-eigenaar "Converteer naar issue" kiest
THEN maakt het systeem een Issue-object aan met `bronRiskId` ingevuld, pre-populeert titel/beschrijving/severity (afgeleid van impact-score), genereert een Planix Task voor de oplossing, en sluit de mitigatie-loop af - waarbij portfolio-rapportage de conversie-ratio risico→issue toont voor leereffect.

### REQ-006: Issue-resolution met geleerde lessen

GIVEN een Issue met status `opgesloten` voor wijziging
WHEN de toegewezen oplosser de status naar `opgelost` zet
THEN eist het systeem invulling van `resolutionType`, `oplossing` (min 50 tekens), en `geleerdeLessen` (min 50 tekens als severity=hoog of kritiek) - en stuurt de issue door naar de melder voor goedkeuring voordat de definitieve sluiting volgt.

### REQ-007: Top-10 risks portfolio-rapportage

GIVEN een gebruiker met rol PMO of portfolio-manager
WHEN deze het portfolio-dashboard opent
THEN toont het systeem de top-10 risks gerangschikt op `score` over alle projecten van de organisatie, met drill-through naar elk individueel risico, een heatmap (probability × impact matrix), en trendlijnen voor totaal-risico-blootstelling over tijd (laatste 12 maanden).

### REQ-008: Mitigatie-effectiviteit-rapportage

GIVEN afgeronde MitigatieActies (`werkelijkeScoreReductie` ingevuld)
WHEN een PMO de mitigatie-effectiviteit-rapportage genereert
THEN berekent het systeem per categorie en per actieType: gemiddelde verwachte score-reductie vs werkelijke score-reductie, totale mitigatie-kosten vs voorkomen impact (impact in euro's indien risico geconverteerd zou zijn naar issue), en presenteert dit als een tabel + barchart - input voor toekomstige risk-bereidheid-instelling.

## Standards

- **NEN-ISO 31000:2018** (Risk management - guidelines, basis voor methodiek)
- **NEN-ISO/IEC 27005** (informatiebeveiligingsrisico's, categorie informatieveiligheid)
- **NEN-ISO 22301** (business continuity raakvlakken)
- **PRINCE2** (Risk Theme, projectgovernance NL-overheid)
- **IPMA Individual Competence Baseline 4.0** (risk management competence)
- **AVG art. 35** (DPIA-overlap voor categorie privacy)
- **BIO** (informatiebeveiligingsrisico's gemeenten/provincies)
- **PMBOK 7** (risk management knowledge area, internationale alignment)

## Cross-app

- **planix base / projects**: Risk hangt onder Project
- **planix tasks**: MitigatieActies zijn primaire Task-koppeling
- **mydash**: portfolio-dashboard top-10 risks, heatmap-widget, trendlijnen
- **decidesk**: stuurgroep-besluiten over geaccepteerde risico's of grote mitigatie-budgetten
- **docudesk**: opslag risk-evaluatie-templates, DPIA-rapporten (voor privacy-risico's)
- **openregister**: data-laag (alle schemas)
- **n8n-nextcloud**: escalatie-flows (REQ-003) configureerbaar via n8n
- **shillinq-ensia**: informatiebeveiligingsrisico's vloeien terug naar ENSIA-bevindingen

## Target users

- **Projectleider**: primaire registratie + status-updates
- **Risk-officer / kwaliteitsmanager**: methodiek-bewaking, review-orkestratie
- **Programmamanager**: programma-overzicht, escalatie-niveau-2
- **PMO**: portfolio-rapportage, top-10 risks
- **Stuurgroep / opdrachtgever**: besluitvorming over hoog-impact risico's en mitigatiebudgetten
- **Actiehouder** (verantwoordelijke mitigatie): taakuitvoering
- **Auditor / IPMA-assessor**: read-only review van risk-process-volwassenheid
- **CISO**: subset informatieveiligheidsrisico's, koppeling naar BIO/ENSIA
