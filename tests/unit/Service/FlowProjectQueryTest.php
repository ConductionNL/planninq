<?php

/**
 * Tests for FlowProjectQuery: the slug-search target of one schema in the project's register.
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
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\OpenRegister\Contract\RegisterSchemaSlugs;
use OCA\Planninq\Service\FlowProjectQuery;
use PHPUnit\Framework\TestCase;

/**
 * The flow read names its register and schema the way OpenRegister's slug search takes them.
 */
class FlowProjectQueryTest extends TestCase {

	/**
	 * The target carries the register and the schema, by slug.
	 *
	 * @return void
	 */
	public function testSlugsNameTheRegisterAndTheSchema(): void {
		$query  = new FlowProjectQuery(
			objectService: new \stdClass(),
			projectId: 'p1',
			from: new DateTimeImmutable('2026-11-01'),
			to: new DateTimeImmutable('2026-11-25'),
		);
		$target = $query->slugs(register: 'planninq', schema: 'task');

		self::assertInstanceOf(expected: RegisterSchemaSlugs::class, actual: $target);
		self::assertSame(expected: 'planninq', actual: $target->register);
		self::assertSame(expected: 'task', actual: $target->schema);
	}//end testSlugsNameTheRegisterAndTheSchema()
}//end class
