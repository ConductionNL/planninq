<?php

/**
 * Planninq project copy exception
 *
 * Raised when copying a project stops part-way; the copy has removed what it wrote.
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
 * A project copy that stopped; everything it wrote has been removed.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string          $step     The step that failed (project, columns, phases, tasks, dependencies).
	 * @param string          $message  What happened.
	 * @param \Throwable|null $previous The cause.
	 */
	public function __construct(
		private string $step,
		string $message,
		?\Throwable $previous = null,
	) {
		parent::__construct(message: $message, previous: $previous);
	}//end __construct()

	/**
	 * The step that failed.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function getStep(): string {
		return $this->step;
	}//end getStep()
}//end class
