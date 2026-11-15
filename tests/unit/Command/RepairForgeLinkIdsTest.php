<?php

/**
 * Tests for the planninq:forge-links:repair-ids command.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Command
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

namespace OCA\Planninq\Tests\Unit\Command;

use OCA\Planninq\Command\RepairForgeLinkIds;
use OCA\Planninq\Service\ForgeLinkIdRepair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command reports every planned rewrite, writes only with --apply and exits 1 on a refusal.
 *
 * @spec openspec/specs/code-forge-links/spec.md#requirement-forge-activity-that-names-a-task-key-is-linked-through-integriq
 */
#[CoversClass(RepairForgeLinkIds::class)]
final class RepairForgeLinkIdsTest extends TestCase {

	/**
	 * The repair double.
	 *
	 * @var ForgeLinkIdRepair&MockObject
	 */
	private ForgeLinkIdRepair $repair;

	/**
	 * Build the repair double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->repair = $this->createMock(ForgeLinkIdRepair::class);
	}//end setUp()

	/**
	 * One plan entry.
	 *
	 * @param string $id       Link id.
	 * @param string $from     Stored forge id.
	 * @param string $to       Canonical forge id.
	 * @param bool   $collides Whether it collides.
	 *
	 * @return array{id:string,task:string,from:string,to:string,collides:bool,data:array<string,mixed>}
	 */
	private function step(string $id, string $from, string $to, bool $collides = false): array {
		return ['id' => $id, 'task' => 'task-1', 'from' => $from, 'to' => $to, 'collides' => $collides, 'data' => ['externalId' => $from]];
	}//end step()

	/**
	 * The command's name, description and --apply flag.
	 *
	 * @return void
	 */
	public function testItIsRegisteredWithAnApplyFlag(): void {
		$command = new RepairForgeLinkIds(repair: $this->repair);

		$this->assertSame('planninq:forge-links:repair-ids', $command->getName());
		$this->assertSame(
			'List stored forge ids that are padded or in another case; rewrite them with --apply.',
			$command->getDescription()
		);
		$option = $command->getDefinition()->getOption('apply');
		$this->assertFalse($option->acceptValue());
		$this->assertSame('Rewrite the listed forge ids. Without it nothing is written.', $option->getDescription());
	}//end testItIsRegisteredWithAnApplyFlag()

	/**
	 * Without --apply nothing is written and every non-colliding entry is "would rewrite".
	 *
	 * @return void
	 */
	public function testADryRunListsThePlanAndWritesNothing(): void {
		$this->repair->expects($this->once())->method('plan')->willReturn([
			$this->step('fl-1', ' gh:a#1 ', 'gh:a#1'),
			$this->step('fl-2', 'GH:A#2', 'gh:a#2', true),
		]);
		$this->repair->expects($this->never())->method('apply');
		$tester = new CommandTester(new RepairForgeLinkIds(repair: $this->repair));

		$this->assertSame(0, $tester->execute([]));
		$this->assertSame(
			'task-1 fl-1: " gh:a#1 " -> "gh:a#1" (would rewrite)' . PHP_EOL
			. 'task-1 fl-2: "GH:A#2" -> "gh:a#2" (collides)' . PHP_EOL
			. 'Would rewrite 1, rewrote 0 forge id(s); 1 collide(s) with a link already on the task and are left; 0 failed.' . PHP_EOL,
			$tester->getDisplay()
		);
	}//end testADryRunListsThePlanAndWritesNothing()

	/**
	 * With --apply each non-colliding entry is applied; a refusal is counted and exits 1.
	 *
	 * @return void
	 */
	public function testApplyWritesAndARefusalFailsTheRun(): void {
		$ok      = $this->step('fl-1', 'GH:A#1', 'gh:a#1');
		$refused = $this->step('fl-2', 'GH:A#2', 'gh:a#2');
		$twin    = $this->step('fl-3', 'GH:A#3', 'gh:a#3', true);
		$this->repair->method('plan')->willReturn([$ok, $refused, $twin]);
		$applied = [];
		$this->repair->expects($this->exactly(2))->method('apply')->willReturnCallback(
			static function (array $step) use (&$applied): bool {
				$applied[] = $step['id'];
				return ($step['id'] === 'fl-1');
			}
		);
		$tester = new CommandTester(new RepairForgeLinkIds(repair: $this->repair));

		$this->assertSame(1, $tester->execute(['--apply' => true]));
		$this->assertSame(['fl-1', 'fl-2'], $applied);
		$display = $tester->getDisplay();
		$this->assertStringContainsString('task-1 fl-1: "GH:A#1" -> "gh:a#1" (rewritten)', $display);
		$this->assertStringContainsString('task-1 fl-2: "GH:A#2" -> "gh:a#2" (failed)', $display);
		$this->assertStringContainsString('task-1 fl-3: "GH:A#3" -> "gh:a#3" (collides)', $display);
		$this->assertStringContainsString(
			'Would rewrite 0, rewrote 1 forge id(s); 1 collide(s) with a link already on the task and are left; 1 failed.',
			$display
		);
	}//end testApplyWritesAndARefusalFailsTheRun()

	/**
	 * An applied run with nothing refused succeeds; an empty plan only prints the totals.
	 *
	 * @return void
	 */
	public function testACleanApplyAndAnEmptyPlanSucceed(): void {
		$this->repair->method('plan')->willReturnOnConsecutiveCalls([$this->step('fl-1', 'GH:A#1', 'gh:a#1')], []);
		$this->repair->method('apply')->willReturn(true);

		$applied = new CommandTester(new RepairForgeLinkIds(repair: $this->repair));
		$this->assertSame(0, $applied->execute(['--apply' => true]));
		$this->assertStringContainsString('Would rewrite 0, rewrote 1 forge id(s); 0 collide(s)', $applied->getDisplay());

		$empty = new CommandTester(new RepairForgeLinkIds(repair: $this->repair));
		$this->assertSame(0, $empty->execute([]));
		$this->assertSame(
			'Would rewrite 0, rewrote 0 forge id(s); 0 collide(s) with a link already on the task and are left; 0 failed.' . PHP_EOL,
			$empty->getDisplay()
		);
	}//end testACleanApplyAndAnEmptyPlanSucceed()
}//end class
