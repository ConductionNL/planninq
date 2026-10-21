<?php

/**
 * Planninq case handover exception
 *
 * Raised when a project cannot be handed over to its case: the case app is
 * not installed, the project has no case link, or the acting user cannot read
 * the case. The reason code tells the sidebar which explanation to show.
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
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Exception;

/**
 * A refused handover.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
 */
class CaseHandoverException extends \RuntimeException {

	/**
	 * The case app (Dossiq) is not installed.
	 *
	 * @var string
	 */
	public const NO_CASE_APP = 'noCaseApp';

	/**
	 * The project has no case link.
	 *
	 * @var string
	 */
	public const NO_CASE = 'noCase';

	/**
	 * The linked case does not exist or the user cannot read it.
	 *
	 * @var string
	 */
	public const CASE_NOT_FOUND = 'caseNotFound';

	/**
	 * Constructor.
	 *
	 * @param string $reason  One of the reason constants.
	 * @param string $message What happened, for the log.
	 */
	public function __construct(
		private string $reason,
		string $message,
	) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The reason code.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()
}//end class
