# Procest Integration

Link Planninq tasks and projects to Procest cases for cross-app case-to-task workflows.

## Overview

Planninq is a sister app to Procest (case management). When a Procest case requires task tracking on a kanban board, Planninq provides the board. The integration is built on optional metadata fields — no direct API calls between apps are required in the MVP. Tasks and projects carry optional case reference fields that Procest can populate, and Planninq displays them as read-only metadata.

## Key Capabilities

### Case Reference on Project

- A project can carry a `caseReference` field containing a Procest case UUID
- Projects with a case reference show a **`Case: {caseNumber}`** badge in the project list and project detail
- The case reference can be set manually via the project edit form by entering a Procest case UUID

### Task Case Link

- A task can carry a `zaakUuid` field containing a Procest case UUID
- Task detail shows a read-only **"Case"** field with a link to the Procest case when `zaakUuid` is set
- The `zaakUuid` can be set manually via the task edit form

### When the Bridge is Disabled

If the Procest bridge toggle is disabled in admin settings, or Procest is not installed:
- `caseReference` and `zaakUuid` fields are still stored and displayed as read-only metadata
- No requests are sent to Procest
- All Planninq functionality remains fully available

## VNG InterneTaak Mapping

Tasks bridged from Procest follow the VNG InterneTaak field mapping:

| Planninq Task field | VNG InterneTaak field |
|-------------------|-----------------------|
| `title` | `gevraagdeHandeling` |
| `assignedTo` | `toegewezenAanGebruikersnaam` |
| `dueDate` | `gevraagdeDatum` |
| `status` (done) | triggers `afhandelingsdatum` |
| `completedAt` | `afhandelingsdatum` |

## Standards

- VNG ZGW InterneTaak (Klantinteracties) — case-task field mapping
- Schema.org Action — task type annotation

## Spec

- [procest-integration spec](https://github.com/ConductionNL/planninq/blob/development/openspec/specs/procest-integration.md)
