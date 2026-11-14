<?php

/**
 * Planninq ProjectCopyRun
 *
 * The state of one project copy: what to copy, how far dates move, the map
 * from source ids to copies, and what was written for the clean-up.
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Mutable state of one copy, owned by ProjectCopyService.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
final class ProjectCopyRun {

	/**
	 * The new project's id, once created.
	 *
	 * @var string
	 */
	public string $projectId = '';

	/**
	 * Source id => copy id, for every copied column, phase and task.
	 *
	 * @var array<string,string>
	 */
	public array $idMap = [];

	/**
	 * Every object written, as [schema, id], in order.
	 *
	 * @var list<array{0:string,1:string}>
	 */
	public array $created = [];

	/**
	 * Which parts to copy.
	 *
	 * @var array<string,bool>
	 */
	public readonly array $parts;

	/**
	 * Days to move dates by; null copies no dates.
	 *
	 * @var int|null
	 */
	public readonly ?int $shiftDays;

	/**
	 * The new project's title, key ('' for none) and owner.
	 *
	 * @var array{title:string,key:string,owner:string}
	 */
	public readonly array $identity;

	/**
	 * Constructor.
	 *
	 * @param object               $objectService OpenRegister's ObjectService.
	 * @param string               $sourceId      The project copied.
	 * @param array<string, mixed> $plan          `parts` (which parts to copy), `shiftDays` (days to move
	 *                                            dates by; null copies no dates) and `identity` (title, key, owner).
	 */
	public function __construct(
		public readonly object $objectService,
		public readonly string $sourceId,
		array $plan,
	) {
		$this->parts     = (array)($plan['parts'] ?? []);
		$this->shiftDays = $plan['shiftDays'] ?? null;
		$this->identity  = (array)($plan['identity'] ?? []) + ['title' => '', 'key' => '', 'owner' => ''];
	}//end __construct()

	/**
	 * Dates moved by the copy's shift, as Y-m-d; every field null when dates are not copied.
	 *
	 * @param array<string, \DateTimeImmutable> $dates The source row's valid dates.
	 *
	 * @return array<string, string|null>
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function shifted(array $dates): array {
		$shifted = [];
		foreach ($dates as $field => $day) {
			$shifted[$field] = null;
			if ($this->shiftDays !== null) {
				$shifted[$field] = $day->modify(sprintf('%+d days', $this->shiftDays))->format('Y-m-d');
			}
		}

		return $shifted;
	}//end shifted()
}//end class
