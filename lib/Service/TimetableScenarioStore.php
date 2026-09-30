<?php

/**
 * Planninq Timetable Scenario Store
 *
 * Reads and writes timetableScenario objects and reads the timetableWish
 * objects, through OpenRegister's ObjectService resolved by name (ADR-022).
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * The generator's access to its scenarios and wishes.
 *
 * Written with RBAC off: the store is reached only from the admin-only
 * generate endpoint and from the queued job, which runs without a user.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
 */
class TimetableScenarioStore {

	/**
	 * The register slug.
	 */
	public const REGISTER = 'planninq';

	/**
	 * The scenario schema slug.
	 */
	public const SCENARIO = 'timetableScenario';

	/**
	 * The wish schema slug.
	 */
	public const WISH = 'timetableWish';

	/**
	 * The lesson schema slug.
	 */
	public const SESSION = 'timetableSession';

	/**
	 * The most wishes one run reads.
	 */
	private const MAX_WISHES = 1000;

	/**
	 * OpenRegister ObjectService FQCN.
	 */
	private const OR_OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService by name.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * One scenario's data, or null when it does not exist.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	public function load(string $id): ?array {
		try {
			$object = $this->objectService()->find(id: $id, register: self::REGISTER, schema: self::SCENARIO, _rbac: false);
		} catch (Throwable) {
			return null;
		}

		return $this->dataOf(object: $object);
	}//end load()

	/**
	 * Store a scenario's data under its id.
	 *
	 * @param string              $id   The scenario id.
	 * @param array<string,mixed> $data The complete scenario.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When OpenRegister refuses it.
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	public function save(string $id, array $data): void {
		unset($data['@self'], $data['id']);
		$saved = $this->objectService()->saveObject(object: $data, register: self::REGISTER, schema: self::SCENARIO, uuid: $id, _rbac: false);
		if ($saved === null) {
			throw new RuntimeException('OpenRegister did not store the scenario.');
		}
	}//end save()

	/**
	 * Every wish.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	public function wishes(): array {
		$results = $this->objectService()->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::WISH,
			filters: ['_limit' => self::MAX_WISHES],
			_rbac: false
		);
		$wishes  = [];
		foreach ((new TimetableSessionRows())->listOf(results: $results) as $row) {
			$data = $this->dataOf(object: $row);
			if ($data !== null) {
				$wishes[] = $data;
			}
		}

		return $wishes;
	}//end wishes()

	/**
	 * The scheduled lessons that start in a window, as stored.
	 *
	 * @param string $from The first moment, ISO 8601.
	 * @param string $to   The last moment, ISO 8601.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
	 */
	public function scheduledLessons(string $from, string $to): array {
		$results = $this->objectService()->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SESSION,
			filters: ['status' => 'scheduled', 'startsAt' => ['gte' => $from, 'lte' => $to], '_limit' => TimetableSessionQuery::MAX_LIMIT],
			_rbac: false
		);
		$first   = strtotime($from);
		$last    = strtotime($to);
		$lessons = [];
		foreach ((new TimetableSessionRows())->listOf(results: $results) as $row) {
			$data   = (array)$this->dataOf(object: $row);
			$starts = strtotime((string)($data['startsAt'] ?? ''));
			// Re-checked here, so the snapshot never depends on OpenRegister applying every filter.
			if (($data['status'] ?? '') === 'scheduled' && $starts !== false && $starts >= $first && $starts <= $last) {
				$lessons[] = $data;
			}
		}

		return $lessons;
	}//end scheduledLessons()

	/**
	 * The lessons from one source that start in a window, drafts and scheduled, with their ids.
	 *
	 * @param string $source The source system.
	 * @param string $from   The first moment, ISO 8601.
	 * @param string $to     The last moment, ISO 8601.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-8.1
	 */
	public function lessonsFrom(string $source, string $from, string $to): array {
		$results = $this->objectService()->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SESSION,
			filters: ['sourceSystem' => $source, 'startsAt' => ['gte' => $from, 'lte' => $to], '_limit' => TimetableSessionQuery::MAX_LIMIT],
			_rbac: false
		);
		$lessons = [];
		foreach ((new TimetableSessionRows())->listOf(results: $results) as $row) {
			$data   = (array)$this->dataOf(object: $row);
			$starts = strtotime((string)($data['startsAt'] ?? ''));
			if (($data['sourceSystem'] ?? '') === $source && $starts !== false && $starts >= strtotime($from) && $starts <= strtotime($to)) {
				$lessons[] = $data;
			}
		}

		return $lessons;
	}//end lessonsFrom()

	/**
	 * Delete one lesson.
	 *
	 * @param string $id The lesson id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-8.1
	 */
	public function deleteLesson(string $id): void {
		$this->objectService()->deleteObject(uuid: $id, register: self::REGISTER, schema: self::SESSION, _rbac: false);
	}//end deleteLesson()

	/**
	 * An object's data with its id, from an ObjectEntity or an array; null for anything else.
	 *
	 * @param mixed $object The object.
	 *
	 * @return array<string,mixed>|null
	 */
	private function dataOf(mixed $object): ?array {
		if (is_object($object) === false && is_array($object) === false) {
			return null;
		}

		$rows = new TimetableSessionRows();
		$data = $rows->dataOf(row: $object);
		$id   = $rows->idOf(row: $object);
		if ($id !== '') {
			$data['id'] = $id;
		}

		return $data;
	}//end dataOf()

	/**
	 * OpenRegister's ObjectService, cleared of any earlier caller's scope.
	 *
	 * @return object
	 *
	 * @throws RuntimeException When OpenRegister is not available.
	 */
	private function objectService(): object {
		try {
			$objectService = $this->container->get(self::OR_OBJECT_SERVICE);
		} catch (Throwable $e) {
			throw new RuntimeException('OpenRegister is not available.', 0, $e);
		}

		if (method_exists($objectService, 'clearCurrents') === true) {
			$objectService->clearCurrents();
		}

		return $objectService;
	}//end objectService()
}//end class
