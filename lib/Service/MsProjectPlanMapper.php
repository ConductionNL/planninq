<?php

/**
 * Planninq Microsoft Project plan mapper
 *
 * Maps a parsed Microsoft Project plan onto planninq's model with one fixed
 * mapping: level-1 summary tasks become phases, level-2 tasks become tasks,
 * deeper tasks collapse into sub-tasks of their level-2 task, milestones
 * become milestone tasks, and finish-to-start links become blocking
 * dependencies while every other link type becomes a related link. Whatever
 * does not carry over is counted, so the owner sees it before confirming.
 * Pure: no I/O.
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
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Maps a parsed plan to phases, tasks, sub-tasks, links and losses.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
 */
class MsProjectPlanMapper {

	/**
	 * Project's link type for finish-to-start.
	 *
	 * @var int
	 */
	private const FINISH_TO_START = 1;

	/**
	 * The loss keys, in the order the preview lists them.
	 *
	 * @var list<string>
	 */
	private const LOSS_KEYS = ['resources', 'collapsedLevels', 'relatedLinks', 'lags', 'phaseLinks', 'unknownLinks'];

	/**
	 * Map a parsed plan.
	 *
	 * @param array{name:string,saveVersion:string,tasks:list<array<string,mixed>>} $plan The plan from MsProjectPlanParser.
	 *
	 * @return array{phases:list<array<string,mixed>>,tasks:list<array<string,mixed>>,subtasks:list<array<string,mixed>>,links:list<array<string,string>>,losses:array<string,int>,counts:array<string,int>}
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
	 */
	public function map(array $plan): array {
		$provenance = ['msProjectFile' => $plan['name'], 'msProjectSaveVersion' => $plan['saveVersion']];
		$losses     = array_fill_keys(self::LOSS_KEYS, 0);
		$out        = ['phases' => [], 'tasks' => [], 'subtasks' => []];
		$kindByUid  = [];
		$phaseUid   = null;
		$level2Uid  = null;
		$path       = [];

		foreach ($plan['tasks'] as $task) {
			$level        = (int)$task['level'];
			$path         = array_slice($path, 0, ($level - 1), true);
			$path[$level] = $task['name'];
			$losses['resources'] += (int)($task['hasResource'] === true);

			if ($level === 1 && $task['summary'] === true) {
				$phaseUid        = $task['uid'];
				$kindByUid[$phaseUid] = 'phase';
				$out['phases'][] = ['uid' => $phaseUid, 'data' => $this->phaseData(task: $task, order: count($out['phases']), provenance: $provenance)];
				continue;
			}

			if ($level <= 2) {
				if ($level === 1) {
					$phaseUid = null;
				}

				$level2Uid = $task['uid'];
				$kindByUid[$task['uid']] = 'task';
				$data = $this->taskData(task: $task, provenance: $provenance, path: null);
				$out['tasks'][] = $this->mapped(task: $task, phaseUid: $phaseUid, parentUid: null, data: $data);
				continue;
			}

			$collapsed = null;
			if ($level > 3) {
				$losses['collapsedLevels']++;
				$collapsed = implode(' > ', array_slice($path, 1, null));
			}

			$kindByUid[$task['uid']] = 'task';
			$data = $this->taskData(task: $task, provenance: $provenance, path: $collapsed);
			$out['subtasks'][] = $this->mapped(task: $task, phaseUid: $phaseUid, parentUid: $level2Uid, data: $data);
		}//end foreach

		$links = $this->links(tasks: $plan['tasks'], kindByUid: $kindByUid, losses: $losses);

		return [
			'phases'   => $out['phases'],
			'tasks'    => $out['tasks'],
			'subtasks' => $out['subtasks'],
			'links'    => $links,
			'losses'   => array_filter($losses),
			'counts'   => $this->counts(out: $out, links: $links),
		];
	}//end map()

	/**
	 * The dependency links between tasks, counting the ones that change or drop.
	 *
	 * @param list<array<string,mixed>> $tasks     The plan's tasks.
	 * @param array<string,string>      $kindByUid 'phase' or 'task' per UID.
	 * @param array<string,int>         $losses    The loss counts, updated.
	 *
	 * @return list<array<string,string>>
	 */
	private function links(array $tasks, array $kindByUid, array &$losses): array {
		$links = [];
		foreach ($tasks as $task) {
			foreach ($task['predecessors'] as $link) {
				$blocker = (string)$link['uid'];
				if (isset($kindByUid[$blocker]) === false) {
					$losses['unknownLinks']++;
					continue;
				}

				if ($kindByUid[$blocker] === 'phase' || $kindByUid[$task['uid']] === 'phase') {
					$losses['phaseLinks']++;
					continue;
				}

				$type = 'blocks';
				if ((int)$link['type'] !== self::FINISH_TO_START) {
					$type = 'relates';
					$losses['relatedLinks']++;
				}

				$losses['lags'] += (int)((int)$link['lag'] !== 0);
				$links[$blocker . '>' . $task['uid']] = ['blockerUid' => $blocker, 'blockedUid' => (string)$task['uid'], 'type' => $type];
			}//end foreach
		}//end foreach

		return array_values($links);
	}//end links()

