---
status: draft
---
# Raadsbesluit Deliverable Chain (decidesk → planix → procest → mydash)

## Purpose

In een gemiddelde Nederlandse gemeente neemt de gemeenteraad 200-400 raadsbesluiten per jaar. Elk besluit draagt het college op iets te doen: een beleid uit te voeren, een budget te besteden, een verordening te implementeren, een initiatief van de stad te honoreren. Wat daarna gebeurt is voor de meeste raadsleden een black box. Het college rapporteert in de bestuursrapportage (vaak twee per jaar) of in de jaarrekening, maanden of jaren na het besluit. Wanneer een raadslid tussendoor vraagt "hoever staat het met motie M-2025-073?", moet de griffier ambtelijk navragen, het antwoord komt schriftelijk in een volgende cyclus, en intussen is de actualiteit weg. Het gevolg is een structureel **vertrouwens- en informatie-tekort tussen raad en college**, dat de afgelopen jaren door commissies (Van Aartsen, Van Zwol) is benoemd als één van de grootste knelpunten in de lokale democratie.

Deze spec introduceert een **cross-app deliverable chain** die elke raadsbesluit automatisch koppelt aan de operationele uitvoering en die uitvoering live zichtbaar maakt voor raad én griffie. Concrete chain: een besluit in decidesk → 0..n planix projecten (de feitelijke uitvoering) → 0..n tasks per project (de werkpakketten) → 0..n procest zaken (de afgehandelde dossiers) → 0..n indicatoren (de meetbare resultaten) → één live status op mydash. Bij elke statuswijziging in de keten (taak gereed, project afgesloten, zaak afgehandeld) propageert een event naar boven, en de raadsbesluit-pagina toont een rollup met percentage voortgang, gerealiseerde mijlpalen, en de eerstvolgende geplande mijlpaal. De griffie configureert per besluit "uiterste rapportage-datum" en escalatie-regels; mydash toont een rood blokje op het griffiers-dashboard wanneer een besluit dreigt over tijd te gaan.

In scope: (a) datamodel `besluit_deliverable_chain` als overkoepelende entiteit; (b) drie koppelingsmechanismen (handmatig, op basis van AI-suggestie uit besluit-tekst, en via templates per besluit-type); (c) rollup-engine die voortgang van onderliggende projecten/zaken naar besluit-niveau aggregeert; (d) automatische status-updates naar de griffie en optionele e-mailrapportage naar individuele raadsleden die een besluit "volgen"; (e) mijlpaal-bewaking met escalatie wanneer een uiterste datum nadert; (f) een raadslid-portaal met "mijn moties / mijn besluiten" lijst en de huidige status; (g) export naar het Lange Termijn Agenda-formaat (LTA) dat griffies typisch onderhouden.

Buiten scope: het juridisch-formele afhandelen van raadsbesluiten (blijft decidesk), de daadwerkelijke project- en taakuitvoering (planix), de zaakafhandeling (procest), de financiële realisatie (financeq — wel referenceerbaar). Deze spec is de cross-app **glue** die deze appartelijk geboorden processen samenbindt tot één keten met één gezicht naar de raad.

## Data Model

Vier nieuwe schemas in `planix/raadsbesluit-deliverable-chain/`, plus enkele velden-toevoegingen aan bestaande schemas via cross-app extension:

**besluit_deliverable_chain**: `uuid`, `decidesk_besluit_id` (FK, required), `besluit_titel` (denormaliseerd cache), `besluit_type` ('raadsbesluit'|'motie'|'amendement'|'toezegging'|'schriftelijke_vraag'), `vastgesteld_op` (date), `uiterste_rapportage_datum` (date, nullable — wanneer college moet rapporteren), `eigenaar_collegelid_id` (FK users), `eigenaar_ambtelijk_id` (FK users — de programma- of teammanager), `status_overall` (enum: niet_gestart / in_uitvoering / afgerond / vertraagd / on_hold / afgewezen_door_college), `voortgang_percentage` (decimal 0-100, auto-berekend), `laatste_rapportage_op` (datetime), `laatste_rapportage_tekst` (text — door verantwoordelijke ingevuld), `volgers_user_ids` (array — raadsleden die updates willen), `bbv_doel_ids` (array, optionele back-link naar bbv-programma-tree), `tags` (array).

