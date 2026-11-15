<?php

/**
 * Planninq project export exception
 *
 * Raised when a project export cannot be produced: the project does not
 * resolve for the requester, or OpenRegister's export gate refuses the
 * project or task schema. The reason code tells the job which failure
 * notification to send.
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
 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Exception;

/**
 * A project export that cannot be produced.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
 */
class ProjectExportException extends \RuntimeException {

	/**
	 * OpenRegister's export gate refused the project or task schema.
	 */
	public const REFUSED = 'refused';

	/**
	 * The project does not exist or the requester cannot read it.
	 */
	public const NOT_FOUND = 'not-found';

	/**
	 * OpenRegister is not installed.
	 */
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Constructor
	 *
	 * @param string $reason  One of the reason constants.
	 * @param string $message The message for the log.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
	 */
	public function __construct(private readonly string $reason, string $message) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The reason code.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()
}//end class
