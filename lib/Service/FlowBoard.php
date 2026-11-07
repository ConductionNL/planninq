<?php

/**
 * Planninq Flow Board
 *
 * The rows a flow replay reads: a project's columns, its tasks and their audit rows.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * A project's columns and tasks, with the audit rows read for them so far.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */
final class FlowBoard {

	/**
	 * Constructor.
	 *
	 * @param array<int,array<string,mixed>> $columns   The project's columns (id, order)
	 * @param array<int,array<string,mixed>> $tasks     Tasks: id, title, key, created, column, status, completedAt
	 * @param array<string,array<int,array>> $histories Task id to its audit rows, oldest first
	 *
	 * @return void
	 */
	public function __construct(
		public readonly array $columns,
		public readonly array $tasks,
		public readonly array $histories=[],
	) {
	}//end __construct()

	/**
	 * The same board with these audit rows.
	 *
	 * @param array<string,array<int,array>> $histories Task id to its audit rows, oldest first
	 *
	 * @return self
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function withHistories(array $histories): self {
		return new self(columns: $this->columns, tasks: $this->tasks, histories: $histories);
	}//end withHistories()
}//end class