**chain_link**: `uuid`, `chain_id` (FK), `link_type` (enum: 'planix_project'|'planix_task'|'procest_zaak'|'docudesk_document'|'externe_url'), `linked_entity_id` (UUID), `linked_entity_label` (denormaliseerd cache), `volgorde` (int), `is_kritiek_pad` (bool — telt mee voor voortgang), `verwacht_gereed_op` (date), `gereed_op` (date, nullable), `toelichting` (text), `gemaakt_door_id`, `gemaakt_via` (enum: 'handmatig'|'ai_suggestie'|'template'|'auto_event').

**chain_mijlpaal**: `uuid`, `chain_id` (FK), `volgorde`, `titel`, `omschrijving`, `geplande_datum` (date), `werkelijke_datum` (date, nullable), `status` (gepland|bereikt|gemist|opgeschoven), `verantwoordelijke_id`, `rapportage_aan` (enum: 'griffie'|'commissie'|'raad'|'extern'), `rapportage_format` ('email'|'brief'|'commissie-stuk'|'open-data').

**chain_event_log**: `uuid`, `chain_id` (FK), `event_type` (project_aangemaakt|task_gereed|zaak_geopend|zaak_afgehandeld|mijlpaal_bereikt|status_gewijzigd|rapportage_verzonden|...), `bron_app` (planix|procest|decidesk|mydash|systeem), `bron_entity_id` (UUID), `timestamp`, `actor_id` (nullable bij systeem-events), `payload` (JSON met details), `propagated_to_overall` (bool — true wanneer dit event de rollup-status veranderde).

**raadslid_volg_voorkeur** (per-user-config): `uuid`, `user_id` (FK), `chain_id` (FK), `notify_op_mijlpaal` (bool), `notify_op_vertraging` (bool), `notify_op_afronding` (bool), `notify_kanaal` (enum: 'email'|'nextcloud-talk'|'app-only'), `gestart_op` (datetime).

Validaties:
- `besluit_deliverable_chain.decidesk_besluit_id` MUST verwijzen naar een bestaand decidesk-besluit met status "vastgesteld" of "aangenomen"; chain kan niet gemaakt worden voor verworpen/ingetrokken besluiten.
- `chain_link.linked_entity_id` MUST corresponderen met een bestaand object in de aangegeven app (via cross-app lookup); referentiële integriteit gecheckt bij create.
- `chain_mijlpaal.geplande_datum` mag niet vóór `chain.vastgesteld_op` liggen.
- `voortgang_percentage` is **niet** direct schrijfbaar via API; alleen door de rollup-engine gezet.
- `chain` heeft max 50 `chain_link` records; bij meer een waarschuwing dat de chain te complex wordt en gesplitst zou moeten worden.

Indexen: `decidesk_besluit_id` (uniek — één chain per besluit), `eigenaar_ambtelijk_id + status_overall`, `uiterste_rapportage_datum` (voor escalatie-query), `volgers_user_ids` (GIN voor raadslid-portaal query).

## Requirements

### REQ-001: Automatische Chain-aanmaak bij Besluitvaststelling

The system SHALL automatically create een `besluit_deliverable_chain` zodra een decidesk-besluit de status "vastgesteld" of "aangenomen" krijgt, met initiële status `niet_gestart` en lege koppelingen.

#### Scenario 1: motie aangenomen
- **GIVEN** decidesk bevat motie "M-2026-014 Onderzoek inzet AED's wijkcentra" met status "concept"
- **WHEN** de griffie de status flipt naar "aangenomen" tijdens raadsvergadering 2026-03-12
- **THEN** wordt automatisch een chain aangemaakt met `decidesk_besluit_id = M-2026-014`, `besluit_type = 'motie'`, `status_overall = 'niet_gestart'`, en de behandelend portefeuillehouder als `eigenaar_collegelid_id`

