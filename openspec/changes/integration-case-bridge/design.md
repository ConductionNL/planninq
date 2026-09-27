# Design: start a project from a case and hand its record back to the case

## Context

Read at development `de35541`:

- Schema `project`: `caseReference` (uuid, "linked Procest case"); schema `task`: `zaakUuid`.
- `src/components/ProjectSettingsSidebar.vue:47-53` shows `caseReference` read-only.
- The `planninq-projects` leaf (ADR-066): client half `src/integrations/registerProjectsLeaf.js`
  (surfaces user-dashboard, app-dashboard, detail-page, single-entity), server half
  `lib/Listener/RegisterProjectsLeafListener.php` (`LEAF_ID`, `SURFACES`, gate-24 parity).
  `src/integrations/projectScope.js` `scopeParams` scopes a detail page by `client` only.
- `src/integrations/CnProjectsWidget.vue` links "New project" to
  `/apps/planninq/projects?new=1&client={client}` (`lib/Listener/RegisterProjectsLeafListener.php:379`),
  and `src/views/ProjectList.vue` reads no route query, so the dialog never opens (defect recorded on
  row `int-projects-on-client`).
- `openspec/specs/procest-integration.md` V1 describes a token-authenticated bridge API
  (`POST /planninq/api/bridge/project`).
- hydra ADR-051 (semantic object handoff) and ADR-048 (cross-app references) prefer handing objects
  over through Open Register primitives over app-to-app APIs; ADR-054 hardens public surfaces.
- The providers list of the matrix records Dossiq's app id as still `procest`.

## Goals / non-goals

Goals: start a project from a case page; hand a project's files and metadata to its case,
provably unchanged. Non-goals: manual case linking, completion mirroring, external zaaksystemen.

## Decisions

### Decision 1: reuse the projects leaf instead of a bridge API

The leaf gains a case scope: on a detail page whose host schema is Dossiq's case, `scopeParams`
returns `{ caseReference: objectId }` and `guardRows` keeps rows with that `caseReference`. The
scope is chosen from the host schema the leaf receives in its props, and both leaf halves list it so
gate-24 keeps them equal. "New project" links to
`/apps/planninq/projects?new=1&case={id}&title={caseTitle}`. Alternative: the main spec's token
bridge. Rejected: a shared-secret endpoint is a public surface (ADR-054), and it would create
projects as nobody in particular instead of as the case handler.

### Decision 2: ProjectList honours `?new=1`

`ProjectList` reads `new`, `case`, `client` and `title` from the route query on mount and opens
`ProjectCreationDialog` prefilled; the dialog shows "Linked case: {title}" read-only and saves
`caseReference`. This also fixes the dead `?new=1&client=` link.

### Decision 3: a handover service copies files and writes a metadata file

`CaseHandoverService::handOver(projectId)` collects the files on the project object and on each of
its tasks through Open Register's file service as the current user, computes SHA-256 per file,
writes each to the case object's files (Dossiq's register) as the same user, re-reads the checksum,
and adds `project-metadata.json` (project fields, members, tasks with key, status, dates and
assignee) with its own checksum. A route `POST /api/projects/{id}/case-handover` exposes it
(`#[NoAdminRequired]`, owner check in the method). The result is appended to a new project property
`caseHandovers` (date, uid, case, files with name and checksum, failures).

### Decision 4: shown only when the case app is there

The action and the leaf scope check `IAppManager::isInstalled('procest')`. The id is Dossiq's
current one; per the fleet rename rule it is not rewritten ahead of Dossiq's own id change.

## Risks / trade-offs

- [Large projects make a slow request] -> the handover runs as a queued job when it holds more than
  twenty files, and the project shows "Handover in progress".

## Open questions

- Does Open Register's file service expose a copy between objects in different registers, or must
  the service stream each file? Task 3.1 decides.
