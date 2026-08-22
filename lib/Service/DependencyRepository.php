<?php

/**
 * Planninq Dependency Repository
 *
 * The OpenRegister data-access half of task dependencies: resolving the
 * ObjectService, reading tasks/projects/edges, and normalising OR's several
 * result shapes (paginated envelope, plain list, entity objects) into plain
 * arrays.
 *
 * Split out of DependencyService, which carried this whole persistence plane
 * alongside the domain validation rules and the graph algorithms and tripped
 * PHPMD's ExcessiveClassComplexity threshold — the rule was correctly naming a
 * real Single Responsibility violation. Every method below is moved verbatim,
 * signatures included, so the split is behaviour-preserving.
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

use OCA\Planninq\Exception\DependencyValidationException;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * OpenRegister reads for task dependency edges.
 *
 * @spec openspec/specs/task-dependencies/spec.md
 */
class DependencyRepository {
	/**
	 * OpenRegister register slug that owns the Planninq data.
	 *
	 * Deliberately still the PRE-RENAME `planix`. The app id became `planninq`,
	 * but the register holding the live data is still slugged `planix` and this
	 * release ships no register-slug migration.
	 *
	 * @var string
	 */
	private const REGISTER = 'planix';

	/**
	 * OpenRegister schema slug for dependency edges.
	 *
	 * @var string
	 */
	private const SCHEMA = 'dependency';

	/**
	 * Constructor for the DependencyRepository.
	 *
	 * @param ContainerInterface $container The container
	 * @param LoggerInterface $logger The logger
	 * @param IAppManager $appManager The app manager (OpenRegister availability probe)
	 *
	 * @return void
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
		private IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Resolve OpenRegister's ObjectService, or throw a mappable exception.
	 *
	 * @return object The OR ObjectService.
	 *
	 * @throws DependencyValidationException When OpenRegister is unavailable.
	 *
	 * @spec openspec/specs/task-dependencies/spec.md
	 */
	public function objectService(): object {
		// ADR-083 rule 1: establish availability BEFORE reaching into the
		// container. The try/catch below is error handling, not a guard — it
		// reports after the fact and cannot distinguish "OpenRegister is not
		// installed" from "OpenRegister is installed and broken". Probing
		// IAppManager first keeps the dependency optional and visible, which is
		// why the lookup stays rather than becoming constructor injection:
		// injecting it would make this class unconstructable on an instance
		// without OpenRegister, turning a clean message into a 500.
		//
		// Same shape as DueReminderWindowService::patch() in this app.
		if ($this->appManager->isInstalled('openregister') === false) {
			$this->logger->error('Planninq: OpenRegister is not installed, dependency edges unavailable');
			throw new DependencyValidationException(
				message: 'OpenRegister is not available.',
				code: DependencyValidationException::CODE_UNAVAILABLE
			);
		}

		try {
			return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
			throw new DependencyValidationException(
				message: 'OpenRegister is not available.',
				code: DependencyValidationException::CODE_UNAVAILABLE
			);
		}
	}//end objectService()

	/**
	 * Fetch a task object by UUID, returning its data array or null.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $taskId UUID of the task.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/specs/task-dependencies/spec.md
	 */
	public function fetchTask(object $objectService, string $taskId): ?array {
		if ($taskId === '') {
			return null;
		}

		$objectService->setRegister(self::REGISTER);
		$objectService->setSchema('task');
		$entity = $objectService->find(id: $taskId);
		if ($entity === null) {
			return null;
		}

		return $entity->getObject();
	}//end fetchTask()