#### Scenario 2: ingetrokken motie geen chain
- **GIVEN** motie M-2026-015 wordt door de indiener ingetrokken voor stemming
- **WHEN** decidesk de status zet op "ingetrokken"
- **THEN** wordt geen chain aangemaakt en bestaande chains (bij heropening) blijven onaangetast

### REQ-002: AI-gesuggereerde Koppelingen op Besluit-tekst

The system SHALL aan eigenaren voorstellen voor `chain_link` records suggereren op basis van een LLM-analyse van de besluit-tekst, met bestaande planix-projecten en procest-zaaktypes als candidate set.

#### Scenario 1: project-suggestie
- **GIVEN** raadsbesluit "RB-2026-031 Vaststellen Beleidsnota Mobiliteit 2026-2030" met tekst die fietsstraten, snelheidsremmers en P+R noemt
- **WHEN** de ambtelijk eigenaar de chain opent en klikt "Stel koppelingen voor"
- **THEN** toont de UI 4 voorgestelde planix-projecten ("Fietsstraat Hoofdweg", "30km-zone wijk Noord", "P+R De Mars uitbreiding", "Mobiliteitsvisie burgerparticipatie") elk met confidence-score en de citatie uit besluit-tekst die de match onderbouwt

#### Scenario 2: koppeling accepteren
- **GIVEN** voorgestelde koppeling "Fietsstraat Hoofdweg" (confidence 0.92)
- **WHEN** de eigenaar klikt "Accepteer en koppel"
- **THEN** wordt een `chain_link` record gemaakt met `link_type='planix_project'`, `gemaakt_via='ai_suggestie'`, en de chain `status_overall` wisselt naar `in_uitvoering`

### REQ-003: Voortgang-rollup met Gewogen Berekening

The system SHALL `voortgang_percentage` op de chain berekenen als gewogen gemiddelde van de voortgang van alle `chain_link` records met `is_kritiek_pad=true`, waarbij planix-projecten task-completion gebruiken, procest-zaken open/afgehandeld telling, en mijlpalen 0/100 binair.

#### Scenario 1: gewogen rollup
- **GIVEN** chain met 3 kritieke koppelingen: project A (60% taken klaar), project B (100% klaar), zaak C (afgehandeld = 100%)
- **WHEN** de rollup-engine draait (na een task-update event)
- **THEN** wordt `voortgang_percentage = (60+100+100)/3 = 86.67`, opgeslagen, en een `chain_event_log` record `voortgang_bijgewerkt` aangemaakt

#### Scenario 2: niet-kritieke koppeling telt niet mee
- **GIVEN** chain met 2 kritieke koppelingen (gemiddeld 80%) en 1 niet-kritieke (50%)
- **WHEN** rollup-engine draait
- **THEN** is `voortgang_percentage = 80` (niet-kritieke wordt genegeerd)

### REQ-004: Mijlpaal-escalatie naar Griffie

The system SHALL elke nacht een sweep doen over alle `chain_mijlpaal` records en bij mijlpalen die over 14, 7, of 0 dagen verlopen automatisch een notificatie sturen naar de eigenaar én de griffie.

#### Scenario 1: 7-dagen-waarschuwing
- **GIVEN** mijlpaal "Conceptnota gereed" met `geplande_datum = 2026-04-20` en status `gepland`, vandaag is 2026-04-13
- **WHEN** de nightly sweep draait
- **THEN** wordt een Nextcloud-notificatie naar de eigenaar gestuurd ("Mijlpaal over 7 dagen: ...") en een e-mail naar `griffie@gemeente.nl` met chain-link

#### Scenario 2: gemiste mijlpaal markering
- **GIVEN** mijlpaal met `geplande_datum = 2026-04-10`, vandaag is 2026-04-13, `werkelijke_datum` nog leeg
- **WHEN** de nightly sweep draait
- **THEN** wordt de mijlpaal status `gemist`, de chain status_overall `vertraagd`, en een gemiste-mijlpaal kaart verschijnt op het griffiers-dashboard

### REQ-005: Raadslid Volg-portaal

The system SHALL elk raadslid een persoonlijk portaal bieden met "besluiten die ik volg" en "moties die ik heb ingediend", met live status en notificatie-instellingen per chain.

