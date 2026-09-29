# School timetable

Planninq keeps a school's timetable: every lesson with its time, group, teacher, room and subject. A rostering system such as Zermelo, Untis, Xedule or TimeEdit delivers the lessons through integriq. Learniq shows them to teachers, pupils and parents.

Planninq has no timetable page of its own. It stores the lessons and answers two kinds of question: "deliver these lessons" and "which lessons does this group or teacher have this week".

## What a lesson holds

| Field | Meaning |
|---|---|
| `externalRef` | The id the rostering system gives the lesson. |
| `sourceSystem` | The system that delivered it, for example `roster-zermelo`, or `manual`. |
| `subject`, `title` | The subject as the school names it, and the name shown. |
| `startsAt`, `endsAt` | When the lesson runs. |
| `groupReference`, `cohortId` | The school's group code, and the learniq cohort it belongs to when known. |
| `teacherReference`, `teacherUserId` | The school's teacher code, and the teacher's Nextcloud account when known. |
| `roomReference`, `roomLabel` | The school's room code and the room name. |
| `status` | `scheduled` or `cancelled`. |
| `importedAt` | When planninq last received the lesson. Planninq fills this in. |

## Delivering the same timetable again

A rostering system sends the whole timetable again every night. Planninq recognises a lesson by its source and its `externalRef`:

- a lesson it has not seen is added;
- a lesson that moved room or time is updated;
- a lesson that did not change is left alone and counted as unchanged.

A lesson with a missing subject or time, or one that ends before it starts, is refused with a reason. The rest of the delivery still lands.

## Who can read the timetable

A lesson is not open to everyone who can sign in. It can be read by:

- members of the Nextcloud group `planninq-timetable`, meant for planners and staff who need the whole school timetable. Create the group and add those people to it;
- the teacher the lesson names, through `teacherUserId`;
- admins.

Pupils and parents see their own lessons in learniq. Learniq works out which groups they belong to and asks planninq for those groups only.

## For integrators

Apps talk to planninq through two events (ADR-041). Look the class up by name and treat a missing class as "planninq is not installed".

- `OCA\Planninq\Event\TimetableUpsertRequestedEvent`: deliver a batch. Read the counts from `getResult()`.
- `OCA\Planninq\Event\TimetableSessionsQueryEvent`: read lessons by `cohortId`, `groupReference`, `teacherUserId` or `teacherReference`, optionally between `from` and `to`. Read them from `getSessions()`. Planninq answers this event without checking the signed-in user, so your app must only ask for a group or teacher that its user is allowed to see.

Two endpoints do the same over HTTP:

| Method | Path | Who |
|---|---|---|
| `GET` | `/apps/planninq/api/timetable/sessions?cohortId=…&from=…&to=…` | Any signed-in user, who gets only the lessons they may read |
| `POST` | `/apps/planninq/api/timetable/sessions/upsert` with `{"sourceSystem": "…", "sessions": […]}` | Admins |

The full contract, with every field and rejection code, is in `openspec/changes/school-timetable-target/contract.md`.
