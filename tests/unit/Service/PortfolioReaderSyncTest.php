<?php

/**
 * PortfolioReaderSync tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Tests
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
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\PortfolioReaderSync;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The writes that keep a portfolio's projects readable by its managers.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
class PortfolioReaderSyncTest extends TestCase {
	use MembershipFixture;

	private const PORTFOLIO = '11111111-1111-4111-8111-111111111111';

	private const PROJECT = '33333333-3333-4333-8333-333333333333';

	private const OTHER = '55555555-5555-4555-8555-555555555555';

	private const TASK = '44444444-4444-4444-8444-444444444444';

	/**
	 * Seed a portfolio with one project and its task, and a project elsewhere.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('projectPortfolio', self::PORTFOLIO, ['title' => 'Ruimte', 'managers' => ['mia']]);
		$this->objects->seed('project', self::PROJECT, ['title' => 'Visie', 'owner' => 'carol', 'members' => ['bob'], 'portfolio' => self::PORTFOLIO, 'portfolioReaders' => ['mia']]);
		$this->objects->seed('project', self::OTHER, ['title' => 'Elders', 'owner' => 'carol', 'members' => [], 'portfolio' => null, 'portfolioReaders' => []]);
		$this->objects->seed('task', self::TASK, ['title' => 'Draft', 'project' => self::PROJECT, 'members' => ['bob', 'carol'], 'portfolioReaders' => ['mia']]);
	}//end setUp()

	/**
	 * The service under test, with the given logger.
	 *
	 * @param LoggerInterface|null $logger The logger, or a mock.
	 *
	 * @return PortfolioReaderSync
	 */
	private function sync(?LoggerInterface $logger = null): PortfolioReaderSync {
		$logger     = ($logger ?? $this->createMock(originalClassName: LoggerInterface::class));
		$membership = $this->membershipService();

		return new PortfolioReaderSync(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			container: $this->container(),
			logger: $logger
		);
	}//end sync()

	/**
	 * New managers reach the portfolio's project and its task, written as the system; another project is untouched.
	 *
	 * @return void
	 */
	public function testManagersReachTheProjectsAndTheirObjects(): void {
		$this->sync()->managersChanged(portfolioId: self::PORTFOLIO, managers: ['noor']);

		$this->assertSame(expected: ['noor'], actual: $this->objects->rows['project'][self::PROJECT]['portfolioReaders']);
		$this->assertSame(expected: ['noor'], actual: $this->objects->rows['task'][self::TASK]['portfolioReaders']);
		$this->assertSame(expected: [], actual: $this->objects->rows['project'][self::OTHER]['portfolioReaders']);

		$projectSave = $this->objects->saves[0];
		$this->assertSame(expected: self::PROJECT, actual: $projectSave['uuid']);
		$this->assertTrue(condition: $projectSave['system']);
		$this->assertFalse(condition: $projectSave['_rbac']);
		$this->assertTrue(condition: $projectSave['silent']);
	}//end testManagersReachTheProjectsAndTheirObjects()

	/**
	 * Releasing a portfolio takes its projects out of it and empties their readers.
	 *
	 * @return void
	 */
	public function testReleaseTakesTheProjectsOutOfThePortfolio(): void {
		$this->sync()->release(portfolioId: self::PORTFOLIO);

		$this->assertNull(actual: $this->objects->rows['project'][self::PROJECT]['portfolio']);
		$this->assertSame(expected: [], actual: $this->objects->rows['project'][self::PROJECT]['portfolioReaders']);
		$this->assertSame(expected: [], actual: $this->objects->rows['task'][self::TASK]['portfolioReaders']);
	}//end testReleaseTakesTheProjectsOutOfThePortfolio()

	/**
	 * A refused project write is logged and leaves the project's objects as they were.
	 *
	 * @return void
	 */
	public function testARefusedWriteIsLoggedAndCopiesNothing(): void {
		$this->objects->failingSaves = [self::PROJECT];
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->sync(logger: $logger)->managersChanged(portfolioId: self::PORTFOLIO, managers: ['noor']);

		$this->assertSame(expected: ['mia'], actual: $this->objects->rows['task'][self::TASK]['portfolioReaders']);
	}//end testARefusedWriteIsLoggedAndCopiesNothing()
}//end class
