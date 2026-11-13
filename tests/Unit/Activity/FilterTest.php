<?php

/**
 * Unit tests for the Planninq activity filter.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Activity
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Activity;

use OCA\Planninq\Activity\Filter;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Activity\Filter
 */
class FilterTest extends TestCase {

	/**
	 * The filter shows only Planninq task activity, under the app's name and icon.
	 *
	 * @return void
	 */
	public function testTheFilterShowsOnlyTaskActivity(): void {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->expects($this->once())->method('imagePath')->with('planninq', 'app.svg')->willReturn('/apps/planninq/img/app.svg');
		$urls->expects($this->once())
			->method('getAbsoluteURL')
			->with('/apps/planninq/img/app.svg')
			->willReturn('https://nc.example/apps/planninq/img/app.svg');
		$filter = new Filter(l: $l10n, urlGenerator: $urls);

		$this->assertSame(expected: 'planninq', actual: $filter->getIdentifier());
		$this->assertSame(expected: 'Planninq', actual: $filter->getName());
		$this->assertSame(expected: 50, actual: $filter->getPriority());
		$this->assertSame(expected: 'https://nc.example/apps/planninq/img/app.svg', actual: $filter->getIcon());
		$this->assertSame(expected: ['planninq_task'], actual: $filter->filterTypes(types: ['file_created', 'planninq_task', 'calendar']));
		$this->assertSame(expected: ['planninq_task'], actual: $filter->filterTypes(types: []));
		$this->assertSame(expected: ['planninq'], actual: $filter->allowedApps());
	}//end testTheFilterShowsOnlyTaskActivity()
}//end class
