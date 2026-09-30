<?php

/**
 * Tests for the generate endpoint: admins only, a refused scenario answers 400,
 * an accepted one is queued as a background job.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Planninq\BackgroundJob\GenerateTimetableScenario;
use OCA\Planninq\Controller\TimetableScenarioController;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableGenerationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The generate endpoint.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
 */
class TimetableScenarioControllerTest extends TestCase {

	/**
	 * A controller for the given caller, with the given generation service and job list.
	 *
	 * @param bool                       $admin      Whether the caller is an admin.
	 * @param TimetableGenerationService $generation The generation service.
	 * @param IJobList                   $jobList    The job list.
	 *
	 * @return TimetableScenarioController
	 */
	private function controller(bool $admin, TimetableGenerationService $generation, IJobList $jobList): TimetableScenarioController {
		$settings = $this->createMock(SettingsService::class);
		$settings->method('isCurrentUserAdmin')->willReturn($admin);
		return new TimetableScenarioController(request: $this->createMock(IRequest::class), generation: $generation, jobList: $jobList, settingsService: $settings);
	}//end controller()

	/**
	 * A non-admin is refused and nothing is queued.
	 *
	 * @return void
	 */
	public function testANonAdminIsRefused(): void {
		$generation = $this->createMock(TimetableGenerationService::class);
		$generation->expects($this->never())->method('queue');
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->never())->method('add');

		$response = $this->controller(admin: false, generation: $generation, jobList: $jobList)->generate(id: 's-1');

		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
		self::assertNotEmpty((new ReflectionMethod(TimetableScenarioController::class, 'generate'))->getAttributes(AuthorizedAdminSetting::class));
	}//end testANonAdminIsRefused()

	/**
	 * An admin's run is queued and answered with 202.
	 *
	 * @return void
	 */
	public function testAnAdminQueuesARun(): void {
		$generation = $this->createMock(TimetableGenerationService::class);
		$generation->method('queue')->willReturn(['status' => 'queued', 'input' => ['lessons' => [['key' => 'a'], ['key' => 'b']]]]);
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->once())->method('add')->with(GenerateTimetableScenario::class, ['scenario' => 's-1']);

		$response = $this->controller(admin: true, generation: $generation, jobList: $jobList)->generate(id: 's-1');

		self::assertSame(expected: Http::STATUS_ACCEPTED, actual: $response->getStatus());
		self::assertSame(expected: ['id' => 's-1', 'status' => 'queued', 'lessons' => 2], actual: $response->getData());
	}//end testAnAdminQueuesARun()

	/**
	 * A scenario the service refuses answers 400 with the reason and queues nothing.
	 *
	 * @return void
	 */
	public function testARefusedScenarioAnswersBadRequest(): void {
		$generation = $this->createMock(TimetableGenerationService::class);
		$generation->method('queue')->willThrowException(new InvalidArgumentException('No activities: learniq did not answer and no CSV was uploaded'));
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->never())->method('add');

		$response = $this->controller(admin: true, generation: $generation, jobList: $jobList)->generate(id: 's-1');

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		self::assertStringContainsString(needle: 'No activities', haystack: $response->getData()['error']);
	}//end testARefusedScenarioAnswersBadRequest()
}//end class
