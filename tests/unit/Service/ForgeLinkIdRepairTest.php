<?php

/**
 * Tests for the forge link id repair and its command.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Command\RepairForgeLinkIds;
use OCA\Planninq\Service\ForgeLinkIdRepair;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The plan lists only non-canonical ids, never makes two equal links, and the command writes only with --apply.
 *
 * @covers \OCA\Planninq\Service\ForgeLinkIdRepair
 * @covers \OCA\Planninq\Command\RepairForgeLinkIds
 * @uses   \OCA\Planninq\Service\ForgeLinkService
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 */
class ForgeLinkIdRepairTest extends TestCase {
	use MembershipFixture;

	private const TASK = '00000000-0000-4000-8000-000000000012';

	private const OTHER = '00000000-0000-4000-8000-000000000013';

	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$link          = ['project' => 'p', 'url' => 'https://github.com/acme/portal/pull/42', 'source' => 'manual'];
		$this->objects->seed('forgeLink', 'fl-clean', ['task' => self::TASK, 'externalId' => 'github:acme/portal#7'] + $link);
		$this->objects->seed('forgeLink', 'fl-padded', ['task' => self::TASK, 'externalId' => ' github:acme/portal#8 '] + $link);
		$this->objects->seed('forgeLink', 'fl-upper', ['task' => self::OTHER, 'externalId' => 'GitHub:Acme/Portal#9'] + $link);
		$this->objects->seed('forgeLink', 'fl-twin-a', ['task' => self::TASK, 'externalId' => 'github:acme/portal#42'] + $link);
		$this->objects->seed('forgeLink', 'fl-twin-b', ['task' => self::TASK, 'externalId' => 'github:ACME/portal#42'] + $link);
		$this->objects->seed('forgeLink', 'fl-elsewhere', ['task' => self::OTHER, 'externalId' => 'github:ACME/portal#42'] + $link);
		$this->objects->seed('forgeLink', 'fl-none', ['task' => self::TASK, 'kind' => 'link'] + $link);
		$this->objects->seed('forgeLink', 'fl-number', ['task' => self::TASK, 'externalId' => 42] + $link);
	}//end setUp()

	private function repair(): ForgeLinkIdRepair {
		return new ForgeLinkIdRepair(forgeLinks: $this->forgeLinkService(), container: $this->container(), logger: new NullLogger());
	}//end repair()

	public function testThePlanListsOnlyIdsOutOfCanonicalForm(): void {
		$plan = array_map(
			static fn (array $step): array => [$step['id'], $step['task'], $step['from'], $step['to'], $step['collides']],
			$this->repair()->plan()
		);

		self::assertSame(
			[
				['fl-padded', self::TASK, ' github:acme/portal#8 ', 'github:acme/portal#8', false],
				['fl-upper', self::OTHER, 'GitHub:Acme/Portal#9', 'github:acme/portal#9', false],
				['fl-twin-b', self::TASK, 'github:ACME/portal#42', 'github:acme/portal#42', true],
				['fl-elsewhere', self::OTHER, 'github:ACME/portal#42', 'github:acme/portal#42', false],
			],
			$plan
		);
		self::assertSame([], $this->objects->saves, 'planning writes nothing');
		self::assertFalse($this->objects->searches[0]['_rbac']);
	}//end testThePlanListsOnlyIdsOutOfCanonicalForm()

	public function testTwoVariantsOnOneTaskBothCollide(): void {
		$this->objects->seed('forgeLink', 'fl-twin-a', ['task' => self::TASK, 'externalId' => 'GITHUB:acme/portal#42']);

		$collides = [];
		foreach ($this->repair()->plan() as $step) {
			$collides[$step['id']] = $step['collides'];
		}

		self::assertTrue($collides['fl-twin-a']);
		self::assertTrue($collides['fl-twin-b']);
		self::assertFalse($collides['fl-elsewhere']);
	}//end testTwoVariantsOnOneTaskBothCollide()

	public function testApplyRewritesOnlyTheIdAsTheSystem(): void {
		$repair = $this->repair();
		$steps  = [];
		foreach ($repair->plan() as $step) {
			$steps[$step['id']] = $step;
		}

		self::assertTrue($repair->apply(step: $steps['fl-padded']));
		self::assertFalse($repair->apply(step: $steps['fl-twin-b']));

		self::assertCount(1, $this->objects->saves);
		$save = $this->objects->saves[0];
		self::assertSame('fl-padded', $save['uuid']);
		self::assertSame(['externalId' => 'github:acme/portal#8'] + $steps['fl-padded']['data'], $save['object']);
		self::assertSame('forgeLink', $save['schema']);
		self::assertFalse($save['_rbac']);
		self::assertFalse($save['_multitenancy']);
		self::assertTrue($save['silent']);
		self::assertTrue($save['_validation']);
	}//end testApplyRewritesOnlyTheIdAsTheSystem()

	public function testARefusedRewriteIsReportedNotThrown(): void {
		$this->objects->failingSaves = ['fl-upper'];
		$repair = $this->repair();
		foreach ($repair->plan() as $step) {
			if ($step['id'] === 'fl-upper') {
				self::assertFalse($repair->apply(step: $step));
			}
		}
	}//end testARefusedRewriteIsReportedNotThrown()

	public function testTheCommandIsADryRunWithoutApply(): void {
		$tester = new CommandTester(new RepairForgeLinkIds(repair: $this->repair()));

		self::assertSame(0, $tester->execute([]));
		self::assertSame([], $this->objects->saves);
		$display = $tester->getDisplay();
		self::assertStringContainsString(self::TASK . ' fl-padded: " github:acme/portal#8 " -> "github:acme/portal#8" (would rewrite)', $display);
		self::assertStringContainsString('fl-twin-b: "github:ACME/portal#42" -> "github:acme/portal#42" (collides)', $display);
		self::assertStringContainsString('Would rewrite 3, rewrote 0 forge id(s); 1 collide(s) with a link already on the task and are left; 0 failed.', $display);
	}//end testTheCommandIsADryRunWithoutApply()

	public function testTheCommandWritesWithApplyAndFailsOnARefusal(): void {
		$this->objects->failingSaves = ['fl-upper'];
		$tester = new CommandTester(new RepairForgeLinkIds(repair: $this->repair()));

		self::assertSame(1, $tester->execute(['--apply' => true]));
		self::assertSame(['fl-padded', 'fl-upper', 'fl-elsewhere'], array_column($this->objects->saves, 'uuid'));
		$display = $tester->getDisplay();
		self::assertStringContainsString('fl-upper: "GitHub:Acme/Portal#9" -> "github:acme/portal#9" (failed)', $display);
		self::assertStringContainsString('Would rewrite 0, rewrote 2 forge id(s); 1 collide(s) with a link already on the task and are left; 1 failed.', $display);
	}//end testTheCommandWritesWithApplyAndFailsOnARefusal()
}//end class
