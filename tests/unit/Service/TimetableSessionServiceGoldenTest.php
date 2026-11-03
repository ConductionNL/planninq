<?php

/**
 * Golden test for the upsert and publish paths of the timetable session service.
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

require_once __DIR__ . '/../Support/InMemoryTimetableObjectService.php';

use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryTimetableObjectService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Throwable;

/**
 * Runs a fixed set of deliveries and publish calls against seeded stores and
 * compares every result, stored row, save and log line with a recording.
 *
 * @covers \OCA\Planninq\Service\TimetableSessionService
 * @uses   \OCA\Planninq\Service\TimetableSessionRows
 * @uses   \OCA\Planninq\Service\TimetableRowRules
 * @uses   \OCA\Planninq\Service\TimetableSessionQuery
 */
class TimetableSessionServiceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/timetable-session-service.golden.json';

	/**
	 * Every case's outcome matches the recording.
	 *
	 * @return void
	 */
	public function testUpsertAndPublishMatchTheGoldenRecording(): void {
		$recorded = [];
		foreach ($this->cases() as $name => $case) {
			$recorded[$name] = $this->runCase(case: $case);
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($recorded as $name => $outcome) {
			self::assertSame(expected: $expected[$name], actual: json_decode((string)json_encode($outcome), true), message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($recorded));
	}//end testUpsertAndPublishMatchTheGoldenRecording()

	/**
	 * One lesson row.
	 *
	 * @param string               $ref    The occurrence id.
	 * @param array<string,string> $extra  Fields to add or replace.
	 *
	 * @return array<string,string>
	 */
	private static function lesson(string $ref, array $extra=[]): array {
		return array_merge(
			[
				'externalRef' => $ref,
				'subject' => 'Wiskunde',
				'startsAt' => '2026-09-28T09:00:00+02:00',
				'endsAt' => '2026-09-28T09:50:00+02:00',
				'groupReference' => '3a',
			],
			$extra
		);
	}//end lesson()

	/**
	 * A stored lesson of the zermelo source.
	 *
	 * @param string               $ref   The occurrence id.
	 * @param array<string,string> $extra Fields to add or replace.
	 *
	 * @return array<string,string>
	 */
	private static function stored(string $ref, array $extra=[]): array {
		return array_merge(self::lesson(ref: $ref), ['sourceSystem' => 'zermelo', 'title' => 'Wiskunde', 'status' => 'scheduled', 'importedAt' => '2026-09-01T00:00:00+00:00'], $extra);
	}//end stored()

	/**
	 * The cases: seeds, store switches and the calls to make.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function cases(): array {
		$seed = [
			's-1' => self::stored(ref: 'a'),
			's-2' => self::stored(ref: 'b', extra: ['status' => 'draft', 'roomReference' => 'A1']),
			's-3' => self::stored(ref: 'c', extra: ['status' => 'cancelled']),
			's-4' => self::stored(ref: 'd', extra: ['status' => 'draft', 'startsAt' => '2026-10-20T09:00:00+02:00', 'endsAt' => '2026-10-20T09:50:00+02:00']),
			's-5' => self::stored(ref: 'e', extra: ['status' => 'draft', 'sourceSystem' => 'magister']),
			's-6' => array_merge(self::stored(ref: 'f'), ['@self' => ['x' => 1]]),
			's-9' => array_diff_key(self::stored(ref: 'm'), ['status' => true]),
		];

		return [
			'create-update-unchanged' => [
				'seed' => $seed,
				'calls' => [
					['upsert', 'zermelo', [self::lesson(ref: 'm', extra: ['status' => 'scheduled']), self::lesson(ref: 'p', extra: ['roomReference' => '  A2 ', 'subject' => "\tFrans "]), self::lesson(ref: 'a'), self::lesson(ref: 'a2'), self::lesson(ref: 'f', extra: ['roomReference' => 'B2']), self::lesson(ref: 'b', extra: ['startsAt' => '2026-09-28T07:00:00Z', 'endsAt' => '2026-09-28T07:50:00Z', 'status' => 'draft', 'roomReference' => 'A1'])]],
				],
			],
			'drafts-and-statuses' => [
				'seed' => $seed,
				'calls' => [
					['upsert', ' zermelo ', [self::lesson(ref: 'a', extra: ['status' => 'draft']), self::lesson(ref: 'c', extra: ['status' => 'draft']), self::lesson(ref: 'b', extra: ['status' => 'scheduled']), self::lesson(ref: 'n', extra: ['status' => 'draft', 'title' => 'Eigen titel'])]],
				],
			],
			'invalid-and-duplicate-rows' => [
				'seed' => [],
				'calls' => [
					['upsert', 'zermelo', [
						'not a row',
						['externalRef' => 'x1'],
						self::lesson(ref: '', extra: []),
						self::lesson(ref: 'x2', extra: ['endsAt' => '2026-09-28T09:00:00+02:00']),
						self::lesson(ref: 'x3', extra: ['startsAt' => 'tomorrowish']),
						self::lesson(ref: 'x4', extra: ['status' => 'moved']),
						self::lesson(ref: 'x5', extra: ['roomReference' => 'OLD']),
						self::lesson(ref: '7', extra: ['subject' => ' Engels ']),
						self::lesson(ref: 'x5', extra: ['roomReference' => 'MID']),
						self::lesson(ref: '7', extra: ['subject' => 'Frans', 'sourceSystem' => 'other']),
						self::lesson(ref: 'x5', extra: ['roomReference' => 'NEW']),
						self::lesson(ref: 'x6', extra: ['courseId' => 'c-1', 'onlineMeetingUrl' => 'http://plain']),
						self::lesson(ref: 'x7', extra: ['subject' => 42]),
					]],
				],
			],
			'all-invalid' => [
				'seed' => [],
				'calls' => [['upsert', 'zermelo', [['externalRef' => 'x1'], 'nope']]],
			],
			'empty-source' => [
				'seed' => [],
				'calls' => [['upsert', '  ', [self::lesson(ref: 'a')]], ['publish', '', '2026-09-01', '2026-09-30']],
			],
			'save-throws' => [
				'seed' => $seed,
				'failSaves' => true,
				'calls' => [['upsert', 'zermelo', [self::lesson(ref: 'new'), self::lesson(ref: 'a', extra: ['roomReference' => 'Z'])]], ['publish', 'zermelo', '2026-09-01T00:00:00Z', '2026-10-31T00:00:00Z']],
			],
			'save-returns-null-or-no-id' => [
				'seed' => $seed,
				'nullSave' => ['nul'],
				'emptyIdSave' => ['a', 'b'],
				'calls' => [['upsert', 'zermelo', [self::lesson(ref: 'nul'), self::lesson(ref: 'a', extra: ['roomReference' => 'Q']), self::lesson(ref: 'b', extra: ['status' => 'scheduled'])]]],
			],
			'ignored-filters-and-two-matches' => [
				'seed' => array_merge($seed, ['s-7' => self::stored(ref: 'a', extra: ['roomReference' => 'second']), '' => self::stored(ref: 'g'), 's-8' => array_merge(self::stored(ref: 'k'), ['externalRef' => 7])]),
				'ignoreFilters' => true,
				'calls' => [['upsert', 'zermelo', [self::lesson(ref: 'a', extra: ['roomReference' => 'R']), self::lesson(ref: 'g'), self::lesson(ref: 'e'), self::lesson(ref: '7')]]],
			],
			'two-stored-matches' => [
				'seed' => array_merge($seed, ['s-7' => self::stored(ref: 'a', extra: ['roomReference' => 'second'])]),
				'calls' => [['upsert', 'zermelo', [self::lesson(ref: 'a', extra: ['roomReference' => 'R'])]]],
			],
			'publish-windows' => [
				'seed' => $seed,
				'calls' => [
					['publish', 'zermelo', '2026-10-31', '2026-09-01'],
					['publish', 'zermelo', 'soon', '2026-09-30'],
					['publish', 'zermelo', '2026-09-01', 'later'],
					['publish', 'nobody', '2026-09-01', '2026-12-31'],
					['publish', 'zermelo', '2026-09-28T09:10:00+02:00', '2026-09-28T09:10:00+02:00'],
					['publish', ' zermelo ', '2026-09-28T09:10:00+02:00', '2026-09-28T09:20:00+02:00'],
					['publish', 'zermelo', '2026-09-01T00:00:00Z', '2026-12-31T00:00:00Z'],
				],
			],
			'publish-skips-outside-and-idless' => [
				'seed' => array_merge($seed, ['' => self::stored(ref: 'h', extra: ['status' => 'draft'])]),
				'ignoreFilters' => true,
				'calls' => [['publish', 'zermelo', '2026-09-28T08:00:00+02:00', '2026-09-28T12:00:00+02:00']],
			],
		];
	}//end cases()

	/**
	 * Run one case and describe what came out.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed>
	 */
	private function runCase(array $case): array {
		$store = new class extends InMemoryTimetableObjectService {
			/**
			 * Refs whose save returns null.
			 *
			 * @var array<int,string>
			 */
			public array $nullSave = [];

			/**
			 * Refs whose saved object answers an empty id.
			 *
			 * @var array<int,string>
			 */
			public array $emptyIdSave = [];

			/**
			 * Save, or answer null or an id-less object for the configured refs.
			 *
			 * @param array<mixed>|object $object        The object.
			 * @param array<mixed>|null   $extend        Unused.
			 * @param mixed               $register      The register.
			 * @param mixed               $schema        The schema.
			 * @param string|null         $uuid          The id.
			 * @param bool                $_rbac         RBAC.
			 * @param bool                $_multitenancy Multitenancy.
			 * @param bool                $silent        Silent.
			 * @param bool                $_validation   Validation.
			 *
			 * @return object|null
			 */
			public function saveObject(array|object $object, ?array $extend=[], mixed $register=null, mixed $schema=null, ?string $uuid=null, bool $_rbac=true, bool $_multitenancy=true, bool $silent=false, bool $_validation=true): ?object {
				$ref = (string)(((array)$object)['externalRef'] ?? '');
				if (in_array($ref, $this->nullSave, true) === true) {
					$this->saves[] = ['schema' => $schema, 'uuid' => $uuid, 'rbac' => $_rbac, 'object' => (array)$object, 'answered' => null];
					return null;
				}

				$saved = parent::saveObject(object: $object, register: $register, schema: $schema, uuid: $uuid, _rbac: $_rbac);
				if (in_array($ref, $this->emptyIdSave, true) === true) {
					return new class {
						/**
						 * No id.
						 *
						 * @return string
						 */
						public function getUuid(): string {
							return '';
						}

						/**
						 * No data.
						 *
						 * @return array<string,mixed>
						 */
						public function getObject(): array {
							return [];
						}
					};
				}

				return $saved;
			}
		};
		foreach (($case['seed'] ?? []) as $id => $data) {
			$store->seed(schema: 'timetableSession', id: (string)$id, data: $data);
		}

		$store->failSaves     = ($case['failSaves'] ?? false);
		$store->ignoreFilters = ($case['ignoreFilters'] ?? false);
		$store->nullSave      = ($case['nullSave'] ?? []);
		$store->emptyIdSave   = ($case['emptyIdSave'] ?? []);

		$logger = new class extends AbstractLogger {
			/**
			 * The log lines.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $lines = [];

			/**
			 * Record one line.
			 *
			 * @param mixed               $level   The level.
			 * @param string|\Stringable  $message The message.
			 * @param array<string,mixed> $context The context.
			 *
			 * @return void
			 */
			public function log($level, string|\Stringable $message, array $context=[]): void {
				$this->lines[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($store);
		$service = new TimetableSessionService(container: $container, logger: $logger);

		$answers = [];
		foreach ($case['calls'] as $call) {
			try {
				$answers[] = match ($call[0]) {
					'upsert' => $service->upsert(sourceSystem: $call[1], sessions: $call[2]),
					'publish' => $service->publish(sourceSystem: $call[1], from: $call[2], to: $call[3]),
				};
			} catch (Throwable $e) {
				$answers[] = ['threw' => get_class($e), 'message' => $e->getMessage()];
			}
		}

		return [
			'answers' => $answers,
			'rows' => $this->masked(value: $this->idRows(rows: ($store->rows['timetableSession'] ?? []))),
			'saves' => $this->masked(value: $store->saves),
			'searches' => count($store->searches),
			'clears' => $store->clears,
			'log' => $logger->lines,
		];
	}//end runCase()

	/**
	 * Stored rows as a list of id and data, so an empty id is a value and never a JSON key.
	 *
	 * @param array<string,array<string,mixed>> $rows The stored rows by id.
	 *
	 * @return list<array{id:string,data:array<string,mixed>}>
	 */
	private function idRows(array $rows): array {
		$list = [];
		foreach ($rows as $id => $data) {
			$list[] = ['id' => (string)$id, 'data' => $data];
		}

		return $list;
	}//end idRows()

	/**
	 * Replace every importedAt stamped during the run by a marker, so the recording is time free.
	 *
	 * @param mixed $value The value.
	 *
	 * @return mixed
	 */
	private function masked(mixed $value): mixed {
		if (is_array($value) === false) {
			return $value;
		}

		foreach ($value as $key => $item) {
			if ($key === 'importedAt' && $item !== '2026-09-01T00:00:00+00:00') {
				$value[$key] = (is_string($item) === true && strtotime($item) !== false) ? 'NOW' : $item;
				continue;
			}

			$value[$key] = $this->masked(value: $item);
		}

		return $value;
	}//end masked()
}//end class
