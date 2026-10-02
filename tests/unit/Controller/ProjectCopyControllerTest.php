<?php

/**
 * Tests for ProjectCopyController: the caller's identity, groups and admin
 * flag reach the copy, and a refusal or failure keeps its status and step.
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\ProjectCopyController;
use OCA\Planninq\Exception\ProjectCopyException;
use OCA\Planninq\Service\ProjectCopyService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyControllerTest extends TestCase {

	/**
	 * A controller for this caller (null: not signed in) over this service.
	 */
	private function controller(?string $uid, ProjectCopyService $service): ProjectCopyController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => (['title' => 'Kopie', 'startDate' => '2027-06-01', 'parts' => ['tasks']][$key] ?? $default)
		);

		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturn(['pmo']);
		$groups->method('isAdmin')->willReturn(false);

		return new ProjectCopyController(request: $request, copyService: $service, userSession: $session, groupManager: $groups);
	}//end controller()

	public function testASignedOutCallerIsRefusedBeforeTheCopy(): void {
		$service = $this->createMock(originalClassName: ProjectCopyService::class);
		$service->expects(self::never())->method('copy');

		self::assertSame(401, $this->controller(uid: null, service: $service)->copy(projectId: 'p1')->getStatus());
	}//end testASignedOutCallerIsRefusedBeforeTheCopy()

	public function testTheCallerAndTheOptionsReachTheCopy(): void {
		$service = $this->createMock(originalClassName: ProjectCopyService::class);
		$service->expects(self::once())->method('copy')
			->with('p1', 'mark', ['pmo'], false, ['title' => 'Kopie', 'key' => null, 'startDate' => '2027-06-01', 'parts' => ['tasks']])
			->willReturn(['id' => 'new', 'project' => ['id' => 'new'], 'counts' => ['columns' => 0, 'phases' => 0, 'tasks' => 3, 'dependencies' => 0]]);

		$response = $this->controller(uid: 'mark', service: $service)->copy(projectId: 'p1');

		self::assertSame(201, $response->getStatus());
		self::assertSame('new', $response->getData()['id']);
	}//end testTheCallerAndTheOptionsReachTheCopy()

	public function testAFailedCopyAnswersWithItsStatusAndStep(): void {
		$service = $this->createMock(originalClassName: ProjectCopyService::class);
		$service->method('copy')->willThrowException(
			new ProjectCopyException(status: 500, reason: 'planninq-copy-failed', message: 'The copy failed while copying tasks; nothing was kept.', step: 'tasks')
		);

		$response = $this->controller(uid: 'mark', service: $service)->copy(projectId: 'p1');

		self::assertSame(500, $response->getStatus());
		self::assertSame('tasks', $response->getData()['step']);
		self::assertSame('planninq-copy-failed', $response->getData()['code']);
	}//end testAFailedCopyAnswersWithItsStatusAndStep()

	public function testARefusalKeepsItsStatus(): void {
		$service = $this->createMock(originalClassName: ProjectCopyService::class);
		$service->method('copy')->willThrowException(new ProjectCopyException(status: 403, reason: 'planninq-copy-forbidden', message: 'no'));

		self::assertSame(403, $this->controller(uid: 'mia', service: $service)->copy(projectId: 'p1')->getStatus());
	}//end testARefusalKeepsItsStatus()
}//end class
