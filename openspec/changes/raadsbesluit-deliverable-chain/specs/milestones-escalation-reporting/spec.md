# Milestones, Escalation & Reporting Specification (Delta)

**Status**: in-progress
**Scope**: planix + n8n
**OpenSpec changes**:
- [raadsbesluit-deliverable-chain](../../) — milestone management, nightly escalation, report generation

## Purpose

Specifies milestone tracking, automated escalation/notifications when milestones approach their due date or are missed, and final report generation upon chain completion.

## ADDED Requirements

### Requirement: Milestone Escalation [REQ-004]

The system SHALL run a nightly automated sweep (via n8n scheduler or planix cron) that:
1. Queries all upcoming `ChainMijlpaal` with `status = gepland` (not yet reached)
2. For each milestone, checks if today is 14, 7, or 0 days before `geplande_datum`
3. For each expired-but-not-reached milestone (today > `geplande_datum` AND `werkelijke_datum` is null), marks `status = gemist`
4. Sends escalation notifications to `verantwoordelijke_id` (owner) and to `griffie@gemeente.nl` (hardcoded)

**Notification content**:
- **14-day warning**: "Mijlpaal '[titel]' van raadsbesluit '[besluit_titel]' wordt over 14 dagen verwacht (dd-mm-jjjj). [Link naar chain-detail]"
- **7-day warning**: "Mijlpaal '[titel]' van raadsbesluit '[besluit_titel]' wordt over 7 dagen verwacht (dd-mm-jjjj). [Link naar chain-detail]"
- **0-day / today**: "Mijlpaal '[titel]' van raadsbesluit '[besluit_titel]' wordt vandaag verwacht. [Link naar chain-detail]"
- **Missed (overdue)**: "Mijlpaal '[titel]' van raadsbesluit '[besluit_titel]' is niet bereikt op gepland moment (dd-mm-jjjj). Status: GEMIST. [Link naar chain-detail]"

**Notification channels**:
- **Owner (verantwoordelijke_id)**: Nextcloud notification (in-app) + optionally e-mail (if opted in to escalation emails)
- **Griffie**: E-mail (always)
- **Raadsleden volgers**: E-mail (if they have `notify_op_mijlpaal = true` in `RaadslidVolgVoorkeur`)

**Event log entry**: Each escalation creates `ChainEventLog` with type `escalatie_notification_verzonden`, payload includes notification text, recipients, channel.

#### Scenario: 7-Day Escalation
- GIVEN `ChainMijlpaal` "Conceptnota gereed" with `geplande_datum = 2026-04-20`, `verantwoordelijke_id = user-pm-X`, `status = gepland`
- WHEN nightly sweep runs on 2026-04-13
- THEN system sends Nextcloud notification to user-pm-X, sends e-mail to griffie@gemeente.nl with milestone details and chain link

#### Scenario: Missed Milestone Marking
- GIVEN `ChainMijlpaal` "Conceptnota gereed" with `geplande_datum = 2026-04-10`, `werkelijke_datum = null`, `status = gepland`
- WHEN nightly sweep runs on 2026-04-13 (today > geplande_datum)
- THEN system updates `status = gemist`, sends "GEMIST" notification to verantwoordelijke and griffie, and triggers chain status auto-transition to `vertraagd` (if not already)

#### Scenario: Raadsled Following Milestone
- GIVEN raadslid Frank follows chain M-2026-014 with `RaadslidVolgVoorkeur.notify_op_mijlpaal = true`
- WHEN milestone reaches 7-day warning
- THEN Frank receives e-mail notification "Mijlpaal ... van raadsbesluit ... wordt over 7 dagen verwacht" (if his notify_kanaal = email)

### Requirement: Lange Termijn Agenda (LTA) Export [REQ-006]

The system SHALL export all active chain milestones to Excel and iCal formats for griffie calendar integration.

**Export endpoints**:
- `GET /api/chains/export/lta/excel?commissie={commissie}&quarter={Q1|Q2|Q3|Q4}&year={2026}`
  - Returns: Excel file with columns `[Datum, Besluit, Mijlpaal, Eigenaar, Rapportage-Format, Status]`
  - Filtered by: commissie (if specified), date range (if quarter specified)
  - Sorted: ascending by date

- `GET /api/chains/export/lta/ical?user_token={uuid}`
  - Returns: iCal feed (RFC 5545) with all upcoming milestones as VEVENT entries
  - Subscription URL is stable per user (token-based)
  - Milestones appear as calendar events: title `[BesluitTitel] – MijlpaalTitel`, location `Gemeentehuis`, description with chain link

**Excel template**:
```
| Datum     | Besluit     | Mijlpaal               | Eigenaar       | Rapportage-Format | Status    |
|-----------|-------------|------------------------|----------------|-------------------|-----------|
| 20-06-26  | M-2026-014  | Onderzoek afgerond     | PM Gezondheid  | commissie-stuk   | gepland   |
| 15-07-26  | RB-2026-031 | College-advies klaar   | PM Mobiliteit  | brief            | gepland   |
| 30-09-26  | M-2026-014  | Raadsstuk ingediend    | PM Gezondheid  | email            | gemist    |
```

**iCal event example**:
```
BEGIN:VEVENT
UID:chain-m-2026-014-milestone-1@gemeente.nl
DTSTART:20260630
SUMMARY:[M-2026-014] Onderzoekscommissie onderzoek afgerond
DESCRIPTION:Raadsbesluit: M-2026-014 Onderzoek inzet AED's wijkcentra\nMijlpaal: Onderzoekscommissie onderzoek afgerond\nEigenaar: PM Gezondheid\nStatus: gepland\nhttps://planix/chains/550e8400.../details
LOCATION:Gemeentehuis
STATUS:CONFIRMED
END:VEVENT
```

