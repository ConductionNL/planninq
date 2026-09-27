# portfolio-overview delta for portfolio-status-overview

## ADDED Requirements

### Requirement: A portfolio office sees every project of a portfolio in one list

The portfolio overview MUST list, for a chosen portfolio, every project in it that the viewer can read, whether the viewer is a member or a portfolio reader, and in any lifecycle status. Each row MUST show the lifecycle status, progress, planned dates, the six aspect statuses with the last report date, and the budget and actual cost when the viewer may see money. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)").

#### Scenario: A portfolio manager opens the overview

- **GIVEN** a portfolio manager of "Ruimte", which holds three projects, one of them completed, and the manager is a member of none
- **WHEN** the manager opens the portfolio overview at /portfolio/status and picks "Ruimte"
- **THEN** all three projects are listed with their lifecycle status, progress and dates
- **AND** each aspect status is shown as a word and an icon, not only as a colour

### Requirement: The six aspects roll up to the portfolio

Above the list, the overview MUST show for each aspect how many projects are on track, at risk and off track, and the portfolio's state for that aspect, which is the worst state among its projects. Projects without a report MUST be counted apart as "No report". A report older than the reporting period MUST be marked out of date. Tier: Enterprise (docs/FEATURES.md has no row; tender 365739 requirements 4020 and 64437).

#### Scenario: The roll-up of a portfolio

- **GIVEN** the portfolio "Ruimte" with four projects: two report money on track, one reports money off track, and one has no report
- **WHEN** a portfolio manager opens /portfolio/status for "Ruimte"
- **THEN** the money roll-up reads "2 on track, 0 at risk, 1 off track, 1 no report"
- **AND** the portfolio's money state reads "Off track"