#### Scenario 1: volg-actie
- **GIVEN** raadslid Frank logt in en opent raadsbesluit "RB-2026-031"
- **WHEN** Frank klikt "Volg deze besluit" en kiest notificatie "bij mijlpalen + bij afronding via e-mail"
- **THEN** wordt een `raadslid_volg_voorkeur` record gemaakt, `volgers_user_ids` op de chain bijgewerkt, en Frank ziet RB-2026-031 in zijn "Mijn besluiten"-lijst met huidige voortgang 23%

#### Scenario 2: notificatie bij afronding
- **GIVEN** Frank volgt RB-2026-031 met notify_op_afronding=true
- **WHEN** de chain status_overall flipt naar `afgerond` na de laatste taak
- **THEN** ontvangt Frank een e-mail "Het besluit RB-2026-031 dat u volgt is afgerond" met link naar de eindrapportage

### REQ-006: Lange Termijn Agenda (LTA) Export

The system SHALL een Lange Termijn Agenda exporteren naar Excel en/of iCal, met alle chain-mijlpalen gerangschikt op datum, gefilterd op commissie/onderwerp, ten behoeve van griffie-onderhoud.

#### Scenario 1: LTA-export per commissie
- **GIVEN** 47 actieve chains met in totaal 134 toekomstige mijlpalen
- **WHEN** de griffier kiest "Exporteer LTA — commissie Ruimte — Q2 2026"
- **THEN** wordt een Excel gegenereerd met kolommen `datum, besluit, mijlpaal, eigenaar, rapportage_format`, gefilterd op de 18 mijlpalen die binnen Q2 in commissie Ruimte vallen

#### Scenario 2: iCal feed voor agenda
- **GIVEN** de griffier wil mijlpalen integreren in zijn Outlook-agenda
- **WHEN** hij de unieke iCal-URL voor zijn user-token gebruikt in Outlook subscriptions
- **THEN** verschijnen alle toekomstige mijlpalen als calendar-events met titel `[chain.titel] — mijlpaal.titel`, locatie `gemeentehuis`, beschrijving met chain-link

### REQ-007: Eindrapportage-generatie naar Decidesk

The system SHALL bij `status_overall = 'afgerond'` automatisch een conceptrapportage genereren (Markdown + PDF via docudesk) en deze als bijlage toevoegen aan het oorspronkelijke decidesk-besluit voor griffie-publicatie.

#### Scenario 1: conceptrapportage
- **GIVEN** chain voor motie M-2026-014 wordt afgerond (alle kritieke koppelingen 100%)
- **WHEN** de rollup-engine status flipt naar `afgerond`
- **THEN** wordt een rapportage-template gevuld met alle mijlpalen (datums + bereikt), alle gekoppelde projecten + uitkomsten, indicator-metingen (indien gekoppeld), de rapportage als concept toegevoegd aan M-2026-014 in decidesk, en de eigenaar krijgt taak "Review en verstuur rapportage"

#### Scenario 2: rapportage-template configuratie
- **GIVEN** griffie wil rapportage per besluit-type een ander format
- **WHEN** in app-settings de template voor `besluit_type = 'motie'` wordt aangepast
- **THEN** wordt voor toekomstige motie-afrondingen het nieuwe template gebruikt

### REQ-008: Mydash Griffie-dashboard Widget

The system SHALL een `griffie-chain-overview` mydash-widget leveren die alle chains samenvat in een traffic-light table met filters op portefeuille, commissie, en deadline-window.

#### Scenario 1: widget-overzicht
- **GIVEN** 47 actieve chains, waarvan 5 `vertraagd`, 12 `in_uitvoering`, 8 `niet_gestart`, 22 mengeling
- **WHEN** de griffier zijn dashboard opent
- **THEN** toont de widget bovenaan 3 KPI-tegels (totaal-actief, vertraagd, deze-week-deadline) en daaronder een gesorteerde lijst met de 5 vertraagde chains bovenaan, elk klik-baar naar de chain-detail

### REQ-009: Cross-app Event Propagation

