<?php

/**
 * Unit tests for WorkTypeService::renameEntries: which entries are
 * rewritten, how a failing save is counted, and when nothing is searched.
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

use OCA\Planninq\Service\OpenRegisterObjects;
use OCA\Planninq\Service\WorkTypeService;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;

/**
 * Tests for renaming a work type on existing entries.
 */
#[CoversClass(WorkTypeService::class)]
final class WorkTypeServiceRenameEntriesTest extends TestCase {

	/**
	 * A fake ObjectService that answers a search and records saves.
	 *
	 * @param mixed       $results  What searchObjectsBySlug answers.
	 * @param string|null $failUuid A uuid whose save throws.
	 *
	 * @return object
	 */
	private function objectService(mixed $results, ?string $failUuid = null): object {
		return new class($results, $failUuid) {
			/** @var array<int, array<string, mixed>> */
			public array $searches = [];

			/** @var array<int, array<string, mixed>> */
			public array $saved = [];

			/**
			 * @param mixed       $results  The search answer.
			 * @param string|null $failUuid A uuid whose save throws.
			 */
			public function __construct(private mixed $results, private ?string $failUuid) {
			}

			/**
			 * @param \OCA\OpenRegister\Contract\RegisterSchemaSlugs $target The register and schema slugs.
			 * @param array<string, mixed> $filters       Filters.
			 * @param boolean              $_rbac         RBAC.
			 * @param boolean              $_multitenancy Multitenancy.
			 *
			 * @return mixed
			 */
			public function searchObjectsBySlug(\OCA\OpenRegister\Contract\RegisterSchemaSlugs $target, array $filters, bool $_rbac, bool $_multitenancy): mixed {
				$registerSlug = $target->register;
				$schemaSlug   = $target->schema;
				$this->searches[] = [$registerSlug, $schemaSlug, $filters, $_rbac, $_multitenancy];
				return $this->results;
			}

			/**
			 * @param array<string, mixed> $object        The object.
			 * @param string               $register      Register.
			 * @param string               $schema        Schema.
			 * @param string               $uuid          Uuid.
			 * @param boolean              $_rbac         RBAC.
			 * @param boolean              $_multitenancy Multitenancy.
			 * @param boolean              $silent        Silent.
			 *
			 * @return array<string, mixed>
			 */
			public function saveObject(array $object, ObjectSaveOptions $options = new ObjectSaveOptions(), bool $_rbac = true, bool $_multitenancy = true): array {
				$register = $options->target->register;
				$schema = $options->target->schema;
				$uuid = $options->uuid;
				$silent = $options->policy->silent;
				if ($uuid === $this->failUuid) {
					throw new RuntimeException('locked');
				}

				$this->saved[] = ['object' => $object, 'register' => $register, 'schema' => $schema, 'uuid' => $uuid, 'flags' => [$_rbac, $_multitenancy, $silent]];
				return $object;
			}
		};
	}//end objectService()

	/**
	 * The service over an ObjectService (or none).
	 *
	 * @param object|null     $objectService The ObjectService.
	 * @param LoggerInterface $logger        The logger.
	 *
	 * @return WorkTypeService
	 */
	private function service(?object $objectService, LoggerInterface $logger): WorkTypeService {
		$objects = $this->createMock(OpenRegisterObjects::class);
		$objects->method('objectService')->willReturn($objectService);
		return new WorkTypeService(jobs: $this->createMock(IJobList::class), objects: $objects, logger: $logger);
	}//end service()

	/**
	 * Entries with the old work type are saved under their own id with the new one,
	 * from array rows (with or without @self) and entity rows; others are left alone.
	 *
	 * @return void
	 */
	public function testMatchingEntriesAreRewrittenUnderTheirOwnId(): void {
		$entity = new class {
			/** @return string */
			public function getUuid(): string {
				return 'e-3';
			}

			/** @return array<string, mixed> */
			public function getObject(): array {
				return ['workType' => 'Design', 'hours' => 2];
			}
		};
		$os = $this->objectService(
			[
				'results' => [
					['@self' => ['id' => 'e-1'], 'workType' => 'Design', 'hours' => 4],
					['id' => 'e-2', 'workType' => 'Build'],
					$entity,
					['workType' => 'Design'],
					'not a row',
				],
			]
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info')
			->with('Planninq: renamed a work type on existing entries', ['from' => 'Design', 'to' => 'UX', 'entries' => 2]);

		self::assertSame(2, $this->service($os, $logger)->renameEntries('Design', 'UX'));

		self::assertSame([['planninq', 'plannedTimeEntry', ['workType' => 'Design'], false, false]], $os->searches);
		self::assertSame(
			[
				['object' => ['workType' => 'UX', 'hours' => 4], 'register' => 'planninq', 'schema' => 'plannedTimeEntry', 'uuid' => 'e-1', 'flags' => [false, false, true]],
				['object' => ['workType' => 'UX', 'hours' => 2], 'register' => 'planninq', 'schema' => 'plannedTimeEntry', 'uuid' => 'e-3', 'flags' => [false, false, true]],
			],
			$os->saved
		);
	}//end testMatchingEntriesAreRewrittenUnderTheirOwnId()

	/**
	 * A failing save is logged and not counted; a bare list of rows is read too.
	 *
	 * @return void
	 */
	public function testAFailingSaveIsLoggedAndNotCounted(): void {
		$os = $this->objectService(
			[
				['id' => 'e-1', 'workType' => 'Design'],
				['id' => 'e-2', 'workType' => 'Design'],
			],
			'e-1'
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')
			->with('Planninq: could not rename the work type of an entry', ['object' => 'e-1', 'exception' => 'locked']);

		self::assertSame(1, $this->service($os, $logger)->renameEntries('Design', 'UX'));
		self::assertSame(['e-2'], array_column($os->saved, 'uuid'));
	}//end testAFailingSaveIsLoggedAndNotCounted()

	/**
	 * Arguments that cannot rename anything.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function noRename(): array {
		return [
			'no from'   => ['', 'UX'],
			'no to'     => ['Design', ''],
			'unchanged' => ['Design', 'Design'],
		];
	}//end noRename()

	/**
	 * Nothing is searched when there is nothing to rename.
	 *
	 * @param string $from The old name.
	 * @param string $to   The new name.
	 *
	 * @return void
	 */
	#[DataProvider('noRename')]
	public function testNothingIsSearchedWithoutARename(string $from, string $to): void {
		$os = $this->objectService([]);

		self::assertSame(0, $this->service($os, $this->createMock(LoggerInterface::class))->renameEntries($from, $to));
		self::assertSame([], $os->searches);
	}//end testNothingIsSearchedWithoutARename()

	/**
	 * Without OpenRegister nothing is renamed.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterNothingIsRenamed(): void {
		self::assertSame(0, $this->service(null, $this->createMock(LoggerInterface::class))->renameEntries('Design', 'UX'));
	}//end testWithoutOpenRegisterNothingIsRenamed()
}//end class
