<?php

/**
 * Planninq Timetable Scenario Controller
 *
 * Starts a generator run for a timetable scenario (admins only).
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

use InvalidArgumentException;
use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\BackgroundJob\GenerateTimetableScenario;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableGenerationService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

/**
 * The generate endpoint of timetable scenarios.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
 */
class TimetableScenarioController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                   $request         The request.
	 * @param TimetableGenerationService $generation      Queues the run.
	 * @param IJobList                   $jobList         Runs it in the background.
	 * @param SettingsService            $settingsService Tells whether the user is an admin.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly TimetableGenerationService $generation,
		private readonly IJobList $jobList,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Queue a generator run for a scenario; the page follows its progress on the stored scenario.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function generate(string $id): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can generate a timetable.'], Http::STATUS_FORBIDDEN);
		}

		try {
			$scenario = $this->generation->queue(id: $id);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$this->jobList->add(GenerateTimetableScenario::class, ['scenario' => $id]);

		return new JSONResponse(['id' => $id, 'status' => $scenario['status'], 'lessons' => count($scenario['input']['lessons'])], Http::STATUS_ACCEPTED);
	}//end generate()
}//end class
