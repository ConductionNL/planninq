# school-timetable delta: timetable-course-query

## ADDED Requirements

### Requirement: Another app reads a course's lessons and their online link (REQ-007)
`TimetableSessionsQueryEvent` MUST carry contract version 2. A lesson MAY carry a `courseId` (the uuid of the course it teaches) and an `onlineMeetingUrl`; an upsert MUST refuse a `courseId` that is not a uuid with `invalid-course-id` and an `onlineMeetingUrl` that is not an absolute address with `invalid-link`, before writing, and an empty value MUST clear the field. `courseId` MUST be accepted as the only identity filter of a query, answering the lessons whose `courseId` equals it under the same window, cancelled and draft rules as every other query. Every lesson a query answers MUST carry `courseId` and `onlineMeetingUrl`, null when unset. Feature tier: V1.

#### Scenario: A course query answers that course's lessons
- GIVEN two lessons of course A in the requested week, one of course A later, one of course B and one with no course
- WHEN a query event asks for `courseId: A` in that week
- THEN the two lessons of course A inside the week are returned, earliest first
- @e2e exclude An in-process event between two apps has no screen to drive; TimetableCourseQueryTest::testACourseQueryAnswersThatCoursesLessons runs the real listener, service and query over an in-memory OpenRegister

#### Scenario: Every lesson carries its course and link
- GIVEN a lesson with a course and an https link and a lesson with neither
- WHEN a cohort query answers both
- THEN the first carries its `courseId` and `onlineMeetingUrl` and the second carries both keys as null
- @e2e exclude An in-process event between two apps has no screen to drive; TimetableCourseQueryTest::testEveryLessonCarriesItsCourseAndLink

#### Scenario: A malformed course or link is refused
- GIVEN a delivery with a lesson whose `courseId` is `spaans` and one whose `onlineMeetingUrl` is `lokaal 12`
- WHEN it is upserted
- THEN both are rejected, with `invalid-course-id` and `invalid-link`, and nothing is written
- @e2e exclude The upsert is reached through an event and an admin endpoint with no screen; TimetableCourseQueryTest::testAMalformedCourseOrLinkIsRefused
