<?php

/**
 * Unit tests for DependencyController.
 *
 * Verifies the HTTP translation of DependencyService results: 201 with the
 * created edge, 200 on delete, every DependencyValidationException code mapped
 * to its status with the service's message, and a logged 500 for anything else.
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
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\DependencyController;
use OCA\Planninq\Exception\DependencyValidationException;
use OCA\Planninq\Service\DependencyService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for DependencyController.
 *
 * @covers \OCA\Planninq\Controller\DependencyController
 * @uses   \OCA\Planninq\Exception\DependencyValidationException
 */
class DependencyControllerTest extends TestCase {

	/**
	 * The dependency service double.
	 *
	 * @var DependencyService&MockObject
	 */
	private DependencyService&MockObject $service;

	/**
	 * The logger double.
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
	 * Build the controller over fresh doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(originalClassName: DependencyService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->controller = new DependencyController(
			request: $this->createMock(originalClassName: IRequest::class),
			dependencyService: $this->service,
			logger: $this->logger,
		);

	}//end setUp()

	/**
	 * Each exception code and the status it maps to.
	 *
	 * @return array<string,array{int,int}>
	 */
	public static function codeProvider(): array {
		return [
			'validation'      => [DependencyValidationException::CODE_VALIDATION, Http::STATUS_UNPROCESSABLE_ENTITY],
			'not found'       => [DependencyValidationException::CODE_NOT_FOUND, Http::STATUS_NOT_FOUND],
			'forbidden'       => [DependencyValidationException::CODE_FORBIDDEN, Http::STATUS_FORBIDDEN],
			'unauthenticated' => [DependencyValidationException::CODE_UNAUTHENTICATED, Http::STATUS_UNAUTHORIZED],
			'unavailable'     => [DependencyValidationException::CODE_UNAVAILABLE, Http::STATUS_SERVICE_UNAVAILABLE],
			'unknown code'    => [99, Http::STATUS_UNPROCESSABLE_ENTITY],
		];

	}//end codeProvider()

	/**
	 * A created edge is returned with 201; the type defaults to `blocks`.
	 *
	 * @return void
	 */
	public function testCreateReturnsTheEdgeWith201(): void {
		$edge = ['id' => 'd-1', 'blocker' => 't-1', 'blocked' => 't-2', 'type' => 'blocks'];
		$this->service->expects($this->once())->method('create')
			->with('t-1', 't-2', 'blocks')
			->willReturn($edge);

		$response = $this->controller->create(blocker: 't-1', blocked: 't-2');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($edge, $response->getData());

	}//end testCreateReturnsTheEdgeWith201()

	/**
	 * An explicit type is passed through and missing ids become empty strings.
	 *
	 * @return void
	 */
	public function testCreatePassesTheTypeAndEmptyIds(): void {
		$this->service->expects($this->once())->method('create')
			->with('', '', 'relates')
			->willReturn(['id' => 'd-2']);

		$response = $this->controller->create(type: 'relates');

		$this->assertSame(['id' => 'd-2'], $response->getData());

	}//end testCreatePassesTheTypeAndEmptyIds()

	/**
	 * A refused create maps the exception code to its status, with the message.
	 *
	 * @param int $code   The exception code.
	 * @param int $status The expected HTTP status.
	 *
	 * @return void
	 *
	 * @dataProvider codeProvider
	 */
	public function testCreateMapsValidationCodes(int $code, int $status): void {
		$this->service->method('create')->willThrowException(new DependencyValidationException('A task cannot block itself.', $code));
		$this->logger->expects($this->never())->method('error');

		$response = $this->controller->create(blocker: 't-1', blocked: 't-1');

		$this->assertSame($status, $response->getStatus());
		$this->assertSame(['error' => 'A task cannot block itself.'], $response->getData());

	}//end testCreateMapsValidationCodes()

	/**
	 * Any other failure on create is logged and answered with a generic 500.
	 *
	 * @return void
	 */
	public function testCreateUnexpectedFailureIsLoggedAnd500(): void {
		$this->service->method('create')->willThrowException(new \RuntimeException('db down'));
		$this->logger->expects($this->once())->method('error')
			->with('Planninq: dependency create failed', ['exception' => 'db down']);

		$response = $this->controller->create(blocker: 't-1', blocked: 't-2');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Failed to create dependency.'], $response->getData());

	}//end testCreateUnexpectedFailureIsLoggedAnd500()

	/**
	 * A delete calls the service with the id and answers deleted.
	 *
	 * @return void
	 */
	public function testDestroyDeletesTheEdge(): void {
		$this->service->expects($this->once())->method('delete')->with('d-1');

		$response = $this->controller->destroy(id: 'd-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['deleted' => true], $response->getData());

	}//end testDestroyDeletesTheEdge()

	/**
	 * A refused delete maps the exception code to its status, with the message.
	 *
	 * @param int $code   The exception code.
	 * @param int $status The expected HTTP status.
	 *
	 * @return void
	 *
	 * @dataProvider codeProvider
	 */
	public function testDestroyMapsValidationCodes(int $code, int $status): void {
		$this->service->method('delete')->willThrowException(new DependencyValidationException('Not a member of this project.', $code));
		$this->logger->expects($this->never())->method('error');

		$response = $this->controller->destroy(id: 'd-1');

		$this->assertSame($status, $response->getStatus());
		$this->assertSame(['error' => 'Not a member of this project.'], $response->getData());

	}//end testDestroyMapsValidationCodes()

	/**
	 * Any other failure on delete is logged with the id and answered with a generic 500.
	 *
	 * @return void
	 */
	public function testDestroyUnexpectedFailureIsLoggedAnd500(): void {
		$this->service->method('delete')->willThrowException(new \RuntimeException('db down'));
		$this->logger->expects($this->once())->method('error')
			->with('Planninq: dependency delete failed', ['exception' => 'db down', 'id' => 'd-1']);

		$response = $this->controller->destroy(id: 'd-1');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Failed to delete dependency.'], $response->getData());

	}//end testDestroyUnexpectedFailureIsLoggedAnd500()
}//end class
