<?php

/**
 * Planninq Scenario Draft Run
 *
 * The state of writing one scenario's week pattern out as draft lessons:
 * the scenario, its lessons by key, the grid's period times and the week
 * being written.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;

/**
 * One run of TimetableScenarioPublisher::rows(); it never leaves the publisher.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-8.1
 */
final class ScenarioDraftRun {

	/**
	 * The scenario's lessons by key, set when the run starts.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public array $lessons = [];

	/**
	 * The grid's period times by key, set when the run starts.
	 *
	 * @var array<string,array{day:string,end:string,key:string,number:int,start:string}>
	 */
	public array $times = [];

	/**
	 * The Monday of the week being written.
	 *
	 * @var DateTimeImmutable|null
	 */
	public ?DateTimeImmutable $monday = null;

	/**
	 * Constructor.
	 *
	 * @param string              $id       The scenario id.
	 * @param array<string,mixed> $scenario The scenario.
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $scenario,
	) {
	}//end __construct()
}//end class
