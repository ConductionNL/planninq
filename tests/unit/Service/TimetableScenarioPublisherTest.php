<?php

/**
 * Tests for publishing a scenario as draft lessons: every week of the window,
 * each payload valid against the real timetableSession fragment, a second
 * scenario replaces the drafts of the first, and a window with published
 * generator lessons is refused.
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
use OCA\Planninq\Service\TimetableScenarioPublisher;
use OCA\Planninq\Service\TimetableScenarioStore;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The real publisher, store and session service over an in-memory ObjectService.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-8.1
 */
class TimetableScenarioPublisherTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The fake ObjectService.
	 *
	 * @var object
	 */
	private object $objects;

	/**
	 * Two finished scenarios for the same two-week window.
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
			public array $stored = ['timetableSession' => [], 'timetableScenario' => []];

			/**
			 * Saved lesson payloads.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $lessonSaves = [];

			/**
			 * The next id.
			 *
			 * @var integer
			 */
			private int $next = 1;

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
				$id = (string)($rest['uuid'] ?? ('n-'.$this->next++));
				if ($rest['schema'] === 'timetableSession') {
					$this->lessonSaves[] = $object;
				}

				$this->stored[(string)$rest['schema']][$id] = array_merge($object, ['id' => $id]);
				return $this->stored[(string)$rest['schema']][$id];
			}

			/**
			 * OpenRegister's deleteObject.
			 *
			 * @param string $uuid The id.
			 *
			 * @return bool
			 */
			public function deleteObject(string $uuid, mixed ...$rest): bool {
				unset($this->stored[(string)$rest['schema']][$uuid]);
				return true;
			}

			/**
			 * OpenRegister's searchObjectsBySlug: exact scalar filters and a startsAt window.
			 *
			 * @param string              $registerSlug The register.
			 * @param string              $schemaSlug   The schema.
			 * @param array<string,mixed> $filters      The filters.
			 *
			 * @return array<string,mixed>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], mixed ...$rest): array {
				$rows = array_filter(
					($this->stored[$schemaSlug] ?? []),
					static function (array $row) use ($filters): bool {
						foreach ($filters as $field => $value) {
							if ($field === 'startsAt') {
								$at = strtotime((string)$row['startsAt']);
								if ($at < strtotime($value['gte']) || $at > strtotime($value['lte'])) {
									return false;
								}

								continue;
							}

							if ($field[0] !== '_' && is_scalar($value) === true && ($row[$field] ?? null) !== $value) {
								return false;
							}
						}

						return true;
					}
				);

				return ['results' => array_values($rows)];
			}
		};

		$scenario = fn (string $id, array $placements): array => [
			'id'         => $id,
			'title'      => 'Try '.$id,
			'source'     => 'generated',
			'weekOf'     => '2026-10-05',
			'windowFrom' => '2026-10-05',
			'windowTo'   => '2026-10-16',
			'status'     => 'done',
			'input'      => [
				'periods' => [],
				'rooms'   => [],
				'wishes'  => [],
				'lessons' => [
					['key' => '3A:English:1', 'activity' => '3A:English', 'group' => '3A', 'subject' => 'English', 'teacher' => 'klaas', 'roomType' => 'classroom', 'length' => 1],
					['key' => '3B:Maths:1', 'activity' => '3B:Maths', 'group' => '3B', 'subject' => 'Maths', 'teacher' => 'noor', 'roomType' => 'classroom', 'length' => 2],
				],
			],
			'placements' => $placements,
		];
		$this->objects->stored['timetableScenario']['s-1'] = $scenario('s-1', [['lesson' => '3A:English:1', 'period' => 'mon-1', 'room' => 'B12'], ['lesson' => '3B:Maths:1', 'period' => 'wed-3', 'room' => 'B13']]);
		$this->objects->stored['timetableScenario']['s-2'] = $scenario('s-2', [['lesson' => '3A:English:1', 'period' => 'tue-2', 'room' => 'B12']]);
	}//end setUp()

	/**
	 * The publisher with the default grid.
	 *
	 * @return TimetableScenarioPublisher
	 */
	private function publisher(): TimetableScenarioPublisher {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => $default);

		return new TimetableScenarioPublisher(
			store: new TimetableScenarioStore(container: $container),
			sessions: new TimetableSessionService(container: $container, logger: $this->createMock(LoggerInterface::class)),
			grid: new TimetableGridService(appConfig: $config)
		);
	}//end publisher()

	/**
	 * The lessons stored from the generator, by external reference.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function generatorLessons(): array {
		$lessons = array_filter($this->objects->stored['timetableSession'], static fn (array $row): bool => $row['sourceSystem'] === TimetableScenarioPublisher::SOURCE);
		return array_column($lessons, null, 'externalRef');
	}//end generatorLessons()

	/**
	 * Scenario "Publish a scenario as drafts": every week of the window gets the pattern as drafts,
	 * each payload valid against the real timetableSession fragment, and the scenario is published.
	 *
	 * @return void
	 */
	public function testEveryWeekOfTheWindowGetsDrafts(): void {
		$result = $this->publisher()->publishDrafts(id: 's-1');

		self::assertSame(expected: 4, actual: $result['created']);
		self::assertSame(expected: 0, actual: $result['rejected']);
		$lessons = $this->generatorLessons();
		self::assertSame(
			expected: ['s-1:3A:English:1:2026-10-05', 's-1:3A:English:1:2026-10-12', 's-1:3B:Maths:1:2026-10-07', 's-1:3B:Maths:1:2026-10-14'],
			actual: array_keys($this->sortedKeys($lessons))
		);
		$maths = $lessons['s-1:3B:Maths:1:2026-10-07'];
		self::assertSame(expected: 'draft', actual: $maths['status']);
		self::assertSame(expected: '10:10', actual: date('H:i', strtotime($maths['startsAt'])));
		self::assertSame(expected: '11:50', actual: date('H:i', strtotime($maths['endsAt'])));
		foreach ($this->objects->lessonSaves as $payload) {
			self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableSession', payload: $payload));
		}

		self::assertSame(expected: 'published', actual: $this->objects->stored['timetableScenario']['s-1']['status']);
	}//end testEveryWeekOfTheWindowGetsDrafts()

	/**
	 * A second scenario for the same window replaces the first one's unpublished drafts.
	 *
	 * @return void
	 */
	public function testASecondScenarioReplacesTheDrafts(): void {
		$this->publisher()->publishDrafts(id: 's-1');
		$result = $this->publisher()->publishDrafts(id: 's-2');

		self::assertSame(expected: 4, actual: $result['removed']);
		self::assertSame(expected: ['s-2:3A:English:1:2026-10-06', 's-2:3A:English:1:2026-10-13'], actual: array_keys($this->sortedKeys($this->generatorLessons())));
	}//end testASecondScenarioReplacesTheDrafts()

	/**
	 * A window with a lesson the generator already published is refused, and nothing is written.
	 *
	 * @return void
	 */
	public function testAPublishedWindowIsRefused(): void {
		$this->publisher()->publishDrafts(id: 's-1');
		$first = array_key_first($this->objects->stored['timetableSession']);
		$this->objects->stored['timetableSession'][$first]['status'] = 'scheduled';
		$before = $this->objects->stored['timetableSession'];

		try {
			$this->publisher()->publishDrafts(id: 's-2');
			self::fail('A published window was overwritten.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString(needle: 'already published', haystack: $e->getMessage());
		}

		self::assertSame(expected: $before, actual: $this->objects->stored['timetableSession']);
		self::assertSame(expected: 'done', actual: $this->objects->stored['timetableScenario']['s-2']['status']);
	}//end testAPublishedWindowIsRefused()

	/**
	 * A scenario taken from the current timetable is not published again as drafts.
	 *
	 * @return void
	 */
	public function testAnImportedScenarioIsRefused(): void {
		$this->objects->stored['timetableScenario']['s-1']['source'] = 'imported';

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('finished generated scenario');
		$this->publisher()->publishDrafts(id: 's-1');
	}//end testAnImportedScenarioIsRefused()

	/**
	 * An array sorted by key.
	 *
	 * @param array<string,mixed> $rows The rows.
	 *
	 * @return array<string,mixed>
	 */
	private function sortedKeys(array $rows): array {
		ksort($rows);
		return $rows;
	}//end sortedKeys()
}//end class
