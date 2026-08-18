<?php

/**
 * Unit tests for SettingsController.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Controller
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

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\SettingsController;
use OCA\Planix\Service\RegisterImportService;
use OCA\Planix\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SettingsController.
 */
class SettingsControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var SettingsController
     */
    private SettingsController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock SettingsService.
     *
     * @var SettingsService&MockObject
     */
    private SettingsService&MockObject $settingsService;

    /**
     * The mocked register import service.
     *
     * @var RegisterImportService&MockObject
     */
    private RegisterImportService&MockObject $registerImport;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request               = $this->createMock(originalClassName: IRequest::class);
        $this->settingsService       = $this->createMock(originalClassName: SettingsService::class);
        $this->registerImport = $this->createMock(originalClassName: RegisterImportService::class);
        $this->userSession           = $this->createMock(originalClassName: IUserSession::class);

        $this->controller = new SettingsController(
            request: $this->request,
            settingsService: $this->settingsService,
            registerImport: $this->registerImport,
            userSession: $this->userSession,
        );

    }//end setUp()

    /**
     * Test that updateUser() rejects an unauthenticated request.
     *
     * @return void
     */
    public function testUpdateUserRequiresAuthentication(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->updateUser();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $response);
        self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $response->getStatus());

    }//end testUpdateUserRequiresAuthentication()

    /**
     * Test that updateUser() delegates to updateUserSettings for an authed user.
     *
     * @return void
     */
    public function testUpdateUserDelegatesToService(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->request->method('getParams')->willReturn(['notify_due_reminder' => false]);

        $this->settingsService->expects($this->once())
            ->method('updateUserSettings')
            ->with('alice', ['notify_due_reminder' => false])
            ->willReturn(['notify_due_reminder' => false]);

        $response = $this->controller->updateUser();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $response);
        self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

    }//end testUpdateUserDelegatesToService()

    /**
     * Test that index() returns a JSONResponse containing the settings from the service.
     *
     * @return void
     */
    public function testIndexReturnsJsonResponseWithSettings(): void
    {
        $settings = [
            'register'               => 'some-uuid',
            'default_columns'        => '["To Do","In Progress","Review","Done"]',
            'allow_project_creation' => 'all',
            'openregisters'          => true,
            'isAdmin'                => false,
        ];

        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn($settings);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: $settings, actual: $result->getData());

    }//end testIndexReturnsJsonResponseWithSettings()

    /**
     * Test that create() returns 403 when the current user is not an admin.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $this->settingsService->expects($this->never())
            ->method('updateSettings');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsForbiddenForNonAdmin()

    /**
     * update() is the PUT face of the same write, and carries the same guard.
     *
     * `Routes::standard()` declares settings#create (POST) and settings#update
     * (PUT) against the same URL, and planix implemented only the POST — so PUT
     * resolved to nothing. Asserting the admin gate here is the point: a
     * delegating method that quietly lost the guard would be a worse bug than
     * the missing method it replaced.
     *
     * @return void
     */
    public function testUpdateReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $this->settingsService->expects($this->never())
            ->method('updateSettings');

        $result = $this->controller->update();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testUpdateReturnsForbiddenForNonAdmin()

    /**
     * update() performs the same write as create() for an admin.
     *
     * @return void
     */
    public function testUpdateCallsUpdateSettingsForAdmin(): void
    {
        $params = ['register' => 'put-uuid'];

        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->settingsService->expects($this->once())
            ->method('updateSettings')
            ->with($params)
            ->willReturn($params);

        $result = $this->controller->update();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertTrue(condition: $result->getData()['success']);

    }//end testUpdateCallsUpdateSettingsForAdmin()

    /**
     * Test that create() calls updateSettings with request params and returns success for admins.
     *
     * @return void
     */
    public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void
    {
        $params  = ['register' => 'new-uuid', 'default_columns' => '["Backlog","Doing","Done"]'];
        $updated = array_merge($params, ['openregisters' => true, 'isAdmin' => true]);

        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->settingsService->expects($this->once())
            ->method('updateSettings')
            ->with($params)
            ->willReturn($updated);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);
        self::assertArrayHasKey(key: 'config', array: $result->getData());

    }//end testCreateCallsUpdateSettingsAndReturnsSuccess()

    /**
     * Test that load() returns 403 when the current user is not an admin.
     *
     * @return void
     */
    public function testLoadReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $this->registerImport->expects($this->never())
            ->method('reload');

        $result = $this->controller->load();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testLoadReturnsForbiddenForNonAdmin()

    /**
     * Test that load() returns the result of reloadConfiguration for admins.
     *
     * @return void
     */
    public function testLoadReturnsConfigurationResult(): void
    {
        $loadResult = [
            'success' => true,
            'message' => 'Configuration imported successfully.',
            'version' => '0.1.0',
        ];

        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        // RegisterImportService::reload() IS the forced import — the former
        // SettingsService::loadConfiguration(force: true). The force intent now
        // lives in the method name, so there are no arguments left to assert.
        $this->registerImport->expects($this->once())
            ->method('reload')
            ->willReturn($loadResult);

        $result = $this->controller->load();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertTrue(condition: $result->getData()['success']);

    }//end testLoadReturnsConfigurationResult()
}//end class
