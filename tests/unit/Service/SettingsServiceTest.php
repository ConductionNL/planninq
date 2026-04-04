<?php

/**
 * Unit tests for SettingsService.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for SettingsService.
 */
class SettingsServiceTest extends TestCase
{

    /**
     * The service under test.
     *
     * @var SettingsService
     */
    private SettingsService $service;

    /**
     * Mock IAppConfig.
     *
     * @var IAppConfig&MockObject
     */
    private IAppConfig&MockObject $appConfig;

    /**
     * Mock IAppManager.
     *
     * @var IAppManager&MockObject
     */
    private IAppManager&MockObject $appManager;

    /**
     * Mock ContainerInterface.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * Mock IGroupManager.
     *
     * @var IGroupManager&MockObject
     */
    private IGroupManager&MockObject $groupManager;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Mock LoggerInterface.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->appConfig    = $this->createMock(originalClassName: IAppConfig::class);
        $this->appManager   = $this->createMock(originalClassName: IAppManager::class);
        $this->container    = $this->createMock(originalClassName: ContainerInterface::class);
        $this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
        $this->userSession  = $this->createMock(originalClassName: IUserSession::class);
        $this->logger       = $this->createMock(originalClassName: LoggerInterface::class);

        $this->service = new SettingsService(
            appConfig: $this->appConfig,
            appManager: $this->appManager,
            container: $this->container,
            groupManager: $this->groupManager,
            userSession: $this->userSession,
            logger: $this->logger,
        );

    }//end setUp()

    /**
     * Test that getAdminSettings() returns default_columns as an array with spec defaults.
     *
     * @return void
     */
    public function testGetAdminSettingsReturnsDefaultColumns(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                static function (string $app, string $key, string $default=''): string {
                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->service->getAdminSettings();

        self::assertArrayHasKey(key: 'default_columns', array: $result);
        self::assertIsArray(actual: $result['default_columns']);
        self::assertSame(expected: ['To Do', 'In Progress', 'Review', 'Done'], actual: $result['default_columns']);

    }//end testGetAdminSettingsReturnsDefaultColumns()

    /**
     * Test that getAdminSettings() returns allow_project_creation default 'all'.
     *
     * @return void
     */
    public function testGetAdminSettingsReturnsAllowProjectCreationDefault(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                static function (string $app, string $key, string $default=''): string {
                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->service->getAdminSettings();

        self::assertArrayHasKey(key: 'allow_project_creation', array: $result);
        self::assertSame(expected: 'all', actual: $result['allow_project_creation']);

    }//end testGetAdminSettingsReturnsAllowProjectCreationDefault()

    /**
     * Test that setAdminSettings() persists default_columns as JSON string.
     *
     * @return void
     */
    public function testSetAdminSettingsPersistsDefaultColumnsAsJson(): void
    {
        $columns = ['Backlog', 'Doing', 'Done'];

        $this->appConfig->expects($this->once())
            ->method('setValueString')
            ->with(
                Application::APP_ID,
                'default_columns',
                json_encode($columns)
            );

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                static function (string $app, string $key, string $default=''): string {
                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $this->service->setAdminSettings(['default_columns' => $columns]);

    }//end testSetAdminSettingsPersistsDefaultColumnsAsJson()

    /**
     * Test that setAdminSettings() silently ignores unknown keys.
     *
     * @return void
     */
    public function testSetAdminSettingsIgnoresUnknownKeys(): void
    {
        $this->appConfig->expects($this->never())
            ->method('setValueString');

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                static function (string $app, string $key, string $default=''): string {
                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $this->service->setAdminSettings(['unknown_key' => 'value', 'another_unknown' => 'data']);

    }//end testSetAdminSettingsIgnoresUnknownKeys()

    /**
     * Test that isCurrentUserAdmin() returns true when user is in admin group.
     *
     * @return void
     */
    public function testIsCurrentUserAdminReturnsTrueForAdmin(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('admin_user');

        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('admin_user')->willReturn(true);

        self::assertTrue(condition: $this->service->isCurrentUserAdmin());

    }//end testIsCurrentUserAdminReturnsTrueForAdmin()

    /**
     * Test that isCurrentUserAdmin() returns false when no user is logged in.
     *
     * @return void
     */
    public function testIsCurrentUserAdminReturnsFalseWhenNoUser(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        self::assertFalse(condition: $this->service->isCurrentUserAdmin());

    }//end testIsCurrentUserAdminReturnsFalseWhenNoUser()
}//end class
