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
use OCA\Planninq\Service\TimetableScenarioImporter;
use OCA\Planninq\Service\TimetableScenarioPublisher;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

/**
 * The generate, import and publish endpoints of timetable scenarios.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
 * @spec openspec/changes/timetabling-generator/tasks.md#task-8.1
 */
class TimetableScenarioController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                   $request         The request.
	 * @param TimetableGenerationService $generation      Queues the run.
	 * @param IJobList                   $jobList         Runs it in the background.
	 * @param SettingsService            $settingsService Tells whether the user is an admin.
	 * @param TimetableScenarioImporter  $importer        Takes the current timetable into a scenario.
	 * @param TimetableScenarioPublisher $publisher       Writes a scenario as draft lessons.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly TimetableGenerationService $generation,
		private readonly IJobList $jobList,
		private readonly SettingsService $settingsService,
		private readonly TimetableScenarioImporter $importer,
		private readonly TimetableScenarioPublisher $publisher,
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
	/**
	 * Fill an imported scenario from the scheduled lessons of its week, scored like a generated one.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function importCurrent(string $id): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can take the current timetable into a scenario.'], Http::STATUS_FORBIDDEN);
		}

		try {
			$scenario = $this->importer->importInto(id: $id);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['id' => $id, 'status' => $scenario['status'], 'metrics' => $scenario['metrics']]);
	}//end importCurrent()
	/**
	 * Write a finished scenario's placements as draft lessons for every week of its window.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-8.1
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function publishDrafts(string $id): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Only an admin can publish a timetable scenario.'], Http::STATUS_FORBIDDEN);
		}

		try {
			$result = $this->publisher->publishDrafts(id: $id);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(array_merge(['id' => $id], $result));
	}//end publishDrafts()
}//end class
