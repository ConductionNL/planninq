<?php

/**
 * Planninq Board View Preference Service
 *
 * A person's card colour and swimlane choice, per project board, so a board
 * opens the way that person left it and nobody else's view changes.
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

use OCA\Planninq\AppInfo\Application;
use OCP\IConfig;

/**
 * Stores each person's board view (card colour and swimlanes) per project.
 *
 * One user value holds a map of project id to {colour, group}; an unknown
 * colour or grouping is stored as `none`, so a stored view is always valid.
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
 */
class BoardViewPreferenceService {

	/**
	 * The user value holding the map of project id to view.
	 *
	 * @var string
	 */
	public const KEY = 'board_views';

	/**
	 * The most project views one person keeps; the least recently saved go first.
	 *
	 * @var int
	 */
	public const MAX = 100;

	/**
	 * Card colour modes.
	 *
	 * @var array<int,string>
	 */
	public const COLOURS = ['none', 'label', 'priority'];

	/**
	 * Swimlane fields.
	 *
	 * @var array<int,string>
	 */
	public const GROUPS = ['none', 'assignee', 'priority', 'epic'];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Per-user values.
	 */
	public function __construct(
		private IConfig $config,
	) {
	}//end __construct()

	/**
	 * A person's board views, by project id.
	 *
	 * @param string $userId The user UID.
	 *
	 * @return array<string,array{colour:string,group:string}>
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function views(string $userId): array {
		$raw     = $this->config->getUserValue($userId, Application::APP_ID, self::KEY, '{}');
		$decoded = json_decode((string) $raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		$views = [];
		foreach ($decoded as $projectId => $view) {
			if (is_string($projectId) === true && $projectId !== '' && is_array($view) === true) {
				$views[$projectId] = $this->normalise(view: $view);
			}
		}

		return $views;
	}//end views()

	/**
	 * Store a person's view of one project board.
	 *
	 * @param string             $userId    The user UID.
	 * @param string             $projectId The project id.
	 * @param array<mixed,mixed> $view      The choice: colour and group.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function save(string $userId, string $projectId, array $view): void {
		if ($projectId === '') {
			return;
		}

		$views = $this->views(userId: $userId);
		unset($views[$projectId]);
		$views[$projectId] = $this->normalise(view: $view);
		$views = array_slice($views, -self::MAX, null, true);

		$this->config->setUserValue($userId, Application::APP_ID, self::KEY, (string) json_encode($views));
	}//end save()

	/**
	 * A valid view, whatever was given.
	 *
	 * @param array<mixed,mixed> $view The view as given.
	 *
	 * @return array{colour:string,group:string}
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	private function normalise(array $view): array {
		$colour = ($view['colour'] ?? 'none');
		$group  = ($view['group'] ?? 'none');
		if (in_array($colour, self::COLOURS, true) === false) {
			$colour = 'none';
		}

		if (in_array($group, self::GROUPS, true) === false) {
			$group = 'none';
		}

		return ['colour' => $colour, 'group' => $group];
	}//end normalise()
}//end class
