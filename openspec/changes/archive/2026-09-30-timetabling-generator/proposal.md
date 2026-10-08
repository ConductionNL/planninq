---
kind: code
---

# Generate a school timetable from hard and soft wishes, and compare scenarios before publishing

## Why

A school or MBO college that stores its timetable in planninq cannot make one there. Lessons arrive
from an outside rostering system through the upsert (`lib/Service/TimetableSessionService.php`), are
reviewed as drafts (`openspec/specs/timetable-draft-review/spec.md`) and are then published. Nothing
in planninq places a lesson in the week.

Two tender rows ask for exactly that:

- `tt-hard-soft-wishes` ("Mark a timetable wish as hard or soft so the generator must or should
  respect it."), tender https://www.tenderned.nl/aankondigingen/overzicht/191698. Zermelo, Untis and
  TimeEdit rate yes. Untis knows five strengths from "must take place" to "must not take place"
  (https://help.untis.at/hc/de/articles/360009220300); Zermelo's automaton never breaks a period
  blocked "op Eis" but may break a wish
  (https://support.zermelo.nl/guides/roostermaker/roosterautomaten-in-zermelo).
- `tt-scenario-compare` ("Build alternative plans side by side and compare them before publishing
  one."), tender https://www.tenderned.nl/aankondigingen/overzicht/414807. Untis and TimeEdit rate
  yes: Untis generates several plans and the timetabler picks the best
  (https://www.untis.at/produkte/untis-die-stundenplanung); TimeEdit compares what-if scenarios on
  room use and staff workload (https://timeedit.com/platform/scheduling/scenario-planning).

Decision: build. Ruben decided on 29 Sep 2026 (build-all DECISIONS.md row 17) to build a generator in
planninq. That reverses two recorded non-goals: learniq's design
`openspec/changes/archive/2026-07-16-timetabling-and-substitution/design.md` (Non-Goals, adopted by
decision D10) and planninq's own 28 Sep decision, which set both rows to `decided-no`. Both rows move
to `specified` with planninq as owner.

## What planninq has today

Read at planninq development `0e355e9`:

- `timetableSession` rows (`lib/Settings/planninq_register.json`): one lesson with `startsAt`,
  `endsAt`, `groupReference`, `teacherUserId`, `roomReference`, `status` (`draft`, `scheduled`,
  `cancelled`). Only admins write them.
- `TimetableSessionService::upsert()`: idempotent by `sourceSystem` and `externalRef`, refuses a
  draft over a published lesson, stamps `importedAt`.
- `POST /api/timetable/sessions/publish`: turns the drafts of one source in a window into
  `scheduled`.
- Learniq lists what has to be scheduled (group, subject, hours per period, teacher) at
  `GET /apps/learniq/api/hour-plans/activities?academicYear=` (learniq change
  `2026-09-29-timetabling-multi-year-hour-plan`).
- No wish, no scenario, no solver.

## What changes

- **Wishes.** A timetabler records wishes on a teacher, a group, a room or one activity: "not on
  these periods", "preferably not on these periods", "at most N lessons a day", "no gaps", "same room
  all week". Each wish is hard (must hold) or soft (should hold, with a weight of 1 to 3).
- **Scenarios.** A scenario is one candidate timetable for one week pattern. It is generated from the
  activities and the wishes, or imported from the lessons planninq already holds. A timetabler keeps
  as many as they like.
- **Generate.** Planninq builds a scenario in the background. It never breaks a hard wish; a lesson
  it cannot place is listed as unplaced with the wish that blocked it. It keeps soft wishes where it
  can and reports each one it broke.
- **Compare.** Two or three scenarios side by side: unplaced lessons, broken soft wishes by weight,
  gaps per teacher, room use, and the lessons that differ.
- **Publish.** A chosen scenario is written as draft lessons through the existing upsert
  (`sourceSystem` `planninq-generator`), so the existing draft review and publish flow applies.

## Scope

### In scope

- Two schemas (`timetableWish`, `timetableScenario`), a period grid admin setting, activities from
  learniq or a CSV upload, a PHP solver behind an interface, a background run, the wish editor, the
  scenario list, the compare page, publish as drafts.

### Out of scope

- Exams, invigilation and student choice placement (learniq changes `timetabling-exam-schedule`,
  `timetabling-student-choice-placement`).
- Spreading annual hours over weeks (`tt-annual-hours`, decided no).
- Travel time between buildings and lesson relations beyond "same room" (`tt-travel-time`,
  `tt-lesson-relations`, decided no).
- A second solver engine. The design keeps the door open for one; see "needs Ruben" in the design.

## Impact

- `lib/Settings/planninq_register.json` (two schemas, register bump), `lib/Timetabling/` (solver,
  input builder, scorer), `lib/BackgroundJob/GenerateTimetableScenario.php`, `lib/Controller/`
  (generate, compare, publish), `src/views/timetabling/`, `l10n/`, `docs/features/`.
- Sibling: learniq keeps owning the activities. Planninq reads them through a typed event
  (ADR-041) that learniq answers; until learniq answers it, a CSV upload fills the same shape. The
  learniq listener is drafted for Ruben, not filed by this lane.

## Risks

### Risk 1: a PHP solver is too slow or too weak for a large college

**Severity**: High
**Mitigation**: the solver runs in time-boxed background steps and keeps its best result so far; a
fixture of 600 lessons must finish inside the budget in PHPUnit. The solver sits behind an interface
with a JSON input contract, so a stronger engine can replace it without touching wishes, scenarios or
the compare page.

### Risk 2: a wish nobody can meet

**Severity**: Medium
**Mitigation**: the run never loops on it. The lesson is listed as unplaced with the hard wish that
blocked it, and the timetabler can soften that wish and run again.
