# Timetable generator

Planninq makes a school timetable for you. You set the wishes, press Generate, and compare the result with the timetable you have now. When you like it, you publish it as draft lessons. Teachers review the drafts before anything goes live.

Only admins run the generator. Everyone with the `planninq-timetable` read right sees the wishes and the scenarios.

## Set the week grid

An admin sets two values through `POST /apps/planninq/api/settings`. There is no screen for them yet.

- `timetable_period_grid`: the school days and the periods of a day, each with a start and an end time. The default is Monday to Friday with eight periods of 50 minutes from 08:30.
- `timetable_generator_budget_minutes`: how many minutes one run may search. The default is 10.

Planninq refuses a grid without days, and periods that overlap.

## Give the generator its activities

The generator needs to know what to place: which group gets which subject, from which teacher, how many lessons a week, and in which kind of room.

- **With learniq installed**, learniq answers with the activities of its hour plan.
- **Without learniq**, an admin sends two CSV sheets to `POST /apps/planninq/api/timetable/input/upload`: `rooms` (reference, capacity, type) and `activities` (group, subject, teacher, lessons per week, lesson length, room type).

## Set the wishes

Go to Timetable, Wishes. A wish applies to a teacher, a group, a room or an activity. Pick its kind:

| Kind | What it asks |
|---|---|
| Not on these periods | No lessons on the periods you tick. |
| Preferably not on these periods | Avoid the periods you tick if possible. |
| At most a number of lessons a day | Never more lessons a day than the limit. |
| No free periods between lessons | Keep the day without gaps. |
| Always the same room | Every lesson in one room. |

A **hard** wish is always kept. A **soft** wish has a weight from 1 to 3. The generator breaks a heavy soft wish last.

## Generate a scenario

1. Go to Timetable, Scenarios and add a scenario. Choose source "generated", the first week (`weekOf`) and the window it covers.
2. Open it and press **Generate**. The run goes to the background and shows how much of the time budget it used.
3. When it is done, the page shows how many lessons have a place.

A generated scenario never breaks a hard wish. A lesson that has no place without breaking one stays off the grid, and the page names the wish that blocked it. Loosen that wish or make it soft, then press **Generate again**.

## Compare with the timetable you have now

1. Add a scenario with source "imported" and the week you want to take.
2. Open it and press **Take the current timetable**. Planninq reads the scheduled lessons of that week and scores them the same way. Hard wishes the current timetable breaks are listed under "Broken hard wishes".
3. Back on Timetable, Scenarios, choose two or three scenarios under "Scenarios to compare".

Each line shows the value of every scenario, with the best one in bold. Below it, the lessons that sit on another period or in another room.

## Publish as draft lessons

Open a generated scenario that is done and press **Publish as draft lessons**. Planninq writes its lessons as drafts for every week of the window, with source `planninq-generator`.

- Drafts of an earlier scenario for the same window are replaced.
- If lessons of that window from the generator are already published, nothing is written and the page tells you why.

Teachers then see their own drafts, and an admin publishes them through the usual draft review. See [school-timetable.md](school-timetable.md).

## The endpoints

| Call | What it does |
|---|---|
| `POST /apps/planninq/api/timetable/input/upload` | Stores the rooms and activities sheets. |
| `POST /apps/planninq/api/timetable/scenarios/{id}/generate` | Starts a run. Answers 202. |
| `POST /apps/planninq/api/timetable/scenarios/{id}/import` | Takes the scheduled lessons of the scenario's week. |
| `POST /apps/planninq/api/timetable/scenarios/{id}/publish-drafts` | Writes the placements as draft lessons. |

All four are for admins only. Anyone else gets 403.

Next: set your week grid and add your first hard wish.
