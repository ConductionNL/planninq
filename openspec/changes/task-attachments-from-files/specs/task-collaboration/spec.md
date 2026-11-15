# task-collaboration delta for task-attachments-from-files

## ADDED Requirements

### Requirement: A member attaches a file that is already in Files

The Files tab of a task MUST let a project member pick a file from their Nextcloud Files. The
task MUST point at that file. The file MUST stay in its own folder, and planninq MUST NOT copy
it.

#### Scenario: Attach a file from Files

- **GIVEN** a project member on the Files tab of a task
- **AND** a file "Presentielijst.docx" in their Nextcloud Files
- **WHEN** they choose "Pick from Files" and select that file
- **THEN** the task lists "Presentielijst.docx" with its folder in Files
- **AND** the file stays in its folder, with no copy made
