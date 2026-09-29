# Tasks: timetable-draft-review

## 0. Precondition

- [x] 0.1 Start after `school-timetable-target` (planninq PR #685) is merged to `development`. Verify: `git log origin/development -- lib/Service/TimetableSessionService.php` shows the merge.

## 1. Schema

- [x] 1.1 Add `draft` to the `timetableSession.status` enum, replace `authorization.read` with the three rules of design Decision 2, bump the register and schema versions, and add catalogue keys for the changed description. Verify: PHPUnit `PlanninqRegisterSchemaTest::testTimetableSessionStatusAllowsDraft` and `testDraftReadRuleNamesTheTeacher`; `npm run check:schema-l10n` exit 0.

## 2. Upsert

- [x] 2.1 Accept `draft` in `TimetableSessionService::STATUSES` and reject a `draft` row whose stored twin is `scheduled` or `cancelled` with `already-published`. Verify: PHPUnit `TimetableSessionServiceTest::testDraftRowIsCreatedAsDraft`, `testDraftNeverReplacesAPublishedLesson` and `testScheduledDeliveryPublishesADraft`.

## 3. Read

- [x] 3.1 `includeDrafts` criterion in `TimetableSessionQuery` (default false) and the `includeDrafts` query parameter on `GET /api/timetable/sessions`. Verify: PHPUnit `TimetableSessionQueryTest::testDraftsAreLeftOutByDefault` and `testDraftsAreIncludedOnRequest`; `TimetableControllerTest::testSessionsPassesIncludeDrafts`.

## 4. Publish

- [x] 4.1 `TimetableSessionService::publish(sourceSystem, from, to)` and `POST /api/timetable/sessions/publish` (admin only) with its route in `appinfo/routes.php`. Verify: PHPUnit `TimetableSessionServiceTest::testPublishTurnsDraftsInTheWindowIntoScheduled` and `testPublishLeavesOtherSourcesAlone`; `TimetableControllerTest::testPublishRefusesANonAdmin`; hydra gates `route-auth` and `route-reachability` pass.

## 5. Contract and live check

- [x] 5.1 Add `includeDrafts`, `already-published` and the publish endpoint to the contract, marked additive under version 1. Verify: the contract diff names each addition; `openspec validate timetable-draft-review --strict` exit 0.
- [x] 5.2 Live check on a test instance: deliver two draft lessons for teacher A, read as teacher A, as teacher B and as a learner, then publish. Verify: Playwright or API test `tests/e2e/timetable-draft.spec.ts` "the named teacher sees a draft", "a learner does not see a draft" and "after publishing everyone sees the lesson".

## Notes from the build (2026-09-29)

- 0.1: #685 (f8ec5c2) and #761 (740d73a) are on development; school-timetable-target is archived in the PR below this one (#770).
- 1.1: the design's Decision 2 read "signed-in users where status is in scheduled, cancelled"; since planninq#711 the first rule is the `planninq-timetable` group, so the status condition sits on that rule, as the design's own context note says. The condition was run through OpenRegister's real `OperatorEvaluator` (`$in` denies `draft` and a missing status, admits `scheduled` and `cancelled`; control: a rule listing `draft` admits it). Extra behavioural test: `TimetableSessionReadRuleTest::testDraftIsReadOnlyByItsTeacherAndAdmins`.
- 2.1: also `TimetableSessionRows::toReadShape()` now carries `draft` (it mapped every status but `cancelled` to `scheduled`, so a draft would have read as published). Every written payload is validated against the real schema in the tests.
- 3.1: the query event test is `TimetableSessionsQueryListenerTest::testConsumerThatDoesNotAskGetsNoDrafts`; the controller test named `TimetableSessionQueryTest` lives in `tests/unit/Service/TimetableSessionQueryTest.php`.
- 4.1: controller test `testPublishRefusesANonAdmin` also asserts the admin path, the attributes and the route.
- 5.1: the additions sit in `openspec/changes/archive/2026-09-29-school-timetable-target/contract.md` (criteria key, endpoint, rejection code) and in `docs/features/school-timetable.md`.
- 5.2: `tests/e2e/timetable-draft.spec.ts` is written against the HTTP API; Playwright does not run from the lane clone, so it first runs in the nightly e2e job. The spec was corrected to the #711 read model: a learner reads no lesson over HTTP at all, so "visible after publishing" is checked for a `planninq-timetable` member, and a scenario for that group seeing no draft was added.

