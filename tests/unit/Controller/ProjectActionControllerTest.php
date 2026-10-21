<?php

/**
 * Tests for ProjectActionController: refusals keep their status and code, failures answer 500.
 *
 * @category Tests
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

use OCA\Planninq\Controller\ProjectActionController;
use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\ProjectActionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/specs/projects.md
 */
class ProjectActionControllerTest extends TestCase {

	private ProjectActionService&MockObject $actions;

	protected function setUp(): void {
		parent::setUp();
		$this->actions = $this->createMock(originalClassName: ProjectActionService::class);
	}//end setUp()

	private function controller(): ProjectActionController {
		return new ProjectActionController(
			request: $this->createMock(originalClassName: IRequest::class),
			actions: $this->actions,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end controller()

	public function testARefusalKeepsItsStatusAndCode(): void {
		$this->actions->method('deleteProject')->willThrowException(new ProjectActionException(reason: 'planninq-project-delete-not-allowed', status: 403, message: 'Only the project owner can delete this project.'));

		$response = $this->controller()->deleteProject(projectId: 'proj-a');

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertSame('planninq-project-delete-not-allowed', $response->getData()['code']);
	}//end testARefusalKeepsItsStatusAndCode()

	public function testACopyAnswers201AndAFailure500(): void {
		$this->actions->method('duplicateTask')->willReturn(['id' => 'copy-1', 'title' => 'Copy of Permit']);
		$this->actions->method('shipRelease')->willThrowException(new \RuntimeException('database gone'));

		self::assertSame(Http::STATUS_CREATED, $this->controller()->duplicateTask(taskId: 't-1')->getStatus());
		$failed = $this->controller()->shipRelease(releaseId: 'rel-1', choice: 'keep');
		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $failed->getStatus());
		self::assertStringNotContainsString('database', $failed->getData()['error']);
	}//end testACopyAnswers201AndAFailure500()
}//end class
