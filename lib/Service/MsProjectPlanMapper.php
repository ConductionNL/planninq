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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
		$out   = $this->withProvenance(out: $this->outline(tasks: $plan['tasks']), plan: $plan);
		$links = $this->links(tasks: $plan['tasks'], kindByUid: $this->kinds(tasks: $plan['tasks']));
		$losses = array_merge(array_fill_keys(self::LOSS_KEYS, 0), $this->taskLosses(tasks: $plan['tasks']), $links['losses']);

		return [
			'phases'   => $out['phases'],
			'tasks'    => $out['tasks'],
			'subtasks' => $out['subtasks'],
			'links'    => $links['links'],
			'losses'   => array_filter($losses),
			'counts'   => $this->counts(out: $out, links: $links['links']),
		];
	}//end map()

	/**
	 * The phases, tasks and sub-tasks in outline order, without provenance.
	 *
	 * @param list<array<string,mixed>> $tasks The plan's tasks.
	 *
	 * @return array<string,list<array<string,mixed>>>
	 */
	private function outline(array $tasks): array {
		$out  = ['phases' => [], 'tasks' => [], 'subtasks' => []];
		$walk = new MsProjectOutlineWalk();
		foreach ($tasks as $task) {
			$place = ($walk->place(task: $task) + ['order' => count($out['phases'])]);
			$out[$place['kind']][] = $this->entry(task: $task, place: $place);
		}

		return $out;
	}//end outline()

	/**
	 * One mapped phase, or one mapped task with its references by Project UID.
	 *
	 * @param array<string,mixed> $task  The plan task.
	 * @param array<string,mixed> $place Its kind, phaseUid, parentUid, path and the phase order.
	 *
	 * @return array<string,mixed>
	 */
	private function entry(array $task, array $place): array {
		if ($place['kind'] === 'phases') {
			return ['uid' => $task['uid'], 'data' => $this->phaseData(task: $task, order: $place['order'])];
		}

		return [
			'uid'       => $task['uid'],
			'phaseUid'  => $place['phaseUid'],
			'parentUid' => $place['parentUid'],
			'data'      => $this->taskData(task: $task, path: $place['path']),
		];
	}//end entry()

	/**
	 * Every entry with the plan's name and version in its metadata.
	 *
	 * @param array<string,list<array<string,mixed>>>                               $out  The mapped phases, tasks and sub-tasks.
	 * @param array{name:string,saveVersion:string,tasks:list<array<string,mixed>>} $plan The plan.
	 *
	 * @return array<string,list<array<string,mixed>>>
	 */
	private function withProvenance(array $out, array $plan): array {
		$provenance = ['msProjectFile' => $plan['name'], 'msProjectSaveVersion' => $plan['saveVersion']];
		foreach ($out as $kind => $entries) {
			foreach ($entries as $index => $entry) {
				$out[$kind][$index]['data']['metadata'] = (['msProjectUid' => $entry['uid']] + $provenance);
			}
		}

		return $out;
	}//end withProvenance()

	/**
	 * 'phase' or 'task' per UID; a later task with the same UID wins.
	 *
	 * @param list<array<string,mixed>> $tasks The plan's tasks.
	 *
	 * @return array<string,string>
	 */
	private function kinds(array $tasks): array {
		$kindByUid = [];
		foreach ($tasks as $task) {
			$kindByUid[$task['uid']] = 'task';
			if ((int)$task['level'] === 1 && $task['summary'] === true) {
				$kindByUid[$task['uid']] = 'phase';
			}
		}

		return $kindByUid;
	}//end kinds()

	/**
	 * The tasks with a resource and the tasks that collapse from below level 3.
	 *
	 * @param list<array<string,mixed>> $tasks The plan's tasks.
	 *
	 * @return array{resources:int,collapsedLevels:int}
	 */
	private function taskLosses(array $tasks): array {
		$losses = ['resources' => 0, 'collapsedLevels' => 0];
		foreach ($tasks as $task) {
			$losses['resources']       += (int)($task['hasResource'] === true);
			$losses['collapsedLevels'] += (int)((int)$task['level'] > 3);
		}

		return $losses;
	}//end taskLosses()

	/**
	 * The dependency links between tasks, counting the ones that change or drop.
	 *
	 * @param list<array<string,mixed>> $tasks     The plan's tasks.
	 * @param array<string,string>      $kindByUid 'phase' or 'task' per UID.
	 *
	 * @return array{links:list<array<string,string>>,losses:array<string,int>}
	 */
	private function links(array $tasks, array $kindByUid): array {
		$links  = [];
		$losses = ['relatedLinks' => 0, 'lags' => 0, 'phaseLinks' => 0, 'unknownLinks' => 0];
		foreach ($this->edges(tasks: $tasks) as $edge) {
			$dropped = $this->dropReason(edge: $edge, kindByUid: $kindByUid);
			if ($dropped !== null) {
				$losses[$dropped]++;
				continue;
			}

			$losses['relatedLinks'] += (int)((int)$edge['type'] !== self::FINISH_TO_START);
			$losses['lags']         += (int)((int)$edge['lag'] !== 0);
			$links[(string)$edge['uid'] . '>' . $edge['blockedUid']] = $this->linkRow(edge: $edge);
		}

		return ['links' => array_values($links), 'losses' => $losses];
	}//end links()

	/**
	 * Every predecessor link with the UID of the task it blocks.
	 *
	 * @param list<array<string,mixed>> $tasks The plan's tasks.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function edges(array $tasks): array {
		$edges = [];
		foreach ($tasks as $task) {
			foreach ($task['predecessors'] as $link) {
				$edges[] = ($link + ['blockedUid' => $task['uid']]);
			}
		}

		return $edges;
	}//end edges()

	/**
	 * Why a link does not carry over: its blocker is unknown, or either end is a phase.
	 *
	 * @param array<string,mixed>  $edge      The link with the blocked task's UID.
	 * @param array<string,string> $kindByUid 'phase' or 'task' per UID.
	 *
	 * @return string|null The loss key, or null when the link carries over.
	 */
	private function dropReason(array $edge, array $kindByUid): ?string {
		$blocker = (string)$edge['uid'];
		if (isset($kindByUid[$blocker]) === false) {
			return 'unknownLinks';
		}

		if ($kindByUid[$blocker] === 'phase' || $kindByUid[$edge['blockedUid']] === 'phase') {
			return 'phaseLinks';
		}

		return null;
	}//end dropReason()

	/**
	 * A carried-over link: finish-to-start blocks, every other type relates.
	 *
	 * @param array<string,mixed> $edge The link with the blocked task's UID.
	 *
	 * @return array<string,string>
	 */
	private function linkRow(array $edge): array {
		$type = 'blocks';
		if ((int)$edge['type'] !== self::FINISH_TO_START) {
			$type = 'relates';
		}

		return ['blockerUid' => (string)$edge['uid'], 'blockedUid' => (string)$edge['blockedUid'], 'type' => $type];
	}//end linkRow()

	/**
	 * A phase payload.
	 *
	 * @param array<string,mixed> $task  The summary task.
	 * @param int                 $order The phase's position.
	 *
	 * @return array<string,mixed>
	 */
	private function phaseData(array $task, int $order): array {
		return array_filter(
			[
				'title'     => $this->title(task: $task),
				'startDate' => $task['start'],
				'endDate'   => $task['finish'],
				'order'     => $order,
			],
			static fn ($value): bool => $value !== null
		);
	}//end phaseData()

	/**
	 * A task payload.
	 *
	 * @param array<string,mixed> $task The plan task.
	 * @param string|null         $path The outline path of a collapsed task.
	 *
	 * @return array<string,mixed>
	 */
	private function taskData(array $task, ?string $path): array {
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
