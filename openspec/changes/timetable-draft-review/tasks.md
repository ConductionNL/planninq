# Tasks: timetable-draft-review

## 0. Precondition

- [ ] 0.1 Start after `school-timetable-target` (planninq PR #685) is merged to `development`. Verify: `git log origin/development -- lib/Service/TimetableSessionService.php` shows the merge.

## 1. Schema

- [ ] 1.1 Add `draft` to the `timetableSession.status` enum, replace `authorization.read` with the three rules of design Decision 2, bump the register and schema versions, and add catalogue keys for the changed description. Verify: PHPUnit `PlanninqRegisterSchemaTest::testTimetableSessionStatusAllowsDraft` and `testDraftReadRuleNamesTheTeacher`; `npm run check:schema-l10n` exit 0.

## 2. Upsert

- [ ] 2.1 Accept `draft` in `TimetableSessionService::STATUSES` and reject a `draft` row whose stored twin is `scheduled` or `cancelled` with `already-published`. Verify: PHPUnit `TimetableSessionServiceTest::testDraftRowIsCreatedAsDraft`, `testDraftNeverReplacesAPublishedLesson` and `testScheduledDeliveryPublishesADraft`.

## 3. Read

- [ ] 3.1 `includeDrafts` criterion in `TimetableSessionQuery` (default false) and the `includeDrafts` query parameter on `GET /api/timetable/sessions`. Verify: PHPUnit `TimetableSessionQueryTest::testDraftsAreLeftOutByDefault` and `testDraftsAreIncludedOnRequest`; `TimetableControllerTest::testSessionsPassesIncludeDrafts`.

## 4. Publish

- [ ] 4.1 `TimetableSessionService::publish(sourceSystem, from, to)` and `POST /api/timetable/sessions/publish` (admin only) with its route in `appinfo/routes.php`. Verify: PHPUnit `TimetableSessionServiceTest::testPublishTurnsDraftsInTheWindowIntoScheduled` and `testPublishLeavesOtherSourcesAlone`; `TimetableControllerTest::testPublishRefusesANonAdmin`; hydra gates `route-auth` and `route-reachability` pass.

## 5. Contract and live check

- [ ] 5.1 Add `includeDrafts`, `already-published` and the publish endpoint to the contract, marked additive under version 1. Verify: the contract diff names each addition; `openspec validate timetable-draft-review --strict` exit 0.
- [ ] 5.2 Live check on a test instance: deliver two draft lessons for teacher A, read as teacher A, as teacher B and as a learner, then publish. Verify: Playwright or API test `tests/e2e/timetable-draft.spec.ts` "the named teacher sees a draft", "a learner does not see a draft" and "after publishing everyone sees the lesson".
