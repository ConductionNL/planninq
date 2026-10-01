<?php

/**
 * Tests for the `planninq/groups` initial state and its registration.
 *
 * @category Tests
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
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\CurrentUserGroupsState;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Services\InitialStateProvider;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class CurrentUserGroupsStateTest extends TestCase {
	public function testItHandsTheCallersGroupIdsToTheBrowser(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->expects($this->once())->method('getUserGroupIds')->with($user)->willReturn(['adviseurs', 7 => 'leiding']);

		$state = new CurrentUserGroupsState(userSession: $session, groupManager: $groups);

		$this->assertInstanceOf(InitialStateProvider::class, $state);
		$this->assertSame('groups', $state->getKey());
		$this->assertSame(['adviseurs', 'leiding'], $state->getData());
		$this->assertSame('["adviseurs","leiding"]', json_encode($state));
	}//end testItHandsTheCallersGroupIdsToTheBrowser()

	public function testWithoutASessionTheListIsEmpty(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$groups = $this->createMock(IGroupManager::class);
		$groups->expects($this->never())->method('getUserGroupIds');

		$state = new CurrentUserGroupsState(userSession: $session, groupManager: $groups);

		$this->assertSame([], $state->getData());
	}//end testWithoutASessionTheListIsEmpty()

	public function testRegisterProvidesTheState(): void {
		$providers = [];
		$context = $this->createMock(IRegistrationContext::class);
		$context->method('registerInitialStateProvider')->willReturnCallback(
			static function (string $class) use (&$providers): void {
				$providers[] = $class;
			}
		);

		$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
		$app->register($context);

		$this->assertContains(CurrentUserGroupsState::class, $providers);
	}//end testRegisterProvidesTheState()
}//end class
