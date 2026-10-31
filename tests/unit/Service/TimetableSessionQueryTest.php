<?php

/**
 * Unit tests for the draft rule of TimetableSessionQuery.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\TimetableSessionQuery;
use PHPUnit\Framework\TestCase;

/**
 * A read returns draft lessons only when it asks for them.
 *
 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-drafts-are-returned-only-when-the-caller-asks-for-them
 */
class TimetableSessionQueryTest extends TestCase {

	/**
	 * One lesson of Jan's week in read shape.
	 *
	 * @param string $status The lesson's status.
	 *
	 * @return array<string,string>
	 */
	private function lesson(string $status): array {
		return [
			'teacherUserId' => 'jan',
			'startsAt' => '2026-10-05T09:00:00+02:00',
			'endsAt' => '2026-10-05T09:50:00+02:00',
			'status' => $status,
		];

	}//end lesson()

	/**
	 * Without includeDrafts a draft is left out, published and cancelled lessons are not.
	 *
	 * @return void
	 */
	public function testDraftsAreLeftOutByDefault(): void {
		$query = new TimetableSessionQuery(criteria: ['teacherUserId' => 'jan']);

		self::assertFalse(condition: $query->matches(session: $this->lesson(status: 'draft')));
		self::assertTrue(condition: $query->matches(session: $this->lesson(status: 'scheduled')));
		self::assertTrue(condition: $query->matches(session: $this->lesson(status: 'cancelled')));

		$explicit = new TimetableSessionQuery(criteria: ['teacherUserId' => 'jan', 'includeDrafts' => 'false']);
		self::assertFalse(condition: $explicit->matches(session: $this->lesson(status: 'draft')));

	}//end testDraftsAreLeftOutByDefault()

	/**
	 * With includeDrafts a draft is returned beside the published lessons.
	 *
	 * @return void
	 */
	public function testDraftsAreIncludedOnRequest(): void {
		foreach ([true, 'true', '1'] as $asked) {
			$query = new TimetableSessionQuery(criteria: ['teacherUserId' => 'jan', 'includeDrafts' => $asked]);

			self::assertTrue(condition: $query->matches(session: $this->lesson(status: 'draft')));
			self::assertTrue(condition: $query->matches(session: $this->lesson(status: 'scheduled')));
		}

	}//end testDraftsAreIncludedOnRequest()
}//end class
