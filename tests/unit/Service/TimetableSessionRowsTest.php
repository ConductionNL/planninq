<?php

/**
 * Unit tests for TimetableSessionRows and TimetableSessionQuery.
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

use InvalidArgumentException;
use OCA\Planninq\Service\TimetableSessionQuery;
use OCA\Planninq\Service\TimetableSessionRows;
use PHPUnit\Framework\TestCase;

/**
 * Tests row reading and query building for the timetable.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */
class TimetableSessionRowsTest extends TestCase {

	/**
	 * An entity that answers its accessors through __call(), the way OpenRegister's does.
	 *
	 * @return object
	 */
	private function magicEntity(): object {
		return new class {
			/**
			 * Magic accessor.
			 *
			 * @param string       $name The method.
			 * @param array<mixed> $args Unused.
			 *
			 * @return mixed
			 */
			public function __call(string $name, array $args): mixed {
				unset($args);
				if ($name === 'getUuid') {
					return 'entity-1';
				}

				if ($name === 'getObject') {
					return ['externalRef' => 'zm-1', 'subject' => 'Wiskunde', 'startsAt' => 'x', 'endsAt' => 'y', 'status' => 'cancelled'];
				}

				return null;
			}
		};

	}//end magicEntity()

	/**
	 * A magic-accessor entity yields its id and data, and its read shape.
	 *
	 * @return void
	 */
	public function testMagicEntityIsRead(): void {
		$rows = new TimetableSessionRows();
		$shape = $rows->toReadShape(row: $this->magicEntity());

		self::assertSame(expected: 'entity-1', actual: $shape['id']);
		self::assertSame(expected: 'Wiskunde', actual: $shape['title']);
		self::assertSame(expected: 'cancelled', actual: $shape['status']);
		self::assertNull(actual: $shape['cohortId']);

	}//end testMagicEntityIsRead()

	/**
	 * A paginated block, a plain list and anything else normalise to a list.
	 *
	 * @return void
	 */
	public function testResultSetsNormaliseToAList(): void {
		$rows = new TimetableSessionRows();

		self::assertSame(expected: [['a' => 1]], actual: $rows->listOf(results: ['results' => [['a' => 1]], 'total' => 1]));
		self::assertSame(expected: [['a' => 1]], actual: $rows->listOf(results: [5 => ['a' => 1]]));
		self::assertSame(expected: [], actual: $rows->listOf(results: 7));
		self::assertNull(actual: $rows->toReadShape(row: ['subject' => 'no id']));
		self::assertSame(expected: 'r-1', actual: $rows->idOf(row: ['@self' => ['id' => 'r-1']]));

	}//end testResultSetsNormaliseToAList()

	/**
	 * The query builds a bounded filter with a widened window and re-checks exactly.
	 *
	 * @return void
	 */
	public function testQueryFiltersAndMatches(): void {
		$query = new TimetableSessionQuery(
			criteria: ['groupReference' => ' 3a ', 'from' => '2026-09-28T00:00:00Z', 'to' => '2026-09-28T23:59:59Z', 'limit' => '0']
		);

		$filters = $query->filters();
		self::assertSame(expected: '3a', actual: $filters['groupReference']);
		self::assertSame(expected: 1, actual: $filters['_limit'], message: 'a limit below one is raised to one');
		self::assertLessThan(expected: strtotime('2026-09-28T00:00:00Z'), actual: strtotime($filters['endsAt']['gte']));
		self::assertGreaterThan(expected: strtotime('2026-09-28T23:59:59Z'), actual: strtotime($filters['startsAt']['lte']));

		$inside = ['groupReference' => '3a', 'status' => 'scheduled', 'startsAt' => '2026-09-28T09:00:00+02:00', 'endsAt' => '2026-09-28T09:50:00+02:00'];
		self::assertTrue(condition: $query->matches(session: $inside));
		self::assertFalse(condition: $query->matches(session: array_merge($inside, ['groupReference' => '3b'])));
		self::assertFalse(condition: $query->matches(session: array_merge($inside, ['startsAt' => '2026-09-29T09:00:00+02:00'])));
		self::assertFalse(condition: $query->matches(session: array_merge($inside, ['endsAt' => 'not a time'])));

	}//end testQueryFiltersAndMatches()

	/**
	 * An unparseable window bound is refused.
	 *
	 * @return void
	 */
	public function testQueryRefusesAnUnreadableBound(): void {
		$this->expectException(InvalidArgumentException::class);
		new TimetableSessionQuery(criteria: ['cohortId' => 'c-1', 'from' => 'next tuesday-ish']);

	}//end testQueryRefusesAnUnreadableBound()
}//end class
