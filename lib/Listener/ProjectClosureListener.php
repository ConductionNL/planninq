<?php

/**
 * Planninq ProjectClosureListener
 *
 * Stamps a project's `closedAt` in the same save that moves its status to
 * `completed` or `archived`, and clears it in the save that reopens it. The
 * project's retention dates from that moment, and its tasks and time entries
 * date from it through their project (retention-on-planninq-records).
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
 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

/**
 * Keeps `closedAt` on a project in step with its status: set when the
 * project closes (completed or archived), kept while it moves between those
 * two, cleared when it reopens. It runs on OpenRegister's pre-save events,
 * so every client that closes a project dates it the same way.
 *
 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
class ProjectClosureListener extends StatusTimestampListener {

	/**
	 * The statuses in which a project is closed.
	 *
	 * @var array<int,string>
	 */
	public const CLOSED_STATUSES = ['completed', 'archived'];

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected function schemaSlug(): string {
		return 'project';
	}//end schemaSlug()

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected function timestampField(): string {
		return 'closedAt';
	}//end timestampField()

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int,string>
	 */
	protected function stampedStatuses(): array {
		return self::CLOSED_STATUSES;
	}//end stampedStatuses()
}//end class
