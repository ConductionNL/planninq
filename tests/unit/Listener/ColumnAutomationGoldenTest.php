<?php

/**
 * Golden test for the column automation listener: the rules a task runs when it enters a column.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
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

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ColumnAutomationListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Records the modified data and the skipped-rule log lines over moves, columns and movers.
 *
 * @covers \OCA\Planninq\Listener\ColumnAutomationListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class ColumnAutomationGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/column-automation.golden.json';

	/**
	 * Mover => uid, '' for no session.
	 */
	private const MOVERS = [
		'nobody'  => '',
		'member'  => 'ben',
		'outside' => 'eve',
	];

	private const TASK = ['title' => 'T', 'priority' => 'normal', 'project' => 'proj', 'column' => 'c-todo', 'assignedTo' => 'anna', 'labels' => [], 'sharedWith' => ['ben', 'carl']];

	/**
	 * Column => its automation (absent for the key 'none').
	 */
	private const COLUMNS = [
		'c-empty'       => [],
		'c-notlist'     => 'x',
		'c-prio'        => [['action' => 'setPriority', 'value' => 'high']],
		'c-prio-same'   => [['action' => 'setPriority', 'value' => 'normal']],
		'c-prio-bad'    => [['action' => 'setPriority', 'value' => 'extreme']],
		'c-assign'      => [['action' => 'assign', 'value' => 'ben']],
		'c-assign-same' => [['action' => 'assign', 'value' => 'anna']],
		'c-assign-out'  => [['action' => 'assign', 'value' => 'eve']],
		'c-assign-none' => [['action' => 'assign', 'value' => '']],
		'c-mover'       => [['action' => 'assignMover']],
		'c-unassign'    => [['action' => 'unassign']],
		'c-label'       => [['action' => 'addLabel', 'value' => 'lab-urg']],
		'c-label-gone'  => [['action' => 'addLabel', 'value' => 'lab-x']],
		'c-label-two'   => [['action' => 'addLabel', 'value' => 'lab-urg'], ['action' => 'addLabel', 'value' => 'lab-low'], ['action' => 'addLabel', 'value' => 'lab-urg']],
		'c-combo'       => [['action' => 'assign', 'value' => 'carl'], ['action' => 'assignMover'], ['action' => 'setPriority', 'value' => 'urgent'], ['action' => 'addLabel', 'value' => 'lab-urg'], ['action' => 'unassign']],
		'c-reassign'    => [['action' => 'unassign'], ['action' => 'assign', 'value' => 'carl']],
		'c-junk'        => ['plain', ['action' => 5], ['value' => 'high'], ['action' => 'assign', 'value' => ['ben']], ['action' => 'setPriority', 'value' => true], ['action' => 'nope', 'value' => 'x'], ['action' => 'setPriority', 'value' => 'low']],
		'c-scalar'      => [['action' => 'assign', 'value' => 7], ['action' => 'setPriority', 'value' => 1.5]],
		'c-number'      => [['action' => 'assign', 'value' => 7], ['action' => 'addLabel', 'value' => 5]],
		'c-keyed'       => ['first' => ['action' => 'setPriority', 'value' => 'bad'], 'second' => ['action' => 'unassign']],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testRulesMatchTheRecording(): void {
		$cases = [];
		foreach (array_merge(array_keys(self::COLUMNS), ['c-none', 'c-missing']) as $column) {
			$cases['create into ' . $column] = ['create', ['column' => $column] + self::TASK, null, []];
			$cases['move into ' . $column]   = ['update', ['column' => $column] + self::TASK, self::TASK, []];
		}

		$cases += [
			'stay in column'          => ['update', ['column' => 'c-prio', 'title' => 'U'] + self::TASK, ['column' => 'c-prio'] + self::TASK, []],
			'stay by ref'             => ['update', ['column' => ['id' => 'c-prio']] + self::TASK, ['column' => 'c-prio'] + self::TASK, []],
			'stay old by uuid'        => ['update', ['column' => 'c-prio'] + self::TASK, ['column' => ['uuid' => 'c-prio']] + self::TASK, []],
			'move by id ref'          => ['update', ['column' => ['id' => 'c-prio']] + self::TASK, self::TASK, []],
			'move by uuid ref'        => ['update', ['column' => ['uuid' => 'c-prio']] + self::TASK, self::TASK, []],
			'move by bad ref'         => ['update', ['column' => ['id' => 5]] + self::TASK, self::TASK, []],
			'move no column'          => ['update', ['title' => 'T'], self::TASK, []],
			'move no old object'      => ['update', ['column' => 'c-prio'] + self::TASK, 'no-old', []],
			'move old without column' => ['update', ['column' => 'c-prio'] + self::TASK, ['title' => 'T'], []],
			'moved by another listener' => ['update', self::TASK, self::TASK, ['column' => 'c-prio', 'note' => 'n']],
			'kept by another listener'  => ['update', ['column' => 'c-prio'] + self::TASK, self::TASK, ['column' => 'c-todo']],
			'earlier change counts'   => ['update', ['column' => 'c-prio-same'] + self::TASK, self::TASK, ['priority' => 'high']],
			'labels not a list'       => ['update', ['column' => 'c-label', 'labels' => 'x'] + self::TASK, self::TASK, []],
			'labels keyed'            => ['update', ['column' => 'c-label', 'labels' => ['a' => 'lab-low']] + self::TASK, self::TASK, []],
			'label already on'        => ['update', ['column' => 'c-label', 'labels' => ['lab-urg']] + self::TASK, self::TASK, []],
			'no labels field'         => ['update', ['column' => 'c-label', 'title' => 'T', 'project' => 'proj'], self::TASK, []],
			'shared not a list'       => ['update', ['column' => 'c-assign', 'sharedWith' => 'ben'] + self::TASK, self::TASK, []],
			'shared keyed'            => ['update', ['column' => 'c-assign', 'sharedWith' => ['x' => 'ben', 'y' => 'dan']] + self::TASK, self::TASK, []],
			'shared without ben'      => ['update', ['column' => 'c-assign', 'sharedWith' => ['carl']] + self::TASK, self::TASK, []],
			'no shared field'         => ['update', ['column' => 'c-mover', 'title' => 'T', 'project' => 'proj'], self::TASK, []],
			'unassign shares'         => ['update', ['column' => 'c-unassign', 'sharedWith' => ['']] + self::TASK, self::TASK, []],
			'no project'              => ['update', ['column' => 'c-assign', 'project' => ''] + self::TASK, self::TASK, []],
			'unknown project'         => ['update', ['column' => 'c-assign', 'project' => 'gone'] + self::TASK, self::TASK, []],
			'project ref'             => ['update', ['column' => 'c-assign', 'project' => ['id' => 'proj']] + self::TASK, self::TASK, []],
			'unassign nobody'         => ['update', ['column' => 'c-unassign', 'title' => 'T', 'project' => 'proj'], self::TASK, []],
			'label schema'            => ['create', ['column' => 'c-prio'] + self::TASK, null, [], 'label'],
		];

		$recorded = [];
		foreach ($cases as $name => $case) {
			foreach (self::MOVERS as $mover => $uid) {
				$recorded[$name . ' / ' . $mover] = $this->move(spec: $case, uid: $uid);
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testRulesMatchTheRecording()

	/**
	 * One write: the modified data and the log lines.
	 *
	 * @param array<int,mixed> $spec The write: kind, sent data, stored data, modified data set before, schema.
	 * @param string           $uid  The mover, '' for no session.
	 *
	 * @return array<string,mixed>
	 */
	private function move(array $spec, string $uid): array {
		[$kind, $data, $old, $before] = $spec;
		$schema = $this->schemaId(slug: ($spec[4] ?? 'task'));
		$this->seed();
		$entity = InMemoryObjectService::entity(uuid: 'task-1', data: $data, register: '1', schema: $schema);
		$event  = new ObjectCreatingEvent($entity);
		if ($kind === 'update') {
			$event = new ObjectUpdatingEvent($entity, $old === 'no-old' ? null : InMemoryObjectService::entity(uuid: 'task-1', data: (array)$old, register: '1', schema: $schema));
		}

		$event->setModifiedData($before);
		$logger = $this->logger();
		$this->listener(uid: $uid, logger: $logger)->handle($event);

		return ['modified' => $event->getModifiedData(), 'log' => $logger->lines];
	}//end move()

	/**
	 * Seed the project, the columns and two labels.
	 *
	 * @return void
	 */
	private function seed(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj', ['title' => 'P', 'owner' => 'olga', 'members' => ['anna', 'ben', 'carl', '7']]);
		foreach (self::COLUMNS as $column => $rules) {
			$this->objects->seed('column', $column, ['title' => $column, 'project' => 'proj', 'automation' => $rules]);
		}

		$this->objects->seed('column', 'c-none', ['title' => 'None', 'project' => 'proj']);
		$this->objects->seed('column', 'c-todo', ['title' => 'To do', 'project' => 'proj', 'automation' => [['action' => 'setPriority', 'value' => 'low']]]);
		$this->objects->seed('label', 'lab-urg', ['title' => 'Urgent']);
		$this->objects->seed('label', 'lab-low', ['title' => 'Low']);
		$this->objects->seed('label', '5', ['title' => 'Five']);
	}//end seed()

	/**
	 * A logger that keeps every line.
	 *
	 * @return AbstractLogger
	 */
	private function logger(): AbstractLogger {
		return new class extends AbstractLogger {
			/**
			 * The lines, in order.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $lines = [];

			/**
			 * Keep one line.
			 *
			 * @param mixed              $level   The level.
			 * @param string|\Stringable $message The message.
			 * @param array<mixed>       $context The context.
			 *
			 * @return void
			 */
			public function log($level, string|\Stringable $message, array $context=[]): void {
				$this->lines[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}//end log()
		};
	}//end logger()

	/**
	 * The listener for one mover.
	 *
	 * @param string         $uid    The mover, '' for no session.
	 * @param AbstractLogger $logger The logger.
	 *
	 * @return ColumnAutomationListener
	 */
	private function listener(string $uid, AbstractLogger $logger): ColumnAutomationListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);

		return new ColumnAutomationListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			logger: $logger
		);
	}//end listener()
}//end class
