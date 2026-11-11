<?php

/**
 * Planninq TaskReferenceRun
 *
 * The task save one TaskReferenceGuardListener check works on: the task object
 * and its data, the stored data, the task's own UUID and, once read, its project.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/tasks/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

/**
 * One task save under check.
 *
 * @spec openspec/specs/tasks/spec.md
 */
final class TaskReferenceRun {

	/**
	 * The task being written.
	 *
	 * @var array<string,mixed>
	 */
	public readonly array $data;

	/**
	 * The task's own UUID, '' on a create.
	 *
	 * @var string
	 */
	public readonly string $ownId;

	/**
	 * The task's project UUID, set once the project is read.
	 *
	 * @var string
	 */
	public string $projectId = '';

	/**
	 * The task's project, set once it is read.
	 *
	 * @var array<string,mixed>
	 */
	public array $project = [];

	/**
	 * Constructor.
	 *
	 * @param object              $task   The task object being written.
	 * @param array<string,mixed> $stored The stored task, empty on a create.
	 */
	public function __construct(public readonly object $task, public readonly array $stored) {
		$this->data  = (array)$task->getObject();
		$this->ownId = (string)($task->getUuid() ?? '');
	}//end __construct()
}//end class
