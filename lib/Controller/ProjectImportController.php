<?php

/**
 * Planninq project import controller
 *
 * Previews and imports a plan saved from Microsoft Project into a project.
 * Importing a plan rewrites a project's structure, so only the project owner
 * or an admin may do it, checked here per project before the file is read.
 * The parsing, mapping and writing live in MsProjectImportService; this is
 * import logic, not a pass-through to OpenRegister (ADR-022).
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
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\MsProjectImportException;
use OCA\Planninq\Service\MsProjectImportService;
use OCA\Planninq\Service\MsProjectPlanParser;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Microsoft Project import endpoints.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
 */
class ProjectImportController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request       The request.
	 * @param MsProjectImportService   $importService Previews and writes the plan.
	 * @param ProjectMembershipService $membership    Reads the project's owner.
	 * @param IUserSession             $userSession   The caller.
	 * @param IGroupManager            $groupManager  Tells an admin.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		IRequest $request,
		private MsProjectImportService $importService,
		private ProjectMembershipService $membership,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Preview importing the uploaded plan into the project; writes nothing.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse 200 with counts, sample, losses, updates and missing titles; 401, 403, 404, 400 or 422 otherwise.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
	 */
	#[NoAdminRequired]
	public function preview(string $projectId): JSONResponse {
		$refusal = $this->refusal(projectId: $projectId);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withUpload(
			run: fn (string $content, string $name): JSONResponse => new JSONResponse(
				$this->importService->preview(projectId: $projectId, content: $content, fileName: $name)
			)
		);
	}//end preview()

	/**
	 * Import the uploaded plan into the project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse 200 with what was created and updated; 500 with how far it got when it stopped; 401, 403, 404, 400 or 422 otherwise.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.2
	 */
	#[NoAdminRequired]
	public function import(string $projectId): JSONResponse {
		$refusal = $this->refusal(projectId: $projectId);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withUpload(
			run: function (string $content, string $name) use ($projectId): JSONResponse {
				$result = $this->importService->import(projectId: $projectId, content: $content, fileName: $name);
				if (isset($result['error']) === true) {
					$result['error'] = 'The import stopped part-way. Run it again to finish it.';
					return new JSONResponse($result, Http::STATUS_INTERNAL_SERVER_ERROR);
				}

				return new JSONResponse($result);
			}
		);
	}//end import()

	/**
	 * Why the caller may not import into this project, or null when they may.
	 *
	 * Owner-or-admin guard (IDOR): a project member who is not the owner, and
	 * anyone else, is refused.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse|null
	 */
	private function refusal(string $projectId): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$owner = $this->membership->ownerOfProject(projectId: $projectId);
		if ($owner === null) {
			return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
		}

		$uid = $user->getUID();
		if ($owner !== $uid && $this->groupManager->isAdmin($uid) === false) {
			$this->logger->info('Planninq: refused a plan import by someone who is not the project owner', ['project' => $projectId]);
			return new JSONResponse(['error' => 'Only the project owner can import a plan.'], Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end refusal()

	/**
	 * Read the uploaded file and run the preview or import on it.
	 *
	 * @param callable(string,string):JSONResponse $run What to do with the content and file name.
	 *
	 * @return JSONResponse
	 */
	private function withUpload(callable $run): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		if (is_array($file) === false || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || is_readable((string)($file['tmp_name'] ?? '')) === false) {
			return new JSONResponse(['error' => 'Choose a file to import.', 'reason' => 'noFile'], Http::STATUS_BAD_REQUEST);
		}

		if ((int)($file['size'] ?? 0) > MsProjectPlanParser::MAX_BYTES) {
			return new JSONResponse(['error' => 'The file is larger than 10 MB.', 'reason' => MsProjectImportException::TOO_LARGE], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			return $run((string)file_get_contents((string)$file['tmp_name']), (string)($file['name'] ?? ''));
		} catch (MsProjectImportException $e) {
			return new JSONResponse(['error' => $e->getMessage(), 'reason' => $e->getReason()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: Microsoft Project import failed', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'The plan could not be read. Please try again.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end withUpload()
}//end class
