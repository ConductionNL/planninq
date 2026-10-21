<?php

/**
 * Tests for BoardOrderController: a refusal keeps its status and code.
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

use OCA\Planninq\Controller\BoardOrderController;
use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\BoardOrderService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
class BoardOrderControllerTest extends TestCase {

	public function testARefusalKeepsItsStatusAndCodeAndASuccessAnswers200(): void {
		$board = $this->createMock(originalClassName: BoardOrderService::class);
		$board->method('orderColumns')->willThrowException(new ProjectActionException(reason: 'planninq-not-column-owner', status: 403, message: 'Only the project owner can change the board columns.'));
		$board->method('removeColumn')->willReturn(2);
		$controller = new BoardOrderController(
			request: $this->createMock(originalClassName: IRequest::class),
			board: $board,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$refused = $controller->orderColumns(projectId: 'proj-a', columns: ['c1']);
		self::assertSame(Http::STATUS_FORBIDDEN, $refused->getStatus());
		self::assertSame('planninq-not-column-owner', $refused->getData()['code']);
		self::assertSame(['moved' => 2], $controller->removeColumn(columnId: 'c1', target: 'c2')->getData());
	}//end testARefusalKeepsItsStatusAndCodeAndASuccessAnswers200()
}//end class
