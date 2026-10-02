<?php

/**
 * Planninq project copy exception
 *
 * Raised when a project copy is refused (policy, rights, input) or fails
 * while writing. The status is the HTTP answer, the reason a stable code for
 * the dialog, and the step the part of the copy that failed.
 *
 * @category Exception
 * @package  OCA\Planninq\Exception
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Exception;

/**
 * A refused or failed project copy.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param int    $status  The HTTP status to answer with.
	 * @param string $reason  A stable code, e.g. planninq-copy-forbidden.
	 * @param string $message What happened, for the answer and the log.
	 * @param string $step    The copy step that failed, or '' for a refusal.
	 */
	public function __construct(
		private int $status,
		private string $reason,
		string $message,
		private string $step = '',
	) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The HTTP status.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function getStatus(): int {
		return $this->status;
	}//end getStatus()

	/**
	 * The reason code.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()

	/**
	 * The step that failed: project, columns, phases, tasks, dependencies or finish.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function getStep(): string {
		return $this->step;
	}//end getStep()
}//end class
