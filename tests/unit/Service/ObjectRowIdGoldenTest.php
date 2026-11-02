<?php

/**
 * Golden test for reading the object UUID from an ObjectService row.
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

use OCA\Planninq\Controller\TimelineController;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\LabelService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Calls extractId() of the timeline controller, the dependency repository
 * and the label service on entity rows (magic and declared getters, getters
 * that throw or answer null, '' or a number), array rows (with and without
 * `@self`) and other values, and compares every answer with the fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Controller\TimelineController
 * @covers \OCA\Planninq\Service\DependencyRepository
 * @covers \OCA\Planninq\Service\LabelService
 */
class ObjectRowIdGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/object-row-id.golden.json';

	/**
	 * Every row the reader is asked about, by name.
	 *
	 * @return array<string, mixed>
	 */
	private function rows(): array {
		$rows = [
			'null'                => null,
			'string'              => 'abc',
			'int'                 => 7,
			'empty array'         => [],
			'id only'             => ['id' => 'a1'],
			'id null'             => ['id' => null],
			'id int'              => ['id' => 5],
			'self and id'         => ['@self' => ['id' => 's1'], 'id' => 'a1'],
			'self null id'        => ['@self' => ['id' => null], 'id' => 'a1'],
			'self empty'          => ['@self' => [], 'id' => 'a2'],
			'self only'           => ['@self' => ['id' => 's2']],
			'self int'            => ['@self' => ['id' => 0]],
			'self empty string'   => ['@self' => ['id' => ''], 'id' => 'a3'],
			'plain object'        => new \stdClass(),
		];
		foreach ([['u1', 'i1'], [null, 'i2'], ['', 'i3'], [null, null], ['', ''], [0, 'i4'], ['u5', null], [12, 13]] as $index => [$uuid, $id]) {
			$rows["magic $index"] = $this->magicEntity(uuid: $uuid, id: $id);
		}

		$rows['uuid throws'] = new class {
			/**
			 * A getter that fails.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				throw new RuntimeException('no uuid');
			}

			/**
			 * The fallback id.
			 *
			 * @return string
			 */
			public function getId(): string {
				return 'after-throw';
			}
		};
		$rows['both throw']  = new class {
			/**
			 * A getter that fails.
			 *
			 * @return mixed
			 */
			public function __call(string $name, array $args): mixed {
				throw new RuntimeException($name);
			}
		};
		$rows['id only declared'] = new class {
			/**
			 * The id.
			 *
			 * @return int
			 */
			public function getId(): int {
				return 42;
			}
		};
		return $rows;
	}

	/**
	 * An entity whose getters go through __call, like OpenRegister's ObjectEntity.
	 *
	 * @param mixed $uuid What getUuid() answers.
	 * @param mixed $id   What getId() answers.
	 *
	 * @return object
	 */
	private function magicEntity(mixed $uuid, mixed $id): object {
		return new class($uuid, $id) {
			/**
			 * @param mixed $uuid What getUuid() answers.
			 * @param mixed $id   What getId() answers.
			 */
			public function __construct(private mixed $uuid, private mixed $id) {
			}

			/**
			 * The magic accessors.
			 *
			 * @param string $name The method.
			 * @param array  $args The arguments.
			 *
			 * @return mixed
			 */
			public function __call(string $name, array $args): mixed {
				return match ($name) {
					'getUuid' => $this->uuid,
					'getId' => $this->id,
					default => throw new RuntimeException($name),
				};
			}
		};
	}

	/**
	 * Every class's answer for every row matches the fixture.
	 *
	 * @return void
	 */
	public function testExtractIdMatchesTheFixture(): void {
		$answers = [];
		foreach ([TimelineController::class, DependencyRepository::class, LabelService::class] as $class) {
			$reflection = new ReflectionClass($class);
			$subject    = $reflection->newInstanceWithoutConstructor();
			$method     = $reflection->getMethod('extractId');
			foreach ($this->rows() as $name => $row) {
				$answers[$class][$name] = $method->invoke($subject, $row);
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string)file_get_contents(self::FIXTURE), true), $answers);
	}//end testExtractIdMatchesTheFixture()
}//end class
