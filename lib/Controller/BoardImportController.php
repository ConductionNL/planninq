<?php

/**
 * Planninq BoardImportController
 *
 * The admin page's import of a board exported from another tool (a Trello
 * board, a Jira project CSV) as a new project. Both endpoints are for admins
 * (and anyone delegated planninq's admin settings), the same gate as the rest
 * of the admin page; Nextcloud refuses every other caller before the
 * controller runs.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use InvalidArgumentException;
use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\BoardExportImportService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Lists the imports and imports one export as a project, for admins.
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 */
class BoardImportController extends Controller {

	/**
	 * The largest export accepted, in bytes.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 10485760;

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request     The request.
	 * @param BoardExportImportService $imports     Maps and writes the export.
	 * @param IUserSession             $userSession The importing admin.
	 * @param LoggerInterface          $logger      The logger.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
	 */
	public function __construct(
		IRequest $request,
		private BoardExportImportService $imports,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The exports this instance can import.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function tools(): JSONResponse {
		return new JSONResponse(['tools' => $this->imports->tools()]);
	}//end tools()

	/**
	 * Import the uploaded `file` with the import named by `tool`.
	 *
	 * @param string $tool The import id, such as `trello` or `jira-csv`.
	 *
	 * @return JSONResponse 201 with the project id, the counts and what was not carried over;
	 *                      400 without a file, 422 for an export that cannot be read.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function import(string $tool): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		if (is_array($file) === false
			|| (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| is_readable((string)($file['tmp_name'] ?? '')) === false
		) {
			return new JSONResponse(['error' => 'Choose a file to import.'], Http::STATUS_BAD_REQUEST);
		}

		if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
			return new JSONResponse(['error' => 'The file is larger than 10 MB.'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$upload = ['name' => (string)($file['name'] ?? ''), 'content' => (string)file_get_contents((string)$file['tmp_name'])];
		try {
			$result = $this->imports->import(tool: $tool, file: $upload, owner: (string)$this->userSession->getUser()?->getUID());
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: board import failed', ['tool' => $tool, 'exception' => $e->getMessage()]);
			$message = 'The import stopped part-way. Check the new project before running it again.';
			return new JSONResponse(['error' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse($result, Http::STATUS_CREATED);
	}//end import()

}//end class
