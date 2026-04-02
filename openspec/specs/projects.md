# Projects Specification

**Status**: in-progress

**Standards**: Schema.org CreativeWork, iCalendar VTODO (parent container reference)
**Feature tier**: MVP

**OpenSpec changes:**
- [register-schemas](../changes/register-schemas/) — defines the Project schema in planix_register.json

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

#### Scenario: Edit project metadata
- GIVEN a project creator or admin is viewing a project
- WHEN they edit the title, description, color, or icon and save
- THEN the system MUST update the project in OpenRegister
- AND the new title, color, and icon MUST be reflected in the sidebar and project list immediately

### Requirement: Member Management [MVP]
The system MUST allow project creators and admins to add and remove members after project creation.

#### Scenario: Add a member
- GIVEN a project creator opens the project settings
- WHEN they search for a Nextcloud user and click "Add member"
- THEN the system MUST add the user to the project's `members` array
- AND the user MUST immediately be able to access the project board and tasks

#### Scenario: Remove a member
- GIVEN a project has members [UserA, UserB] and UserB has tasks assigned
- WHEN the project creator removes UserB
- THEN the system MUST remove UserB from `members`
- AND UserB MUST no longer appear in the project list or board
- AND tasks assigned to UserB MUST remain assigned to them (not auto-reassigned)
- AND a warning MUST be shown: "UserB has N assigned tasks in this project"

#### Scenario: Leave a project
- GIVEN a non-creator project member is viewing a project
- WHEN the member clicks "Leave project"
- THEN the system MUST remove them from `members`
- AND if the user is the last member, the system MUST warn: "You are the last member. Leave anyway?"

### Requirement: Project Deletion [MVP]
The system MUST allow admins and project creators to permanently delete a project.

#### Scenario: Delete a project
- GIVEN a project exists with tasks
- WHEN a Nextcloud admin or the project creator clicks "Delete project" and confirms
- THEN the system MUST delete the project, all its tasks, all linked TimeEntries, and all columns
- AND the project MUST be removed from all members' project lists immediately

#### Scenario: Delete confirmation with task count
- GIVEN a project has tasks
- WHEN the user initiates project deletion
- THEN the confirmation dialog MUST state: "This will permanently delete {N} tasks and all their time entries. This cannot be undone."

## User Stories

- As a team lead, I want to create a project so that I can group related tasks
- As a project creator, I want to add team members so that they can see and work on tasks
- As a user, I want to see a list of my active projects so that I can navigate between them quickly
- As an admin, I want to archive completed projects so that the project list stays manageable
- As a user bridging Procest, I want a Planix project automatically created from a case so that I can track case-related tasks on a kanban board
- As a project creator, I want to update the project title, color, and icon so that it stays recognizable as scope evolves
- As a project creator, I want to remove a member who has left the team, with a warning about their assigned tasks
- As a team member, I want to leave a project I no longer contribute to so that my project list stays relevant
- As an admin, I want to permanently delete a project and all its data so that stale projects don't clutter the system

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
- [ ] Project title, description, color, and icon can be edited; changes reflect immediately in sidebar and list
- [ ] Members can be added by searching Nextcloud users; added members gain immediate board access
- [ ] Removing a member shows a warning if they have assigned tasks; tasks remain assigned after removal
- [ ] A member can leave a project via "Leave project"; last-member warning shown before confirming
- [ ] Deleting a project requires admin or creator permission and a confirmation dialog stating task/entry count
- [ ] Project deletion cascades to all tasks, columns, and TimeEntries

## Notes

- Project deletion is a destructive operation — requires confirmation dialog and admin permission
- Procest bridge integration (V1): the `caseReference` field allows a Procest case to own a Planix project; task completions may mirror back to the case status
- Admin setting `default_columns` defines which columns are created when a new project is initialized
- Future: project templates (V1) will pre-populate columns, labels, and default tasks from a template
