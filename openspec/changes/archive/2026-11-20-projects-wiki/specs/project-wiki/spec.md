# project-wiki delta for projects-wiki

## ADDED Requirements

### Requirement: A project member keeps wiki pages next to the tasks

Every project MUST have a wiki. Everyone who can read the project MUST be able to read its pages, and only people who may change tasks in the project MUST be able to write them. A page MUST hold a title and a Markdown body, and MUST accept attached images and files. Tier: V1 (docs/FEATURES.md has no row; it extends "Nextcloud Files (attachment via CnObjectSidebar)").

#### Scenario: Writing the first page

- **GIVEN** a project member on the Wiki tab at /projects/:id/wiki of a project without pages
- **WHEN** the member presses "Add page", enters the title "Werkafspraken", writes the body and saves
- **THEN** the page shows its title and formatted body at /projects/:id/wiki/:pageId
- **AND** the tree on the left lists "Werkafspraken"

#### Scenario: A viewer reads but cannot write

- **GIVEN** a person with the role "Viewer" on the project
- **WHEN** the viewer opens a page at /projects/:id/wiki/:pageId
- **THEN** the page body is shown
- **AND** there is no "Edit" or "Add page" button
- **AND** a PUT to `/apps/openregister/api/objects/planninq/wikiPage/{id}` with the viewer's session answers 403

### Requirement: A project member organises pages in a tree

Pages MUST form a tree within their project. A member who can write MUST be able to add a subpage, move a page under another page and reorder siblings. A page MUST NOT move under itself or one of its subpages, or into another project. The tree MUST be operable by keyboard. Tier: V1 (docs/FEATURES.md has no row).

#### Scenario: Moving a page under another page

- **GIVEN** the top-level pages "Werkafspraken" and "Overleg"
- **WHEN** a project member chooses "Move" on "Overleg", picks "Werkafspraken" as the new parent and confirms
- **THEN** the tree shows "Overleg" under "Werkafspraken"
- **AND** the page breadcrumb reads "Werkafspraken / Overleg"

#### Scenario: A page cannot move under its own subpage

- **GIVEN** "Overleg" is a subpage of "Werkafspraken"
- **WHEN** a project member tries to move "Werkafspraken" under "Overleg"
- **THEN** the move dialog does not offer "Overleg" as a target
- **AND** a PATCH that sets that parent through the object API is refused

#### Scenario: Browsing the tree by keyboard

- **GIVEN** a project member with focus on the page tree
- **WHEN** the member presses the Down arrow to reach "Werkafspraken", Right to open it and Enter on "Overleg"
- **THEN** the page "Overleg" opens
- **AND** the tree item "Werkafspraken" reports that it is expanded

### Requirement: A page keeps its history and one writer at a time

Every saved change MUST be kept in the page's history with author and time. A project manager MUST be able to restore an earlier version. While one person edits a page, others MUST see who is editing and MUST NOT be able to overwrite a newer version without seeing it. Tier: V1 (docs/FEATURES.md, "Audit trail on all task changes (CnObjectSidebar)"; no wiki row).

#### Scenario: Restoring an earlier version

- **GIVEN** a page edited three times
- **WHEN** a project manager opens "History" on the page, opens the first version and presses "Restore this version"
- **THEN** the page body is the first version again
- **AND** the history shows the restore as the newest entry

#### Scenario: A second writer sees the page is being edited

- **GIVEN** "Ada Jansen" has the editor open on a page
- **WHEN** another project member opens the same page
- **THEN** the page shows "Ada Jansen is editing this page"
- **AND** the "Edit" button is disabled until she saves or cancels
