# Projects Specification

**Status**: idea

**Standards**: Schema.org CreativeWork, iCalendar VTODO (parent container reference)
**Feature tier**: MVP

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

## Purpose

A project is the top-level container for tasks and the kanban board in Planix. Projects group related work, define a team (members), and provide the kanban board (columns) that tasks flow through. Each project has exactly one implicit kanban board. Tasks without a column are in the project's backlog. Projects can optionally be linked to a Procest case for cross-app integration.

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for the full entity definitions.

| Property | Type | Required | Default |
|----------|------|----------|---------|
| `title` | string | Yes | — |
| `description` | string | No | — |
| `status` | enum: active, archived, completed | Yes | `active` |
| `color` | string (hex) | No | — |
| `icon` | string (emoji or MDI icon name) | No | — |
| `members` | string[] (user UIDs) | No | [] |
| `defaultAssignee` | string (user UID) | No | — |
| `caseReference` | string (Procest case UUID) | No | null |
| `labels` | string[] | No | [] |

## Requirements

### Requirement: Project Lifecycle [MVP]
The system MUST allow authenticated users to create, read, update, and archive projects.

#### Scenario: Create a project
- GIVEN a user is authenticated
- WHEN the user creates a project with a title
- THEN the system MUST store the project with status `active`
- AND the system MUST create the default column set (To Do, In Progress, Review, Done) as configured in admin settings
- AND the creating user MUST be added as the first project member

#### Scenario: Archive a project
- GIVEN a project has status `active`
- WHEN an admin or project creator archives the project
- THEN the system MUST set the project's status to `archived`
- AND the project MUST be hidden from the default project list
- AND all tasks within the project MUST remain accessible via the archived project view

#### Scenario: Member access control
- GIVEN a project has members [UserA, UserB]
- WHEN UserC (not a member) attempts to view the project board
- THEN the system MUST return a 403 Forbidden response
- AND the project MUST NOT appear in UserC's project list

#### Scenario: Procest bridge — case creates project
- GIVEN the Procest bridge is enabled in admin settings
- WHEN a Procest case creates a linked Planix project
- THEN the project MUST have `caseReference` set to the Procest case UUID
- AND a label `[Case: {caseNumber}]` MUST be applied to the project

#### Scenario: OpenRegister not installed
- GIVEN Planix is installed but OpenRegister is not
- WHEN any user opens Planix
- THEN the system MUST show a centered NcEmptyContent (no sidebar, no navigation) with an appropriate message
- AND admin users MUST see an "Install OpenRegister" button linking to the Nextcloud App Store

## User Stories

- As a team lead, I want to create a project so that I can group related tasks
- As a project creator, I want to add team members so that they can see and work on tasks
- As a user, I want to see a list of my active projects so that I can navigate between them quickly
- As an admin, I want to archive completed projects so that the project list stays manageable
- As a user bridging Procest, I want a Planix project automatically created from a case so that I can track case-related tasks on a kanban board

## Acceptance Criteria

- [ ] A project can be created with a title; other fields are optional
- [ ] Creating a project auto-generates default columns (configurable in admin settings)
- [ ] Creating user is automatically added as the first member
- [ ] Non-members cannot view or access a project's board or tasks
- [ ] Archiving a project removes it from the default list but preserves all tasks
- [ ] The project list shows active projects sorted by recent activity
- [ ] Projects are color-coded with the chosen hex color in the sidebar and list
- [ ] The OpenRegister dependency check shows NcEmptyContent when OpenRegister is absent
- [ ] `caseReference` links the project back to its Procest case (if applicable)

## Notes

- Project deletion is a destructive operation — requires confirmation dialog and admin permission
- Procest bridge integration (V1): the `caseReference` field allows a Procest case to own a Planix project; task completions may mirror back to the case status
- Admin setting `default_columns` defines which columns are created when a new project is initialized
- Future: project templates (V1) will pre-populate columns, labels, and default tasks from a template
