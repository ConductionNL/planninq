<?php

/**
 * Unit tests for LabelController.
 *
 * Verifies the admin-only authorization posture (non-admins get 403 on both the
 * usage listing and the cascade delete), the happy-path responses (label list /
 * tasksUpdated), and the error mapping for an unavailable OpenRegister.
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
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\LabelController;
use OCA\Planix\Service\LabelService;
use OCA\Planix\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LabelController.
 */
class LabelControllerTest extends TestCase {

	/**
	 * The controller under test.
	 *
	 * @var LabelController
	 */
	private LabelController $controller;

	/**
	 * Mock IRequest.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mock LabelService.
	 *
	 * @var LabelService&MockObject
	 */
	private LabelService&MockObject $labelService;

	/**
	 * Mock SettingsService.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settingsService;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->labelService = $this->createMock(originalClassName: LabelService::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);

		$this->controller = new LabelController(
			request: $this->request,
			labelService: $this->labelService,
			settingsService: $this->settingsService,
		);

	}//end setUp()

	/**
	 * index() returns 403 for a non-admin and never touches the service.
	 *
	 * @return void
	 */
	public function testIndexRejectsNonAdmin(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(false);
		$this->labelService->expects($this->never())->method('listWithUsage');

		$response = $this->controller->index();

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testIndexRejectsNonAdmin()

	/**
	 * index() returns the labels for an admin.
	 *
	 * @return void
	 */
	public function testIndexReturnsLabelsForAdmin(): void {
		$labels = [['id' => 'L1', 'title' => 'Bug', 'usageCount' => 2]];
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->labelService->method('listWithUsage')->willReturn($labels);

		$response = $this->controller->index();

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['labels' => $labels], $response->getData());
	}//end testIndexReturnsLabelsForAdmin()

	/**
	 * destroy() returns 403 for a non-admin and never runs the cascade.
	 *
	 * @return void
	 */
	public function testDestroyRejectsNonAdmin(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(false);
		$this->labelService->expects($this->never())->method('deleteWithCascade');

		$response = $this->controller->destroy(id: 'L1');

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testDestroyRejectsNonAdmin()

	/**
	 * destroy() runs the cascade and reports tasksUpdated for an admin.
	 *
	 * @return void
	 */
	public function testDestroyRunsCascadeForAdmin(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->labelService->method('deleteWithCascade')->with('L1')->willReturn(['tasksUpdated' => 3]);

		$response = $this->controller->destroy(id: 'L1');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['success' => true, 'tasksUpdated' => 3], $response->getData());
	}//end testDestroyRunsCascadeForAdmin()

	/**
	 * destroy() maps an OpenRegister-unavailable RuntimeException to 503.
	 *
	 * @return void
	 */
	public function testDestroyMapsUnavailableToServiceUnavailable(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->labelService->method('deleteWithCascade')
			->willThrowException(new \RuntimeException('OpenRegister is not available.'));

		$response = $this->controller->destroy(id: 'L1');

		self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
	}//end testDestroyMapsUnavailableToServiceUnavailable()
}//end class
