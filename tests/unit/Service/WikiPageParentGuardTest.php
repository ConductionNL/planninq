<?php

/**
 * Tests for WikiPageParentGuard: the parent rule of a wiki page.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\WikiPageParentGuard;
use PHPUnit\Framework\TestCase;

/**
 * The guard walks the stored parent chain and refuses cycles, other projects and unknown parents.
 *
 * @covers \OCA\Planninq\Service\WikiPageParentGuard
 */
class WikiPageParentGuardTest extends TestCase {

	/**
	 * A guard over stored pages, recording which pages it read.
	 *
	 * @param array<string,array<string,mixed>> $pages Stored pages by UUID.
	 * @param array<int,string>                 $reads Collects the UUIDs read.
	 *
	 * @return WikiPageParentGuard
	 */
	private function guard(array $pages, array &$reads=[]): WikiPageParentGuard {
		$membership = $this->createMock(ProjectMembershipService::class);
		$membership->method('objectData')->willReturnCallback(
			static function (string $schema, string $id) use ($pages, &$reads): ?array {
				self::assertSame('wikiPage', $schema);
				$reads[] = $id;
				return ($pages[$id] ?? null);
			}
		);

		return new WikiPageParentGuard(membership: $membership);
	}//end guard()

	/**
	 * A top-level page is never checked, and nothing is read for it.
	 *
	 * @return void
	 */
	public function testATopLevelPageReadsNothing(): void {
		$reads = [];
		self::assertNull($this->guard([], $reads)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: ''));
		self::assertSame([], $reads);
	}//end testATopLevelPageReadsNothing()

	/**
	 * A page under itself is refused without reading the store.
	 *
	 * @return void
	 */
	public function testAPageUnderItselfIsACycle(): void {
		$reads = [];
		$error = $this->guard([], $reads)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'a');
		self::assertSame(WikiPageParentGuard::ERROR_CYCLE, $error['code']);
		self::assertSame([], $reads);
	}//end testAPageUnderItselfIsACycle()

	/**
	 * A new page (no UUID yet) may sit under any page of its project, however deep.
	 *
	 * @return void
	 */
	public function testANewPageOnlyNeedsAParentOfItsProject(): void {
		$pages = ['b' => ['project' => 'p', 'parent' => 'a'], 'a' => ['project' => 'p']];
		$reads = [];
		self::assertNull($this->guard($pages, $reads)->refusal(page: ['id' => '', 'project' => 'p'], parentId: 'b'));
		self::assertSame(['b'], $reads, 'a new page cannot be anyone\'s ancestor, so the chain is not walked');
	}//end testANewPageOnlyNeedsAParentOfItsProject()

	/**
	 * The project is read from a plain or an expanded reference.
	 *
	 * @return void
	 */
	public function testTheParentsProjectMayBeExpanded(): void {
		$pages = ['b' => ['project' => ['id' => 'p']], 'c' => ['project' => ['@self' => ['id' => 'q']]]];
		self::assertNull($this->guard($pages)->refusal(page: ['id' => 'x', 'project' => 'p'], parentId: 'b'));
		self::assertSame(
			WikiPageParentGuard::ERROR_PROJECT,
			$this->guard($pages)->refusal(page: ['id' => 'x', 'project' => 'p'], parentId: 'c')['code']
		);
	}//end testTheParentsProjectMayBeExpanded()

	/**
	 * A page under its grandchild is a cycle; under an unrelated deep page it is not.
	 *
	 * @return void
	 */
	public function testTheWholeChainIsWalked(): void {
		$pages = [
			'child'      => ['project' => 'p', 'parent' => 'a'],
			'grandchild' => ['project' => 'p', 'parent' => 'child'],
			'other'      => ['project' => 'p', 'parent' => 'root'],
			'root'       => ['project' => 'p'],
		];
		self::assertSame(
			WikiPageParentGuard::ERROR_CYCLE,
			$this->guard($pages)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'grandchild')['code']
		);
		self::assertNull($this->guard($pages)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'other'));
	}//end testTheWholeChainIsWalked()

	/**
	 * A chain that is already broken into a loop, or that has a missing link, ends the walk.
	 *
	 * @return void
	 */
	public function testABrokenStoredChainEndsTheWalk(): void {
		$loop = ['x' => ['project' => 'p', 'parent' => 'y'], 'y' => ['project' => 'p', 'parent' => 'x']];
		self::assertSame(WikiPageParentGuard::ERROR_CYCLE, $this->guard($loop)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'x')['code']);

		$gap = ['x' => ['project' => 'p', 'parent' => 'gone']];
		self::assertNull($this->guard($gap)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'x'));
	}//end testABrokenStoredChainEndsTheWalk()

	/**
	 * A chain longer than the limit is refused rather than walked forever.
	 *
	 * @return void
	 */
	public function testAnOverlongChainIsRefused(): void {
		$pages = [];
		for ($i = 0; $i <= WikiPageParentGuard::MAX_DEPTH + 1; $i++) {
			$pages['p' . $i] = ['project' => 'p', 'parent' => 'p' . ($i + 1)];
		}

		$reads = [];
		self::assertSame(
			WikiPageParentGuard::ERROR_CYCLE,
			$this->guard($pages, $reads)->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'p0')['code']
		);
		self::assertCount(WikiPageParentGuard::MAX_DEPTH + 1, $reads);
	}//end testAnOverlongChainIsRefused()

	/**
	 * An unknown parent is named as unknown.
	 *
	 * @return void
	 */
	public function testAnUnknownParentIsRefused(): void {
		self::assertSame(
			['code' => WikiPageParentGuard::ERROR_UNKNOWN, 'message' => 'The parent page does not exist.'],
			$this->guard([])->refusal(page: ['id' => 'a', 'project' => 'p'], parentId: 'gone')
		);
	}//end testAnUnknownParentIsRefused()

}//end class
