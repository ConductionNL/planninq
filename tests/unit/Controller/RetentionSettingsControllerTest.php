<?php

/**
 * Tests for RetentionSettingsController: the admin reads and saves the
 * retention category per planninq schema; anyone else is refused.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\RetentionSettingsController;
use OCA\Planninq\Service\RetentionCategoryService;
use OCA\Planninq\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Controller\RetentionSettingsController
 */
class RetentionSettingsControllerTest extends TestCase {

	/**
	 * The controller over the given collaborators.
	 *
	 * @param bool                     $admin     Whether the caller is an admin.
	 * @param RetentionCategoryService $retention The service.
	 * @param mixed                    $picks     The `picks` request parameter.
	 *
	 * @return RetentionSettingsController
	 */
	private function controller(bool $admin, RetentionCategoryService $retention, mixed $picks = []): RetentionSettingsController {
		$settings = $this->createMock(SettingsService::class);
		$settings->method('isCurrentUserAdmin')->willReturn($admin);
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->with('picks', [])->willReturn($picks);

		return new RetentionSettingsController($request, $retention, $settings);
	}//end controller()

	/**
	 * A non-admin can neither read nor save.
	 *
	 * @return void
	 */
	public function testANonAdminIsRefused(): void {
		$retention = $this->createMock(RetentionCategoryService::class);
		$retention->expects($this->never())->method('categories');
		$retention->expects($this->never())->method('save');

		$controller = $this->controller(admin: false, retention: $retention, picks: ['project' => 'B1']);

		self::assertSame(Http::STATUS_FORBIDDEN, $controller->index()->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $controller->update()->getStatus());
		self::assertSame(['error' => 'Admin privileges required.'], $controller->update()->getData());
	}//end testANonAdminIsRefused()

	/**
	 * The admin reads the categories and the current pick.
	 *
	 * @return void
	 */
	public function testTheAdminReadsTheCategoriesAndThePick(): void {
		$retention = $this->createMock(RetentionCategoryService::class);
		$retention->method('categories')->willReturn([['category' => 'B1', 'description' => 'Projectdossier', 'period' => 'P1Y', 'action' => 'vernietigen']]);
		$retention->method('picks')->willReturn(['project' => 'B1', 'task' => '', 'plannedTimeEntry' => '']);

		$response = $this->controller(admin: true, retention: $retention)->index();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('B1', $response->getData()['categories'][0]['category']);
		self::assertSame(['project' => 'B1', 'task' => '', 'plannedTimeEntry' => ''], $response->getData()['picks']);
	}//end testTheAdminReadsTheCategoriesAndThePick()

	/**
	 * The admin's pick goes to the service as sent.
	 *
	 * @return void
	 */
	public function testTheAdminSavesThePick(): void {
		$retention = $this->createMock(RetentionCategoryService::class);
		$retention->expects($this->once())->method('save')->with(['project' => 'B1', 'task' => 'B1'])
			->willReturn(['picks' => ['project' => 'B1', 'task' => 'B1', 'plannedTimeEntry' => ''], 'refused' => [], 'applied' => true]);

		$response = $this->controller(admin: true, retention: $retention, picks: ['project' => 'B1', 'task' => 'B1'])->update();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertTrue($response->getData()['applied']);
	}//end testTheAdminSavesThePick()

	/**
	 * A refused category answers 400 with the refused schemas; a non-object body never reaches the service.
	 *
	 * @return void
	 */
	public function testARefusedOrMalformedPickAnswersBadRequest(): void {
		$retention = $this->createMock(RetentionCategoryService::class);
		$retention->method('save')->willReturn(['picks' => ['project' => '', 'task' => '', 'plannedTimeEntry' => ''], 'refused' => ['task'], 'applied' => false]);

		$response = $this->controller(admin: true, retention: $retention, picks: ['task' => 'Z9'])->update();
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['task'], $response->getData()['refused']);
		self::assertSame('Not a category of the selection list.', $response->getData()['error']);

		$never = $this->createMock(RetentionCategoryService::class);
		$never->expects($this->never())->method('save');
		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller(admin: true, retention: $never, picks: 'B1')->update()->getStatus());
	}//end testARefusedOrMalformedPickAnswersBadRequest()
}//end class
