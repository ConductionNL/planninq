<?php

/**
 * Unit tests for SettingsController.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\SettingsController;
use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\RiskScaleService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableGridService;
use OCP\IAppConfig;
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
class SettingsControllerTest extends TestCase {

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
	 * The mocked risk scale service.
	 *
	 * @var RiskScaleService&MockObject
	 */
	private RiskScaleService&MockObject $riskScale;

	/**
	 * The mocked export to Nextcloud Tasks.
	 *
	 * @var \OCA\Planninq\Service\TaskCalendarExportService&MockObject
	 */
	private \OCA\Planninq\Service\TaskCalendarExportService&MockObject $taskExport;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);
		$this->registerImport = $this->createMock(originalClassName: RegisterImportService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->riskScale = $this->createMock(originalClassName: RiskScaleService::class);
		$this->taskExport = $this->createMock(originalClassName: \OCA\Planninq\Service\TaskCalendarExportService::class);

		$this->controller = new SettingsController(
			request: $this->request,
			settingsService: $this->settingsService,
			registerImport: $this->registerImport,
			userSession: $this->userSession,
			riskScale: $this->riskScale,
			timetableGrid: new TimetableGridService(appConfig: $this->appConfig()),
			switches: $this->createMock(\OCA\Planninq\Service\NotificationSwitchService::class),
			taskExport: $this->taskExport,
		);

	}//end setUp()

	/**
	 * Test that updateUser() rejects an unauthenticated request.
	 *
	 * @return void
	 */
	public function testUpdateUserRequiresAuthentication(): void {
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
	public function testUpdateUserDelegatesToService(): void {
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
	 * Test that updateUser() applies the export switch and answers with its state (planning-calendar task 2.1).
	 *
	 * @return void
	 */
	public function testUpdateUserAppliesTheTaskExportSwitch(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->request->method('getParams')->willReturn(['export_tasks_to_caldav' => true]);
		$this->settingsService->method('updateUserSettings')->willReturn([]);

		$this->taskExport->expects($this->once())->method('apply')->with('alice', ['export_tasks_to_caldav' => true]);
		$this->taskExport->method('values')->willReturn(['export_tasks_to_caldav' => true, 'caldavAvailable' => true]);

		$data = $this->controller->updateUser()->getData();

		self::assertTrue($data['config']['export_tasks_to_caldav']);
		self::assertTrue($data['config']['caldavAvailable']);

	}//end testUpdateUserAppliesTheTaskExportSwitch()

	/**
	 * Test that index() returns a JSONResponse containing the settings from the service.
	 *
	 * @return void
	 */
	public function testIndexReturnsJsonResponseWithSettings(): void {
		$settings = [
			'register' => 'some-uuid',
			'default_columns' => '["To Do","In Progress","Review","Done"]',
			'allow_project_creation' => 'all',
			'openregisters' => true,
			'isAdmin' => false,
		];

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);

		$this->settingsService->expects($this->once())
			->method('getSettings')
			->willReturn($settings);

		$result = $this->controller->index();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(
			expected: $settings + ['timetable_period_grid' => TimetableGridService::DEFAULT_GRID, 'timetable_generator_budget_minutes' => '10'],
			actual: $result->getData()
		);

	}//end testIndexReturnsJsonResponseWithSettings()

	/**
	 * Test that create() returns 403 when the current user is not an admin.
	 *
	 * @return void
	 */
	public function testCreateReturnsForbiddenForNonAdmin(): void {
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
	 * (PUT) against the same URL, and planninq implemented only the POST — so PUT
	 * resolved to nothing. Asserting the admin gate here is the point: a
	 * delegating method that quietly lost the guard would be a worse bug than
	 * the missing method it replaced.
	 *
	 * @return void
	 */
	public function testUpdateReturnsForbiddenForNonAdmin(): void {
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
	public function testUpdateCallsUpdateSettingsForAdmin(): void {
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
	public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void {
		$params = ['register' => 'new-uuid', 'default_columns' => '["Backlog","Doing","Done"]'];
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
	public function testLoadReturnsForbiddenForNonAdmin(): void {
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
	public function testLoadReturnsConfigurationResult(): void {
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
	/**
	 * A smaller risk scale is refused while risks use a higher level, and nothing is saved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testCreateRefusesASmallerRiskScaleWhileRisksUseAHigherLevel(): void {
		$scale = json_encode(['levels' => 4, 'likelihood' => ['a', 'b', 'c', 'd'], 'impact' => ['a', 'b', 'c', 'd'], 'thresholds' => ['medium' => 4, 'high' => 9]]);
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->request->method('getParams')->willReturn(['risk_scale' => $scale]);
		$this->riskScale->method('normalise')->willReturn(json_decode((string)$scale, true));
		$this->riskScale->method('conflict')->with(4)->willReturn(['count' => 2, 'level' => 5]);
		$this->riskScale->method('refusal')->with(2, 5)->willReturn('2 risks use level 5. Change them first.');
		$this->settingsService->expects($this->never())->method('updateSettings');

		$result = $this->controller->create();

		self::assertSame(expected: Http::STATUS_CONFLICT, actual: $result->getStatus());
		self::assertSame(expected: '2 risks use level 5. Change them first.', actual: $result->getData()['message']);
		self::assertSame(expected: 'risk-scale-in-use', actual: $result->getData()['error']);
		self::assertSame(expected: 2, actual: $result->getData()['count']);
		self::assertSame(expected: 5, actual: $result->getData()['level']);

	}//end testCreateRefusesASmallerRiskScaleWhileRisksUseAHigherLevel()

	/**
	 * A malformed risk scale is refused with 400.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testCreateRefusesAMalformedRiskScale(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->request->method('getParams')->willReturn(['risk_scale' => '{"levels": 9}']);
		$this->riskScale->method('normalise')->willReturn(null);
		$this->riskScale->expects($this->never())->method('conflict');
		$this->settingsService->expects($this->never())->method('updateSettings');

		$result = $this->controller->create();

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
		self::assertSame(expected: 'risk-scale-invalid', actual: $result->getData()['error']);

	}//end testCreateRefusesAMalformedRiskScale()

	/**
	 * A valid risk scale no risk is in the way of is saved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function testCreateSavesAValidRiskScale(): void {
		$scale = json_encode(['levels' => 3, 'likelihood' => ['Low', 'Medium', 'High'], 'impact' => ['Low', 'Medium', 'High'], 'thresholds' => ['medium' => 3, 'high' => 6]]);
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->request->method('getParams')->willReturn(['risk_scale' => $scale]);
		$this->riskScale->method('normalise')->willReturn(json_decode((string)$scale, true));
		$this->riskScale->method('conflict')->with(3)->willReturn(null);
		$this->settingsService->expects($this->once())->method('updateSettings')
			->with(['risk_scale' => $scale])
			->willReturn(['risk_scale' => $scale]);

		$result = $this->controller->create();

		self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());

	}//end testCreateSavesAValidRiskScale()

	/**
	 * App config values stored by the timetable grid, in memory.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * An IAppConfig that keeps values in $this->stored.
	 *
	 * @return IAppConfig
	 */
	private function appConfig(): IAppConfig {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->stored[$key] = $value;
				return true;
			}
		);
		return $config;
	}//end appConfig()

	/**
	 * Task 1.2: the settings carry the week grid and budget, and an admin saves them; a refused grid is not stored.
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-1.2
	 *
	 * @return void
	 */
	public function testTheSettingsCarryTheTimetableGrid(): void {
		$this->userSession->method('getUser')->willReturn($this->createMock(originalClassName: \OCP\IUser::class));
		$this->settingsService->method('getSettings')->willReturn(['isAdmin' => true]);
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->settingsService->method('updateSettings')->willReturn(['isAdmin' => true]);

		$read = $this->controller->index()->getData();
		self::assertSame(expected: TimetableGridService::DEFAULT_GRID, actual: $read['timetable_period_grid']);
		self::assertSame(expected: '10', actual: $read['timetable_generator_budget_minutes']);

		$this->request->method('getParams')->willReturnOnConsecutiveCalls(
			['timetable_period_grid' => '{"days":["mon"],"periods":[{"start":"09:00","end":"08:00"}]}'],
			['timetable_period_grid' => '{"days":["tue","mon"],"periods":[{"start":"08:00","end":"08:45"}]}', 'timetable_generator_budget_minutes' => '20'],
		);
		$this->controller->create();
		self::assertSame(expected: [], actual: $this->stored, message: 'a period that ends before it starts is refused');

		$saved = $this->controller->create()->getData()['config'];
		self::assertSame(expected: '{"days":["mon","tue"],"periods":[{"start":"08:00","end":"08:45"}]}', actual: $saved['timetable_period_grid']);
		self::assertSame(expected: '20', actual: $saved['timetable_generator_budget_minutes']);
	}//end testTheSettingsCarryTheTimetableGrid()
}//end class