	/**
	 * One mapped task with its references by Project UID.
	 *
	 * @param array<string,mixed> $task      The plan task.
	 * @param string|null         $phaseUid  The UID of its phase.
	 * @param string|null         $parentUid The UID of its parent task.
	 * @param array<string,mixed> $data      The payload.
	 *
	 * @return array<string,mixed>
	 */
	private function mapped(array $task, ?string $phaseUid, ?string $parentUid, array $data): array {
		return ['uid' => $task['uid'], 'phaseUid' => $phaseUid, 'parentUid' => $parentUid, 'data' => $data];
	}//end mapped()

	/**
	 * A phase payload.
	 *
	 * @param array<string,mixed>  $task       The summary task.
	 * @param int                  $order      The phase's position.
	 * @param array<string,string> $provenance The plan's name and version.
	 *
	 * @return array<string,mixed>
	 */
	private function phaseData(array $task, int $order, array $provenance): array {
		return array_filter(
			[
				'title'     => $this->title(task: $task),
				'startDate' => $task['start'],
				'endDate'   => $task['finish'],
				'order'     => $order,
				'metadata'  => (['msProjectUid' => $task['uid']] + $provenance),
			],
			static fn ($value): bool => $value !== null
		);
	}//end phaseData()

	/**
	 * A task payload.
	 *
	 * @param array<string,mixed>  $task       The plan task.
	 * @param array<string,string> $provenance The plan's name and version.
	 * @param string|null          $path       The outline path of a collapsed task.
	 *
	 * @return array<string,mixed>
	 */
	private function taskData(array $task, array $provenance, ?string $path): array {
		$description = trim(implode("\n\n", array_filter([$path, $task['notes']], static fn ($part): bool => $part !== null && $part !== '')));
		if ($description === '') {
			$description = null;
		}

		$start       = $task['start'];
		$issueType   = null;
		if ($task['milestone'] === true) {
			$start     = ($task['finish'] ?? $task['start']);
			$issueType = 'milestone';
		}

		return array_filter(
			[
				'title'             => $this->title(task: $task),
				'description'       => $description,
				'status'            => $this->status(percent: (int)$task['percent']),
				'startDate'         => $start,
				'dueDate'           => ($task['finish'] ?? $start),
				'estimatedDuration' => $task['minutes'],
				'percentComplete'   => (int)$task['percent'],
				'issueType'         => $issueType,
				'metadata'          => (['msProjectUid' => $task['uid']] + $provenance),
			],
			static fn ($value): bool => $value !== null
		);
	}//end taskData()

	/**
	 * The status progress implies.
	 *
	 * @param int $percent Percent complete.
	 *
	 * @return string
	 */
	private function status(int $percent): string {
		if ($percent >= 100) {
			return 'done';
		}

		if ($percent > 0) {
			return 'in_progress';
		}

		return 'open';
	}//end status()

	/**
	 * A task's title; a nameless task is called after its UID.
	 *
	 * @param array<string,mixed> $task The plan task.
	 *
	 * @return string
	 */
	private function title(array $task): string {
		$name = trim((string)$task['name']);
		if ($name === '') {
			return 'Task ' . $task['uid'];
		}

		return $name;
	}//end title()

	/**
	 * What the import creates, per kind.
	 *
	 * @param array<string,list<array<string,mixed>>> $out   The mapped phases, tasks and sub-tasks.
	 * @param list<array<string,string>>              $links The links.
	 *
	 * @return array<string,int>
	 */
	private function counts(array $out, array $links): array {
		$milestones = 0;
		$tasks      = 0;
		$subtasks   = 0;
		foreach (['tasks', 'subtasks'] as $kind) {
			foreach ($out[$kind] as $task) {
				if (($task['data']['issueType'] ?? '') === 'milestone') {
					$milestones++;
					continue;
				}

				$tasks    += (int)($kind === 'tasks');
				$subtasks += (int)($kind === 'subtasks');
			}
		}

		return [
			'phases'     => count($out['phases']),
			'tasks'      => $tasks,
			'subtasks'   => $subtasks,
			'milestones' => $milestones,
			'links'      => count($links),
		];
	}//end counts()
}//end class
