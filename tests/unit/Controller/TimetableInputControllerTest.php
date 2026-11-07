<?php

/**
 * Tests for the sheet upload endpoint (timetabling-generator task 2.2).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\TimetableInputController;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableCsvParser;
use OCA\Planninq\Service\TimetableGridService;
use OCA\Planninq\Service\TimetableInputBuilder;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Admins only; nothing is kept when a line is refused.
 */
class TimetableInputControllerTest extends TestCase {

	/**
	 * The app config values, in memory.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * A controller with the real parser and builder, the given body and caller.
	 *
	 * @param array<string,mixed> $body  The request parameters.
	 * @param bool                $admin Whether the caller is an admin.
	 *
	 * @return TimetableInputController
	 */
	private function controller(array $body, bool $admin): TimetableInputController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key): mixed => ($body[$key] ?? null));

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = '', bool $lazy = false): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false): bool {
				$this->stored[$key] = $value;
				return true;
			}
		);

		$settings = $this->createMock(SettingsService::class);
		$settings->method('isCurrentUserAdmin')->willReturn($admin);

		return new TimetableInputController(
			request: $request,
			parser: new TimetableCsvParser(),
			inputBuilder: new TimetableInputBuilder(dispatcher: $this->createMock(IEventDispatcher::class), appConfig: $config, grid: new TimetableGridService(appConfig: $config)),
			settingsService: $settings,
		);
	}//end controller()

	/**
	 * The two sheets of the spec's school.
	 *
	 * @return array<string,string>
	 */
	private function sheets(): array {
		return [
			'rooms'      => "reference,capacity,type\nr-101,30,classroom\n",
			'activities' => "group,subject,teacher,lessons per week,lesson length,room type\n3A,English,klaas,3,1,classroom\n3A,Maths,noor,2,2,classroom\n",
		];
	}//end sheets()

	/**
	 * An admin uploads both sheets: they are kept and counted.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testAnAdminUploadIsKept(): void {
		$response = $this->controller(body: $this->sheets(), admin: true)->upload();

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(expected: ['rooms' => 1, 'activities' => 2, 'lessons' => 5], actual: $response->getData());
		self::assertCount(expectedCount: 2, haystack: json_decode($this->stored['timetable_csv_activities'], true));
		self::assertSame(expected: 'r-101', actual: json_decode($this->stored['timetable_csv_rooms'], true)[0]['reference']);
	}//end testAnAdminUploadIsKept()

	/**
	 * A non-admin is refused and nothing is kept; the route is admin-only.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testANonAdminIsRefused(): void {
		$response = $this->controller(body: $this->sheets(), admin: false)->upload();

		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
		self::assertSame(expected: [], actual: $this->stored);
		self::assertNotSame(expected: [], actual: (new ReflectionMethod(TimetableInputController::class, 'upload'))->getAttributes(AuthorizedAdminSetting::class));
	}//end testANonAdminIsRefused()

	/**
	 * A refused line keeps nothing and answers with the lines.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testARefusedLineKeepsNothing(): void {
		$body = ['activities' => "group,subject,teacher,lessons per week,lesson length,room type\n3A,Swimming,piet,1,1,pool\n"] + $this->sheets();
		$response = $this->controller(body: $body, admin: true)->upload();

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		self::assertSame(expected: 'unknown-room-type', actual: $response->getData()['errors']['activities'][0]['code']);
		self::assertSame(expected: 2, actual: $response->getData()['errors']['activities'][0]['line']);
		self::assertSame(expected: [], actual: $this->stored);

		$missing = $this->controller(body: ['rooms' => 'x'], admin: true)->upload();
		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $missing->getStatus());
	}//end testARefusedLineKeepsNothing()
}//end class
