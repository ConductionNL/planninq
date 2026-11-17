## ADDED Requirements

### Requirement: A schedule run is checked whole and written in order on the server

When a member confirms "Move all", the system MUST send the dragged task and every pushed task to
the server as one run. The server MUST refuse the run and write none of it when the caller does not
work on the project, a task of the run is not in the project, a date is not a valid day or a start
is after its due date, or a `blocks` link the run touches would leave a blocked task starting before
its blocker is due. A run that passes MUST be written in the run's order; the first write that
fails MUST stop the run, the tasks before it MUST stay moved, and the answer MUST name the task it
stopped at and how many were written. The timeline MUST tell the member where the run stopped.

#### Scenario: A run that breaks a link writes nothing

- **GIVEN** task Design blocking task Build, which starts Monday 9 November 2026
- **WHEN** a member's run moves Design's due date to Tuesday 10 November and leaves Build alone
- **THEN** the server refuses the run with a conflict and Design keeps its due date

#### Scenario: A write run stops at the first failure and reports it

- **GIVEN** a run moving Design, Build and Test, and OpenRegister refuses the write of Build
- **WHEN** the member chooses "Move all"
- **THEN** Design carries its new dates, Build and Test keep theirs
- **AND** the timeline says "Stopped at Build: its dates could not be saved. The tasks before it were moved."
