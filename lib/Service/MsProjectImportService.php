<?php

/**
 * Planninq Microsoft Project import service
 *
 * Previews and imports a plan saved from Microsoft Project into one project.
 * Both calls are stateless: the preview parses, maps and compares with what an
 * earlier import created, and writes nothing; the import takes the same file
 * again and writes phases, tasks, sub-tasks and links in that order. Objects
 * are matched on their Microsoft Project task UID (stored in `metadata`), so a
 * newer plan updates what an earlier import created and a failed import can be
 * run again without duplicates. Objects whose UID left the plan are listed,
 * never deleted.
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
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\MsProjectImportException;
use Psr\Log\LoggerInterface;

/**
 * Previews and writes a Microsoft Project plan.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
 */
class MsProjectImportService {

	/**
	 * The planninq register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * How many tasks the preview lists.
	 *
	 * @var int
	 */
	private const SAMPLE_SIZE = 50;

	/**
	 * Constructor.
	 *
	 * @param MsProjectPlanParser  $parser       Reads the XML.
	 * @param MsProjectPlanMapper  $mapper       Maps the plan onto planninq's model.
	 * @param DependencyRepository $repository   Resolves OpenRegister's ObjectService.
	 * @param DependencyService    $dependencies Checks and writes the links.
	 * @param LoggerInterface      $logger       The logger.
	 */
	public function __construct(
		private MsProjectPlanParser $parser,
		private MsProjectPlanMapper $mapper,
		private DependencyRepository $repository,
		private DependencyService $dependencies,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * What importing this file into the project would do; writes nothing.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $content   The file content.
	 * @param string $fileName  The uploaded file name.
	 *
	 * @return array{counts:array<string,int>,losses:array<string,int>,sample:list<array<string,mixed>>,updates:int,missing:list<string>}
	 *
	 * @throws MsProjectImportException When the file is refused.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
	 */
	public function preview(string $projectId, string $content, string $fileName): array {
		$mapped   = $this->mapper->map(plan: $this->parser->parse(content: $content, fileName: $fileName));
		$existing = $this->existing(objectService: $this->repository->objectService(), projectId: $projectId);
		$uids     = $this->uidsOf(mapped: $mapped);

		$sample = [];
		foreach (array_slice(array_merge($mapped['tasks'], $mapped['subtasks']), 0, self::SAMPLE_SIZE) as $task) {
			$sample[] = [
				'title'     => $task['data']['title'],
				'kind'      => $this->kind(task: $task),
				'startDate' => ($task['data']['startDate'] ?? null),
				'dueDate'   => ($task['data']['dueDate'] ?? null),
			];
		}

		return [
			'counts'  => $mapped['counts'],
			'losses'  => $mapped['losses'],
			'sample'  => $sample,
			'updates' => count(array_intersect_key($existing['projectPhase'] + $existing['task'], $uids)),
			'missing' => $this->missing(existing: $existing, uids: $uids),
		];
	}//end preview()

	/**
	 * Import this file into the project.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $content   The file content.
	 * @param string $fileName  The uploaded file name.
	 *
	 * @return array<string,mixed> Counts created and updated, the missing titles, refused links and losses; `error` when the import stopped part-way.
	 *
	 * @throws MsProjectImportException When the file is refused.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.2
	 */
	public function import(string $projectId, string $content, string $fileName): array {
		$mapped        = $this->mapper->map(plan: $this->parser->parse(content: $content, fileName: $fileName));
		$objectService = $this->repository->objectService();
		$existing      = $this->existing(objectService: $objectService, projectId: $projectId);
		$result        = [
			'created'      => ['phases' => 0, 'tasks' => 0, 'subtasks' => 0, 'links' => 0],
			'updated'      => ['phases' => 0, 'tasks' => 0, 'subtasks' => 0],
			'missing'      => $this->missing(existing: $existing, uids: $this->uidsOf(mapped: $mapped)),
			'refusedLinks' => 0,
			'losses'       => $mapped['losses'],
		];

		try {
			$ids = [];
			foreach (['phases' => 'projectPhase', 'tasks' => 'task', 'subtasks' => 'task'] as $kind => $schema) {
				foreach ($mapped[$kind] as $object) {
					$data = ($object['data'] + ['project' => $projectId]);
					$data = $this->withReferences(data: $data, object: $object, ids: $ids);

					$match = ($existing[$schema][$object['uid']] ?? null);
					$ids[$object['uid']] = $this->write(objectService: $objectService, schema: $schema, data: $data, match: $match);
					$outcome = 'updated';
					if ($match === null) {
						$outcome = 'created';
					}

					$result[$outcome][$kind]++;
				}
			}

			$links = [];
			foreach ($mapped['links'] as $link) {
				$links[] = ['blocker' => $ids[$link['blockerUid']], 'blocked' => $ids[$link['blockedUid']], 'type' => $link['type']];
			}

			$linked = $this->dependencies->createImported(projectId: $projectId, links: $links);
			$result['created']['links'] = $linked['created'];
			$result['refusedLinks']     = $linked['refused'];
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: Microsoft Project import stopped part-way', ['project' => $projectId, 'exception' => $e->getMessage()]);
			$result['error'] = $e->getMessage();
		}//end try

		return $result;
	}//end import()

	/**
	 * Set the phase and parent references from the UIDs already written.
	 *
	 * @param array<string,mixed>  $data   The payload.
	 * @param array<string,mixed>  $object The mapped object.
	 * @param array<string,string> $ids    Written UUIDs by Project UID.
	 *
	 * @return array<string,mixed>
	 */
	private function withReferences(array $data, array $object, array $ids): array {
		$phase = ($object['phaseUid'] ?? null);
		if ($phase !== null && isset($ids[$phase]) === true) {
			$data['phase'] = $ids[$phase];
		}

		$parent = ($object['parentUid'] ?? null);
		if ($parent !== null && isset($ids[$parent]) === true) {
			$data['parent'] = $ids[$parent];
		}

		return $data;
	}//end withReferences()

	/**
	 * Create an object, or update the one an earlier import made.
	 *
	 * An update changes only what the plan owns; status, column, assignee and
	 * everything else a member set in planninq stays.
	 *
	 * @param object                                         $objectService OpenRegister's ObjectService.
	 * @param string                                         $schema        The schema slug.
	 * @param array<string,mixed>                            $data          The mapped payload.
	 * @param array{id:string,data:array<string,mixed>}|null $match         The earlier object.
	 *
	 * @return string The object's UUID.
	 */
	private function write(object $objectService, string $schema, array $data, ?array $match): string {
		$uuid = null;
		if ($match !== null) {
			$uuid = $match['id'];
			unset($data['status'], $match['data']['@self'], $match['data']['id']);
			$data = array_merge($match['data'], $data);
		}

		$saved = $objectService->saveObject(object: $data, register: self::REGISTER, schema: $schema, uuid: $uuid);

		return $this->idOf(row: $saved, fallback: (string)$uuid);
	}//end write()

	/**
	 * The project's phases and tasks an earlier import created, by Project UID.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $projectId     The project UUID.
	 *
	 * @return array{projectPhase:array<string,array{id:string,data:array<string,mixed>}>,task:array<string,array{id:string,data:array<string,mixed>}>}
	 */
	private function existing(object $objectService, string $projectId): array {
		$existing = ['projectPhase' => [], 'task' => []];
		foreach (array_keys($existing) as $schema) {
			$rows = $objectService->searchObjectsBySlug(registerSlug: self::REGISTER, schemaSlug: $schema, filters: ['project' => $projectId]);
			if (is_array($rows) === true && array_key_exists('results', $rows) === true) {
				$rows = (array)$rows['results'];
			}

			foreach ((array)$rows as $row) {
				$data = $this->dataOf(row: $row);
				$uid  = (string)($data['metadata']['msProjectUid'] ?? '');
				if ($uid !== '') {
					$existing[$schema][$uid] = ['id' => $this->idOf(row: $row, fallback: ''), 'data' => $data];
				}
			}
		}

		return $existing;
	}//end existing()

	/**
	 * The titles of imported objects whose UID is not in the new plan.
	 *
	 * @param array<string,array<string,array{id:string,data:array<string,mixed>}>> $existing The earlier objects.
	 * @param array<string,bool>                                                   $uids     The new plan's UIDs.
	 *
	 * @return list<string>
	 */
	private function missing(array $existing, array $uids): array {
		$titles = [];
		foreach ($existing as $objects) {
			foreach (array_diff_key($objects, $uids) as $object) {
				$titles[] = (string)($object['data']['title'] ?? '');
			}
		}

		return $titles;
	}//end missing()

	/**
	 * Every Project UID the mapped plan writes.
	 *
	 * @param array<string,mixed> $mapped The mapping.
	 *
	 * @return array<string,bool>
	 */
	private function uidsOf(array $mapped): array {
		$uids = [];
		foreach (['phases', 'tasks', 'subtasks'] as $kind) {
			foreach ($mapped[$kind] as $object) {
				$uids[(string)$object['uid']] = true;
			}
		}

		return $uids;
	}//end uidsOf()

	/**
	 * How the preview names a mapped task.
	 *
	 * @param array<string,mixed> $task The mapped task.
	 *
	 * @return string task, subtask or milestone
	 */
	private function kind(array $task): string {
		if (($task['data']['issueType'] ?? '') === 'milestone') {
			return 'milestone';
		}

		if ($task['parentUid'] !== null) {
			return 'subtask';
		}

		return 'task';
	}//end kind()

	/**
	 * The UUID of an entity or array row.
	 *
	 * @param mixed  $row      The row.
	 * @param string $fallback The value when none is found.
	 *
	 * @return string
	 */
	private function idOf(mixed $row, string $fallback): string {
		// Use is_callable, not method_exists: an OpenRegister entity serves getUuid() through __call().
		if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
			return (string)($row->getUuid() ?? $fallback);
		}

		if (is_array($row) === true) {
			return (string)($row['@self']['id'] ?? ($row['id'] ?? $fallback));
		}

		return $fallback;
	}//end idOf()

	/**
	 * The data of an entity or array row.
	 *
	 * @param mixed $row The row.
	 *
	 * @return array<string,mixed>
	 */
	private function dataOf(mixed $row): array {
		if (is_object($row) === true && is_callable([$row, 'getObject']) === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			return $row;
		}

		return [];
	}//end dataOf()
}//end class
