<?php

/**
 * Planninq Microsoft Project outline walk
 *
 * Walks a parsed plan's tasks in outline order and says where each one lands:
 * a phase, a task under the current phase, or a sub-task of the current
 * level-2 task, with the outline path of a task that collapses from a deeper
 * level. One walk per mapping.
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
 * The outline position of each task of one plan.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
 */
class MsProjectOutlineWalk {

	/**
	 * The UID of the current phase.
	 *
	 * @var string|null
	 */
	private ?string $phaseUid = null;

	/**
	 * The UID of the current level-1 or level-2 task.
	 *
	 * @var string|null
	 */
	private ?string $level2Uid = null;

	/**
	 * The task names on the way to the current task, by outline level.
	 *
	 * @var array<int,string>
	 */
	private array $path = [];

	/**
	 * Where the next task in outline order lands.
	 *
	 * @param array<string,mixed> $task The plan task.
	 *
	 * @return array{kind:string,phaseUid:string|null,parentUid:string|null,path:string|null}
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
	 */
	public function place(array $task): array {
		$level        = (int)$task['level'];
		$this->path   = array_slice($this->path, 0, ($level - 1), true);
		$this->path[$level] = $task['name'];

		if ($level === 1 && $task['summary'] === true) {
			$this->phaseUid = $task['uid'];
			return ['kind' => 'phases', 'phaseUid' => null, 'parentUid' => null, 'path' => null];
		}

		if ($level <= 2) {
			return $this->topTask(task: $task, level: $level);
		}

		return ['kind' => 'subtasks', 'phaseUid' => $this->phaseUid, 'parentUid' => $this->level2Uid, 'path' => $this->collapsedPath(level: $level)];
	}//end place()

	/**
	 * A level-1 or level-2 task; a level-1 task ends the current phase.
	 *
	 * @param array<string,mixed> $task  The plan task.
	 * @param int                 $level Its outline level.
	 *
	 * @return array{kind:string,phaseUid:string|null,parentUid:string|null,path:string|null}
	 */
	private function topTask(array $task, int $level): array {
		if ($level === 1) {
			$this->phaseUid = null;
		}

		$this->level2Uid = $task['uid'];

		return ['kind' => 'tasks', 'phaseUid' => $this->phaseUid, 'parentUid' => null, 'path' => null];
	}//end topTask()

	/**
	 * The outline path of a task deeper than level 3, which collapses into a sub-task.
	 *
	 * @param int $level The task's outline level.
	 *
	 * @return string|null Null for a level-3 task.
	 */
	private function collapsedPath(int $level): ?string {
		if ($level <= 3) {
			return null;
		}

		return implode(' > ', array_slice($this->path, 1, null));
	}//end collapsedPath()
}//end class
