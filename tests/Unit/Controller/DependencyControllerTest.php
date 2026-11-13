<?php

/**
 * Unit tests for DependencyController: creating and deleting task dependencies and how refusals map to HTTP statuses.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\DependencyController;
use OCA\Planninq\Exception\DependencyValidationException;
use OCA\Planninq\Service\DependencyService;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Controller\DependencyController
 * @uses   \OCA\Planninq\Exception\DependencyValidationException
 */
class DependencyControllerTest extends TestCase {

	/**
	 * The dependency service.
	 *
	 * @var DependencyService&MockObject
	 */
	private DependencyService&MockObject $service;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * The controller under test.
	 *
	 * @var DependencyController
	 */
	private DependencyController $controller;

	/**
	 * Build the controller over mocked collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service    = $this->createMock(originalClassName: DependencyService::class);
		$this->logger     = $this->createMock(originalClassName: LoggerInterface::class);
		$this->controller = new DependencyController(
			request: $this->createMock(originalClassName: IRequest::class),
			dependencyService: $this->service,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * A created edge is answered 201, as `blocks` when no type is given.
	 *
	 * @return void
	 */
	public function testCreateAnswersTheEdge(): void {
		$edge = ['id' => 'dep-1', 'blocker' => 't-1', 'blocked' => 't-2', 'type' => 'blocks'];
		$this->service->expects($this->once())->method('create')->with('t-1', 't-2', 'blocks')->willReturn($edge);

		$response = $this->controller->create(blocker: 't-1', blocked: 't-2');

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: $edge, actual: $response->getData());
	}//end testCreateAnswersTheEdge()

	/**
	 * Missing ends are passed on as empty strings, and the given type is kept.
	 *
	 * @return void
	 */
	public function testCreatePassesMissingEndsAsEmpty(): void {
		$this->service->expects($this->once())->method('create')->with('', '', 'relates')->willReturn([]);

		$this->controller->create(type: 'relates');
	}//end testCreatePassesMissingEndsAsEmpty()

	/**
	 * Every refusal code answers its status with the refusal's message.
	 *
	 * @return void
	 */
	public function testRefusalsMapToTheirStatuses(): void {
		$cases = [
			DependencyValidationException::CODE_VALIDATION      => 422,
			DependencyValidationException::CODE_NOT_FOUND       => 404,
			DependencyValidationException::CODE_FORBIDDEN       => 403,
			DependencyValidationException::CODE_UNAUTHENTICATED => 401,
			DependencyValidationException::CODE_UNAVAILABLE     => 503,
		];
		foreach ($cases as $code => $status) {
			$service = $this->createMock(originalClassName: DependencyService::class);
			$service->method('create')->willThrowException(new DependencyValidationException(message: 'refused '.$code, code: $code));
			$service->method('delete')->willThrowException(new DependencyValidationException(message: 'refused '.$code, code: $code));
			$controller = new DependencyController(
				request: $this->createMock(originalClassName: IRequest::class),
				dependencyService: $service,
				logger: $this->logger
			);

			foreach ([$controller->create(blocker: 'a', blocked: 'b'), $controller->destroy(id: 'dep-1')] as $response) {
				$this->assertSame(expected: $status, actual: $response->getStatus(), message: 'code '.$code);
				$this->assertSame(expected: ['error' => 'refused '.$code], actual: $response->getData());
			}
		}
	}//end testRefusalsMapToTheirStatuses()

	/**
	 * An unexpected failure answers 500 with a generic message and logs the cause.
	 *
	 * @return void
	 */
	public function testAnUnexpectedFailureIsLoggedAndHidden(): void {
		$this->service->method('create')->willThrowException(new RuntimeException('db down'));
		$this->service->method('delete')->willThrowException(new RuntimeException('db down'));
		$this->logger->expects($this->exactly(count: 2))->method('error');

		$created = $this->controller->create(blocker: 'a', blocked: 'b');
		$deleted = $this->controller->destroy(id: 'dep-1');

		$this->assertSame(expected: 500, actual: $created->getStatus());
		$this->assertSame(expected: ['error' => 'Failed to create dependency.'], actual: $created->getData());
		$this->assertSame(expected: 500, actual: $deleted->getStatus());
		$this->assertSame(expected: ['error' => 'Failed to delete dependency.'], actual: $deleted->getData());
	}//end testAnUnexpectedFailureIsLoggedAndHidden()

	/**
	 * A delete removes the named edge and says so.
	 *
	 * @return void
	 */
	public function testDestroyDeletesTheEdge(): void {
		$this->service->expects($this->once())->method('delete')->with('dep-1');

		$response = $this->controller->destroy(id: 'dep-1');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['deleted' => true], actual: $response->getData());
	}//end testDestroyDeletesTheEdge()
}//end class
