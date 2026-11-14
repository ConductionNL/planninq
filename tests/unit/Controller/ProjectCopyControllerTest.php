<?php

/**
 * Unit tests for ProjectCopyController.
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\ProjectCopyController;
use OCA\Planninq\Service\ProjectCopyService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The route hands the caller and the body to the service and answers its status.
 */
#[CoversClass(ProjectCopyController::class)]
class ProjectCopyControllerTest extends TestCase {

	/**
	 * A controller for this caller and body.
	 *
	 * @param ProjectCopyService  $service The copy service.
	 * @param string|null         $uid     The caller, or null for none.
	 * @param array<string,mixed> $body    The request body.
	 *
	 * @return ProjectCopyController
	 */
	private function controller(ProjectCopyService $service, ?string $uid, array $body = []): ProjectCopyController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => ($body[$key] ?? $default));
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new ProjectCopyController(request: $request, copies: $service, userSession: $session);
	}//end controller()

	/**
	 * A signed-in non-admin reaches the route; the service decides the policy.
	 *
	 * @return void
	 */
	public function testTheRouteIsOpenToSignedInUsers(): void {
		$this->assertCount(1, (new ReflectionMethod(ProjectCopyController::class, 'copy'))->getAttributes(NoAdminRequired::class));
	}//end testTheRouteIsOpenToSignedInUsers()

	/**
	 * Without a caller nothing is copied.
	 *
	 * @return void
	 */
	public function testNoCallerIs401(): void {
		$service = $this->createMock(ProjectCopyService::class);
		$service->expects($this->never())->method('copy');

		$this->assertSame(401, $this->controller(service: $service, uid: null)->copy(projectId: 'p1')->getStatus());
	}//end testNoCallerIs401()

	/**
	 * The caller and only the fields sent reach the service; a created copy answers 201 with the project.
	 *
	 * @return void
	 */
	public function testACopyIsCreatedForTheCaller(): void {
		$service = $this->createMock(ProjectCopyService::class);
		$service->expects($this->once())
			->method('copy')
			->with('p1', 'dave', ['title' => 'Woo', 'parts' => ['people' => true]])
			->willReturn(['status' => 201, 'project' => ['id' => 'p2', 'title' => 'Woo']]);

		$response = $this->controller(service: $service, uid: 'dave', body: ['title' => 'Woo', 'parts' => ['people' => true], 'owner' => 'mallory'])->copy(projectId: 'p1');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame(['id' => 'p2', 'title' => 'Woo'], $response->getData());
	}//end testACopyIsCreatedForTheCaller()

	/**
	 * A refusal keeps its status and message.
	 *
	 * @return void
	 */
	public function testARefusalKeepsItsStatus(): void {
		$service = $this->createMock(ProjectCopyService::class);
		$service->method('copy')->willReturn(['status' => 403, 'error' => 'Project creation is restricted to administrators.']);

		$response = $this->controller(service: $service, uid: 'dave', body: ['title' => 'Woo'])->copy(projectId: 'p1');

		$this->assertSame(403, $response->getStatus());
		$this->assertSame(['error' => 'Project creation is restricted to administrators.'], $response->getData());
	}//end testARefusalKeepsItsStatus()
}//end class
