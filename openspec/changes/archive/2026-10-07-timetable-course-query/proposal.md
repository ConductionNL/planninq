---
kind: code
---

# Answer a timetable query by course, and carry a lesson's course and online link

## Why

With planninq installed, learniq's timetable source resolves to planninq, and two things it needs are missing. Its elective picker cannot warn about two overlapping electives, because planninq's query event has no way to ask for one course's lessons. Its timetable never shows a Join action, because a planninq lesson has no online link. Evidence from the learniq live pass: `livepass/learniq/timetabling-student-choice-placement/slots.txt` and `livepass/learniq/timetabling-online-lesson-link/`.

Decision: Ruben, DECISIONS row 53 (learniq live pass D8): "build the planninq path: planninq answers the course query and carries a lesson link; learniq reads both." The contract both apps build against is `for-ruben/planninq-timetable-course-query-and-lesson-link.md` (contract v2). learniq's half is on its branch `fix/planninq-course-sessions-and-link`.

## What changes

- `TimetableSessionsQueryEvent::CONTRACT_VERSION` becomes 2. learniq sends a course query only from version 2.
- The `timetableSession` schema gains `courseId` (uuid, nullable) and `onlineMeetingUrl` (uri, nullable). An import sets them through the existing upsert; a malformed value is refused with `invalid-course-id` or `invalid-link` before anything is written, and an empty value clears the field.
- `courseId` becomes an identity key of the query: a query naming only a course answers that course's lessons, with the same window, cancelled and draft rules as every other query.
- Every lesson the query answers carries `courseId` and `onlineMeetingUrl`, null when unset, on every query.

## Out of scope

- No screen in planninq edits either field yet; they arrive through the import (the upsert event and the admin endpoint).
- learniq's reading of both fields lives in learniq.
