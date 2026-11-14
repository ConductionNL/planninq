<?php

/**
 * Unit tests for the Planninq activity Filter.
 *
 * The filter is what the Activity app shows in its sidebar: its name, priority,
 * icon and the single activity type and app it narrows the stream to.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Activity
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

namespace OCA\Planninq\Tests\Unit\Activity;

use OCA\Planninq\Activity\Filter;
use OCA\Planninq\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Filter.
 *
 * @covers \OCA\Planninq\Activity\Filter
 */
class FilterTest extends TestCase {

	/**
	 * The filter over doubles that echo their input.
	 *
	 * @return Filter
	 */
	private function filter(): Filter {
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(
			static fn (string $app, string $file): string => '/apps/' . $app . '/img/' . $file
		);
		$urls->method('getAbsoluteURL')->willReturnCallback(
			static fn (string $path): string => 'https://cloud.example/' . ltrim($path, '/')
		);

		return new Filter(l: $l, urlGenerator: $urls);

	}//end filter()

	/**
	 * The identifier and the app it allows are both the app id.
	 *
	 * @return void
	 */
	public function testIdentifierAndAllowedApps(): void {
		$filter = $this->filter();

		$this->assertSame(Application::APP_ID, $filter->getIdentifier());
		$this->assertSame([Application::APP_ID], $filter->allowedApps());

	}//end testIdentifierAndAllowedApps()

	/**
	 * The name is the translated app name and the priority is the shared default.
	 *
	 * @return void
	 */
	public function testNameAndPriority(): void {
		$filter = $this->filter();

		$this->assertSame('Planninq', $filter->getName());
		$this->assertSame(50, $filter->getPriority());

	}//end testNameAndPriority()

	/**
	 * The icon is the app svg resolved to an absolute URL.
	 *
	 * @return void
	 */
	public function testIcon(): void {
		$this->assertSame(
			'https://cloud.example/apps/' . Application::APP_ID . '/img/app.svg',
			$this->filter()->getIcon()
		);

	}//end testIcon()

	/**
	 * filterTypes narrows the stream to the one planninq activity type, whatever is asked.
	 *
	 * @return void
	 */
	public function testFilterTypesIsAlwaysThePlanninqType(): void {
		$filter = $this->filter();

		$this->assertSame([Filter::ACTIVITY_TYPE], $filter->filterTypes([]));
		$this->assertSame([Filter::ACTIVITY_TYPE], $filter->filterTypes(['files', 'comments']));
		$this->assertSame(['planninq_task'], $filter->filterTypes([]));

	}//end testFilterTypesIsAlwaysThePlanninqType()
}//end class
