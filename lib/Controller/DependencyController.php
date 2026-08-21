<?php

/**
 * Planix Dependency Controller
 *
 * HTTP surface for creating and deleting task dependency edges. The only
 * writes that route through planix (reads go straight to OpenRegister per
 * ADR-022) because edge creation needs server-side graph validation
 * (self/duplicate/cross-project/cycle) that ObjectService cannot perform.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Exception\DependencyValidationException;
use OCA\Planix\Service\DependencyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for task dependency edges.
 *
 * Both endpoints declare `#[NoAdminRequired]` (authenticated users) and rely
 * on the per-object project-membership guard inside DependencyService for the
 * actual authorization — there is no admin shortcut, and no method is reachable
 * without passing that guard (gate-5 route-auth, gate-7 no-admin-idor).
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
class DependencyController extends Controller {
	/**
	 * Constructor for the DependencyController.
	 *
	 * @param IRequest $request The request object.
	 * @param DependencyService $dependencyService The dependency domain service.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private DependencyService $dependencyService,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * Map a DependencyValidationException code to an HTTP status + JSON body.
	 *
	 * @param DependencyValidationException $e The domain exception.
	 *
	 * @return JSONResponse
	 */
	private function toResponse(DependencyValidationException $e): JSONResponse {
		$status = match ($e->getCode()) {
			DependencyValidationException::CODE_NOT_FOUND => Http::STATUS_NOT_FOUND,
			DependencyValidationException::CODE_FORBIDDEN => Http::STATUS_FORBIDDEN,
			DependencyValidationException::CODE_UNAUTHENTICATED => Http::STATUS_UNAUTHORIZED,
			DependencyValidationException::CODE_UNAVAILABLE => Http::STATUS_SERVICE_UNAVAILABLE,
			default => Http::STATUS_UNPROCESSABLE_ENTITY,
		};

		return new JSONResponse(['error' => $e->getMessage()], $status);
	}//end toResponse()

	/**
	 * Create a dependency edge (blocker → blocked).
	 *
	 * The project-membership guard (IDOR protection) lives in
	 * DependencyService::create; this method only carries the auth posture and
	 * translates the result into HTTP. Validation failures (self-edge,
	 * duplicate, cross-project, cycle) surface as 422 with an explanatory
	 * message; non-members get 403.
	 *
	 * @param string|null $blocker UUID of the blocking task.
	 * @param string|null $blocked UUID of the blocked task.
	 *
	 * @return JSONResponse 201 with the created edge; 4xx/5xx on failure.
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	#[NoAdminRequired]
	public function create(?string $blocker = null, ?string $blocked = null): JSONResponse {
		try {
			$edge = $this->dependencyService->create(blocker: (string)$blocker, blocked: (string)$blocked);
			return new JSONResponse($edge, Http::STATUS_CREATED);
		} catch (DependencyValidationException $e) {
			// Project-membership guard (IDOR): a non-member caller is rejected by
			// DependencyService with CODE_FORBIDDEN, surfaced here as 403.
			if ($e->getCode() === DependencyValidationException::CODE_FORBIDDEN) {
				return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
			}

			return $this->toResponse(e: $e);
		} catch (\Throwable $e) {
			$this->logger->error('Planix: dependency create failed', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'Failed to create dependency.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}//end create()

	/**
	 * Delete a dependency edge after a project-membership guard.
	 *
	 * @param string $id UUID of the dependency edge to delete.
	 *
	 * @return JSONResponse 200 on success; 4xx/5xx on failure.
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	#[NoAdminRequired]
	public function destroy(string $id): JSONResponse {
		try {
			$this->dependencyService->delete(id: $id);
			return new JSONResponse(['deleted' => true]);
		} catch (DependencyValidationException $e) {
			// Project-membership guard (IDOR): a non-member caller is rejected by
			// DependencyService with CODE_FORBIDDEN, surfaced here as 403.
			if ($e->getCode() === DependencyValidationException::CODE_FORBIDDEN) {
				return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
			}

			return $this->toResponse(e: $e);
		} catch (\Throwable $e) {
			$this->logger->error('Planix: dependency delete failed', ['exception' => $e->getMessage(), 'id' => $id]);
			return new JSONResponse(['error' => 'Failed to delete dependency.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}//end destroy()
}//end class
