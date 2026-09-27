---
kind: code
---

# Start a project from a case and hand its record back to the case

## Why

Planninq's projects and tasks already carry a case link: `project.caseReference` and
`task.zaakUuid` (`lib/Settings/planninq_register.json`). The link is display only
(`src/components/ProjectSettingsSidebar.vue:47-53`), and neither direction of the bridge exists.

A case handler in the case app (Dossiq, app id still `procest`) cannot start a project for a case.
The main spec describes it (`openspec/specs/procest-integration.md`, "Procest Bridge, Create Project
from Case", V1) and links a change that was never written; the matrix marks the row `specified` on
that claim. The matrix also found that Dossiq references planninq nowhere but a repair step.

When a project ends, its documents and metadata stay in planninq. Gemeente Sittard-Geleen's tender
for a project management tool asks for them to be moved, unchanged, into the case system for the
record (TenderNed 365739, requirements 4054 and 4126,
https://www.tenderned.nl/aankondigingen/overzicht/365739). Nothing exports a project's files or
metadata today.

Parity rows: `int-project-from-case`, `int-archive-to-case` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `int-project-from-case` was marked specified with no change directory;
`int-archive-to-case` carries tender demand.

## What changes

- On a case's page in Dossiq, planninq's Projects panel lists the projects linked to that case and
  offers "New project", which opens planninq's New project dialog with the case linked and the
  case title filled in.
- On a project linked to a case, the owner chooses "Hand over to case": every file on the project
  and its tasks, plus a metadata file describing the project, is copied byte for byte to the case,
  and the handover is recorded on the project with the file checksums.
- Both actions run with the user's own rights on the case; no shared token and no public endpoint.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `int-project-from-case`, `int-archive-to-case`.

### `int-project-from-case`: Create a project from a case.

- Area `integration`. Planninq is rated `no`, built.state `specified`, owner `ConductionNL/planninq`.
- Built evidence: "openspec/specs/procest-integration.md:13 describes it; no planninq route, dialog or listener creates a project from a case, and procest/src + procest/lib reference planninq only in lib/Repair/MigrateSchemaApplicationId.php"
- Note: "procest was read from the live workspace checkout (another session's branch), read-only."
- Demand: none recorded on the row.
- Competitors rated yes: none.

### `int-archive-to-case`: Hand a project's documents and metadata over unchanged to the case management system for the record.

- Area `integration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/ProjectSettingsSidebar.vue:47-53 displays caseReference when set; no action exports a project's attachments or metadata to dossiq/procest or a ZGW DRC. grep for 'zaakUuid', 'caseReference', 'drc', 'archive' in lib/ finds only the portal field exclusion list in lib/Portal/PortalContributionProvider.php:64."
- Note: "Demand row from TenderNed 365739, gemeente Sittard-Geleen, Projectmanagementtool (published 2025-01-31, intelligence database tender id 285): requirements 4054, 4126 (bestanden en metadata voor dossiervorming ongewijzigd overzetten naar het zaaksysteem)."
- Demand (tender, via origin): https://www.tenderned.nl/aankondigingen/overzicht/365739
- Competitors rated yes: none.

## Scope

### In scope

- Case scoping for the existing `planninq-projects` leaf, so Dossiq can place it on a case page.
- `ProjectList` opening the New project dialog from `?new=1` with a prefilled case link and title.
- A handover service and action for projects with a case link.

### Out of scope

- Editing the case link by hand on a project or task (row `int-case-ref`, deferred: partial, built,
  no demand).
- Mirroring task completion to the case (a V1 scenario of the main spec with no matrix row).
- Handing over to an external zaaksysteem through a ZGW Documenten API: a later integriq connector.
- The Dossiq side: placing the leaf on its case schema is a change in ConductionNL/dossiq, named
  here as a dependency and not written in this repo.

## Impact

- `src/integrations/projectScope.js`, `src/integrations/registerProjectsLeaf.js` and
  `lib/Listener/RegisterProjectsLeafListener.php` (case scope, kept in parity by gate-24),
  `src/integrations/CnProjectsWidget.vue`, `src/views/ProjectList.vue`,
  `src/dialogs/ProjectCreationDialog.vue`, a new `lib/Service/CaseHandoverService.php` and
  controller route, `lib/Settings/planninq_register.json` (project `caseHandovers`), `l10n/`.
- Cross-project: ConductionNL/dossiq adds planninq's leaf to its case schema's linked types.

## Risks

### Risk 1: A handover copies files the user may not see
**Severity**: High
**Mitigation**: the service reads the project's and tasks' files as the requesting user through Open
Register, so it can only copy what that user can read, and writes to the case as that user, so Open
Register refuses a case they cannot write.

### Risk 2: "Unchanged" is claimed but not proven
**Severity**: Medium
**Mitigation**: each file's SHA-256 is computed before and after the copy and stored in the handover
record; a mismatch fails that file and the record says so.

### Risk 3: The case app is not installed
**Severity**: Low
**Mitigation**: both actions are hidden unless the case app (`procest`, the id Dossiq still uses) is
installed; the check moves with Dossiq's id when it lands, never before.
