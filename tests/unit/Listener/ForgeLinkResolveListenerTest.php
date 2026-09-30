<?php

/**
 * ForgeLinkResolveListener: a forge link finds its task by key, gets its
 * project from the task, and is refused for an unknown key or a repeat.
 *
 * Real ProjectMembershipService and TaskScopeResolver over the in-memory
 * ObjectService; every accepted payload is validated against the real
 * forgeLink fragment of lib/Settings/planninq_register.json.
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
 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\Planninq\Listener\ForgeLinkResolveListener;
use OCA\Planninq\Listener\ProjectMemberAccessListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-1.2
 */
class ForgeLinkResolveListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const LINK = [
		'taskKey'    => 'vc-12',
		'kind'       => 'mergeRequest',
		'url'        => 'https://github.com/acme/portal/pull/42',
		'title'      => 'VC-12 fix printer driver',
		'repository' => 'acme/portal',
		'externalId' => 'github:acme/portal#42',
		'state'      => 'open',
		'author'     => 'octocat',
		'occurredAt' => '2026-09-30T10:00:00+00:00',
		'source'     => 'integriq',
	];

	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', '00000000-0000-4000-8000-0000000000a1', ['title' => 'A', 'key' => 'VC', 'members' => ['alice'], 'owner' => 'carol']);
		$this->objects->seed('project', '00000000-0000-4000-8000-0000000000b1', ['title' => 'B', 'key' => 'OPS', 'members' => ['dave'], 'owner' => 'dave']);
		$this->objects->seed('task', '00000000-0000-4000-8000-000000000012', ['title' => 'Printer driver', 'key' => 'VC-12', 'project' => '00000000-0000-4000-8000-0000000000a1']);
		$this->objects->seed('task', '00000000-0000-4000-8000-0000000000c1', ['title' => 'Backups', 'key' => 'OPS-1', 'project' => '00000000-0000-4000-8000-0000000000b1']);
	}//end setUp()

	private function listener(): ForgeLinkResolveListener {
		return new ForgeLinkResolveListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	private function link(array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'fl-1', data: $data, register: '1', schema: $this->schemaId(slug: 'forgeLink'));
	}//end link()

	public function testKeyResolvesTaskAndProject(): void {
		$event = new ObjectCreatingEvent($this->link(data: self::LINK));

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000a1', 'taskKey' => 'VC-12'], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'forgeLink', payload: array_merge(self::LINK, $event->getModifiedData())));
	}//end testKeyResolvesTaskAndProject()

	public function testUnknownKeyIsRejected(): void {
		$event = new ObjectCreatingEvent($this->link(data: ['taskKey' => 'ZZ-9'] + self::LINK));

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(ForgeLinkResolveListener::ERROR_NO_TASK, ($event->getErrors()['code'] ?? null));
		self::assertSame([], $event->getModifiedData());
	}//end testUnknownKeyIsRejected()

	public function testDuplicateExternalIdIsRejected(): void {
		$this->objects->seed('forgeLink', 'fl-0', ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000a1', 'externalId' => 'github:acme/portal#42', 'url' => 'https://github.com/acme/portal/pull/42', 'source' => 'manual']);

		$event = new ObjectCreatingEvent($this->link(data: ['source' => 'manual'] + self::LINK));
		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(ForgeLinkResolveListener::ERROR_DUPLICATE, ($event->getErrors()['code'] ?? null));

		// The same forge id on another task is a link of its own.
		$other = new ObjectCreatingEvent($this->link(data: ['taskKey' => 'OPS-1', 'source' => 'manual'] + self::LINK));
		$this->listener()->handle($other);
		self::assertFalse($other->isPropagationStopped());
	}//end testDuplicateExternalIdIsRejected()

	public function testManualLinkGetsProjectFromTask(): void {
		$manual = ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000b1', 'kind' => 'link', 'url' => 'https://example.org/notes'];
		$event  = new ObjectCreatingEvent($this->link(data: $manual));

		$this->listener()->handle($event);

		self::assertSame(['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000a1', 'taskKey' => 'VC-12', 'source' => 'manual'], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'forgeLink', payload: array_merge($manual, $event->getModifiedData())));
	}//end testManualLinkGetsProjectFromTask()

	/**
	 * The membership gate reads the project from the task too, so a link
	 * claiming the caller's own project cannot land on another project's task.
	 */
	public function testMemberGateUsesTheTasksProjectNotTheSentOne(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('dave');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$gate = new ProjectMemberAccessListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$event = new ObjectCreatingEvent($this->link(data: ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000b1', 'url' => 'https://example.org/x']));
		$gate->handle($event);

		self::assertTrue($event->isPropagationStopped(), 'dave is no member of proj-a, the project of VC-12');
	}//end testMemberGateUsesTheTasksProjectNotTheSentOne()
	/**
	 * Integriq hands over the forge text; planninq finds the key in it.
	 */
	public function testKeyInForgeTextResolves(): void {
		$event = new ObjectCreatingEvent($this->link(data: ['taskKey' => 'Merge branch feature/vc-12-printer (see SHA-256 notes)'] + self::LINK));

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000a1', 'taskKey' => 'VC-12'], $event->getModifiedData());
	}//end testKeyInForgeTextResolves()

	/**
	 * A later event about the same merge request replaces the integration's
	 * link, so its state follows the forge and no second link appears.
	 */
	public function testLaterIntegriqEventReplacesTheLink(): void {
		$this->objects->seed('forgeLink', 'fl-0', ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-0000000000a1', 'externalId' => 'github:acme/portal#42', 'url' => 'https://github.com/acme/portal/pull/42', 'state' => 'open', 'source' => 'integriq']);

		$event = new ObjectCreatingEvent($this->link(data: ['state' => 'merged'] + self::LINK));
		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame([], $this->objects->searchObjectsBySlug(registerSlug: 'planninq', schemaSlug: 'forgeLink', filters: ['externalId' => 'github:acme/portal#42']));
	}//end testLaterIntegriqEventReplacesTheLink()
}//end class