	/**
	 * Fetch all dependency edges that belong to a given project.
	 *
	 * An edge belongs to a project when its blocker task is in that project
	 * (the same-project invariant guarantees the blocked task is too).
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $projectId UUID of the project.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/specs/task-dependencies/spec.md
	 */
	public function fetchProjectEdges(object $objectService, string $projectId): array {
		$taskIds = $this->fetchProjectTaskIds(objectService: $objectService, projectId: $projectId);
		if ($taskIds === []) {
			return [];
		}

		$taskIdSet = array_fill_keys($taskIds, true);
		$edges = [];
		foreach ($this->fetchAllEdges(objectService: $objectService) as $edge) {
			$blockerId = (string)($edge['blocker'] ?? '');
			if (isset($taskIdSet[$blockerId]) === true) {
				$edges[] = $edge;
			}
		}

		return $edges;
	}//end fetchProjectEdges()

	/**
	 * Fetch the UUIDs of every task in a project.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $projectId UUID of the project.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/specs/task-dependencies/spec.md
	 */
	public function fetchProjectTaskIds(object $objectService, string $projectId): array {
		// `searchObjectsBySlug()`, not `searchObjects()` — the same defect that
		// 500'd the timeline endpoint (`Unknown named parameter $filters`, and
		// `searchObjects()` ignoring the register/schema left by the setters).
		// Here the fatal lands on dependency creation, whose cross-project guard
		// reads this list.
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: 'task',
			filters: ['project' => $projectId]
		);

		$ids = [];
		foreach ($this->normaliseResults(results: $results) as $row) {
			$id = $this->extractId(row: $row);
			if ($id !== '') {
				$ids[] = $id;
			}
		}

		return $ids;
	}//end fetchProjectTaskIds()

	/**
	 * Fetch every dependency edge in the register, normalised to plain arrays
	 * carrying at least `id`, `blocker`, and `blocked`.
	 *
	 * @param object $objectService The OR ObjectService.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/specs/task-dependencies/spec.md
	 */
	public function fetchAllEdges(object $objectService): array {
		// See fetchProjectTaskIds(): `searchObjects()` silently matches nothing
		// when the register/schema came from the setters. The cycle detector
		// reads this edge list, so an empty result made every cycle check pass
		// vacuously.
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SCHEMA
		);

		$edges = [];
		foreach ($this->normaliseResults(results: $results) as $row) {
			$data = $this->extractData(row: $row);
			$data['id'] = $this->extractId(row: $row);
			$edges[] = $data;
		}

		return $edges;
	}//end fetchAllEdges()

	/**
	 * Normalise an ObjectService result set (which may be a paginated array
	 * with a `results` key, a plain list, or a list of entity objects) to a
	 * plain list of rows.
	 *
	 * @param mixed $results The raw ObjectService return value.
	 *
	 * @return array<int,mixed>
	 */
	private function normaliseResults(mixed $results): array {
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			return (array)$results['results'];
		}

		if (is_array($results) === true) {
			return $results;
		}

		return [];
	}//end normaliseResults()

	/**
	 * Extract the object UUID from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return string
	 */
	private function extractId(mixed $row): string {
		if (is_object($row) === true) {
			// `is_callable()`, NOT `method_exists()` — see the identical fix in
			// LabelService::extractId(). \OCP\AppFramework\Db\Entity implements
			// its accessors through `__call()` and declares neither `getUuid()`
			// nor `getId()`, so `method_exists()` skipped both branches and this
			// helper returned '' for every entity. Here that emptied the task-id
			// set the cross-project guard and the cycle detector compare
			// against.
			foreach (['getUuid', 'getId'] as $getter) {
				if (is_callable([$row, $getter]) === false) {
					continue;
				}

				try {
					$value = $row->$getter();
				} catch (\Throwable $e) {
					continue;
				}

				if ($value !== null && (string)$value !== '') {
					return (string)$value;
				}
			}//end foreach
		}//end if

		if (is_array($row) === true) {
			if (isset($row['@self']['id']) === true) {
				return (string)$row['@self']['id'];
			}

			return (string)($row['id'] ?? '');
		}

		return '';
	}//end extractId()

	/**
	 * Extract the object data array from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return array<string,mixed>
	 */
	private function extractData(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			return $row;
		}

		return [];
	}//end extractData()
}//end class
