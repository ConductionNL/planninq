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
 * Tests for SettingsService admin settings methods.
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
     * Test getAdminSettings() returns defaults when no values are stored.
     *
     * @return void
     */
    public function testGetAdminSettingsReturnsDefaults(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default='') use (&$args): string {
                    return $default;
                }
            );

        $result = $this->service->getAdminSettings();

        self::assertArrayHasKey(key: 'default_columns', array: $result);
        self::assertArrayHasKey(key: 'allow_project_creation', array: $result);

        $columns = json_decode($result['default_columns'], true);
        self::assertIsArray(actual: $columns);
        self::assertContains(needle: 'To Do', haystack: $columns);
        self::assertContains(needle: 'Done', haystack: $columns);
        self::assertSame(expected: 'all', actual: $result['allow_project_creation']);

    }//end testGetAdminSettingsReturnsDefaults()

    /**
     * Test that updateSettings() stores only known admin keys and ignores unknown ones.
     *
     * The private setAdminSettings() is exercised via the public updateSettings()
     * entry point, which is the intended caller.
     *
     * @return void
     */
    public function testSetAdminSettingsIgnoresUnknownKeys(): void
    {
        // Expect setValueString to be called exactly once, with 'default_columns' — never with 'unknown_key'.
        $this->appConfig->expects($this->once())
            ->method('setValueString')
            ->with(
                $this->anything(),
                'default_columns',
                '["Sprint","Done"]'
            )
            ->willReturn(true);

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default=''): string {
                    if ($key === 'default_columns') {
                        return '["Sprint","Done"]';
                    }

                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $this->service->updateSettings(
            [
                'default_columns' => '["Sprint","Done"]',
                'unknown_key'     => 'should be ignored',
            ]
        );

        // PHPUnit verifies the expects(once())+with() constraint at test end:
        // if setValueString had been called for 'unknown_key' the test would fail.
    }//end testSetAdminSettingsIgnoresUnknownKeys()

    /**
     * Test that updateSettings() stores allow_project_creation value.
     *
     * The private setAdminSettings() is exercised via the public updateSettings()
     * entry point, which is the intended caller.
     *
     * @return void
     */
    public function testSetAdminSettingsStoresAllowProjectCreation(): void
    {
        // Verify setValueString is called with the correct key/value pair.
        $this->appConfig->expects($this->once())
            ->method('setValueString')
            ->with(
                $this->anything(),
                'allow_project_creation',
                'admins'
            )
            ->willReturn(true);

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default=''): string {
                    if ($key === 'allow_project_creation') {
                        return 'admins';
                    }

                    return $default;
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->service->updateSettings(['allow_project_creation' => 'admins']);

        self::assertSame(expected: 'admins', actual: $result['allow_project_creation']);

    }//end testSetAdminSettingsStoresAllowProjectCreation()

    /**
     * Test isCurrentUserAdmin() returns true when user is in admin group.
     *
     * @return void
     */
    public function testIsCurrentUserAdminReturnsTrueForAdmin(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('admin');

        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

        self::assertTrue(condition: $this->service->isCurrentUserAdmin());

    }//end testIsCurrentUserAdminReturnsTrueForAdmin()

    /**
     * Test isCurrentUserAdmin() returns false when no user is logged in.
     *
     * @return void
     */
    public function testIsCurrentUserAdminReturnsFalseWithoutUser(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        self::assertFalse(condition: $this->service->isCurrentUserAdmin());

    }//end testIsCurrentUserAdminReturnsFalseWithoutUser()

    /**
     * Test getSettings() merges admin settings into the full settings response.
     *
     * @return void
     */
    public function testGetSettingsIncludesAdminKeys(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default=''): string {
                    $values = [
                        'register'               => 'reg-123',
                        'default_columns'        => '["Sprint","Review","Done"]',
                        'allow_project_creation' => 'admins',
                    ];
                    return ($values[$key] ?? $default);
                }
            );

        $this->appManager->method('isInstalled')->with('openregister')->willReturn(true);
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->service->getSettings();

        self::assertArrayHasKey(key: 'default_columns', array: $result);
        self::assertArrayHasKey(key: 'allow_project_creation', array: $result);
        self::assertSame(expected: 'admins', actual: $result['allow_project_creation']);
        self::assertTrue(condition: $result['openregisters']);

    }//end testGetSettingsIncludesAdminKeys()
}//end class
