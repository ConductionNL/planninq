# Design: timetable generator with hard and soft wishes and scenario compare

## Context

Read at planninq development `0e355e9`:

- `lib/Service/TimetableSessionService.php`: `upsert(sourceSystem, rows)` creates, updates or counts
  unchanged lessons by `sourceSystem` + `externalRef`; a draft never replaces a published lesson;
  `publish(sourceSystem, from, to)` turns drafts into `scheduled`.
- `lib/Service/TimetableSessionRows.php`: the read shape and the publish window check.
- `lib/Event/TimetableSessionsQueryEvent.php`: the typed event other apps use to read lessons
  (ADR-041). The same pattern carries activities the other way.
- Learniq: `GET /apps/learniq/api/hour-plans/activities?academicYear=` returns rows of cohort,
  subject (course), hours per period and teacher (from `SubjectTeacherAssignment`). Learniq owns
  rooms (`Room.capacity`) too.
- Admin-only writes on `timetableSession`; the group `planninq-timetable` reads.

## Goals / non-goals

Goals: record hard and soft wishes; generate a week pattern that never breaks a hard wish and keeps
as many soft wishes as it can; keep several scenarios, generated or imported; compare them; publish
one as drafts.

Non-goals: exams and invigilation, student choice placement, annual hour spreading, travel time,
multi-week rotations (a week pattern repeats over a window), a second solver engine in this change.

## Decisions

### Decision 1: the solver is a PHP local search behind a `TimetableSolver` interface

The problem: place every lesson of every activity in a period of the week grid and a room, so that no
teacher, group or room has two lessons at once, every hard wish holds, and the weighted count of
broken soft wishes is as low as possible.

Three ways to solve it were weighed:

| Option | What it is | For | Against |
|---|---|---|---|
| A. PHP local search | Greedy construction (most constrained lesson first), then simulated annealing on the soft penalty, in `lib/Timetabling/` | Runs on every Nextcloud with no extra container; testable in PHPUnit; deterministic with a seed | Weaker than a constraint solver on large, tight rosters; PHP CPU time in cron, so it runs in time-boxed steps |
| B. Sidecar with OR-Tools CP-SAT | A Python ExApp (AppAPI) that takes the JSON input and returns placements | Much better results on tight rosters; proves infeasibility | Needs AppAPI and a deploy daemon on the instance; one more container to build, release and keep secure; not available on every customer instance |
| C. An existing engine (FET) as a CLI | Planninq writes FET's XML and runs the binary | Mature for schools | A binary on the Nextcloud host, GPL-3 alongside EUPL, a file format we do not own |

Chosen for this change: **A**, behind an interface. `TimetableSolver::solve(SolverInput $input,
int $seconds): SolverResult` is the only thing the rest of the code calls. `SolverInput` is a plain
JSON-serialisable contract (periods, rooms, teachers, groups, lessons, wishes), so B can be added
later as a second implementation without a data migration. Whether B should be built as well is a
question for Ruben (below).

### Decision 2: two schemas, and the input is snapshotted into the scenario

- `timetableWish`: `appliesTo` (`teacher`, `group`, `room`, `activity`), `reference` (uid, group
  reference, room reference or activity key), `kind` (`unavailable`, `avoid`, `maxPerDay`,
  `noGaps`, `sameRoom`), `periods` (period keys for unavailable and avoid), `limit` (for maxPerDay),
  `strength` (`hard`, `soft`), `weight` (1 to 3, soft only), `note`. Hard and soft is one field, so
  a wish can be softened in place when a run reports it blocked a lesson.
- `timetableScenario`: `title`, `source` (`generated`, `imported`), `weekOf` (the first Monday of the
  window), `window` (from, to), `status` (`queued`, `running`, `done`, `failed`, `published`),
  `seed`, `input` (the `SolverInput` it was made from), `placements` (lesson key, period key, room
  reference), `unplaced` (lesson key and the blocking hard wish), `brokenWishes` (wish id, lesson
  keys, weight), `metrics` (see decision 5), `publishedAt`.

