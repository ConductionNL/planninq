<?php

/**
 * Tests for reading and mapping a Microsoft Project plan (integration-msproject-import, tasks 1.1 and 1.2).
 *
 * The fixture tests/fixtures/msproject/contractor-plan.xml is a plan in
 * Microsoft Project's XML format (MSPDI): two summary phases, tasks with a
 * level-3 and a level-4 child, a milestone, finish-to-start and
 * start-to-start links, a link to a summary task and two resource
 * assignments. Every mapped payload is validated against the real register
 * schema, so what the import writes is what OpenRegister accepts.
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

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Exception\MsProjectImportException;
use OCA\Planninq\Service\MsProjectPlanMapper;
use OCA\Planninq\Service\MsProjectPlanParser;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MsProjectPlanParser
 * @covers \OCA\Planninq\Service\MsProjectPlanMapper
 */
class MsProjectImportServiceTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The fixture plan's XML.
	 *
	 * @return string
	 */
	private function fixture(): string {
		return (string)file_get_contents(__DIR__ . '/../../fixtures/msproject/contractor-plan.xml');
	}//end fixture()

	/**
	 * The fixture, parsed and mapped.
	 *
	 * @return array<string,mixed>
	 */
	private function mapped(): array {
		$plan = (new MsProjectPlanParser())->parse(content: $this->fixture(), fileName: 'Renovatie stadhuis.xml');
		return (new MsProjectPlanMapper())->map(plan: $plan);
	}//end mapped()

	/**
	 * The mapped object with this Project UID.
	 *
	 * @param array<string,mixed> $mapped The mapping.
	 * @param string              $uid    The UID.
	 *
	 * @return array<string,mixed>
	 */
	private function byUid(array $mapped, string $uid): array {
		foreach (['phases', 'tasks', 'subtasks'] as $kind) {
			foreach ($mapped[$kind] as $object) {
				if ($object['uid'] === $uid) {
					return $object;
				}
			}
		}

		self::fail('No mapped object with UID ' . $uid);
	}//end byUid()

	/**
	 * Assert parsing refuses the content with this code.
	 *
	 * @param string $content  The file content.
	 * @param string $fileName The file name.
	 * @param string $reason   The expected reason code.
	 *
	 * @return void
	 */
	private function assertRefused(string $content, string $fileName, string $reason): void {
		try {
			(new MsProjectPlanParser())->parse(content: $content, fileName: $fileName);
			self::fail('The file was accepted.');
		} catch (MsProjectImportException $e) {
			self::assertSame($reason, $e->getReason());
		}
	}//end assertRefused()

	/**
	 * A DOCTYPE is refused before anything is parsed.
	 *
	 * @return void
	 */
	public function testRejectsDoctype(): void {
		$xml = '<?xml version="1.0"?><!DOCTYPE Project [<!ELEMENT Project ANY>]><Project xmlns="http://schemas.microsoft.com/project"><Tasks/></Project>';
		$this->assertRefused(content: $xml, fileName: 'plan.xml', reason: MsProjectImportException::UNSAFE);
	}//end testRejectsDoctype()

	/**
	 * An external entity is refused and its target is never read.
	 *
	 * @return void
	 */
	public function testRejectsExternalEntity(): void {
		$xml = '<?xml version="1.0"?><!DOCTYPE p [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
			. '<Project xmlns="http://schemas.microsoft.com/project"><Name>&x;</Name><Tasks/></Project>';
		$this->assertRefused(content: $xml, fileName: 'plan.xml', reason: MsProjectImportException::UNSAFE);

		$entityOnly = '<?xml version="1.0"?><Project xmlns="http://schemas.microsoft.com/project"><!ENTITY x SYSTEM "http://example.com/x"><Tasks/></Project>';
		$this->assertRefused(content: $entityOnly, fileName: 'plan.xml', reason: MsProjectImportException::UNSAFE);
	}//end testRejectsExternalEntity()

	/**
	 * More than 2,000 tasks, or more than 10 MB, is refused before mapping.
	 *
	 * @return void
	 */
	public function testRejectsOverTwoThousandTasks(): void {
		$tasks = str_repeat('<Task><UID>1</UID><Name>T</Name><OutlineLevel>1</OutlineLevel></Task>', 2001);
		$xml   = '<?xml version="1.0"?><Project xmlns="http://schemas.microsoft.com/project"><Tasks>' . $tasks . '</Tasks></Project>';
		$this->assertRefused(content: $xml, fileName: 'plan.xml', reason: MsProjectImportException::TOO_MANY_TASKS);

		$this->assertRefused(content: str_repeat(' ', (10 * 1024 * 1024) + 1), fileName: 'plan.xml', reason: MsProjectImportException::TOO_LARGE);

		$exactly = str_repeat('<Task><UID>1</UID><Name>T</Name><OutlineLevel>1</OutlineLevel></Task>', 2000);
		$plan    = (new MsProjectPlanParser())->parse(
			content: '<?xml version="1.0"?><Project xmlns="http://schemas.microsoft.com/project"><Tasks>' . $exactly . '</Tasks></Project>',
			fileName: 'plan.xml'
		);
		self::assertCount(2000, $plan['tasks']);
	}//end testRejectsOverTwoThousandTasks()

	/**
	 * A binary .mpp file is refused with the hint to save the plan as XML.
	 *
	 * @return void
	 */
	public function testMppIsRefusedWithHint(): void {
		$ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 64);
		$this->assertRefused(content: $ole, fileName: 'planning.mpp', reason: MsProjectImportException::MPP);
		$this->assertRefused(content: $ole, fileName: 'planning.bin', reason: MsProjectImportException::MPP);
		$this->assertRefused(content: $this->fixture(), fileName: 'planning.mpp', reason: MsProjectImportException::MPP);
		$this->assertRefused(content: 'not a plan', fileName: 'plan.xml', reason: MsProjectImportException::NOT_A_PLAN);
		$this->assertRefused(content: '<?xml version="1.0"?><Other/>', fileName: 'plan.xml', reason: MsProjectImportException::NOT_A_PLAN);
	}//end testMppIsRefusedWithHint()

	/**
	 * The contractor's plan is read with its name, tasks, links and resources.
	 *
	 * @return void
	 */
	public function testParsesSampleContractorPlan(): void {
		$plan = (new MsProjectPlanParser())->parse(content: $this->fixture(), fileName: 'Renovatie stadhuis.xml');

		self::assertSame('Renovatie stadhuis.xml', $plan['name']);
		self::assertSame('14', $plan['saveVersion']);
		// The project summary task (outline level 0) is not part of the plan.
		self::assertCount(10, $plan['tasks']);

		$metselwerk = $plan['tasks'][2];
		self::assertSame('3', $metselwerk['uid']);
		self::assertSame('Metselwerk', $metselwerk['name']);
		self::assertSame(2, $metselwerk['level']);
		self::assertSame('2027-03-15', $metselwerk['start']);
		self::assertSame('2027-04-02', $metselwerk['finish']);
		self::assertSame(7230, $metselwerk['minutes']);
		self::assertSame(20, $metselwerk['percent']);
		self::assertSame([['uid' => '2', 'type' => 1, 'lag' => 0]], $metselwerk['predecessors']);
		self::assertFalse($metselwerk['hasResource'], 'An unassigned placeholder is not a resource.');
		self::assertTrue($plan['tasks'][1]['hasResource']);
	}//end testParsesSampleContractorPlan()

	/**
	 * A summary task at outline level 1 becomes a phase.
	 *
	 * @return void
	 */
	public function testSummaryLevelOneBecomesPhase(): void {
		$mapped = $this->mapped();

		self::assertSame(['Ruwbouw', 'Afbouw'], array_column(array_column($mapped['phases'], 'data'), 'title'));
		$ruwbouw = $this->byUid(mapped: $mapped, uid: '1');
		self::assertSame(['title' => 'Ruwbouw', 'startDate' => '2027-03-01', 'endDate' => '2027-04-16', 'order' => 0], array_intersect_key($ruwbouw['data'], array_flip(['title', 'startDate', 'endDate', 'order'])));
		self::assertArrayNotHasKey('status', $ruwbouw['data'], 'A phase closes with its concluding document, so the import never sets one.');

		$fundering = $this->byUid(mapped: $mapped, uid: '2');
		self::assertSame('1', $fundering['phaseUid']);
		$asbest = $this->byUid(mapped: $mapped, uid: '10');
		self::assertNull($asbest['phaseUid'], 'A level-1 task outside any phase has no phase.');
		self::assertSame(1, $this->byUid(mapped: $mapped, uid: '8')['data']['order']);

		foreach ($mapped['phases'] as $phase) {
			$payload = ($phase['data'] + ['project' => '11111111-1111-4111-8111-111111111111']);
			self::assertSame([], $this->registerSchemaErrors(slug: 'projectPhase', payload: $payload));
		}
	}//end testSummaryLevelOneBecomesPhase()

	/**
	 * Level 3 and deeper become sub-tasks of their level-2 task; a collapsed path is kept.
	 *
	 * @return void
	 */
	public function testDeepLevelsCollapseToOneSubtaskLevel(): void {
		$mapped = $this->mapped();

		self::assertSame(['5', '6'], array_column($mapped['subtasks'], 'uid'));
		$level3 = $this->byUid(mapped: $mapped, uid: '5');
		$level4 = $this->byUid(mapped: $mapped, uid: '6');
		self::assertSame('4', $level3['parentUid']);
		self::assertSame('4', $level4['parentUid']);
		self::assertSame('1', $level4['phaseUid']);
		self::assertStringStartsWith('Kozijnen > Kozijnen voorbereiden > Maatvoering', $level4['data']['description']);
		self::assertArrayNotHasKey('description', $level3['data'], 'A level-3 task sits where it was; no path is needed.');
	}//end testDeepLevelsCollapseToOneSubtaskLevel()

	/**
	 * A milestone is a task of type milestone whose start and due dates are equal.
	 *
	 * @return void
	 */
	public function testMilestoneMapping(): void {
		$milestone = $this->byUid(mapped: $this->mapped(), uid: '7');

		self::assertSame('milestone', $milestone['data']['issueType']);
		self::assertSame('2027-04-16', $milestone['data']['startDate']);
		self::assertSame('2027-04-16', $milestone['data']['dueDate']);
		self::assertSame(1, $this->mapped()['counts']['milestones']);
	}//end testMilestoneMapping()

	/**
	 * Progress sets the status; dates, duration, notes and provenance are carried.
	 *
	 * @return void
	 */
	public function testProgressSetsStatus(): void {
		$mapped = $this->mapped();

		self::assertSame('done', $this->byUid(mapped: $mapped, uid: '2')['data']['status']);
		self::assertSame('in_progress', $this->byUid(mapped: $mapped, uid: '3')['data']['status']);
		self::assertSame('open', $this->byUid(mapped: $mapped, uid: '9')['data']['status']);

		$fundering = $this->byUid(mapped: $mapped, uid: '2')['data'];
		self::assertSame('2027-03-01', $fundering['startDate']);
		self::assertSame('2027-03-12', $fundering['dueDate']);
		self::assertSame(4800, $fundering['estimatedDuration']);
		self::assertSame(100, $fundering['percentComplete']);
		self::assertSame(['msProjectUid' => '2', 'msProjectFile' => 'Renovatie stadhuis.xml', 'msProjectSaveVersion' => '14'], $fundering['metadata']);
		self::assertSame('Binnen en buiten, twee lagen.', $this->byUid(mapped: $mapped, uid: '9')['data']['description']);

		foreach (array_merge($mapped['tasks'], $mapped['subtasks']) as $task) {
			$payload = ($task['data'] + ['project' => '11111111-1111-4111-8111-111111111111']);
			self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $payload), 'UID ' . $task['uid']);
		}
	}//end testProgressSetsStatus()

	/**
	 * A finish-to-start link becomes a blocking dependency.
	 *
	 * @return void
	 */
	public function testFinishToStartBecomesBlocks(): void {
		$links = $this->mapped()['links'];

		self::assertContains(['blockerUid' => '2', 'blockedUid' => '3', 'type' => 'blocks'], $links);
		self::assertContains(['blockerUid' => '3', 'blockedUid' => '9', 'type' => 'blocks'], $links);
		self::assertCount(3, $links);
	}//end testFinishToStartBecomesBlocks()

	/**
	 * Every other link type becomes a related link and its lag is dropped.
	 *
	 * @return void
	 */
	public function testOtherLinkTypesBecomeRelates(): void {
		$links = $this->mapped()['links'];

		self::assertContains(['blockerUid' => '3', 'blockedUid' => '7', 'type' => 'relates'], $links);
		self::assertSame([], $this->registerSchemaErrors(slug: 'dependency', payload: ['blocker' => '11111111-1111-4111-8111-111111111111', 'blocked' => '21111111-1111-4111-8111-111111111111', 'type' => 'relates']));
	}//end testOtherLinkTypesBecomeRelates()

	/**
	 * Every part of the plan that does not carry over is counted.
	 *
	 * @return void
	 */
	public function testLossesAreCounted(): void {
		$mapped = $this->mapped();

		self::assertSame(
			[
				'resources'       => 2,
				'collapsedLevels' => 1,
				'relatedLinks'    => 1,
				'lags'            => 1,
				'phaseLinks'      => 1,
			],
			$mapped['losses']
		);
		self::assertSame(['phases' => 2, 'tasks' => 5, 'subtasks' => 2, 'milestones' => 1, 'links' => 3], $mapped['counts']);
	}//end testLossesAreCounted()
}//end class
