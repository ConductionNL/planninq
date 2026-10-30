<?php

/**
 * Planninq project action exception
 *
 * Raised when a server-side project action (delete a project, ship a release,
 * delete or copy a task tree) is refused. It carries the HTTP status and a
 * code the client branches on.
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
 * @spec openspec/specs/projects/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Exception;

/**
 * A refused project action.
 *
 * @spec openspec/specs/projects/spec.md
 */
class ProjectActionException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $reason  The error code a client branches on.
	 * @param int    $status  The HTTP status to answer with.
	 * @param string $message What happened, shown to the person.
	 */
	public function __construct(
		private string $reason,
		private int $status,
		string $message,
	) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The error code.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()

	/**
	 * The HTTP status.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function getStatus(): int {
		return $this->status;
	}//end getStatus()
}//end class
