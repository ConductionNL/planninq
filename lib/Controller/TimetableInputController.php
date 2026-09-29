<?php

/**
 * Planninq Timetable Input Controller
 *
 * Admins upload the rooms and activities sheets the timetable generator uses
 * when no app supplies the hour plan.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableCsvParser;
use OCA\Planninq\Service\TimetableInputBuilder;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Takes the uploaded rooms and activities sheets.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.2
 */
class TimetableInputController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request         The request.
	 * @param TimetableCsvParser    $parser          Reads the sheets.
	 * @param TimetableInputBuilder $inputBuilder    Keeps the parsed sheets.
	 * @param SettingsService       $settingsService Answers whether the caller is an admin.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly TimetableCsvParser $parser,
		private readonly TimetableInputBuilder $inputBuilder,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Parse and keep the rooms and activities sheets. Admins only.
	 *
	 * Nextcloud's admin middleware refuses everyone else before this runs; the
	 * explicit check is defence in depth, as in TimetableController::upsert().
	 * Nothing is kept when either sheet has a refused line.
	 *
	 * @return JSONResponse 200 with the counts; 400 with the refused lines; 403 for a non-admin.
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.2
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function upload(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can upload timetable activities.'], Http::STATUS_FORBIDDEN);
		}

		$roomsCsv      = $this->request->getParam('rooms');
		$activitiesCsv = $this->request->getParam('activities');
		if (is_string($roomsCsv) === false || is_string($activitiesCsv) === false) {
			return new JSONResponse(['error' => 'Send the rooms and the activities sheet as text.'], Http::STATUS_BAD_REQUEST);
		}

		$rooms      = $this->parser->parseRooms(csv: $roomsCsv);
		$types      = array_values(array_unique(array_column($rooms['rows'], 'type')));
		$activities = $this->parser->parseActivities(csv: $activitiesCsv, roomTypes: $types);
		$errors     = [
			'rooms'      => $rooms['errors'],
			'activities' => $activities['errors'],
		];
		if ($rooms['errors'] !== [] || $activities['errors'] !== []) {
			return new JSONResponse(['error' => 'Some lines were refused. Nothing was kept.', 'errors' => $errors], Http::STATUS_BAD_REQUEST);
		}

		$this->inputBuilder->storeUpload(rooms: $rooms['rows'], activities: $activities['rows']);

		return new JSONResponse(
			[
				'rooms'      => count($rooms['rows']),
				'activities' => count($activities['rows']),
				'lessons'    => array_sum(array_column($activities['rows'], 'lessonsPerWeek')),
			]
		);
	}//end upload()
}//end class
