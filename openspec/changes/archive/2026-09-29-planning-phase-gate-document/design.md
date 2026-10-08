# Design: close a project phase only once its concluding document is uploaded

## Context

What exists at de35541:

- `projectPhase` (`lib/Settings/planninq_register.json:584-752`): `title` and `project` required; `description`, `status` (`open`, `in_progress`, `completed`, `cancelled`, default `open`, `:708-718`), `order`, `billable`, `budgetHours`, `startDate`, `endDate`. Its authorization block (`:596-690`) grants read, create, update and delete to members of the referenced project and to admins. There is no lifecycle declaration, so any member can PATCH `status: completed` at any time.
- `task.phase` (`:220-227`) lets a task name the phase it delivers. The shipped requirement "A project may be broken into phases" (`openspec/specs/project-delivery/spec.md:53-78`) covers the schema only.
- No view, store action or component reads phases. The only hit for "phase" in `src/` is a comment (`src/App.vue:122`).
- Files on an object: the task detail page shows an Attachments tab through `CnObjectSidebar` over OpenRegister's per-object files API, with no planninq PHP (`src/views/TaskDetail.vue:124-137`, props from `taskCollaborationSidebarConfig` in `src/utils/taskHelpers.js`).
- The register asks for `"openregister": "^v0.2.10"` (`lib/Settings/planninq_register.json:11`).
- OpenRegister (read at 63ddfd5, `openspec/specs/object-lifecycle/spec.md`, requirement "Direct lifecycle-field edits guarded on update") supports a schema annotation `x-openregister-lifecycle` with a `field`, an `initial` value and `transitions`, each with a `from` list and a `to` value and optionally a `requires` tag. On every update through `saveObject()`, its `LifecycleValidationListener` rejects a change of the lifecycle field that no transition allows (HTTP 422) and, when the matched transition has a `requires` tag, resolves a guard registered under that tag and runs its `check()`; a deny becomes HTTP 403 with the guard's message. Guards implement `OCA\OpenRegister\Lifecycle\LifecycleGuardInterface` (`check(array $object, string $action, string $userId): GuardResult`), must not mutate the object, and are registered with `registerService()` under the tag; a missing tag fails closed.

What is missing: any screen for phases, a place for phase documents, and the rule.

## Goals / non-goals

Goals:
- Project members manage phases and their documents where they manage the project.
- "No concluding document, no closed phase", enforced by OpenRegister for every client.

Non-goals:
- Approval of the document's content, phase templates, phases on the timeline.

## Decisions

### Decision 1: the rule is declared on the schema, the check is a planninq guard
`projectPhase` gets an `x-openregister-lifecycle` block on `status` with `initial: open` and transitions: open to in_progress; open or in_progress to completed with `requires: planninq.phase.concludingDocument`; open or in_progress to cancelled; completed or cancelled back to in_progress (reopen). `lib/Lifecycle/PhaseConcludingDocumentGuard.php` implements `LifecycleGuardInterface` and is registered in `lib/AppInfo/Application.php` under that tag. This follows ADR-031: the rule lives on the schema and OpenRegister enforces it, so the page, the API and any other client get the same answer. The alternatives were a check in the Vue store (bypassed by any direct PATCH) or a planninq controller for "close phase" (a pass-through the object API makes redundant, and still bypassable, ADR-022).

### Decision 2: what the guard checks
Allow when `concludingDocument` on the incoming phase data is non-empty and a file with that id is attached to this phase object; deny otherwise with the message "Upload the concluding document before you close this phase." The file lookup goes through OpenRegister's object file service, read-only. The guard never writes.

### Decision 3: documents are files on the phase object
Phase documents are uploaded to the phase through the same OpenRegister per-object files API the task Attachments tab uses, so they live in the register's Nextcloud folder, carry Nextcloud file permissions and show up in the phase's audit trail. `concludingDocument` (nullable string, the file id) marks which one concludes the phase. No new storage and no copy of the file.

### Decision 4: a phases page per project
A new custom page `ProjectPhases` at `/projects/:id/phases` (manifest entry and `src/registry.js`), reached by a "Phases" button in the project board header next to Backlog and Timeline (`src/views/ProjectBoard.vue:41-55`). It lists the project's phases in `order` with status, dates, budget hours and the concluding document when there is one; "Add phase" and "Edit" open `PhaseEditDialog`; "Move up" and "Move down" reorder with the keyboard. Selecting a phase opens `CnObjectSidebar` for it with Files, Notes and Audit trail tabs. Placement is Projecten > project (ADR-001), not a menu.

### Decision 5: closing is one dialog
"Close phase" opens `PhaseCloseDialog`. It lists the phase's files, lets the member upload one, asks them to pick the concluding document, then sends one PATCH with `concludingDocument` and `status: completed`. If OpenRegister answers 403 from the guard, the dialog shows the guard's message and stays open. The "Close phase" button is not hidden when no file exists: the dialog explains what is needed, which is clearer than a missing button.

### Decision 6: the OpenRegister minimum version moves up
The register's `openregister` constraint rises to the first release that ships the lifecycle update guard with `requires` tags. A PHPUnit test asserts that the constraint and the lifecycle block ship together.

## Risks / trade-offs

- [Writes that skip `saveObject()`] -> OpenRegister's own spec names this; planninq writes only through the object API and `ObjectService::saveObject`.
- [A completed phase loses its file later] -> The page flags "Concluding document missing"; the audit trail shows the removal.
- [Seed or imported phases already `completed` without a document] -> The guard runs only on a change of `status`, so existing rows are not rejected on unrelated edits; the page flags them.

## Open questions

- The exact key OpenRegister's annotation validator expects for a transition's action name is read from its `object-lifecycle` spec at implementation time; this design only fixes the states, the `from`/`to` pairs and the guard tag.

## Built at HEAD (29 Sep 2026)

The code at `a9cd351` and OpenRegister at its current development settled four points this design left open.

- **The guard is named by its class, not a tag.** OpenRegister's `LifecycleGuardRegistry` resolves `requires` from its own container, then from the server container. A plain tag such as `planninq.phase.concludingDocument` resolves in neither and fails closed on every close. The `complete` transition therefore names `OCA\Planninq\Lifecycle\PhaseConcludingDocumentGuard`, which the server container autowires from planninq, as OpenRegister's own `DenialFinaliseGuard` is named. Nothing is registered in `Application.php`.
- **The transitions are named** `start`, `complete` (with `requires`), `cancel` and `reopen`; `final` lists completed and cancelled. OpenRegister's `LifecycleAnnotationValidator` (origin/development) returns no finding for the block, and one for a control with an undeclared `to` state.
- **The OpenRegister constraint** becomes `>=v1.1.7`, the first plain release tag that carries `LifecycleGuardInterface` and the `requires` step in `LifecycleValidationListener`. OpenRegister stores the constraint and does not enforce it; a caret range would have excluded the 2.x line the fleet runs.
- **The page is reached from the Phases tab**, not a board-header button: since `projects-overview-logs-risks` every project page shares one row of tabs (`ProjectTabs`), and a board-header button would be a second way in. The close dialog shows the guard's refusal as the translated sentence for the code `lifecycle-guard-denied`, not OpenRegister's English message.
