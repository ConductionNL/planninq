## Status

Draft — planix spec brief, 2026-05-21.

# Capacity Planning — Resource Scheduling

## Purpose

Planix moet kunnen beantwoorden wie wanneer wat doet, en — vooral — wie nog wat kan. Vandaag plannen veel teams projecten met een mix van Jira, Excel en post-its, met als gevolg dat dezelfde senior-developer op drie projecten 100% gepland staat terwijl een junior 40% leeg is. Capacity Planning introduceert een eerste-klas resource-laag in planix: per persoon een geconfigureerde capaciteit (uren per week, rekening houdend met part-time-percentage, vakantie, ziekte en non-billable-tijd zoals opleidingen of teamoverleg), per taak / sprint / project een forecast (toegewezen uren of story-points omgerekend), en een continue vergelijking tussen die twee. Onder-bezetting (capaciteit > forecast) signaleert wie nog capaciteit heeft voor extra werk; over-bezetting (forecast > capaciteit) escaleert vroeg, idealiter weken vóór de deadline, zodat herschikking of scope-cut nog kan. Skill-matching koppelt taken aan personen met de juiste competenties — geen Vue-taak op iemand die alleen .NET kan. Forecast-vs-actual sluit de loop: na afloop zien we wie hoeveel uren werkelijk besteed heeft, wat de forecast-accuracy verbetert.

## Data Model

- **ResourceProfiel**: persoon, fte (0.0-1.0), uren_per_week_nominaal, kosten_per_uur, locatie, beschikbaar_van, beschikbaar_tot.
- **Skill**: code, naam, categorie (taal/framework/domein/soft-skill).
- **PersoonSkill**: persoon, skill, niveau (junior/medior/senior/expert), zelfverklaard_of_geverifieerd, laatste_evidence-ref.
- **Afwezigheid**: persoon, type (vakantie/ziekte/opleiding/overig), van, tot, uren, status (gepland/geboekt/actueel).
- **CapaciteitWeek**: persoon, week (ISO-week), bruto_uren, afgetrokken_uren_afwezigheid, afgetrokken_uren_overhead, netto_beschikbaar_uren.
- **ForecastAllocatie**: persoon, project of taak, week, gepland_uren, skill-vereiste, status (concept/bevestigd).
- **WerkelijkUur**: persoon, project of taak, datum, uren, omschrijving, geboekt_op.
- **AlertSignaal**: type (overbezetting/onderbezetting/skill-mismatch/afwezigheid-conflict), persoon, periode, ernst, status (open/erkend/opgelost).

## Requirements

**REQ-001: Capaciteit afleiden uit fte + afwezigheid + overhead.** GIVEN een ResourceProfiel met fte=0.8 (32u nominaal) en een Afwezigheid van 16u die week en een overhead-policy van 4u/week (teamoverleg, mail), WHEN de week-capaciteit berekend wordt, THEN CapaciteitWeek.netto_beschikbaar_uren = 32 − 16 − 4 = 12 uren, en deze waarde is real-time afleidbaar zonder handmatige invoer.

**REQ-002: Skill-matching bij taak-toewijzing.** GIVEN een taak met skill-vereiste {vue: medior+, accessibility: junior+}, WHEN een planner het toewijs-dialoog opent, THEN planix toont kandidaten gesorteerd op (1) skill-match-score (alle vereisten gedekt, hoger niveau = beter), (2) beschikbare capaciteit in de taak-periode, (3) kosten_per_uur; ongeschikte personen worden niet verborgen maar wel grijs weergegeven met reden.

**REQ-003: Over- en onderbezetting-alerts.** GIVEN een persoon waarvoor in een week sum(ForecastAllocatie.gepland_uren) > CapaciteitWeek.netto_beschikbaar_uren, WHEN de planning-recalculate-job draait (event-driven bij elke mutatie + nightly), THEN een AlertSignaal van type=overbezetting wordt gecreëerd of bijgewerkt met ernst gebaseerd op overschrijdings-percentage; onderbezetting (>20% restcapaciteit en taken in backlog die deze skills vragen) creëert een ander signaal richting resource-manager.

