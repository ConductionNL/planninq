# project-custom-fields Specification

## Purpose
Admins add fields to every project, such as a policy area or a contract value, and project managers fill them in on the Details tab; the server checks every value against its field.

## Requirements

### Requirement: An admin adds custom fields to projects

An admin MUST be able to define project fields with a label and a type: text, number, date, choice, person or yes/no, optionally required. Every project's Details tab MUST show an input per field, and its overview MUST show the filled values. The server MUST reject a value that does not match its field's type and an empty required field. Tier: Enterprise (docs/FEATURES.md, "Custom task fields"; the project variant has no dedicated row).

#### Scenario: Adding a choice field

- **GIVEN** an admin on Beheer, Project fields at /project-fields
- **WHEN** the admin adds the field "Beleidsveld" of type "Choice" with the options "Wonen", "Mobiliteit" and "Economie", marked required
- **AND** a project manager opens the Details tab of the project settings sidebar at /projects/:id
- **THEN** the tab shows a "Beleidsveld" select with those three options
- **AND** saving without a choice shows "Beleidsveld is required" next to the field

#### Scenario: A wrong value type is refused by the server

@e2e exclude API-level validation with no screen, asserted by ProjectHierarchyGuardListenerTest (task 3.2)
- **GIVEN** a number field "Contractwaarde"
- **WHEN** a project manager sends a PATCH to `/apps/openregister/api/objects/planninq/project/{id}` with `customFields.contractwaarde` set to "veel"
- **THEN** the request is refused and the error names "Contractwaarde"
