<?php

/**
 * Tests for ReadsObjectResults: result sets read as a plain list of rows, rows read as their data.
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
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ReadsObjectResults;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\ReadsObjectResults
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
class ReadsObjectResultsTest extends TestCase {

	/**
	 * A class using the trait, with its private methods opened up.
	 *
	 * @return object
	 */
	private function reader(): object {
		return new class {
			use ReadsObjectResults;

			/**
			 * The rows of a result set.
			 *
			 * @param mixed $results The result set.
			 *
			 * @return array<int,mixed>
			 */
			public function rows(mixed $results): array {
				return $this->normaliseResults(results: $results);
			}//end rows()

			/**
			 * The data of a row.
			 *
			 * @param mixed $row The row.
			 *
			 * @return array<string,mixed>
			 */
			public function data(mixed $row): array {
				return $this->extractData(row: $row);
			}//end data()
		};
	}//end reader()

	/**
	 * A paginated answer reads as its `results` list.
	 *
	 * @return void
	 */
	public function testAPaginatedAnswerReadsAsItsResults(): void {
		$rows = $this->reader()->rows(['results' => [['id' => 'a']], 'total' => 1]);

		$this->assertSame([['id' => 'a']], $rows);
	}//end testAPaginatedAnswerReadsAsItsResults()

	/**
	 * A plain list is kept as it is, and anything else reads as no rows.
	 *
	 * @return void
	 */
	public function testAPlainListIsKeptAndANonArrayReadsAsNoRows(): void {
		$reader = $this->reader();

		$this->assertSame([['id' => 'a'], ['id' => 'b']], $reader->rows([['id' => 'a'], ['id' => 'b']]));
		$this->assertSame([], $reader->rows(null));
		$this->assertSame([], $reader->rows('results'));
	}//end testAPlainListIsKeptAndANonArrayReadsAsNoRows()

	/**
	 * An entity row reads as its object data, an array row as itself, anything else as nothing.
	 *
	 * @return void
	 */
	public function testARowReadsAsItsData(): void {
		$entity = new class {
			/**
			 * The object data.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return ['title' => 'Entity'];
			}//end getObject()
		};
		$reader = $this->reader();

		$this->assertSame(['title' => 'Entity'], $reader->data($entity));
		$this->assertSame(['@self' => ['id' => 'x'], 'title' => 'Row'], $reader->data(['@self' => ['id' => 'x'], 'title' => 'Row']));
		$this->assertSame([], $reader->data(new \stdClass()));
		$this->assertSame([], $reader->data(42));
	}//end testARowReadsAsItsData()
}//end class