The system SHALL listen naar events van planix (`project.status_changed`, `task.completed`), procest (`zaak.status_changed`), en decidesk (`besluit.amendment`) en bij elk relevant event de chain-status hercalculen + audit-loggen.

#### Scenario 1: task-completion propagatie
- **GIVEN** chain met gekoppelde project P-2026-014 waar 5 van 8 taken open zijn (37.5% klaar)
- **WHEN** een gebruiker de 6e taak completes in planix → planix publiceert `task.completed` event
- **THEN** ontvangt chain-listener het event, project-progress wordt 62.5%, rollup-engine herberekent `voortgang_percentage` van de chain, en een `chain_event_log` `voortgang_bijgewerkt` wordt geschreven

#### Scenario 2: besluit-amendment behoudt chain
- **GIVEN** raadsbesluit RB-2026-009 met actieve chain (3 koppelingen, 45% voortgang)
- **WHEN** de raad het besluit amendeert (nieuw versie, oude versie blijft historisch), decidesk publiceert `besluit.amendment` met `new_besluit_id`
- **THEN** wordt de bestaande chain `decidesk_besluit_id` bijgewerkt naar new_id, een audit-log entry geschreven, en alle koppelingen blijven intact

### REQ-010: Permissions en Beslotenheid

The system SHALL respect besloten-beleid van besluiten: chains voor besluiten in besloten vergadering zijn alleen zichtbaar voor `besluit_eigenaar`, `griffie`, en expliciet gemachtigde users; publieksversie filtert ze weg.

#### Scenario 1: besloten chain afgeschermd
- **GIVEN** raadsbesluit RB-2026-007 over personeelszaak met `decidesk.openbaar = false`
- **WHEN** een willekeurig raadslid zonder fractievoorzitter-rol de chain-lijst opent
- **THEN** ontbreekt deze chain in de lijst; directe URL retourneert 403 met `{"error":"besloten_chain"}`

## Standards & Sources

- **Gemeentewet art. 169** — actieve informatieplicht college aan raad; deze spec geeft daar een continue-livestream-implementatie aan in plaats van periodieke rapportage.
- **Gemeentewet art. 155a/b** — recht van enquête, recht van onderzoek; chain-trace is bewijsmateriaal voor onderzoekscommissies.
- **Provinciewet art. 167** — vergelijkbaar voor provincies (informatieplicht GS aan PS).
- **Waterschapswet art. 90** — equivalent voor waterschappen.
- **Code interbestuurlijke verhoudingen** — afspraken transparantie raad ↔ college, doorvertaling van rijksniveau.
- **VNG Modelverordening raadsondersteuning** — basis voor griffie-rolmodel.
- **Handreiking Lange Termijn Agenda (VNG/Raadsledenacademie)** — beschrijft LTA-praktijk die deze spec digitaliseert; iCal-export gebaseerd op de typische LTA-structuur die griffies hanteren.
- **VNG-handreiking actieve informatieplicht** — checklist voor wat het college proactief moet melden.
- **GEMMA 2 procesarchitectuur "Raadsbesluit afhandelen"** — referentieproces dat de canonical happy-path van besluit naar afronding beschrijft.
- **GEMMA referentiecomponent "Bestuurlijke besluitvorming"** — koppeling met decidesk.
- **Wet open overheid (Woo)** — categorieën actief openbaar; raadsbesluiten + afhandeling vallen onder informatiecategorie "vergaderstukken decentrale overheden" + "convenanten" + "beschikkingen".
- **Awb art. 4:8 en 4:13** — termijnen voor beslissingen op aanvraag; chain-mijlpalen kunnen automatisch op Awb-termijnen schedulen.
- **Code "Goed openbaar bestuur" (BZK)** — principes van responsiviteit, transparantie en lerend bestuur.
- **VNG-Code voor informatievoorziening overheid (i-NUP)** — basis voor digitale informatieoverdracht.
- **iCal RFC 5545** — voor LTA-feed naar Outlook/Google Calendar/Nextcloud Calendar.
- **CloudEvents v1.0** — event-format voor cross-app event propagation tussen decidesk/planix/procest.
- **W3C ActivityStreams 2.0** — overweging voor toekomstige federated activity-stream van besluit-events.
- **NL Design System** — raadslid-portaal en publieksversie volgen NL DS-componenten.
- **WCAG 2.2 AA** — toegankelijkheid voor raadsleden (vergrijzende doelgroep) en burgers.

