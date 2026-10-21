# timetable-generator delta for timetabling-generator

## ADDED Requirements

### Requirement: A timetabler marks each wish as hard or soft

The system MUST let a timetabler record a wish on a teacher, a group, a room or one activity, and
MUST store for each wish whether it is hard (the generator must respect it) or soft (the generator
should respect it), with a weight of 1 to 3 for a soft wish. Only admins MAY write wishes; members of
`planninq-timetable` MAY read them. Tier: V1.

#### Scenario: A timetabler marks a wish as hard

- **GIVEN** the teacher `klaas` cannot teach on Wednesday afternoon
- **WHEN** the timetabler adds the wish "not on these periods" for `klaas` on Wednesday periods 5 to 8, strength hard
- **THEN** the wish is listed under `klaas` as hard
- **AND** a soft wish "preferably not on Friday period 8" for `klaas` is listed with its weight

### Requirement: A generated timetable never breaks a hard wish

The system MUST generate a scenario in which no teacher, group or room has two lessons in the same
period and no hard wish is broken. A lesson that cannot be placed without breaking a hard wish MUST
be listed as unplaced with the hard wish that blocked it. The system SHOULD keep soft wishes and MUST
report every soft wish it broke with its weight. Tier: V1.

#### Scenario: Generate a scenario that respects a hard wish

- **GIVEN** activities for group 3A and the hard wish that `klaas` does not teach on Wednesday periods 5 to 8
- **WHEN** the timetabler generates a scenario
- **THEN** the scenario has no lesson of `klaas` on Wednesday periods 5 to 8
- **AND** it reports 0 broken hard wishes

#### Scenario: A lesson that cannot be placed is listed with its reason

- **GIVEN** a teacher whose hard wishes leave fewer free periods than their lessons
- **WHEN** the timetabler generates a scenario
- **THEN** the lessons that do not fit are listed as unplaced
- **AND** each names the hard wish that blocked it

### Requirement: A timetabler compares scenarios before publishing one

The system MUST keep several scenarios per week pattern, generated or made from the current
timetable, and MUST show two or three of them side by side with the same measures: lessons placed and
unplaced, hard and soft wishes broken, teacher gaps, lessons per day and room use, marking the best
value per measure and listing the lessons that differ. Tier: V1.

#### Scenario: Compare a generated and an imported scenario

- **GIVEN** a scenario made from the current timetable and a generated scenario for the same week
- **WHEN** the timetabler opens the compare page with both
- **THEN** each measure shows both values with the better one marked
- **AND** the lessons whose period or room differ are listed

### Requirement: Publishing a scenario uses the draft review

The system MUST publish a chosen scenario by writing its lessons as draft `timetableSession` rows with
`sourceSystem` `planninq-generator` for every week of the scenario's window, so the existing draft
review and publish flow applies, and MUST refuse to replace lessons of that window that are already
published from that source. Tier: V1.

#### Scenario: Publish a scenario as drafts

- **GIVEN** a generated scenario for the weeks of 5 to 30 October
- **WHEN** the admin publishes it
- **THEN** its lessons exist as drafts for every week of that window
- **AND** a teacher sees their own draft lessons and nobody else does until the drafts are published
