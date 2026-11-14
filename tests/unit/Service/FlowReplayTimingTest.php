<?php

/**
 * Unit tests for when FlowReplay starts a task's cycle time.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Planninq\Service\FlowBoard;
use OCA\Planninq\Service\FlowReplay;
use PHPUnit\Framework\TestCase;

/**
 * Cycle time of a task that never left the first column, and of a task with an audit row from before its creation.
 *
 * @covers \OCA\Planninq\Service\FlowReplay
 * @covers \OCA\Planninq\Service\FlowBoard
 */
class FlowReplayTimingTest extends TestCase {

	/**
	 * A task that stays in the first column starts when it first becomes in_progress.
	 *
	 * @return void
	 */
	public function testCycleStartsAtInProgressWhenTheTaskStaysInTheFirstColumn(): void {
		$timing = $this->timing(
			history: [
				['created' => '2026-10-03T09:00:00Z', 'changed' => ['status' => ['old' => 'open', 'new' => 'in_progress']]],
				['created' => '2026-10-05T09:00:00Z', 'changed' => ['status' => ['old' => 'in_progress', 'new' => 'done']]],
			]
		);

		self::assertSame(expected: '2026-10-03T09:00:00+00:00', actual: $timing['startedAt']);
		self::assertSame(expected: 2.0, actual: $timing['cycleDays']);
	}//end testCycleStartsAtInProgressWhenTheTaskStaysInTheFirstColumn()

	/**
	 * An audit row dated before the task's creation counts from the creation.
	 *
	 * @return void
	 */
	public function testAnAuditRowBeforeCreationCountsFromTheCreation(): void {
		$timing = $this->timing(
			history: [
				['created' => '2026-09-28T09:00:00Z', 'changed' => ['status' => ['old' => 'open', 'new' => 'in_progress']]],
				['created' => '2026-10-05T09:00:00Z', 'changed' => ['status' => ['old' => 'in_progress', 'new' => 'done']]],
			]
		);

		self::assertSame(expected: '2026-10-01T09:00:00+00:00', actual: $timing['startedAt']);
		self::assertSame(expected: 4.0, actual: $timing['cycleDays']);
	}//end testAnAuditRowBeforeCreationCountsFromTheCreation()

	/**
	 * The timing of the one task, created 1 October and done on 5 October in the first column.
	 *
	 * @param array<int,array<string,mixed>> $history The task's audit rows.
	 *
	 * @return array<string,mixed>
	 */
	private function timing(array $history): array {
		$board = new FlowBoard(
			columns: [['id' => 'todo', 'order' => 0], ['id' => 'doing', 'order' => 1]],
			tasks: [['id' => 't1', 'column' => 'todo', 'status' => 'done', 'created' => '2026-10-01T09:00:00Z', 'completedAt' => '2026-10-05T09:00:00Z']],
			histories: ['t1' => $history]
		);
		$utc  = new DateTimeZone('UTC');
		$days = (new FlowReplay())->replay(
			board: $board,
			window: [
				'from' => new DateTimeImmutable('2026-10-05', $utc),
				'to'   => new DateTimeImmutable('2026-10-05', $utc),
				'now'  => new DateTimeImmutable('2026-10-06', $utc),
			]
		);

		return $days['2026-10-05']['finished'][0];
	}//end timing()
}//end class