Both are admin-write and `planninq-timetable`-read, like `timetableSession`. The input is snapshotted
so a scenario can be compared and re-run after the activities or wishes change, and so compare never
mixes two versions of the input.

### Decision 3: activities come from learniq through a typed event, or from a CSV

Planninq dispatches `TimetableActivitiesQueryEvent(academicYear)`; a listener in learniq fills it with
the hour plan activities. Duck-typed calls into learniq are not used (fleet rule). Until learniq
answers, the generate dialog offers "Upload activities (CSV)" with the same columns: group, subject,
teacher, lessons per week, lesson length in periods, room type. The learniq listener is drafted to
`~/memcap-work/build-all/for-ruben/learniq-timetable-activities-event.md`, not filed by the lane.
Rooms: the same event returns rooms (reference, capacity, type); the CSV has a room sheet.

### Decision 4: the week grid is an admin setting

`timetable_period_grid`: days (Monday to Friday by default) and periods with start and end times
(8 periods of 50 minutes from 08:30 by default). A period key is `mon-3`. Placements are keys; the
publish step turns a key and the scenario window into `startsAt`/`endsAt` for every week.

### Decision 5: compare reads metrics computed once per scenario

When a run finishes (or an import is snapshotted) the scorer stores `metrics`: lessons placed and
unplaced, hard wishes broken (always 0 for a generated scenario, counted for an imported one), soft
wishes broken and their weighted sum, gaps per teacher (total and worst), lessons per day per group
(worst), room use (share of periods used, per room type). The compare page shows two or three
scenarios' metrics side by side, marks the best value per line, and lists the lessons whose period or
room differs. The scorer is the same class the solver optimises with, so the numbers match.

### Decision 6: a run is a queued job in time-boxed steps

`POST /api/timetable/scenarios/{id}/generate` sets `status` `queued` and adds
`GenerateTimetableScenario` (a `QueuedJob`). Each step runs the solver for at most 60 seconds from
the best state so far and stores it; the job re-queues itself until the time budget (admin setting,
default 10 minutes) is spent or the soft penalty stops improving. The page shows progress from the
stored scenario. No request waits on the solver.

### Decision 7: publish writes drafts through the existing upsert

Publishing a scenario upserts its placements as `draft` lessons with `sourceSystem`
`planninq-generator` and `externalRef` `{scenario}:{lesson}:{date}`, for every week of the window.
The existing draft review (teachers see their own drafts) and publish endpoint then apply unchanged.
Only one scenario per window can be published; publishing another replaces the drafts that are
not yet published and refuses when lessons of the window are already published from this source.

## Needs Ruben

**Which solver engine do we build?**

1. PHP local search only (option A). Runs everywhere, no extra infrastructure; results weaker on large
   and tight rosters.
2. PHP local search now, a CP-SAT sidecar as a second engine later (A, then B), chosen per instance
   in the admin settings. **Recommended**: every customer gets a working generator in this change,
   and a college that needs better results can switch engines later without a migration.
3. CP-SAT sidecar only (option B). Best results; the generator does nothing on an instance without
   AppAPI and a deploy daemon.
4. FET as a CLI engine (option C). Mature, but a GPL binary on the host and a file format we do not
   own.

Until Ruben answers, tasks 1 to 4 (schemas, grid, activities, wish editor) do not depend on the
answer; task 5 onward assumes option 2.

## Risks / trade-offs

- [A college with 3,000 weekly lessons] -> time-boxed steps keep cron healthy; the result may be worse
  than a CP-SAT engine, which is why the interface exists.
- [Activities from learniq change after a run] -> the scenario keeps its own input snapshot; the page
  shows "Activities changed since this scenario was made" when the current input differs.
- [An imported scenario breaks hard wishes] -> it is compared honestly: its broken hard wishes are
  counted, not hidden.
