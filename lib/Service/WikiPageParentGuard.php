<?php

/**
 * Planninq WikiPageParentGuard
 *
 * The rule a wiki page's parent must meet that OpenRegister cannot express:
 * the parent is a page of the same project, and it is neither the page itself
 * nor one of its own subpages. The parent chain is walked as the system, so
 * the answer does not depend on which pages the caller can see; the refusal
 * names no page the caller did not name.
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
 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Parent checks for wiki pages.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
 */
class WikiPageParentGuard {

	/**
	 * The schema slug of a wiki page.
	 *
	 * @var string
	 */
	public const SCHEMA = 'wikiPage';

	/**
	 * Error code: the page would sit under itself or one of its own subpages.
	 *
	 * @var string
	 */
	public const ERROR_CYCLE = 'planninq-wiki-page-cycle';

	/**
	 * Error code: the parent is not a page of this page's project.
	 *
	 * @var string
	 */
	public const ERROR_PROJECT = 'planninq-wiki-page-other-project';

	/**
	 * Error code: the parent page does not exist.
	 *
	 * @var string
	 */
	public const ERROR_UNKNOWN = 'planninq-wiki-page-parent-unknown';

	/**
	 * How many ancestors are followed before the chain counts as a cycle.
	 *
	 * A stored chain this long is already broken; refusing keeps the walk finite.
	 *
	 * @var int
	 */
	public const MAX_DEPTH = 100;

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads stored pages as the system.
	 *
	 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
	 */
	public function __construct(
		private ProjectMembershipService $membership,
	) {
	}//end __construct()

	/**
	 * Why a page may not take this parent, or null when it may.
	 *
	 * @param string $pageId    The page's UUID; empty while it is being created.
	 * @param string $projectId The project the page belongs to.
	 * @param string $parentId  The parent page asked for; empty for a top-level page.
	 *
	 * @return array{code: string, message: string}|null The refusal, or null.
	 *
	 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
	 */
	public function refusal(string $pageId, string $projectId, string $parentId): ?array {
		if ($parentId === '') {
			return null;
		}

		if ($pageId !== '' && $parentId === $pageId) {
			return self::cycle();
		}

		$parent = $this->membership->objectData(schema: self::SCHEMA, id: $parentId);
		if ($parent === null) {
			return ['code' => self::ERROR_UNKNOWN, 'message' => 'The parent page does not exist.'];
		}

		if (self::referenceId(value: ($parent['project'] ?? null)) !== $projectId) {
			return ['code' => self::ERROR_PROJECT, 'message' => 'A page can only sit under a page of the same project.'];
		}

		if ($pageId !== '' && $this->hasAncestor(page: $parent, ancestorId: $pageId) === true) {
			return self::cycle();
		}

		return null;
	}//end refusal()

	/**
	 * Whether the page, or any page above it, is the given ancestor.
	 *
	 * @param array<string,mixed> $page       The page whose chain is walked.
	 * @param string              $ancestorId The UUID looked for.
	 *
	 * @return bool True when the ancestor is in the chain, or the chain is too long to trust.
	 */
	private function hasAncestor(array $page, string $ancestorId): bool {
		$seen = [];
		for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
			$parentId = self::referenceId(value: ($page['parent'] ?? null));
			if ($parentId === '') {
				return false;
			}

			if ($parentId === $ancestorId || isset($seen[$parentId]) === true) {
				return true;
			}

			$seen[$parentId] = true;
			$next = $this->membership->objectData(schema: self::SCHEMA, id: $parentId);
			if ($next === null) {
				return false;
			}

			$page = $next;
		}

		return true;
	}//end hasAncestor()

	/**
	 * The cycle refusal.
	 *
	 * @return array{code: string, message: string}
	 */
	private static function cycle(): array {
		return ['code' => self::ERROR_CYCLE, 'message' => 'A page cannot sit under itself or one of its own subpages.'];
	}//end cycle()

	/**
	 * A reference's UUID, whether stored as a string or an expanded object.
	 *
	 * @param mixed $value The stored reference.
	 *
	 * @return string The UUID, or '' when there is none.
	 *
	 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
	 */
	public static function referenceId(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['@self']['id'] ?? ''));
		}

		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end referenceId()

}//end class
