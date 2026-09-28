<?php

/**
 * Tests for RiskScaleService (projects-overview-logs-risks, task 3.4).
 *
 * The risk scale is an admin setting: 3 to 5 levels, a label per level for
 * likelihood and impact, and two score thresholds. A smaller scale is refused
 * while a risk still uses a level above it. Risks are read through the
 * in-memory OpenRegister ObjectService the membership tests use.
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

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Service\RiskScaleService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\RiskScaleService
 */
class RiskScaleServiceTest extends TestCase {
	use MembershipFixture;

	/**
	 * Seed three risks: two at likelihood 5, one at impact 4.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('risk', 'r1', ['title' => 'Supplier late', 'project' => 'p', 'likelihood' => 5, 'impact' => 2]);
		$this->objects->seed('risk', 'r2', ['title' => 'Budget cut', 'project' => 'p', 'likelihood' => 5, 'impact' => 1]);
		$this->objects->seed('risk', 'r3', ['title' => 'Key person leaves', 'project' => 'q', 'likelihood' => 2, 'impact' => 4]);
		$this->objects->seed('risk', 'r4', ['title' => 'Small', 'project' => 'q', 'likelihood' => 1, 'impact' => 1]);

	}//end setUp()

	/**
	 * The service over the fixture container.
	 *
	 * @return RiskScaleService
	 */
	private function service(): RiskScaleService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new RiskScaleService(
			container: $this->container(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * A three-level scale with its own labels is accepted and normalised.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testAValidThreeLevelScaleIsNormalised(): void {
		$raw = json_encode(
			[
				'levels' => 3,
				'likelihood' => [' Low ', 'Medium', 'High'],
				'impact' => ['Low', 'Medium', 'High'],
				'thresholds' => ['medium' => 3, 'high' => 6],
			]
		);

		self::assertSame(
			expected: [
				'levels' => 3,
				'likelihood' => ['Low', 'Medium', 'High'],
				'impact' => ['Low', 'Medium', 'High'],
				'thresholds' => ['medium' => 3, 'high' => 6],
			],
			actual: RiskScaleService::normalise(raw: (string)$raw)
		);

	}//end testAValidThreeLevelScaleIsNormalised()

	/**
	 * A malformed scale is refused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testAMalformedScaleIsRefused(): void {
		$labels = ['a', 'b', 'c'];
		$cases = [
			'not json' => '{',
			'two levels' => json_encode(['levels' => 2, 'likelihood' => ['a', 'b'], 'impact' => ['a', 'b'], 'thresholds' => ['medium' => 2, 'high' => 3]]),
			'six levels' => json_encode(['levels' => 6, 'likelihood' => array_fill(0, 6, 'x'), 'impact' => array_fill(0, 6, 'x'), 'thresholds' => ['medium' => 5, 'high' => 12]]),
			'label count' => json_encode(['levels' => 3, 'likelihood' => ['a', 'b'], 'impact' => $labels, 'thresholds' => ['medium' => 3, 'high' => 6]]),
			'empty label' => json_encode(['levels' => 3, 'likelihood' => ['a', ' ', 'c'], 'impact' => $labels, 'thresholds' => ['medium' => 3, 'high' => 6]]),
			'thresholds out of order' => json_encode(['levels' => 3, 'likelihood' => $labels, 'impact' => $labels, 'thresholds' => ['medium' => 6, 'high' => 3]]),
			'threshold above the top score' => json_encode(['levels' => 3, 'likelihood' => $labels, 'impact' => $labels, 'thresholds' => ['medium' => 3, 'high' => 10]]),
		];

		foreach ($cases as $name => $raw) {
			self::assertNull(actual: RiskScaleService::normalise(raw: (string)$raw), message: $name);
		}

	}//end testAMalformedScaleIsRefused()

	/**
	 * The default scale is five levels and itself valid.
	 *
	 * @return void
	 */
	public function testTheDefaultScaleIsValid(): void {
		$default = RiskScaleService::normalise(raw: RiskScaleService::DEFAULT_SCALE);

		self::assertIsArray(actual: $default);
		self::assertSame(expected: 5, actual: $default['levels']);

	}//end testTheDefaultScaleIsValid()

	/**
	 * A smaller scale is refused while risks use a higher level, naming the count and the level.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testASmallerScaleIsRefusedWhileRisksUseAHigherLevel(): void {
		$service = $this->service();

		self::assertSame(expected: ['count' => 2, 'level' => 5], actual: $service->conflict(levels: 4));
		self::assertSame(
			expected: '2 risks use level 5. Change them first.',
			actual: RiskScaleService::refusal(count: 2, level: 5)
		);
		self::assertSame(expected: ['count' => 3, 'level' => 5], actual: $service->conflict(levels: 3));
		self::assertNull(actual: $service->conflict(levels: 5));
		self::assertSame(
			expected: '1 risk uses level 4. Change it first.',
			actual: RiskScaleService::refusal(count: 1, level: 4)
		);

	}//end testASmallerScaleIsRefusedWhileRisksUseAHigherLevel()

	/**
	 * The check reads every risk as the system, not as the admin who saves.
	 *
	 * @return void
	 */
	public function testTheCheckReadsAsTheSystem(): void {
		$this->service()->conflict(levels: 4);

		foreach ($this->objects->searches as $search) {
			self::assertSame(expected: 'risk', actual: $search['schema']);
			self::assertFalse(condition: $search['_rbac']);
			self::assertFalse(condition: $search['_multitenancy']);
		}

		self::assertNotEmpty(actual: $this->objects->searches);

	}//end testTheCheckReadsAsTheSystem()
}//end class
