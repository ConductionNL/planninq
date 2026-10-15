<?php

/**
 * Tests for ProjectPrincipalCleanupListener: a deleted account or group is
 * taken off every project, with the real Nextcloud event classes.
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
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Listener\ProjectPrincipalCleanupListener;
use OCA\Planninq\Service\ProjectRoles;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectPrincipalCleanupListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'p-owned', ['title' => 'Owned', 'owner' => 'olga', 'managers' => ['zed'], 'members' => ['olga', 'bob'], 'viewers' => ['olga']]);
		$this->objects->seed('project', 'p-member', ['title' => 'Member', 'owner' => 'anna', 'members' => ['anna', 'olga'], 'memberGroups' => ['adviseurs']]);
		$this->objects->seed('project', 'p-untouched', ['title' => 'Untouched', 'owner' => 'anna', 'members' => ['anna'], 'viewerGroups' => ['lezers']]);
	}//end setUp()

	/**
	 * The listener under test.
	 *
	 * @return ProjectPrincipalCleanupListener
	 */
	private function listener(): ProjectPrincipalCleanupListener {
		return new ProjectPrincipalCleanupListener(
			membership: $this->membershipService(),
			roles: new ProjectRoles(),
			container: $this->container(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	/**
	 * What was saved, by project id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function saved(): array {
		$saved = [];
		foreach ($this->objects->saves as $save) {
			$saved[(string)$save['uuid']] = $save;
		}

		return $saved;
	}//end saved()

	public function testADeletedOwnerHandsOverToAManagerAndLeavesEveryList(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('olga');

		$this->listener()->handle(new UserDeletedEvent($user));

		$saved = $this->saved();
		self::assertSame(['p-owned', 'p-member'], array_keys($saved));
		self::assertSame('zed', $saved['p-owned']['object']['owner']);
		self::assertSame(['bob'], $saved['p-owned']['object']['members']);
		self::assertSame([], $saved['p-owned']['object']['viewers']);
		self::assertSame('anna', $saved['p-member']['object']['owner']);
		self::assertSame(['anna'], $saved['p-member']['object']['members']);
		self::assertFalse($saved['p-owned']['_rbac'], 'no session exists when an account is deleted');
		self::assertFalse($saved['p-owned']['silent'], 'the project update must reach the sync listener, which copies the lists to its work');
	}//end testADeletedOwnerHandsOverToAManagerAndLeavesEveryList()

	public function testADeletedGroupLeavesEveryGroupList(): void {
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getGID')->willReturn('adviseurs');

		$this->listener()->handle(new GroupDeletedEvent($group));

		$saved = $this->saved();
		self::assertSame(['p-member'], array_keys($saved));
		self::assertSame([], $saved['p-member']['object']['memberGroups']);
		self::assertSame(['anna', 'olga'], $saved['p-member']['object']['members']);
	}//end testADeletedGroupLeavesEveryGroupList()

	public function testEverySavedProjectPassesTheRegisterSchema(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('olga');
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getGID')->willReturn('adviseurs');

		$listener = $this->listener();
		$listener->handle(new UserDeletedEvent($user));
		$listener->handle(new GroupDeletedEvent($group));

		self::assertNotSame([], $this->objects->saves);
		foreach ($this->objects->saves as $save) {
			self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: (array)$save['object']), (string)$save['uuid']);
		}
	}//end testEverySavedProjectPassesTheRegisterSchema()

	public function testRegisterSubscribesBothEvents(): void {
		$listeners = [];
		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		$context->method('registerEventListener')->willReturnCallback(
			static function (string $event, string $listener) use (&$listeners): void {
				$listeners[$event][] = $listener;
			}
		);

		$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
		$app->register($context);

		self::assertContains(ProjectPrincipalCleanupListener::class, ($listeners[UserDeletedEvent::class] ?? []));
		self::assertContains(ProjectPrincipalCleanupListener::class, ($listeners[GroupDeletedEvent::class] ?? []));
	}//end testRegisterSubscribesBothEvents()
}//end class
