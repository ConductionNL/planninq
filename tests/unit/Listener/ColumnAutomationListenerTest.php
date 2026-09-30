<?php

/**
 * Tests for ColumnAutomationListener: the rules of the column a task enters
 * run in the same save, whichever client moved it, and a rule whose value is
 * no longer valid is skipped without blocking the move.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ColumnAutomationListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.2
 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.3
 */
class ColumnAutomationListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT = '5b0e8f3a-8d1c-4f7e-9a51-2f7c0b1d9e44';
	private const TODO    = '1a2b3c4d-0000-4000-8000-000000000001';
	private const REVIEW  = '1a2b3c4d-0000-4000-8000-000000000002';
	private const BLOCKED = '1a2b3c4d-0000-4000-8000-000000000003';
	private const URGENT  = '9f1c7d2e-3b4a-4c5d-8e6f-7a8b9c0d1e2f';

	private const TASK = ['title' => 'Export to CSV', 'status' => 'open', 'priority' => 'normal', 'project' => self::PROJECT, 'column' => self::TODO, 'assignedTo' => 'anna', 'labels' => []];

	/**
	 * Messages the listener logged, in order.
	 *
	 * @var array<int,array{0:string,1:array<string,mixed>}>
	 */
	private array $logged = [];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', self::PROJECT, ['title' => 'Webshop', 'members' => ['anna', 'ben'], 'owner' => 'olga']);
		$this->objects->seed('column', self::TODO, ['title' => 'To do', 'project' => self::PROJECT, 'order' => 0, 'automation' => []]);
		$this->objects->seed('column', self::REVIEW, ['title' => 'Review', 'project' => self::PROJECT, 'order' => 2, 'automation' => [['action' => 'assignMover']]]);
		$this->objects->seed('column', self::BLOCKED, ['title' => 'Blocked', 'project' => self::PROJECT, 'order' => 3, 'automation' => [['action' => 'setPriority', 'value' => 'high']]]);
		$this->objects->seed('label', self::URGENT, ['title' => 'Urgent', 'color' => '#c00000']);
	}//end setUp()

	private function listener(string $actor = 'ben'): ColumnAutomationListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(function (string $message, array $context = []): void {
			$this->logged[] = [$message, $context];
		});

		return new ColumnAutomationListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			logger: $logger
		);
	}//end listener()

	private function task(array $data, string $schema = 'task'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'task-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end task()

	private function move(string $column, array $changes = []): ObjectUpdatingEvent {
		return new ObjectUpdatingEvent($this->task(data: ['column' => $column] + $changes + self::TASK), $this->task(data: self::TASK));
	}//end move()

	private function rules(string $column, array $rules): void {
		$this->objects->seed('column', $column, ['title' => 'Column', 'project' => self::PROJECT, 'order' => 5, 'automation' => $rules]);
	}//end rules()

	private function assertStoredTaskIsValid(ObjectUpdatingEvent|ObjectCreatingEvent $event): void {
		$payload = array_merge(self::TASK, $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $payload), 'the merged task passes the real task schema');
	}//end assertStoredTaskIsValid()

	public function testAssignRuleSetsAssignee(): void {
		$this->rules(column: self::REVIEW, rules: [['action' => 'assign', 'value' => 'ben']]);
		$event = $this->move(column: self::REVIEW);

		$this->listener(actor: 'anna')->handle($event);

		self::assertSame(['assignedTo' => 'ben'], $event->getModifiedData());
		$this->assertStoredTaskIsValid(event: $event);
	}//end testAssignRuleSetsAssignee()

	/**
	 * Scenario "Moving a card into Review assigns the mover".
	 */
	public function testAssignMoverUsesCurrentUser(): void {
		$event = $this->move(column: self::REVIEW);

		$this->listener(actor: 'ben')->handle($event);

		self::assertSame(['assignedTo' => 'ben'], $event->getModifiedData());
		self::assertFalse($event->isPropagationStopped());
	}//end testAssignMoverUsesCurrentUser()

	/**
	 * Scenario "A move through the API runs the same rules".
	 */
	public function testSetPriorityRuleSetsPriority(): void {
		$event = $this->move(column: self::BLOCKED);

		$this->listener()->handle($event);

		self::assertSame(['priority' => 'high'], $event->getModifiedData());
		$this->assertStoredTaskIsValid(event: $event);
	}//end testSetPriorityRuleSetsPriority()

	public function testAddLabelAppendsOnce(): void {
		$this->rules(column: self::REVIEW, rules: [['action' => 'addLabel', 'value' => self::URGENT], ['action' => 'addLabel', 'value' => self::URGENT]]);

		$fresh = $this->move(column: self::REVIEW);
		$this->listener()->handle($fresh);
		self::assertSame(['labels' => [self::URGENT]], $fresh->getModifiedData());
		$this->assertStoredTaskIsValid(event: $fresh);

		$carried = $this->move(column: self::REVIEW, changes: ['labels' => [self::URGENT]]);
		$this->listener()->handle($carried);
		self::assertSame([], $carried->getModifiedData(), 'a label already on the task is not added again');
	}//end testAddLabelAppendsOnce()

	/**
	 * Scenario "Editing a task without moving it runs nothing".
	 */
	public function testNoColumnChangeRunsNothing(): void {
		$stored = ['column' => self::REVIEW] + self::TASK;
		$event  = new ObjectUpdatingEvent($this->task(data: ['title' => 'Export to CSV and XLSX'] + $stored), $this->task(data: $stored));

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());

		$toBacklog = $this->move(column: '');
		$this->listener()->handle($toBacklog);
		self::assertSame([], $toBacklog->getModifiedData(), 'moving to the backlog enters no column');
	}//end testNoColumnChangeRunsNothing()

	public function testCreateInColumnRunsRules(): void {
		$event = new ObjectCreatingEvent($this->task(data: ['column' => self::BLOCKED] + self::TASK));

		$this->listener()->handle($event);

		self::assertSame(['priority' => 'high'], $event->getModifiedData());
	}//end testCreateInColumnRunsRules()

	/**
	 * Scenario "A rule for a former member is skipped".
	 */
	public function testNonMemberAssigneeIsSkipped(): void {
		$this->rules(column: self::REVIEW, rules: [['action' => 'assign', 'value' => 'carl'], ['action' => 'setPriority', 'value' => 'high']]);
		$event = $this->move(column: self::REVIEW);

		$this->listener()->handle($event);

		self::assertSame(['priority' => 'high'], $event->getModifiedData(), 'the card keeps its assignee, the next rule still runs');
		self::assertFalse($event->isPropagationStopped(), 'the move itself succeeds');
		self::assertCount(1, $this->logged);
		self::assertSame(['column' => self::REVIEW, 'rule' => 0, 'action' => 'assign'], $this->logged[0][1]);
	}//end testNonMemberAssigneeIsSkipped()

	public function testInvalidPriorityIsSkipped(): void {
		$this->rules(column: self::REVIEW, rules: [['action' => 'setPriority', 'value' => 'critical'], ['action' => 'addLabel', 'value' => '00000000-0000-4000-8000-00000000dead'], ['action' => 'closeTask']]);
		$event = $this->move(column: self::REVIEW);

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());
		self::assertCount(3, $this->logged, 'each invalid rule is logged with its index');
		self::assertSame([0, 1, 2], array_map(static fn (array $entry): int => $entry[1]['rule'], $this->logged));
	}//end testInvalidPriorityIsSkipped()

	/**
	 * Risk 2: another pre-save listener's data (the completedAt stamp) survives.
	 */
	public function testMergesWithDataFromAnotherListener(): void {
		$event = $this->move(column: self::BLOCKED, changes: ['status' => 'done']);
		$event->setModifiedData(['completedAt' => '2026-09-30T10:00:00+00:00']);

		$this->listener()->handle($event);

		self::assertSame(['completedAt' => '2026-09-30T10:00:00+00:00', 'priority' => 'high'], $event->getModifiedData());
	}//end testMergesWithDataFromAnotherListener()

	public function testLaterRuleWinsAndUnassignClears(): void {
		$this->rules(column: self::REVIEW, rules: [['action' => 'assign', 'value' => 'anna'], ['action' => 'unassign'], ['action' => 'setPriority', 'value' => 'low'], ['action' => 'setPriority', 'value' => 'urgent']]);
		$event = $this->move(column: self::REVIEW);

		$this->listener()->handle($event);

		self::assertSame(['assignedTo' => '', 'priority' => 'urgent'], $event->getModifiedData());
		$this->assertStoredTaskIsValid(event: $event);
	}//end testLaterRuleWinsAndUnassignClears()

	public function testNoSessionSkipsAssignMoverAndOtherSchemasAreIgnored(): void {
		$event = $this->move(column: self::REVIEW);
		$this->listener(actor: '')->handle($event);
		self::assertSame([], $event->getModifiedData(), 'a background job has no mover');

		$column = new ObjectUpdatingEvent($this->task(data: ['column' => self::BLOCKED] + self::TASK, schema: 'label'), $this->task(data: self::TASK, schema: 'label'));
		$this->listener()->handle($column);
		self::assertSame([], $column->getModifiedData(), 'not a task');
	}//end testNoSessionSkipsAssignMoverAndOtherSchemasAreIgnored()

	public function testTheMoverLeavesSharedWithWhenMadeResponsible(): void {
		$event = $this->move(column: self::REVIEW, changes: ['sharedWith' => ['ben', 'olga']]);

		$this->listener(actor: 'ben')->handle($event);

		self::assertSame(['assignedTo' => 'ben', 'sharedWith' => ['olga']], $event->getModifiedData());
		$this->assertStoredTaskIsValid(event: $event);
	}//end testTheMoverLeavesSharedWithWhenMadeResponsible()

	public function testPrioritiesMatchTheTaskSchema(): void {
		$register = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
		self::assertSame($register['components']['schemas']['task']['properties']['priority']['enum'], ColumnAutomationListener::PRIORITIES);
		self::assertSame($register['components']['schemas']['column']['properties']['automation']['items']['properties']['action']['enum'], ColumnAutomationListener::ACTIONS);
	}//end testPrioritiesMatchTheTaskSchema()
}//end class
