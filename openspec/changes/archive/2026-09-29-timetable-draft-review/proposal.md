---
kind: code
---

# Show a draft timetable to the teachers it names before learners see it

## Why

A school checks a new timetable with its teachers before learners and parents see it. Two timetabling competitors in planninq's matrix do this, and planninq cannot.

Planninq becomes the owner of the school timetable under decision D10 (learniq round 1, `market-intelligence/learniq/_round1/compare/decisions.md`, Ruben, 2026-09-27; built now under D25). The change that brings the timetable in is `school-timetable-target` (planninq PR #685, open at `9801e16` on 28 September 2026). It stores each lesson as a `timetableSession` with the status `scheduled` or `cancelled`, and every signed-in user may read it. A delivered lesson is visible to everyone the moment it lands. There is no stage in which only the teachers see it.

The row came to planninq from learniq. The learniq OpenSpec-pass lane recorded `tt-draft-publish` as `decided-no` for learniq with the reason "a draft timetable that is published later is a state of the timetable data, which lives in planninq's timetableSession; learniq renders what is published", and moved `built.owner` to `ConductionNL/planninq` (learniq PR #1132). This change is planninq's half.

Parity row: `tt-draft-publish` in planninq's `openspec/parity/capabilities.json` ("Publish a draft timetable to teachers for review before students see it.", area `timetabling`, planninq rated `no`, built.state `none`, owner `ConductionNL/planninq`).

Decision: build, because two competitors rate it yes:

- Xedule (https://support.xedule.nl/hc/nl/articles/36898315959314-Export-My-Xedule, read 2026-09-27): "Klik op de knop Concept publiceren ... Het roosters is hiermee niet zichtbaar voor de student, maar wel voor de docenten".
- TimeEdit (https://timeedit.com/platform/scheduling/viewer, read 2026-09-27): "Share draft schedules with teachers and department heads before publishing. Reviewers can see their upcoming timetable, flag concerns, and sign off".

Zermelo and Untis are `unknown` on the row: Zermelo publishes straight to the portal, and Untis keeps a timetable hidden from everyone until it is published.

## What changes

- A delivered lesson can carry the status `draft`.
- A draft lesson is readable by an admin and by the teacher it names, and by nobody else.
- A consumer receives draft lessons only when it asks for them, so learniq's current read keeps showing published lessons only.
- An admin publishes the drafts of one source in a date window, and they become `scheduled` for everyone.
- A later delivery never turns a published lesson back into a draft.

## Scope

### In scope

- The `draft` status on `timetableSession`, its read rule, the upsert rule for it, the `includeDrafts` read criterion and the admin publish endpoint.
- The contract addendum for `includeDrafts`, the new rejection code and the publish endpoint, all additive under contract version 1.

### Out of scope

- A comment or sign-off step for reviewing teachers (TimeEdit's "flag concerns"). Teachers can already raise a concern outside the timetable; a review workflow can follow on demand.
- Rendering a draft mark in learniq. That is learniq's half and is listed for the coordinator; learniq's `sessions-from-planninq` reads published lessons until it opts in.
- Making drafts in a rostering system. The rostering system or the admin delivers them; planninq does not build a timetable (learniq `openspec/changes/archive/2026-07-16-timetabling-and-substitution/design.md` Non-Goals, adopted by D10).

## Dependencies

- `school-timetable-target` (planninq PR #685) must be on `development` first. Every file this change touches is created by that change.

## Impact

- Schema: `timetableSession.status` gains `draft`; the schema's `authorization.read` gains a rule for draft rows; register and schema versions are bumped.
- Backend: `lib/Service/TimetableSessionService.php` (status list, upsert guard, publish), `lib/Service/TimetableSessionQuery.php` (`includeDrafts`), `lib/Controller/TimetableController.php` (publish action, `includeDrafts` parameter), `appinfo/routes.php` (one route).
- Contract: `openspec/changes/school-timetable-target/contract.md` or its spec after archive gains the additive keys.
