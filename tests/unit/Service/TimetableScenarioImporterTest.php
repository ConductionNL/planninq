<?php

/**
 * Tests for making a scenario from the current timetable: the scheduled lessons
 * of the scenario's week become its placements, scored with the same scorer, and
 * a broken hard wish is counted, not hidden.
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

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use InvalidArgumentException;
use OCA\Planninq\Service\TimetableGridService;
use OCA\Planninq\Service\TimetableInputBuilder;
use OCA\Planninq\Service\TimetableScenarioImporter;
use OCA\Planninq\Service\TimetableScenarioStore;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The real store over an in-memory ObjectService holding lessons, a wish and a scenario.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-6.1
 */
class TimetableScenarioImporterTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The fake ObjectService.
	 *
	 * @var object
	 */
	private object $objects;

	/**
	 * Lessons in the week of 5 October 2026 (one on the next Monday, one cancelled), a hard wish and an imported scenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->objects = new class {
			/**
			 * Stored objects by schema and id.
			 *
			 * @var array<string,array<string,array<string,mixed>>>
			 */
			public array $stored = ['timetableSession' => [], 'timetableWish' => [], 'timetableScenario' => []];

			/**
			 * Saved scenario payloads.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $saves = [];

			/**
			 * OpenRegister's find.
			 *
			 * @param string $id The id.
			 *
			 * @return array<string,mixed>|null
			 */
			public function find(string $id, mixed ...$rest): ?array {
				return ($this->stored[(string)$rest['schema']][$id] ?? null);
			}

			/**
			 * OpenRegister's saveObject.
			 *
			 * @param array<string,mixed> $object The data.
			 *
			 * @return array<string,mixed>
			 */
			public function saveObject(array $object, mixed ...$rest): array {
				$this->saves[] = $object;
				$this->stored[(string)$rest['schema']][(string)$rest['uuid']] = array_merge($object, ['id' => (string)$rest['uuid']]);
				return $object;
			}

			/**
			 * OpenRegister's searchObjectsBySlug, applying the startsAt window like OpenRegister does.
			 *
			 * @param string              $registerSlug The register.
			 * @param string              $schemaSlug   The schema.
			 * @param array<string,mixed> $filters      The filters.
			 *
			 * @return array<string,mixed>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], mixed ...$rest): array {
				$rows = array_values($this->stored[$schemaSlug] ?? []);
				if (isset($filters['startsAt']) === true) {
					$rows = array_values(
						array_filter(
							$rows,
							static fn (array $row): bool => strtotime($row['startsAt']) >= strtotime($filters['startsAt']['gte'])
								&& strtotime($row['startsAt']) <= strtotime($filters['startsAt']['lte'])
						)
					);
				}

				return ['results' => $rows];
			}
		};

		$lesson = fn (string $id, string $group, string $subject, string $teacher, string $starts, string $ends, string $room='B12', string $status='scheduled'): array => [
			'id'             => $id,
			'externalRef'    => $id,
			'sourceSystem'   => 'roster-zermelo',
			'subject'        => $subject,
			'startsAt'       => $starts,
			'endsAt'         => $ends,
			'groupReference' => $group,
			'teacherUserId'  => $teacher,
			'roomReference'  => $room,
			'status'         => $status,
		];
		foreach ([
			$lesson('l-1', '3A', 'English', 'klaas', '2026-10-05T08:30:00+02:00', '2026-10-05T09:20:00+02:00'),
			$lesson('l-2', '3A', 'English', 'klaas', '2026-10-07T12:40:00+02:00', '2026-10-07T13:30:00+02:00'),
			$lesson('l-3', '3B', 'Maths', 'noor', '2026-10-05T09:20:00+02:00', '2026-10-05T11:00:00+02:00', 'B13'),
			$lesson('l-4', '3B', 'Maths', 'noor', '2026-10-06T07:00:00+02:00', '2026-10-06T07:45:00+02:00', 'B13'),
			$lesson('l-5', '3A', 'Art', 'piet', '2026-10-12T08:30:00+02:00', '2026-10-12T09:20:00+02:00'),
		] as $row) {
			$this->objects->stored['timetableSession'][$row['id']] = $row;
		}

		$this->objects->stored['timetableWish']['w-1'] = ['id' => 'w-1', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => ['wed-5', 'wed-6', 'wed-7', 'wed-8'], 'strength' => 'hard'];
		$this->objects->stored['timetableScenario']['s-1'] = [
			'id'         => 's-1',
			'title'      => 'Current timetable',
			'source'     => 'imported',
			'weekOf'     => '2026-10-05',
			'windowFrom' => '2026-10-05',
			'windowTo'   => '2026-10-30',
			'status'     => 'queued',
		];
	}//end setUp()

	/**
	 * The importer with the default grid.
	 *
	 * @return TimetableScenarioImporter
	 */
	private function importer(): TimetableScenarioImporter {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => $default);
		$grid = new TimetableGridService(appConfig: $config);

		return new TimetableScenarioImporter(
			store: new TimetableScenarioStore(container: $container),
			grid: $grid,
			inputBuilder: new TimetableInputBuilder(dispatcher: $this->createMock(IEventDispatcher::class), appConfig: $config, grid: $grid)
		);
	}//end importer()

	/**
	 * An imported timetable that breaks a hard wish shows that breach in its metrics; a lesson
	 * off the period times is unplaced with reason offGrid; a two-period lesson keeps its length.
	 *
	 * @return void
	 */
	public function testAnImportedTimetableShowsItsHardWishBreach(): void {
		$scenario = $this->importer()->importInto(id: 's-1');

		self::assertSame(expected: 'done', actual: $scenario['status']);
		self::assertSame(expected: 1, actual: $scenario['metrics']['hardWishesBroken']);
		self::assertSame(expected: 'w-1', actual: $scenario['brokenWishes'][0]['wish']);
		self::assertSame(expected: ['3A:English:2'], actual: $scenario['brokenWishes'][0]['lessons']);
		self::assertSame(
			expected: [
				['lesson' => '3A:English:1', 'period' => 'mon-1', 'room' => 'B12'],
				['lesson' => '3B:Maths:1', 'period' => 'mon-2', 'room' => 'B13'],
				['lesson' => '3A:English:2', 'period' => 'wed-6', 'room' => 'B12'],
			],
			actual: $this->sortedByLesson($scenario['placements'])
		);
		self::assertSame(expected: [['lesson' => '3B:Maths:2', 'wish' => null, 'reason' => 'offGrid']], actual: $scenario['unplaced']);
		$lengths = array_column($scenario['input']['lessons'], 'length', 'key');
		self::assertSame(expected: 2, actual: $lengths['3B:Maths:1']);
		self::assertArrayNotHasKey(key: '3A:Art:1', array: $lengths);
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: end($this->objects->saves)));
	}//end testAnImportedTimetableShowsItsHardWishBreach()

	/**
	 * A generated scenario, and a week without lessons, are refused.
	 *
	 * @return void
	 */
	public function testAGeneratedScenarioOrAnEmptyWeekIsRefused(): void {
		$this->objects->stored['timetableScenario']['s-2'] = array_merge($this->objects->stored['timetableScenario']['s-1'], ['id' => 's-2', 'source' => 'generated']);
		$this->objects->stored['timetableScenario']['s-3'] = array_merge($this->objects->stored['timetableScenario']['s-1'], ['id' => 's-3', 'weekOf' => '2027-01-04']);

		foreach (['s-2' => 'imported scenario', 's-3' => 'no scheduled lessons'] as $id => $message) {
			try {
				$this->importer()->importInto(id: $id);
				self::fail('Scenario '.$id.' was imported.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString(needle: $message, haystack: $e->getMessage());
			}
		}
	}//end testAGeneratedScenarioOrAnEmptyWeekIsRefused()

	/**
	 * Placements sorted by period order then lesson, for a stable comparison.
	 *
	 * @param array<int,array<string,string>> $placements The placements.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function sortedByLesson(array $placements): array {
		usort($placements, static fn (array $one, array $two): int => [$one['period'][0] === 'w', $one['period'], $one['lesson']] <=> [$two['period'][0] === 'w', $two['period'], $two['lesson']]);
		return $placements;
	}//end sortedByLesson()
}//end class
