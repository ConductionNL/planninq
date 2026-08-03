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
use OCA\Planix\Service\DueReminderWindowService;
use OCA\Planix\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
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
     * Mock IConfig.
     *
     * @var IConfig&MockObject
     */
    private IConfig&MockObject $config;

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
        $this->config       = $this->createMock(originalClassName: IConfig::class);
        $this->appManager   = $this->createMock(originalClassName: IAppManager::class);
        $this->container    = $this->createMock(originalClassName: ContainerInterface::class);
        $this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
        $this->userSession  = $this->createMock(originalClassName: IUserSession::class);
        $this->logger       = $this->createMock(originalClassName: LoggerInterface::class);

        // Real (not mocked) collaborator: DueReminderWindowService is a
        // behaviour-preserving move of the former private patchDueReminderWindow()
        // and resolves everything from the SAME mocked appManager/container, so
        // these tests keep exercising the real code path.
        $this->service = new SettingsService(
            appConfig: $this->appConfig,
            config: $this->config,
            appManager: $this->appManager,
            container: $this->container,
            groupManager: $this->groupManager,
            userSession: $this->userSession,
            logger: $this->logger,
            dueReminderWindow: new DueReminderWindowService(
                appManager: $this->appManager,
                container: $this->container,
                logger: $this->logger,
            ),
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
        $stored = [];
        $this->appConfig->expects($this->exactly(count: 1))
            ->method('setValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $value) use (&$stored): bool {
                    $stored[$key] = $value;
                    return true;
                }
            );

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default='') use (&$stored): string {
                    return ($stored[$key] ?? $default);
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

        self::assertArrayHasKey(key: 'default_columns', array: $stored);
        self::assertArrayNotHasKey(key: 'unknown_key', array: $stored);

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
        $stored = [];
        $this->appConfig->method('setValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $value) use (&$stored): bool {
                    $stored[$key] = $value;
                    return true;
                }
            );

        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default='') use (&$stored): string {
                    return ($stored[$key] ?? $default);
                }
            );

        $this->appManager->method('isInstalled')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->service->updateSettings(['allow_project_creation' => 'admins']);

        self::assertSame(expected: 'admins', actual: $stored['allow_project_creation']);
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

    /**
     * Test validateLeadHours() accepts in-range integers and rejects the rest.
     *
     * @return void
     */
    public function testValidateLeadHoursBounds(): void
    {
        self::assertSame(expected: 1, actual: $this->service->validateLeadHours('1'));
        self::assertSame(expected: 24, actual: $this->service->validateLeadHours('24'));
        self::assertSame(expected: 336, actual: $this->service->validateLeadHours('336'));

        self::assertNull(actual: $this->service->validateLeadHours('0'));
        self::assertNull(actual: $this->service->validateLeadHours('337'));
        self::assertNull(actual: $this->service->validateLeadHours('1000'));
        self::assertNull(actual: $this->service->validateLeadHours('abc'));
        self::assertNull(actual: $this->service->validateLeadHours(''));
        self::assertNull(actual: $this->service->validateLeadHours('12.5'));
        self::assertNull(actual: $this->service->validateLeadHours('-5'));

    }//end testValidateLeadHoursBounds()

    /**
     * Test getDueReminderLeadHours() returns the default 24 when unset.
     *
     * @return void
     */
    public function testGetDueReminderLeadHoursDefault(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default=''): string {
                    return $default;
                }
            );

        self::assertSame(expected: 24, actual: $this->service->getDueReminderLeadHours());

    }//end testGetDueReminderLeadHoursDefault()

    /**
     * Test getDueReminderLeadHours() returns a stored, in-range value.
     *
     * @return void
     */
    public function testGetDueReminderLeadHoursStored(): void
    {
        $this->appConfig->method('getValueString')
            ->willReturnCallback(
                function (string $appId, string $key, string $default=''): string {
                    return ($key === 'due_reminder_lead_hours') ? '48' : $default;
                }
            );

        self::assertSame(expected: 48, actual: $this->service->getDueReminderLeadHours());

    }//end testGetDueReminderLeadHoursStored()

    /**
     * Test leadHoursToDuration() formats the ISO-8601 window.
     *
     * @return void
     */
    public function testLeadHoursToDuration(): void
    {
        // Moved to DueReminderWindowService alongside patch(); same assertions.
        $windowService = new DueReminderWindowService(
            appManager: $this->appManager,
            container: $this->container,
            logger: $this->logger,
        );

        self::assertSame(expected: 'PT24H', actual: $windowService->leadHoursToDuration(24));
        self::assertSame(expected: 'PT1H', actual: $windowService->leadHoursToDuration(1));
        self::assertSame(expected: 'PT336H', actual: $windowService->leadHoursToDuration(336));

    }//end testLeadHoursToDuration()

    /**
     * Test setNotifyDueReminder(false) stores false AND writes the OR override.
     *
     * @return void
     */
    public function testSetNotifyDueReminderOffWritesOverride(): void
    {
        $this->appManager->method('isInstalled')->with('openregister')->willReturn(true);

        $stored = [];
        $this->config->expects($this->once())
            ->method('setUserValue')
            ->willReturnCallback(
                function (string $userId, string $app, string $key, string $value) use (&$stored): void {
                    $stored[$key] = $value;
                }
            );

        $prefService = new TestPreferenceServiceSpy();
        $this->container->method('get')->willReturn($prefService);

        $this->service->setNotifyDueReminder(userId: 'bob', enabled: false);

        self::assertSame(expected: 'false', actual: $stored['notify_due_reminder']);
        self::assertCount(expectedCount: 1, haystack: $prefService->calls);
        self::assertSame(expected: 'bob', actual: $prefService->calls[0]['userId']);
        self::assertSame(expected: 'task', actual: $prefService->calls[0]['schemaSlug']);
        self::assertSame(expected: 'taskDueSoon', actual: $prefService->calls[0]['notificationKey']);
        self::assertSame(expected: ['enabled' => false], actual: $prefService->calls[0]['override']);

    }//end testSetNotifyDueReminderOffWritesOverride()

    /**
     * Test setNotifyDueReminder(true) stores true AND clears the OR override.
     *
     * @return void
     */
    public function testSetNotifyDueReminderOnClearsOverride(): void
    {
        $this->appManager->method('isInstalled')->with('openregister')->willReturn(true);

        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with('carol', Application::APP_ID, 'notify_due_reminder', 'true');

        $prefService = new TestPreferenceServiceSpy();
        $this->container->method('get')->willReturn($prefService);

        $this->service->setNotifyDueReminder(userId: 'carol', enabled: true);

        self::assertCount(expectedCount: 1, haystack: $prefService->calls);
        self::assertNull(actual: $prefService->calls[0]['override']);

    }//end testSetNotifyDueReminderOnClearsOverride()

    /**
     * Test that the OR-unavailable path still stores the IConfig value and
     * skips the override (no exception, returns false from the writer).
     *
     * @return void
     */
    public function testSetNotifyDueReminderOpenRegisterUnavailable(): void
    {
        $this->appManager->method('isInstalled')->with('openregister')->willReturn(false);

        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with('dave', Application::APP_ID, 'notify_due_reminder', 'false');

        // Container::get must never be reached when OR is unavailable.
        $this->container->expects($this->never())->method('get');

        $this->service->setNotifyDueReminder(userId: 'dave', enabled: false);

        self::assertFalse(condition: $this->service->writeDueReminderOverride('dave', ['enabled' => false]));

    }//end testSetNotifyDueReminderOpenRegisterUnavailable()

    /**
     * Test isNotifyDueReminderEnabled() resolves the stored per-user value (default on).
     *
     * @return void
     */
    public function testGetNotifyDueReminderDefaultsOn(): void
    {
        $this->config->method('getUserValue')
            ->willReturnCallback(
                function (string $userId, string $app, string $key, string $default=''): string {
                    return $default;
                }
            );

        self::assertTrue(condition: $this->service->isNotifyDueReminderEnabled('eve'));

    }//end testGetNotifyDueReminderDefaultsOn()

    /**
     * Test isNotifyDueReminderEnabled() returns false for a stored opt-out.
     *
     * @return void
     */
    public function testGetNotifyDueReminderStoredOff(): void
    {
        $this->config->method('getUserValue')->willReturn('false');

        self::assertFalse(condition: $this->service->isNotifyDueReminderEnabled('frank'));

    }//end testGetNotifyDueReminderStoredOff()
}//end class

/**
 * Test double recording NotificationPreferenceService::setOverride calls.
 */
class TestPreferenceServiceSpy
{

    /**
     * Recorded setOverride calls.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $calls = [];

    /**
     * Record a setOverride call.
     *
     * @param string                    $userId          The user UID.
     * @param string                    $schemaSlug      The schema slug.
     * @param string                    $notificationKey The notification key.
     * @param array<string,mixed>|null  $override        The override body or null.
     *
     * @return void
     */
    public function setOverride(string $userId, string $schemaSlug, string $notificationKey, ?array $override): void
    {
        $this->calls[] = [
            'userId'          => $userId,
            'schemaSlug'      => $schemaSlug,
            'notificationKey' => $notificationKey,
            'override'        => $override,
        ];

    }//end setOverride()
}//end class
