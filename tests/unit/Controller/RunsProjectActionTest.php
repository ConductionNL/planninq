<?php

/**
 * Tests for RunsProjectAction: a write answers with its result, its refusal, or a logged generic error.
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
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\RunsProjectAction;
use OCA\Planninq\Exception\ProjectActionException;
use OCP\AppFramework\Http\JSONResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\RunsProjectAction
 * @uses   \OCA\Planninq\Exception\ProjectActionException
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
class RunsProjectActionTest extends TestCase {

	/**
	 * The logger the runner writes to.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Set up the logger.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * Run one action through a class using the trait.
	 *
	 * @param callable $action  The action.
	 * @param string   $context The log context.
	 *
	 * @return JSONResponse
	 */
	private function runAction(callable $action, string $context): JSONResponse {
		$runner = new class($this->logger) {
			use RunsProjectAction;

			/**
			 * Constructor.
			 *
			 * @param LoggerInterface $logger The logger.
			 */
			public function __construct(private LoggerInterface $logger) {
			}//end __construct()

			/**
			 * Run an action.
			 *
			 * @param callable $action  The action.
			 * @param string   $context The log context.
			 *
			 * @return JSONResponse
			 */
			public function answer(callable $action, string $context): JSONResponse {
				return $this->run(action: $action, context: $context);
			}//end answer()
		};

		return $runner->answer(action: $action, context: $context);
	}//end runAction()

	/**
	 * A finished action answers 200 with its result and logs nothing.
	 *
	 * @return void
	 */
	public function testAFinishedActionAnswersItsResult(): void {
		$this->logger->expects($this->never())->method('error');

		$response = $this->runAction(action: fn (): array => ['moved' => 3], context: 'column remove');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['moved' => 3], $response->getData());
	}//end testAFinishedActionAnswersItsResult()

	/**
	 * A refusal answers with its own status, message and code.
	 *
	 * @return void
	 */
	public function testARefusalAnswersItsStatusAndCode(): void {
		$this->logger->expects($this->never())->method('error');

		$response = $this->runAction(
			action: function (): array {
				throw new ProjectActionException(reason: 'planninq-not-allowed', status: 403, message: 'Not yours.');
			},
			context: 'project delete'
		);

		$this->assertSame(403, $response->getStatus());
		$this->assertSame(['error' => 'Not yours.', 'code' => 'planninq-not-allowed'], $response->getData());
	}//end testARefusalAnswersItsStatusAndCode()

	/**
	 * Any other failure is logged with its context and answers a generic 500.
	 *
	 * @return void
	 */
	public function testAnyOtherFailureIsLoggedAndAnswers500(): void {
		$this->logger->expects($this->once())->method('error')
			->with('Planninq: task copy failed', ['exception' => 'disk full']);

		$response = $this->runAction(
			action: function (): array {
				throw new \RuntimeException('disk full');
			},
			context: 'task copy'
		);

		$this->assertSame(500, $response->getStatus());
		$this->assertSame(['error' => 'Something went wrong. Please try again.'], $response->getData());
	}//end testAnyOtherFailureIsLoggedAndAnswers500()
}//end class
