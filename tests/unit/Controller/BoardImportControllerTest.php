<?php

/**
 * Tests for BoardImportController.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Planninq\Controller\BoardImportController;
use OCA\Planninq\Service\BoardExportImportService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * The upload reaches the import as name and content, refusals become 4xx, and both endpoints are admin-only.
 *
 * @covers \OCA\Planninq\Controller\BoardImportController
 */
class BoardImportControllerTest extends TestCase {

	/**
	 * A temporary upload.
	 *
	 * @var string
	 */
	private string $tmp = '';

	/**
	 * Write the uploaded file.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->tmp = (string)tempnam(sys_get_temp_dir(), 'board');
		file_put_contents($this->tmp, '{"name": "Vergunningen"}');
	}//end setUp()

	/**
	 * Remove the uploaded file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		@unlink($this->tmp);
	}//end tearDown()

	/**
	 * The controller over a request carrying the given upload.
	 *
	 * @param array|null               $file    The `file` upload.
	 * @param BoardExportImportService $imports The import service double.
	 *
	 * @return BoardImportController
	 */
	private function controller(?array $file, BoardExportImportService $imports): BoardImportController {
		$request = $this->createMock(IRequest::class);
		$request->method('getUploadedFile')->with('file')->willReturn($file);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new BoardImportController(request: $request, imports: $imports, userSession: $session, logger: $this->createMock(LoggerInterface::class));
	}//end controller()

	/**
	 * An upload as PHP describes it.
	 *
	 * @param int $size The reported size.
	 *
	 * @return array<string,mixed>
	 */
	private function upload(int $size=24): array {
		return ['name' => 'Vergunningen.json', 'tmp_name' => $this->tmp, 'error' => UPLOAD_ERR_OK, 'size' => $size];
	}//end upload()

	/**
	 * The uploaded file reaches the import with its name, as the signed-in admin, and answers 201.
	 *
	 * @return void
	 */
	public function testTheUploadIsImportedAsTheAdmin(): void {
		$imports = $this->createMock(BoardExportImportService::class);
		$imports->expects(self::once())->method('import')
			->with('trello', ['name' => 'Vergunningen.json', 'content' => '{"name": "Vergunningen"}'], 'admin')
			->willReturn(['project' => 'p1', 'columns' => 3, 'tasks' => 4, 'losses' => []]);

		$response = $this->controller($this->upload(), $imports)->import('trello');

		self::assertSame(201, $response->getStatus());
		self::assertSame(['project' => 'p1', 'columns' => 3, 'tasks' => 4, 'losses' => []], $response->getData());
	}//end testTheUploadIsImportedAsTheAdmin()

	/**
	 * No file, a failed upload or an oversized file never reach the import.
	 *
	 * @return void
	 */
	public function testAMissingOrOversizedFileIsRefused(): void {
		$imports = $this->createMock(BoardExportImportService::class);
		$imports->expects(self::never())->method('import');

		self::assertSame(400, $this->controller(null, $imports)->import('trello')->getStatus());
		self::assertSame(400, $this->controller(['error' => UPLOAD_ERR_PARTIAL] + $this->upload(), $imports)->import('trello')->getStatus());
		self::assertSame(422, $this->controller($this->upload(BoardImportController::MAX_BYTES + 1), $imports)->import('trello')->getStatus());
	}//end testAMissingOrOversizedFileIsRefused()

	/**
	 * An unreadable export answers 422 with its reason; a failure part-way answers 500 without internals.
	 *
	 * @return void
	 */
	public function testRefusalsAndFailuresAreAnswered(): void {
		$refused = $this->createMock(BoardExportImportService::class);
		$refused->method('import')->willThrowException(new InvalidArgumentException('The export has no lists or statuses to make columns of.'));
		$response = $this->controller($this->upload(), $refused)->import('trello');
		self::assertSame([422, ['error' => 'The export has no lists or statuses to make columns of.']], [$response->getStatus(), $response->getData()]);

		$broken = $this->createMock(BoardExportImportService::class);
		$broken->method('import')->willThrowException(new RuntimeException('db gone'));
		$response = $this->controller($this->upload(), $broken)->import('trello');
		self::assertSame(500, $response->getStatus());
		self::assertStringNotContainsString('db gone', (string)json_encode($response->getData()));
	}//end testRefusalsAndFailuresAreAnswered()

	/**
	 * The list of imports is passed through.
	 *
	 * @return void
	 */
	public function testTheImportsAreListed(): void {
		$imports = $this->createMock(BoardExportImportService::class);
		$imports->method('tools')->willReturn([['id' => 'trello', 'label' => 'Trello board (JSON export)', 'format' => 'json']]);

		self::assertSame(['tools' => [['id' => 'trello', 'label' => 'Trello board (JSON export)', 'format' => 'json']]], $this->controller(null, $imports)->tools()->getData());
	}//end testTheImportsAreListed()

	/**
	 * Both endpoints sit behind planninq's admin setting and neither opens to non-admins.
	 *
	 * @return void
	 */
	public function testBothEndpointsAreAdminOnly(): void {
		foreach (['tools', 'import'] as $method) {
			$reflection = new ReflectionMethod(BoardImportController::class, $method);
			self::assertSame([], $reflection->getAttributes(NoAdminRequired::class), $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			self::assertCount(1, $attributes, $method);
			self::assertSame(['settings' => AdminSettings::class], $attributes[0]->getArguments(), $method);
		}
	}//end testBothEndpointsAreAdminOnly()

}//end class
