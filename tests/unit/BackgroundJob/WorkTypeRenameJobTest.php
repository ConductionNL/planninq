<?php

/**
 * Unit tests for WorkTypeRenameJob.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
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

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\WorkTypeRenameJob;
use OCA\Planninq\Service\OpenRegisterObjects;
use OCA\Planninq\Service\WorkTypeService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The rename job writes the new name on the entries that carry the old one.
 */
#[CoversClass(WorkTypeRenameJob::class)]
#[UsesClass(WorkTypeService::class)]
final class WorkTypeRenameJobTest extends TestCase {

	/**
	 * A fake OpenRegister object service: finds by `workType`, records saves.
	 *
	 * @param array<int,array<string,mixed>> $rows The stored entries.
	 *
	 * @return object
	 */
	private function objectService(array $rows): object {
		return new class($rows) {
			/**
			 * The saves made, in order.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $saved = [];

			/**
			 * The searches made.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $searched = [];

			/**
			 * Constructor.
			 *
			 * @param array<int,array<string,mixed>> $rows The stored entries.
			 */
			public function __construct(private array $rows) {
			}

			/**
			 * Find by the filters.
			 *
			 * @param mixed ...$args The named arguments.
			 *
			 * @return array<string,mixed>
			 */
			public function searchObjectsBySlug(mixed ...$args): array {
				$this->searched[] = $args;
				$found = array_values(array_filter($this->rows, fn (array $row): bool => ($row['workType'] ?? null) === $args['filters']['workType']));
				return ['results' => $found];
			}

			/**
			 * Record a save; the entry 'e-bad' refuses.
			 *
			 * @param mixed ...$args The named arguments.
			 *
			 * @return array<string,mixed>
			 */
			public function saveObject(mixed ...$args): array {
				if ($args['uuid'] === 'e-bad') {
					throw new \RuntimeException('refused');
				}

				$this->saved[] = $args;
				return $args['object'];
			}
		};
	}//end objectService()

	/**
	 * The job over a fake object service.
	 *
	 * @param object|null $objectService The object service, or null when OpenRegister is missing.
	 *
	 * @return WorkTypeRenameJob
	 */
	private function job(?object $objectService): WorkTypeRenameJob {
		$objects = $this->createMock(originalClassName: OpenRegisterObjects::class);
		$objects->method('objectService')->willReturn($objectService);

		return new WorkTypeRenameJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			workTypes: new WorkTypeService(
				jobs: $this->createMock(originalClassName: IJobList::class),
				objects: $objects,
				logger: $this->createMock(originalClassName: LoggerInterface::class),
			),
		);
	}//end job()

	/**
	 * Every entry with the old name gets the new one, as the system and silently, its other fields kept;
	 * an entry that refuses is skipped and does not stop the rest.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function testRenameUpdatesExistingEntries(): void {
		$service = $this->objectService(
			rows: [
				['@self' => ['id' => 'e-1'], 'workType' => 'Meeting', 'duration' => 45, 'user' => 'anna'],
				['@self' => ['id' => 'e-bad'], 'workType' => 'Meeting', 'duration' => 10],
				['id' => 'e-2', 'workType' => 'Meeting', 'duration' => 30],
				['id' => 'e-3', 'workType' => 'Development', 'duration' => 120],
			]
		);

		$renamed = $this->job(objectService: $service)->renameFromArgument(argument: ['from' => 'Meeting', 'to' => ' Overleg ']);
		self::assertSame(expected: 2, actual: $renamed);

		self::assertSame(expected: 'planninq', actual: $service->searched[0]['registerSlug']);
		self::assertSame(expected: 'plannedTimeEntry', actual: $service->searched[0]['schemaSlug']);
		self::assertFalse(condition: $service->searched[0]['_rbac']);
		self::assertSame(expected: ['e-1', 'e-2'], actual: array_column($service->saved, 'uuid'));
		self::assertSame(expected: ['workType' => 'Overleg', 'duration' => 45, 'user' => 'anna'], actual: $service->saved[0]['object']);
		self::assertSame(expected: ['workType' => 'Overleg', 'id' => 'e-2', 'duration' => 30], actual: $service->saved[1]['object']);
		foreach ($service->saved as $save) {
			self::assertFalse(condition: $save['_rbac']);
			self::assertFalse(condition: $save['_multitenancy']);
			self::assertTrue(condition: $save['silent']);
		}
	}//end testRenameUpdatesExistingEntries()

	/**
	 * A malformed argument, the same name twice, or no OpenRegister renames nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function testNothingIsRenamedWithoutAValidRename(): void {
		$service = $this->objectService(rows: [['id' => 'e-1', 'workType' => 'Meeting']]);
		$job     = $this->job(objectService: $service);

		self::assertSame(expected: 0, actual: $job->renameFromArgument(argument: null));
		self::assertSame(expected: 0, actual: $job->renameFromArgument(argument: ['from' => 'Meeting']));
		self::assertSame(expected: 0, actual: $job->renameFromArgument(argument: ['from' => 'Meeting', 'to' => 'Meeting']));
		self::assertSame(expected: 0, actual: $job->renameFromArgument(argument: ['from' => '', 'to' => 'Overleg']));
		self::assertSame(expected: 0, actual: $job->renameFromArgument(argument: ['from' => 'Meeting', 'to' => str_repeat('x', 101)]));
		self::assertSame(expected: [], actual: $service->searched);

		self::assertSame(expected: 0, actual: $this->job(objectService: null)->renameFromArgument(argument: ['from' => 'Meeting', 'to' => 'Overleg']));
	}//end testNothingIsRenamedWithoutAValidRename()

	/**
	 * The queued run performs the rename.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function testRunRenames(): void {
		$service = $this->objectService(rows: [['id' => 'e-1', 'workType' => 'Meeting']]);

		(new ReflectionMethod(WorkTypeRenameJob::class, 'run'))->invoke($this->job(objectService: $service), '{"from":"Meeting","to":"Overleg"}');

		self::assertSame(expected: ['e-1'], actual: array_column($service->saved, 'uuid'));
	}//end testRunRenames()
}//end class