**REQ-004: Multi-week forecast horizon.** GIVEN een planner opent de capaciteit-view, WHEN de view geladen wordt, THEN planix toont een heatmap van 12 weken vooruit per persoon, met cellen gekleurd naar bezettings-percentage (0-50% groen, 50-90% geel, 90-110% oranje, >110% rood); klik op een cel toont breakdown per ForecastAllocatie.

**REQ-005: Vakantie/afwezigheid-import en conflict-detectie.** GIVEN een Afwezigheid wordt geboekt (handmatig of via koppeling met HR-systeem), WHEN opgeslagen, THEN planix herberekent CapaciteitWeek voor de getroffen weken, en als er ForecastAllocaties in die periode bestaan die nu over-budget zijn, wordt een AlertSignaal van type=afwezigheid-conflict gecreëerd met voorgestelde herplannings-opties (verschuif taak, herwijs aan andere persoon, scope-cut).

**REQ-006: Forecast-vs-actual analyse.** GIVEN ForecastAllocaties en bijbehorende WerkelijkUren over een gesloten periode, WHEN een manager de retrospective-view opent, THEN planix toont per persoon en per project de forecast/actual-ratio, de gemiddelde afwijking, en identificeert systematische over- of onderschatting per skill-categorie om toekomstige forecasts te kalibreren.

**REQ-007: Wat-als scenario's zonder live data te muteren.** GIVEN een planner wil "wat als deze nieuwe opdracht binnenkomt", WHEN scenario-mode wordt aangezet, THEN mutaties zijn lokaal aan het scenario gebonden; alerts en heatmaps tonen het hypothetische resultaat; pas bij "scenario toepassen" worden ForecastAllocaties bevestigd naar de live planning.

**REQ-008: Privacy-laag op individuele cijfers.** GIVEN niet alle gebruikers mogen alle persoons-data zien, WHEN een gebruiker zonder rol resource-manager een team-view opent, THEN individuele bezettings-percentages worden vervangen door "beschikbaar/beperkt/vol", en kosten_per_uur is niet zichtbaar; volledige cijfers alleen voor rollen resource-manager, finance, of de persoon zelf.

## Standards

- **ISO 21500 / ISO 21502** — project- en programma-management.
- **PRINCE2 / Agile Project Management** — resource-planning-praktijken.
- **OAuth 2.0 + SCIM** — voor HR-systeem-integratie (vakantie-import).
- **iCalendar (RFC 5545)** — voor afwezigheid-export naar persoonlijke agenda's.
- **ISO 27001** — toegang tot persoonsgebonden bezettings-data.

## Cross-app

- **openregister** — ResourceProfiel / Skill / Afwezigheid / ForecastAllocatie / WerkelijkUur schemas.
- **openconnector** — adapters naar HR-systemen (AFAS HR, Visma, SAP SuccessFactors) voor fte/afwezigheid-import.
- **financeq** — kosten_per_uur tarieven en doorbelasting per project.
- **purchaseq** — inkoop van externe capaciteit (ZZP/detachering) wordt resource binnen planix met dezelfde scheduling-logica.
- **docudesk** — opslag van skill-certificaten en evidence voor skill-niveaus.

## Target users

- **Resource manager / capacity planner** — primaire user; bezetting bewaken, allocaties wijzigen, alerts adresseren.
- **Project manager / scrum master** — taken toewijzen, deadlines bewaken, escaleren bij overbezetting.
- **Teamlid (developer/consultant)** — eigen capaciteit en planning inzien, vakantie aanvragen, uren boeken.
- **Lijnmanager** — onder-/overbezetting van directe rapportages overzien.
- **HR** — fte en verlof-data leveren via koppeling, niet handmatig in planix muteren.
- **Finance controller** — forecast-actual-ratio's voor projectmarge en doorbelasting.
