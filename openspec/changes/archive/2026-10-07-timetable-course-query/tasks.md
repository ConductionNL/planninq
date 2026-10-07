# Tasks: timetable-course-query

## 1. Contract v2

- [x] 1.1 `courseId` and `onlineMeetingUrl` on the `timetableSession` schema (0.4.0, register 0.37.0), writable through the upsert, validated before writing, empty clears. Verify: `TimetableCourseQueryTest::testTheStoredLessonFitsTheRegisterSchema` validates what is stored against the real fragment with Opis; `testAMalformedCourseOrLinkIsRefused`.
- [x] 1.2 `courseId` is an identity key of `TimetableSessionQuery`, and the refusal names it. Verify: `TimetableCourseQueryTest::testACourseQueryAnswersThatCoursesLessons`, `testTheRefusalNamesTheCourse`.
- [x] 1.3 Every lesson in the read shape carries `courseId` and `onlineMeetingUrl`, null when unset; `CONTRACT_VERSION` is 2. Verify: `TimetableCourseQueryTest::testEveryLessonCarriesItsCourseAndLink`, `testTheContractVersionSaysTheCourseQueryExists`.

## 2. Live check (with learniq)

- [x] 2.1 After learniq's `fix/planninq-course-sessions-and-link` lands: set `courseId` on two overlapping planninq lessons for two elective courses; `GET /apps/learniq/api/timetable/course-slots?courseIds=<A>,<B>&withCore=1` as a learner answers both slots and the picker warns; set an https `onlineMeetingUrl` on a lesson and My timetable shows Join, opening in a new tab. learniq refreshes its stub of the event class (`tests/Stubs/Planninq/Event/TimetableSessionsQueryEvent.php`) when this lands. Verified live 3 Oct 2026 on the shared instance (planninq e20f5517, learniq 41e54a90): two lessons for courses LP-A and LP-B on Tuesday 10:00-10:50Z, `course-slots` as lp-learner answered both in the same slot, the picker warned "Live pass safety basics and Live pass advanced tooling meet at the same time: Tuesday 12:00–12:50.", and My timetable showed Join on the lesson with an https link, opening a new tab (build-all livepass/RESULT-lane5.md, "learniq D8").
