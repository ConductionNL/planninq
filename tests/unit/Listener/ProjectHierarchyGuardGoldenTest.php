<?php

/**
 * Golden test for the project hierarchy guard: parents, custom fields, portfolio readers.
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
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\ProjectHierarchyGuardListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\ProjectRulesService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Records errors, stops, modified data, writes and log lines over writes and actors.
 *
 * @covers \OCA\Planninq\Listener\ProjectHierarchyGuardListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Service\ProjectRulesService
 * @uses   \OCA\Planninq\Service\FinanceLineService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class ProjectHierarchyGuardGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/project-hierarchy-guard.golden.json';

	/**
	 * Actor => [uid, admin, groups].
	 */
	private const ACTORS = [
		'nobody'  => ['', false, []],
		'system'  => ['sys', false, []],
		'admin'   => ['root', true, []],
		'owner'   => ['carol', false, []],
		'member'  => ['bob', false, []],
		'group'   => ['gus', false, ['staff']],
		'outside' => ['eve', false, []],
	];

	/**
	 * Write name => [kind, schema slug, uuid, sent data, stored data or null].
	 */
	private const WRITES = [
		'create plain'            => ['create', 'project', 'new', ['title' => 'N', 'portfolio' => 'pf-2'], null],
		'create readers sent'     => ['create', 'project', 'new', ['title' => 'N', 'portfolioReaders' => ['bob']], null],
		'create readers same'     => ['create', 'project', 'new', ['title' => 'N', 'portfolio' => 'pf-1', 'portfolioReaders' => ['mia']], null],
		'create portfolio ref'    => ['create', 'project', 'new', ['title' => 'N', 'portfolio' => ['id' => 'pf-2']], null],
		'create portfolio gone'   => ['create', 'project', 'new', ['title' => 'N', 'portfolio' => 'pf-x'], null],
		'create under top'        => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'prog'], null],
		'create under mid'        => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'proj'], null],
		'create under sub'        => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'sub'], null],
		'create under hidden'     => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'hidden'], null],
		'create under group'      => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'grp'], null],
		'create under missing'    => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'missing'], null],
		'create parent ref id'    => ['create', 'project', 'new', ['title' => 'N', 'parent' => ['id' => 'sub']], null],
		'create parent ref self'  => ['create', 'project', 'new', ['title' => 'N', 'parent' => ['@self' => ['id' => 'hidden']]], null],
		'create parent spaces'    => ['create', 'project', 'new', ['title' => 'N', 'parent' => ' sub '], null],
		'create parent number'    => ['create', 'project', 'new', ['title' => 'N', 'parent' => 7], null],
		'create parent blank'     => ['create', 'project', 'new', ['title' => 'N', 'parent' => '  '], null],
		'create named like self'  => ['create', 'project', 'sub', ['title' => 'N', 'parent' => 'prog'], null],
		'update cycle'            => ['update', 'project', 'prog', ['title' => 'P', 'parent' => 'sub'], ['title' => 'P']],
		'update self parent'      => ['update', 'project', 'solo', ['title' => 'S', 'parent' => 'solo'], ['title' => 'S']],
		'update ok'               => ['update', 'project', 'solo', ['title' => 'S', 'parent' => 'prog'], ['title' => 'S']],
		'update same parent'      => ['update', 'project', 'sub', ['title' => 'U', 'parent' => 'proj'], ['title' => 'U', 'parent' => 'proj']],
		'update same parent ref'  => ['update', 'project', 'sub', ['title' => 'U', 'parent' => ['id' => 'proj']], ['title' => 'U', 'parent' => ' proj']],
		'update mid to solo'      => ['update', 'project', 'proj', ['title' => 'M', 'parent' => 'solo'], ['title' => 'M', 'parent' => 'prog']],
		'update top to solo'      => ['update', 'project', 'prog', ['title' => 'P', 'parent' => 'solo'], ['title' => 'P']],
		'update to hidden'        => ['update', 'project', 'solo', ['title' => 'S', 'parent' => 'hidden'], ['title' => 'S']],
		'update parent cleared'   => ['update', 'project', 'sub', ['title' => 'U', 'parent' => null], ['title' => 'U', 'parent' => 'proj']],
		'update no old'           => ['update', 'project', 'solo', ['title' => 'S', 'parent' => 'sub'], 'no-old'],
		'update no old same'      => ['update', 'project', 'sub', ['title' => 'U', 'parent' => 'proj'], 'no-old'],
		'fields good'             => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['code' => 'C1', 'phase' => 'a', 'size' => 3]], null],
		'fields unknown'          => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['code' => 'C1', 'nope' => 1]], null],
		'fields bad choice'       => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['code' => 'C1', 'phase' => 'z']], null],
		'fields bad number'       => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['code' => 'C1', 'size' => '3']], null],
		'fields bad date'         => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['code' => 'C1', 'due' => '3 May']], null],
		'fields missing required' => ['create', 'project', 'new', ['title' => 'N', 'customFields' => ['phase' => 'a']], null],
		'fields not a list'       => ['create', 'project', 'new', ['title' => 'N', 'customFields' => 'x'], null],
		'fields unchanged'        => ['update', 'project', 'solo', ['title' => 'S', 'customFields' => ['nope' => 1]], ['title' => 'S', 'customFields' => ['nope' => 1]]],
		'fields kept unknown'     => ['update', 'project', 'solo', ['title' => 'S', 'customFields' => ['nope' => 1, 'code' => 'C2']], ['title' => 'S', 'customFields' => ['nope' => 1]]],
		'fields stored not list'  => ['update', 'project', 'solo', ['title' => 'S', 'customFields' => ['code' => 'C2']], ['title' => 'S', 'customFields' => 'old']],
		'fields stored not list unknown' => ['update', 'project', 'solo', ['title' => 'S', 'customFields' => ['nope' => 1, 'code' => 'C2']], ['title' => 'S', 'customFields' => 'old']],
		'fields and bad parent'   => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'sub', 'customFields' => ['nope' => 1]], null],
		'fields after good parent' => ['create', 'project', 'new', ['title' => 'N', 'parent' => 'prog', 'customFields' => ['nope' => 1]], null],
		'delete project'          => ['delete', 'project', 'prog', ['title' => 'P', 'parent' => 'sub'], null],
		'portfolio managers'      => ['update', 'projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['mia', 'nils']], ['title' => 'One', 'managers' => ['mia']]],
		'portfolio same'          => ['update', 'projectPortfolio', 'pf-1', ['title' => 'One!', 'managers' => ['mia']], ['title' => 'One', 'managers' => ['mia']]],
		'portfolio cleared'       => ['update', 'projectPortfolio', 'pf-1', ['title' => 'One'], ['title' => 'One', 'managers' => ['mia']]],
		'portfolio no old'        => ['update', 'projectPortfolio', 'pf-2', ['title' => 'Two', 'managers' => ['dirk', 'eva']], 'no-old'],
		'portfolio no old kept'   => ['update', 'projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['mia']], 'no-old'],
		'portfolio no projects'   => ['update', 'projectPortfolio', 'pf-3', ['title' => 'Three', 'managers' => ['zoe']], ['title' => 'Three']],
		'portfolio failing save'  => ['update', 'projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['nils']], ['title' => 'One', 'managers' => ['mia']]],
		'portfolio create'        => ['create', 'projectPortfolio', 'pf-9', ['title' => 'Nine', 'managers' => ['mia']], null],
		'portfolio create used id' => ['create', 'projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['nils']], null],
		'portfolio delete'        => ['delete', 'projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['mia']], null],
		'portfolio delete empty'  => ['delete', 'projectPortfolio', 'pf-3', ['title' => 'Three'], null],
		'task write'              => ['create', 'task', 'task-1', ['title' => 'T', 'parent' => 'sub', 'customFields' => ['nope' => 1]], null],
		'other register'          => ['create', 'project@9', 'new', ['title' => 'N', 'parent' => 'sub'], null],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testGuardMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::WRITES as $write => $spec) {
			foreach (self::ACTORS as $actor => $who) {
				$recorded[$write . ' / ' . $actor] = $this->guard(spec: $spec, who: $who, system: ($actor === 'system'), failing: ($write === 'portfolio failing save'));
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
	}//end testGuardMatchesTheRecording()

	/**
	 * One write: what the guard did.
	 *
	 * @param array<int,mixed>                           $spec    The write.
	 * @param array{0:string,1:bool,2:array<int,string>} $who     The actor.
	 * @param bool                                       $system  Whether it runs as a system operation.
	 * @param bool                                       $failing Whether saving prog fails.
	 *
	 * @return array<string,mixed>
	 */
	private function guard(array $spec, array $who, bool $system, bool $failing): array {
		[$kind, $slug, $uuid, $data, $old] = $spec;
		$this->seed();
		if ($failing === true) {
			$this->objects->failingSaves = ['prog'];
		}

		$register = '1';
		if (str_ends_with($slug, '@9') === true) {
			[$slug, $register] = ['project', '9'];
		}

		$entity = InMemoryObjectService::entity(uuid: $uuid, data: $data, register: $register, schema: $this->schemaId(slug: $slug));
		$event  = match ($kind) {
			'create' => new ObjectCreatingEvent($entity),
			'delete' => new ObjectDeletingEvent($entity),
			default  => new ObjectUpdatingEvent($entity, $old === 'no-old' ? null : InMemoryObjectService::entity(uuid: $uuid, data: (array)$old, register: $register, schema: $this->schemaId(slug: $slug))),
		};

		$logger   = $this->logger();
		$listener = $this->listener(who: $who, logger: $logger);
		if ($system === true) {
			SystemOperationContext::run(static fn () => $listener->handle($event));
		} else {
			$listener->handle($event);
		}

		return [
			'errors'   => $event->getErrors(),
			'stopped'  => $event->isPropagationStopped(),
			'modified' => $event->getModifiedData(),
			'saves'    => $this->objects->saves,
			'log'      => $logger->lines,
		];
	}//end guard()

	/**
	 * Seed portfolios, a three-level chain and loose projects, and the project fields.
	 *
	 * @return void
	 */
	private function seed(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('projectPortfolio', 'pf-1', ['title' => 'One', 'managers' => ['mia']]);
		$this->objects->seed('projectPortfolio', 'pf-2', ['title' => 'Two', 'managers' => ['dirk', 'eva']]);
		$this->objects->seed('projectPortfolio', 'pf-3', ['title' => 'Three']);
		$this->objects->seed('project', 'prog', ['title' => 'P', 'owner' => 'carol', 'members' => ['bob'], 'portfolio' => 'pf-1', 'portfolioReaders' => ['mia']]);
		$this->objects->seed('project', 'proj', ['title' => 'M', 'owner' => 'carol', 'parent' => 'prog', 'portfolio' => 'pf-1']);
		$this->objects->seed('project', 'sub', ['title' => 'U', 'owner' => 'carol', 'parent' => 'proj']);
		$this->objects->seed('project', 'solo', ['title' => 'S', 'owner' => 'carol']);
		$this->objects->seed('project', 'hidden', ['title' => 'H', 'owner' => 'zed']);
		$this->objects->seed('project', 'grp', ['title' => 'G', 'owner' => 'zed', 'ownerGroups' => ['staff']]);
		$this->objects->seed('task', 'task-p', ['title' => 'T', 'project' => 'prog', 'portfolioReaders' => ['mia']]);
		$this->objects->seed('projectField', 'f-1', ['key' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true]);
		$this->objects->seed('projectField', 'f-2', ['key' => 'phase', 'label' => 'Phase', 'type' => 'choice', 'options' => ['a', 'b']]);
		$this->objects->seed('projectField', 'f-3', ['key' => 'size', 'type' => 'number']);
		$this->objects->seed('projectField', 'f-4', ['key' => 'due', 'label' => 'Due', 'type' => 'date']);
		$this->objects->seed('projectField', 'f-5', ['key' => 'risk', 'type' => 'text', 'appliesTo' => 'task']);
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
	 * The listener acting as one actor; the group manager answers only for that actor.
	 *
	 * @param array{0:string,1:bool,2:array<int,string>} $who    The actor.
	 * @param AbstractLogger                             $logger The logger.
	 *
	 * @return ProjectHierarchyGuardListener
	 */
	private function listener(array $who, AbstractLogger $logger): ProjectHierarchyGuardListener {
		[$uid, $admin, $groupIds] = $who;
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => ($admin === true && $id === $uid));
		$groups->method('getUserGroupIds')->willReturnCallback(static fn (IUser $member): array => ($member->getUID() === $uid ? $groupIds : []));

		$membership = $this->membershipService();

		return new ProjectHierarchyGuardListener(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			rules: new ProjectRulesService(membership: $membership, userSession: $session, groupManager: $groups),
			scopeResolver: $this->scopeResolver(),
			container: $this->container(),
			logger: $logger
		);
	}//end listener()
}//end class
