<?php

/**
 * Planninq TaskCompletionListener
 *
 * Stamps a task's `completedAt` in the same save that moves its status to
 * `done`, and clears it in the save that moves the status away from `done`.
 * It runs on OpenRegister's pre-save events, so the finish time is right
 * whichever client changed the status: the board, the API, a flow or an
 * import. Flow reports and the dashboard's "completed today" read it.
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
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

/**
 * Keeps `completedAt` in step with a task's `done` status.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
 */
class TaskCompletionListener extends StatusTimestampListener {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
	 */
	protected function schemaSlug(): string {
		return 'task';
	}//end schemaSlug()

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
	 */
	protected function timestampField(): string {
		return 'completedAt';
	}//end timestampField()

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
	 */
	protected function stampedStatuses(): array {
		return ['done'];
	}//end stampedStatuses()
}//end class
