<?php

/**
 * Unit tests for FinanceLineService's matching and syncing edges.
 *
 * Covers the empty-input answers of projectIdForKey(), importedLineWith() and
 * syncProject(), a line already in step being left alone, a project owner and
 * portfolio stored as objects, and a write that fails being logged and not
 * counted.
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

use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for FinanceLineService.
 *
 * @covers \OCA\Planninq\Service\FinanceLineService
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class FinanceLineServiceStepTest extends TestCase {
	use MembershipFixture;

	/**
	 * The service over the in-memory OpenRegister.
	 *
	 * @param ContainerInterface|null $container The container FinanceLineService writes through, the fixture's when null.
	 * @param LoggerInterface|null    $logger    The logger, a mock when null.
	 *
	 * @return FinanceLineService
	 */
	private function service(?ContainerInterface $container = null, ?LoggerInterface $logger = null): FinanceLineService {
		return new FinanceLineService(
			membership: $this->membershipService(),
			container: ($container ?? $this->container()),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end service()

	/**
	 * Empty input answers the empty value without reading OpenRegister.
	 *
	 * @return void
	 */
	public function testEmptyInputAnswersEmptyWithoutAnyRead(): void {
		$service = $this->service();

		self::assertSame('', $service->projectIdForKey('   '));
		self::assertSame('', $service->importedLineWith('', 'own'));
		self::assertSame(0, $service->syncProject(''));
		self::assertSame([], $this->objects->searches);
		self::assertSame([], $this->objects->finds);
	}//end testEmptyInputAnswersEmptyWithoutAnyRead()

	/**
	 * An owner and portfolio stored as objects are read by their id or uuid.
	 *
	 * @return void
	 */
	public function testAnOwnerAndPortfolioStoredAsObjectsAreReadByTheirId(): void {
		$this->container();
		$this->objects->seed('project', 'p-1', ['title' => 'P', 'owner' => ['id' => 'carol'], 'portfolio' => ['uuid' => 'pf-1'], 'portfolioReaders' => ['dave']]);

		self::assertSame(
			['financeReaders' => ['carol', 'dave'], 'projectOwner' => 'carol', 'portfolio' => 'pf-1'],
			$this->service()->keptFieldsFor('p-1')
		);
	}//end testAnOwnerAndPortfolioStoredAsObjectsAreReadByTheirId()

	/**
	 * A line already in step is not written again.
	 *
	 * @return void
	 */
	public function testALineInStepIsNotWritten(): void {
		$this->container();
		$this->objects->seed('project', 'p-1', ['title' => 'P', 'owner' => 'carol']);
		$this->objects->seed('financeLine', 'l-1', ['project' => 'p-1', 'financeReaders' => ['carol'], 'projectOwner' => 'carol', 'portfolio' => null]);

		self::assertSame(0, $this->service()->syncProject('p-1'));
		self::assertSame([], $this->objects->saves);
	}//end testALineInStepIsNotWritten()

	/**
	 * A line out of step whose write fails is logged with its id and not counted.
	 *
	 * @return void
	 */
	public function testAFailedWriteIsLoggedAndNotCounted(): void {
		$this->container();
		$this->objects->seed('project', 'p-1', ['title' => 'P', 'owner' => 'carol']);
		$this->objects->seed('financeLine', 'l-1', ['project' => 'p-1', 'financeReaders' => [], 'projectOwner' => 'eve']);

		$failing = $this->createMock(originalClassName: ContainerInterface::class);
		$failing->method('get')->willThrowException(new \RuntimeException('no object service'));

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Planninq: could not bring a finance line in step with its project; the wrong people may see it',
				['line' => 'l-1', 'exception' => 'no object service']
			);

		self::assertSame(0, $this->service(container: $failing, logger: $logger)->syncProject('p-1'));
		self::assertSame([], $this->objects->saves);
	}//end testAFailedWriteIsLoggedAndNotCounted()
}//end class
