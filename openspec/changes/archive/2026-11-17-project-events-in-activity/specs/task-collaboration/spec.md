# task-collaboration delta for project-events-in-activity

## ADDED Requirements

### Requirement: Project events are published to Nextcloud Activity

Planninq MUST publish project events to the Nextcloud activity stream: a project created,
archived, restored or deleted, and a person added to or removed from a project. The audience
MUST be the project's people, the person who made the change MUST NOT get an entry for it, and
the entries MUST show under the "Planninq" filter in the Activity app.

#### Scenario: Archiving a project reaches its members

- **GIVEN** `alice` and `bob` are members of the project "Vergunningen"
- **WHEN** `alice` archives the project
- **THEN** `bob`'s activity stream shows that `alice` archived "Vergunningen"
- **AND** `alice` sees no entry for her own change

#### Scenario: A new member reads that they were added

- **GIVEN** a project owner on the Members tab of the project "Vergunningen"
- **WHEN** the owner adds `carla`
- **THEN** `carla`'s activity stream shows that she was added to "Vergunningen"