**Filtering**:
- By commissie: chain tagged with commissie or milestone `rapportage_aan = commissie`
- By date range: `geplande_datum` within specified quarter/year
- By status: optional filter on milestone status (gepland, bereikt, gemist)

#### Scenario: LTA-Export per Commissie
- GIVEN 47 active chains with 134 toekomstige milestones; Q2 2026 has 18 milestones for commissie "Ruimte"
- WHEN griffier requests "Export LTA – Commissie Ruimte – Q2 2026"
- THEN system returns Excel with 18 rows (date, besluit, mijlpaal, eigenaar, format, status), sorted by date

#### Scenario: iCal Feed for Outlook
- GIVEN griffier has user_token `abc123`
- WHEN griffier adds iCal subscription URL `https://planix/api/chains/export/lta/ical?user_token=abc123` to Outlook
- THEN Outlook syncs calendar events for all upcoming milestones; refreshes daily; events titled `[M-2026-014] Onderzoek afgerond`

### Requirement: Eindrapportage Generation [REQ-007]

The system SHALL automatically generate a final report (Markdown + PDF) when a chain transitions to `status = afgerond`. The report is attached to the original decidesk-besluit.

**Report generation trigger**: When `status_overall` changes to `afgerond` (via rollup engine), system calls `ChainReportService.generateReport(chain_id)`.

**Report structure** (Markdown template):
```markdown
# Uitvoeringsrapport Raadsbesluit [BESLUIT_ID]

**Titel**: [TITEL]
**Vastgesteld op**: [DATE]
**Afgerond op**: [TODAY]

## Samenvatting
[AUTO-GENERATED SUMMARY: X% voortgang, Y milestones bereikt, Z projecten afgerond]

## Gekoppelde Projecten en Zaken
- Project "[ProjectName]" — Status: AFGEROND, [X tasks completed]
- Zaak "[ZaakType]" — Status: AFGEHANDELD, [details]
- ...

## Milestones
| Mijlpaal | Geplande Datum | Werkelijke Datum | Status  |
|----------|---|---|---|
| Onderzoek afgerond | 30-06-26 | 28-06-26 | BEREIKT |
| College-advies | 15-07-26 | 14-07-26 | BEREIKT |

## Eigenaar Rapportage
[COPIED FROM chain.laatste_rapportage_tekst]

## Metadata
- Eigenaar collegelid: [NAAM]
- Eigenaar ambtelijk: [NAAM]
- Volgers: [COUNT] raadsleden
```

**PDF generation**:
- Calls `docudesk.generatePdf()` with Markdown template
- Returns PDF file; stored as `FileService` attachment on BesluitDeliverableChain
- Also attached to decidesk-besluit via cross-app reference

**Decidesk integration**:
- Once PDF generated, planix publishes cross-app event `chain.eindrapportage_klaar` with file URL
- Decidesk listener receives event, adds PDF as bijlage to original besluit
- Decidesk sends notification to griffie: "Eindrapportage voor raadsbesluit [ID] is beschikbaar"

**Template customization**:
- Griffie can customize report template per `besluit_type` in app config
- Template uses Handlebars syntax: `{{chain.titel}}`, `{{links.length}}`, `{{milestones | bereikt | length}}`

#### Scenario: Report Generation on Completion
- GIVEN chain for motie M-2026-014 with 2 milestones (both bereikt), 1 project (100%), 0 zaak
- WHEN all critical links reach 100% and all milestones bereikt
- THEN chain status → `afgerond`, `ChainReportService.generateReport()` is called:
  - Generates Markdown with milestones table, project summary, owner's narrative (latest_rapportage_tekst)
  - Calls docudesk to render PDF
  - Stores PDF as file attachment to BesluitDeliverableChain
  - Publishes `chain.eindrapportage_klaar` event
  - Decidesk receives event, attaches PDF to M-2026-014, sends griffie notification

#### Scenario: Template Customization
- GIVEN griffie wants motie-reports to include "Aanbevelingen voor vervolgacties"
- WHEN griffie updates template for `besluit_type = motie` in app config
- THEN for all future motie-completions, reports include new section

## Non-Functional Requirements

- **Escalation performance**: Nightly sweep of all upcoming milestones completes in <2 seconds
- **Escalation reliability**: Notifications are idempotent (same escalation cannot be sent twice for same milestone on same day)
- **Report generation**: Markdown → PDF conversion completes in <5s
- **Calendar sync**: iCal feed returns <1000 events per query; pagination for large sets
- **Audit**: All escalations, exports, and report generations logged in `ChainEventLog`

## Acceptance Criteria

- [ ] Nightly escalation sweep identifies upcoming milestones (14d, 7d, 0d thresholds)
- [ ] Escalation notifications sent to verantwoordelijke (Nextcloud) and griffie (e-mail)
- [ ] Raadsleden following chain with notify_op_mijlpaal=true receive milestone notifications
- [ ] Missed milestones marked as `gemist` by nightly sweep
- [ ] Excel LTA export returns correct milestones, sorted by date, filtered by commissie/quarter
- [ ] iCal feed produces valid RFC 5545 events, subscribable in Outlook/Google Calendar
- [ ] Report generation triggers on chain afgerond status
- [ ] Markdown template renders all chain data correctly
- [ ] PDF generated and attached to both chain and decidesk-besluit
- [ ] Report generation is idempotent (re-running doesn't create duplicate PDFs)

## Notes

- Nightly sweep scheduled via n8n workflow or Nextcloud cron; configuration in app settings
- iCal feed URL includes user_token for authentication (user can revoke/regenerate token in settings)
- Report templates support both Dutch and English; language inferred from municipality locale
