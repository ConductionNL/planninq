# time-work-type delta for time-timer-and-work-type

## ADDED Requirements

### Requirement: An admin keeps a list of work types

The admin settings MUST let an admin add, rename and retire work types, a list of names that is empty by default. Renaming SHALL offer to update the entries that already carry the old name. Retiring SHALL remove the name from the picker and SHALL leave existing entries unchanged. Tier: V1 (docs/FEATURES.md, time tracking; this change adds the row).

#### Scenario: An admin adds work types

- **GIVEN** an admin on the planninq admin settings, section "Work types"
- **WHEN** they add "Development", "Meeting" and "Support" and save
- **THEN** the time entry form offers those three names

### Requirement: Every time entry records its work type when types exist

When the work type list is not empty, the time entry form MUST require a work type, and the entry SHALL store the chosen name. When the list is empty, the form SHALL show no work type field. The work type SHALL be stored on planninq's time entry record, which keeps it after the hours move to humaniq. Tier: V1.

#### Scenario: An entry records its work type

- **GIVEN** work types "Development" and "Meeting", and Anna in the "Log time" form of "Sprint review"
- **WHEN** she enters 45 minutes, picks "Meeting" and saves
- **THEN** the stored entry has `workType` "Meeting"

#### Scenario: No work types means no field

- **GIVEN** an empty work type list
- **WHEN** Anna opens the "Log time" form
- **THEN** the form has no work type field and saves as before

### Requirement: The time report can be split by work type

The time report MUST offer a chart of logged minutes per work type. Tier: V1.

#### Scenario: The time report splits minutes by work type

- **GIVEN** entries of 120 minutes "Development" and 45 minutes "Meeting"
- **WHEN** a user opens the "Time spent" report
- **THEN** the "By work type" chart shows 120 for "Development" and 45 for "Meeting"
