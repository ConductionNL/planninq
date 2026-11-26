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

		self::assertTrue($mover->hasHoursToMove(data: ['duration' => 30]));
		self::assertTrue($mover->hasHoursToMove(data: ['duration' => '15', 'timeEntry' => '  ']));
		self::assertFalse($mover->hasHoursToMove(data: ['duration' => 30, 'timeEntry' => 'owner-1']));
		self::assertFalse($mover->hasHoursToMove(data: ['duration' => 30, 'timeEntry' => ['id' => 'owner-1']]));
		self::assertFalse($mover->hasHoursToMove(data: ['duration' => 0]));
		self::assertFalse($mover->hasHoursToMove(data: []));
	}//end testOnlyUnreferencedRowsWithMinutesHaveHoursToMove()

	/**
	 * A reference reads as a uuid from a string, a resolved object or its `@self`.
	 *
	 * @return void
	 */
	public function testAReferenceReadsAsItsUuid(): void {
		$mover = $this->mover();

		self::assertSame('abc', $mover->referenceId(value: ' abc '));
		self::assertSame('abc', $mover->referenceId(value: ['id' => 'abc']));
		self::assertSame('self', $mover->referenceId(value: ['@self' => ['id' => 'self'], 'id' => 'abc']));
		self::assertSame('', $mover->referenceId(value: null));
		self::assertSame('', $mover->referenceId(value: 42));
	}//end testAReferenceReadsAsItsUuid()

	/**
	 * The minutes on a row become hours to two decimals on the owner's entry.
	 *
	 * @return void
	 */
	public function testMinutesBecomeHoursToTwoDecimals(): void {
		self::assertSame(0.33, $this->mover()->ownerEntry(data: ['duration' => 20])['hours']);
	}//end testMinutesBecomeHoursToTwoDecimals()

	/**
	 * The mover over a logger double.
	 *
	 * @return LoggedHoursMover
	 */
	private function mover(): LoggedHoursMover {
		return new LoggedHoursMover($this->createMock(LoggerInterface::class));
	}//end mover()
}//end class
