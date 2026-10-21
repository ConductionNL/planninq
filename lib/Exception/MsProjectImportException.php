<?php

/**
 * Planninq Microsoft Project import exception
 *
 * Raised when a file is not a Microsoft Project plan planninq can import, or
 * is not safe to read. The reason code tells the dialog which explanation to
 * show; the message is for the log.
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
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Exception;

/**
 * A refused Microsoft Project file.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
 */
class MsProjectImportException extends \RuntimeException {

	/**
	 * The binary .mpp format: save the plan as XML instead.
	 *
	 * @var string
	 */
	public const MPP = 'mpp';

	/**
	 * A DOCTYPE or entity declaration.
	 *
	 * @var string
	 */
	public const UNSAFE = 'unsafe';

	/**
	 * Larger than 10 MB.
	 *
	 * @var string
	 */
	public const TOO_LARGE = 'tooLarge';

	/**
	 * More than 2,000 tasks.
	 *
	 * @var string
	 */
	public const TOO_MANY_TASKS = 'tooManyTasks';

	/**
	 * Not XML, or XML that is not a Microsoft Project plan.
	 *
	 * @var string
	 */
	public const NOT_A_PLAN = 'notAPlan';

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
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()
}//end class
