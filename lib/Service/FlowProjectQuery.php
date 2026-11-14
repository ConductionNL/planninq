<?php

/**
 * Planninq FlowProjectQuery.
 *
 * Which project's flow to read, over which days.
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
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;

/**
 * One read of a project's flow.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */
final class FlowProjectQuery {
	/**
	 * Constructor.
	 *
	 * @param object            $objectService OpenRegister's ObjectService
	 * @param string            $projectId     The project's UUID (already read with RBAC on)
	 * @param DateTimeImmutable $from          First day
	 * @param DateTimeImmutable $to            Last day
	 */
	public function __construct(
		public readonly object $objectService,
		public readonly string $projectId,
		public readonly DateTimeImmutable $from,
		public readonly DateTimeImmutable $to,
	) {
	}//end __construct()
}//end class
