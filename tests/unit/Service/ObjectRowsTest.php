<?php

/**
 * Tests for ObjectRows: search rows read as uuid and data, whatever shape OpenRegister answers in.
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
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ObjectRows;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\ObjectRows
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
 */
class ObjectRowsTest extends TestCase {

	/**
	 * A search fake that records its arguments and answers what it was given.
	 *
	 * @param mixed $answer What searchObjectsBySlug() returns.
	 *
	 * @return object
	 */
	private function objectService(mixed $answer): object {
		return new class($answer) {
			/**
			 * The arguments of the last call.
			 *
			 * @var array<string, mixed>
			 */
			public array $asked = [];

			/**
			 * Constructor.
			 *
			 * @param mixed $answer The answer.
			 */
			public function __construct(private readonly mixed $answer) {
			}//end __construct()

			/**
			 * The search.
			 *
			 * @return mixed
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters, bool $_rbac, bool $_multitenancy): mixed {
				$this->asked = compact('registerSlug', 'schemaSlug', 'filters', '_rbac', '_multitenancy');
				return $this->answer;
			}//end searchObjectsBySlug()
		};
	}//end objectService()

	/**
	 * An envelope of array rows is read as uuid and data, as the system, and rows without a uuid are dropped.
	 *
	 * @return void
	 */
	public function testAnEnvelopeOfArrayRows(): void {
		$service = $this->objectService(
			[
				'results' => [
					['@self' => ['id' => 'a-1'], 'title' => 'Ontwerp'],
					['id' => 'a-2', 'title' => 'Bouw'],
					['title' => 'zonder id'],
				],
			]
		);

		$rows = ObjectRows::search(objectService: $service, register: 'planninq', schema: 'task', filters: ['project' => 'p-1']);

		$this->assertSame(
			[
				['id' => 'a-1', 'data' => ['title' => 'Ontwerp']],
				['id' => 'a-2', 'data' => ['id' => 'a-2', 'title' => 'Bouw']],
			],
			$rows
		);
		$this->assertSame(
			['registerSlug' => 'planninq', 'schemaSlug' => 'task', 'filters' => ['project' => 'p-1'], '_rbac' => false, '_multitenancy' => false],
			$service->asked
		);
	}//end testAnEnvelopeOfArrayRows()

	/**
	 * A plain list of entities is read through getUuid() and getObject().
	 *
	 * @return void
	 */
	public function testAListOfEntities(): void {
		$entity = new class {
			/**
			 * The uuid, served through __call() as OpenRegister's entities do.
			 *
			 * @param string       $name      The method.
			 * @param array<mixed> $arguments Its arguments.
			 *
			 * @return mixed
			 */
			public function __call(string $name, array $arguments): mixed {
				return ($name === 'getUuid') ? 'e-1' : null;
			}//end __call()

			/**
			 * The data.
			 *
			 * @return array<string, mixed>
			 */
			public function getObject(): array {
				return ['title' => 'Oplevering'];
			}//end getObject()
		};

		$this->assertSame(
			[['id' => 'e-1', 'data' => ['title' => 'Oplevering']]],
			ObjectRows::search(objectService: $this->objectService([$entity]), register: 'planninq', schema: 'task', filters: [])
		);
		$this->assertSame('', ObjectRows::id(row: 'not a row'));
		$this->assertSame([], ObjectRows::data(row: 42));
		$this->assertSame([], ObjectRows::search(objectService: $this->objectService(null), register: 'planninq', schema: 'task', filters: []));
	}//end testAListOfEntities()
}//end class