## Cross-app Integration

- **decidesk** (bron): publiceert `besluit.vastgesteld`, `besluit.amendment`, `besluit.ingetrokken`; consumeert chain-status voor besluit-detail-pagina (toont "uitvoering: 45% — bekijk chain"); ontvangt eindrapportage als bijlage.
- **planix** (host + uitvoering): publiceert `project.*`, `task.*` events; consumeert chains in de Gantt-view (besluit-context per project); deze spec leeft technisch in planix.
- **procest** (uitvoering): publiceert `zaak.*` events; consumeert chain-context wanneer zaak gekoppeld is (banner "deze zaak draagt bij aan motie M-2026-014").
- **mydash** (zichtbaarheid): griffie-widget + raadslid-portaal als mydash-tegels; portefeuillehouder krijgt persoonlijke chain-rollup.
- **docudesk** (rapportages): genereert eindrapportage-PDF op basis van chain-data + template.
- **bbv-programma-tree** (planix): chain-link via `bbv_doel_ids` voor strategische context (welk programma-doel dient dit besluit).
- **n8n** (automatisering): n8n-flow voor nightly mijlpaal-sweep + e-mail-verzending naar volgers; n8n-flow voor maandelijkse digest aan college.
- **opencatalogi** (open data): afgeronde chains met openbaar besluit publiceerbaar als opencatalogi-publicatie type "uitvoering raadsbesluit" voor lokale open-data portalen.

## Target Users

- **Griffiers en griffie-medewerkers**: primaire beheerders van de keten, configureren mijlpalen, escaleren overschrijdingen, exporteren LTA, beantwoorden ad-hoc raadslid-vragen over status van eigen moties, faciliteren commissie-vergaderingen met live-data ipv stuk-van-vorige-maand.
- **Ambtelijk eigenaren (programma- en teammanagers)**: koppelen projecten/zaken aan chains, rapporteren voortgang, bewaken eigen portfolio van chains, schrijven concept-eindrapportages.
- **Portefeuillehouders (collegeleden)**: gebruiken mydash-rollup om wekelijks bij te lopen, voorbereiding op vragenuur, commissie-stukken, persoonlijke aandachtspunten markeren ("dit besluit moet niet vertragen, persoonlijke belofte aan raad").
- **Raadsleden / Provinciale Statenleden / AB-leden**: volgen besluiten (eigen moties + collectief interessante), ontvangen notificaties op mijlpalen, halen ad-hoc voortgang op zonder griffie te belasten, gebruiken trace voor oppositie-werk en debat-voorbereiding.
- **Fractie-medewerkers / fractie-voorzitters**: bewaken voortgang van fractie-prioriteiten, signaleren wanneer college achterloopt op aangenomen amendementen.
- **Bestuurssecretaris / concerncontroller / gemeentesecretaris**: cross-portefeuille bewaking, signaleren van clusters van vertraging, escalatie richting directie, agendering in MT bij structurele overschrijdingen.
- **Burgers / journalistiek / lokale rekenkamer**: publieksversie inzicht in uitvoering openbare besluiten, materiaal voor rekenkamer-onderzoeken naar effectiviteit van bestuurlijke sturing, journalistieke verantwoording over coalitie-akkoorden.
- **Wetenschappelijk onderzoek (bestuurskunde-faculteiten)**: kwantitatief onderzoek naar follow-through rate van raadsbesluiten, vergelijking tussen gemeenten/coalities.
- **Provinciaal toezicht / interbestuurlijk toezicht (BZK)**: in artikel-12 of preventief toezicht scenario's audit-trail van besluit-uitvoering raadpleegbaar, signalen van bestuurlijke disfunctioneren.
- **Adviesbureaus die college-doorlichtingen uitvoeren**: gebruiken chain-data voor benchmark-analyses.
