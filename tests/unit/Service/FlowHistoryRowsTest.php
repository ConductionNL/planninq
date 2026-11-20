<?php

/**
 * Unit tests for FlowHistoryService's row reading.
 *
 * Covers plainRow() on entity rows (uuid, data, a DateTime or other created
 * value, an entity without accessors), a paginated `results` answer from
 * OpenRegister, and an audit trail that is not available.
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

use DateTimeImmutable;
use DateTimeZone;
use OCA\Planninq\Service\FlowHistoryService;
use OCA\Planninq\Service\FlowProjectQuery;
use OCA\Planninq\Service\FlowReplay;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for FlowHistoryService's plain rows and its audit-trail fallback.
 *
 * @covers \OCA\Planninq\Service\FlowHistoryService
 * @uses   \OCA\Planninq\Service\FlowBoard
 * @uses   \OCA\Planninq\Service\FlowProjectQuery
 * @uses   \OCA\Planninq\Service\FlowReplay
 */
class FlowHistoryRowsTest extends TestCase {

	/**
	 * The service with an empty cache, the clock at 2026-09-30 12:00 UTC.
	 *
	 * @param ContainerInterface|null $container The container, a never-called mock when null.
	 * @param LoggerInterface|null    $logger    The logger, a mock when null.
	 *
	 * @return FlowHistoryService
	 */
	private function service(?ContainerInterface $container = null, ?LoggerInterface $logger = null): FlowHistoryService {
		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturn(null);
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-09-30 12:00:00', new DateTimeZone('UTC')));

		return new FlowHistoryService(
			container: ($container ?? $this->createMock(originalClassName: ContainerInterface::class)),
			cacheFactory: $factory,
			timeFactory: $time,
			replay: new FlowReplay(),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end service()

	/**
	 * An entity with data, uuid and a DateTime created gives the uuid as id and created as ISO 8601.
	 *
	 * @return void
	 */
	public function testAnEntityRowTakesItsUuidAndCreatedMoment(): void {
		$entity = new class () {
			public function getObject(): array {
				return ['id' => 'stale', 'title' => 'Task'];
			}

			public function getUuid(): string {
				return 'u-1';
			}

			public function getCreated(): DateTimeImmutable {
				return new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('UTC'));
			}
		};

		self::assertSame(
			['id' => 'u-1', 'title' => 'Task', 'created' => '2026-09-01T10:00:00+00:00'],
			$this->service()->plainRow($entity)
		);
	}//end testAnEntityRowTakesItsUuidAndCreatedMoment()

	/**
	 * Without a uuid accessor the data's id is kept, and a created value that is no DateTime is null.
	 *
	 * @return void
	 */
	public function testAnEntityWithoutUuidKeepsItsDataIdAndDropsANonDateCreated(): void {
		$entity = new class () {
			public function getObject(): array {
				return ['id' => 7, 'title' => 'Task'];
			}

			public function getCreated(): string {
				return '2026-09-01';
			}
		};

		self::assertSame(['id' => '7', 'title' => 'Task', 'created' => null], $this->service()->plainRow($entity));
	}//end testAnEntityWithoutUuidKeepsItsDataIdAndDropsANonDateCreated()

	/**
	 * An object without any accessor is a row with an empty id and no created moment.
	 *
	 * @return void
	 */
	public function testAnObjectWithoutAccessorsIsAnEmptyRow(): void {
		self::assertSame(['id' => '', 'created' => null], $this->service()->plainRow(new \stdClass()));
	}//end testAnObjectWithoutAccessorsIsAnEmptyRow()

	/**
	 * A paginated answer is read from `results`, and with the audit trail unavailable the flow is
	 * built without histories and the failure is logged.
	 *
	 * @return void
	 */
	public function testAPaginatedAnswerAndAnUnavailableAuditTrail(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::once())
			->method('get')
			->with('OCA\OpenRegister\Db\AuditTrailMapper')
			->willThrowException(new \RuntimeException('no audit'));

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with('Planninq flow: the audit trail is not available', ['exception' => 'no audit']);

		$objects = new class () {
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array {
				if ($schemaSlug === 'column') {
					return ['results' => [['@self' => ['id' => 'c-todo'], 'title' => 'To do', 'order' => 0], ['title' => 'no id']], 'total' => 2];
				}

				return ['results' => [['@self' => ['id' => 'T', 'created' => '2026-09-01T10:00:00+00:00'], 'title' => 'T', 'column' => 'c-todo', 'status' => 'open']]];
			}
		};

		$flow = $this->service(container: $container, logger: $logger)->forProject(
			new FlowProjectQuery(
				objectService: $objects,
				projectId: 'p1',
				from: new DateTimeImmutable('2026-09-29', new DateTimeZone('UTC')),
				to: new DateTimeImmutable('2026-09-30', new DateTimeZone('UTC'))
			)
		);

		self::assertSame('p1', $flow['projectId']);
		self::assertSame([['id' => 'c-todo', 'title' => 'To do', 'color' => null]], $flow['columns']);
		self::assertSame(['2026-09-29', '2026-09-30'], array_column($flow['days'], 'date'));
	}//end testAPaginatedAnswerAndAnUnavailableAuditTrail()
}//end class
