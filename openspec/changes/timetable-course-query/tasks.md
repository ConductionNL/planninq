# Tasks: timetable-course-query

## 1. Contract v2

- [x] 1.1 `courseId` and `onlineMeetingUrl` on the `timetableSession` schema (0.4.0, register 0.37.0), writable through the upsert, validated before writing, empty clears. Verify: `TimetableCourseQueryTest::testTheStoredLessonFitsTheRegisterSchema` validates what is stored against the real fragment with Opis; `testAMalformedCourseOrLinkIsRefused`.
- [x] 1.2 `courseId` is an identity key of `TimetableSessionQuery`, and the refusal names it. Verify: `TimetableCourseQueryTest::testACourseQueryAnswersThatCoursesLessons`, `testTheRefusalNamesTheCourse`.
- [x] 1.3 Every lesson in the read shape carries `courseId` and `onlineMeetingUrl`, null when unset; `CONTRACT_VERSION` is 2. Verify: `TimetableCourseQueryTest::testEveryLessonCarriesItsCourseAndLink`, `testTheContractVersionSaysTheCourseQueryExists`.

## 2. Live check (with learniq)

- [ ] 2.1 After learniq's `fix/planninq-course-sessions-and-link` lands: set `courseId` on two overlapping planninq lessons for two elective courses; `GET /apps/learniq/api/timetable/course-slots?courseIds=<A>,<B>&withCore=1` as a learner answers both slots and the picker warns; set an https `onlineMeetingUrl` on a lesson and My timetable shows Join, opening in a new tab. learniq refreshes its stub of the event class (`tests/Stubs/Planninq/Event/TimetableSessionsQueryEvent.php`) when this lands.
