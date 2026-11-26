<?php

/**
 * Tests for LoggedHoursMover.
 *
 * Which rows still have hours to move, and how a reference reads.
 * The move itself is covered through MoveLoggedHoursToHumaniqTest and
 * LoggedHoursHandoverListenerTest.
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
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\LoggedHoursMover;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\LoggedHoursMover
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
class LoggedHoursMoverTest extends TestCase {

	/**
	 * A row has hours to move only when it carries minutes and references no humaniq entry.
	 *
	 * @return void
	 */
	public function testOnlyUnreferencedRowsWithMinutesHaveHoursToMove(): void {
		$mover = $this->mover();

		self::assertTrue(condition: $mover->hasHoursToMove(data: ['duration' => 30]));
		self::assertTrue(condition: $mover->hasHoursToMove(data: ['duration' => '15', 'timeEntry' => '  ']));
		self::assertFalse(condition: $mover->hasHoursToMove(data: ['duration' => 30, 'timeEntry' => 'owner-1']));
		self::assertFalse(condition: $mover->hasHoursToMove(data: ['duration' => 30, 'timeEntry' => ['id' => 'owner-1']]));
		self::assertFalse(condition: $mover->hasHoursToMove(data: ['duration' => 0]));
		self::assertFalse(condition: $mover->hasHoursToMove(data: []));
	}//end testOnlyUnreferencedRowsWithMinutesHaveHoursToMove()

	/**
	 * A reference reads as a uuid from a string, a resolved object or its `@self`.
	 *
	 * @return void
	 */
	public function testAReferenceReadsAsItsUuid(): void {
		$mover = $this->mover();

		self::assertSame(expected: 'abc', actual: $mover->referenceId(value: ' abc '));
		self::assertSame(expected: 'abc', actual: $mover->referenceId(value: ['id' => 'abc']));
		self::assertSame(expected: 'self', actual: $mover->referenceId(value: ['@self' => ['id' => 'self'], 'id' => 'abc']));
		self::assertSame(expected: '', actual: $mover->referenceId(value: null));
		self::assertSame(expected: '', actual: $mover->referenceId(value: 42));
	}//end testAReferenceReadsAsItsUuid()

	/**
	 * The minutes on a row become hours to two decimals on the owner's entry.
	 *
	 * @return void
	 */
	public function testMinutesBecomeHoursToTwoDecimals(): void {
		self::assertSame(expected: 0.33, actual: $this->mover()->ownerEntry(data: ['duration' => 20])['hours']);
	}//end testMinutesBecomeHoursToTwoDecimals()

	/**
	 * The mover over a logger double.
	 *
	 * @return LoggedHoursMover
	 */
	private function mover(): LoggedHoursMover {
		return new LoggedHoursMover(
			container: $this->createMock(originalClassName: ContainerInterface::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end mover()
}//end class
