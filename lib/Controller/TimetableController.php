<?php

/**
 * Planninq Timetable Controller
 *
 * The HTTP doors onto the school timetable planninq holds (decision D10):
 * a signed-in user reads the sessions of a cohort, group or teacher that the
 * schema's read rule lets them see (planninq#711), and an admin can upsert a batch by hand, for example to load an export while the
 * rostering adapter is still dormant. Every rule lives in
 * {@see \OCA\Planninq\Service\TimetableSessionService}; this class only
 * translates HTTP into a service call and the service's exceptions into
 * status codes (ADR-105). Contract:
 * `openspec/changes/school-timetable-target/contract.md`.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use InvalidArgumentException;
use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use RuntimeException;

/**
 * Reads and upserts timetable sessions over HTTP.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
 */
class TimetableController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                $request         The request.
	 * @param IUserSession            $userSession     The current user session.
	 * @param TimetableSessionService $sessions        The timetable rules.
	 * @param SettingsService         $settingsService Answers whether the caller is an admin.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly TimetableSessionService $sessions,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * List the sessions of a cohort, group or teacher, optionally in a window.
	 *
	 * @param string|null $cohortId         Fleet cohort id.
	 * @param string|null $groupReference   The school's group code.
	 * @param string|null $teacherUserId    The teacher's Nextcloud user id.
	 * @param string|null $teacherReference The school's teacher code.
	 * @param string|null $from             ISO 8601 window start.
	 * @param string|null $to               ISO 8601 window end.
	 * @param int|null    $limit            Maximum rows (default 500, capped at 1,000).
	 * @param bool|null   $includeDrafts    Include draft lessons the caller may read (default false).
	 *
	 * @return JSONResponse 200 with results; 400 on bad criteria; 401 without a user; 503 without OpenRegister.
	 *
	 * @no-admin-idor-exempt TimetableSessionService::list() reads through OpenRegister with RBAC on,
	 *   so the timetableSession read rule answers the per-row question for this caller: admins read
	 *   every lesson, the planninq-timetable group every published one, a teacher reads the lessons whose
	 *   teacherUserId is their own, and any other signed-in user gets an empty list (planninq#711).
	 *   The mandatory identity filter bounds the read.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-drafts-are-returned-only-when-the-caller-asks-for-them
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function sessions(
		?string $cohortId = null,
		?string $groupReference = null,
		?string $teacherUserId = null,
		?string $teacherReference = null,
		?string $from = null,
		?string $to = null,
		?int $limit = null,
		?bool $includeDrafts = null,
	): JSONResponse {
		if ($this->userSession->getUser() === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$criteria = array_filter(
			[
				'cohortId' => $cohortId,
				'groupReference' => $groupReference,
				'teacherUserId' => $teacherUserId,
				'teacherReference' => $teacherReference,
				'from' => $from,
				'to' => $to,
				'limit' => $limit,
				'includeDrafts' => $includeDrafts,
			],
			static fn (mixed $value): bool => $value !== null && $value !== ''
		);

		try {
			$results = $this->sessions->list(criteria: $criteria);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return new JSONResponse(
			[
				'results' => $results,
				'total' => count($results),
				'window' => ['from' => $from, 'to' => $to],
			]
		);
	}//end sessions()

	/**
	 * Upsert one batch of sessions from one source. Admins only.
	 *
	 * Nextcloud's admin middleware refuses everyone else before this runs; the
	 * explicit check below is defence in depth, as in LabelController.
	 *
	 * @return JSONResponse 200 with the upsert result; 400 on a bad body; 403 for a non-admin; 503 without OpenRegister.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function upsert(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can load a timetable by hand.'], Http::STATUS_FORBIDDEN);
		}

		$sourceSystem = $this->request->getParam('sourceSystem');
		$rows = $this->request->getParam('sessions');

		if (is_string($sourceSystem) === false || trim($sourceSystem) === '') {
			return new JSONResponse(['error' => 'Name the source system of this delivery.'], Http::STATUS_BAD_REQUEST);
		}

		if (is_array($rows) === false || array_is_list($rows) === false) {
			return new JSONResponse(['error' => 'Send the lessons as a list under sessions.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->sessions->upsert(sourceSystem: $sourceSystem, sessions: $rows);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return new JSONResponse($result);
	}//end upsert()

	/**
	 * Publish the drafts of one source in a window. Admins only.
	 *
	 * Nextcloud's admin middleware refuses everyone else before this runs; the
	 * explicit check below is defence in depth, as in upsert().
	 *
	 * @return JSONResponse 200 with contractVersion, published and failed; 400 on a bad body; 403 for a non-admin; 503 without OpenRegister.
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-an-admin-publishes-the-drafts-of-one-source-in-a-date-window
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function publish(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can publish a timetable.'], Http::STATUS_FORBIDDEN);
		}

		$sourceSystem = $this->request->getParam('sourceSystem');
		$from = $this->request->getParam('from');
		$to = $this->request->getParam('to');
		if (is_string($sourceSystem) === false || is_string($from) === false || is_string($to) === false) {
			return new JSONResponse(['error' => 'Send sourceSystem, from and to.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->sessions->publish(sourceSystem: $sourceSystem, from: $from, to: $to);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return new JSONResponse($result);
	}//end publish()
}//end class
