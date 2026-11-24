<?php

/**
 * Planninq case handover controller
 *
 * Hands a project's files and metadata over to the case it is linked to, and
 * reports whether that is possible here and which handovers were made. Only
 * the project owner or an admin may do either, checked per project in the
 * method. The copying lives in CaseHandoverService; this is not a
 * pass-through to OpenRegister (ADR-022).
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
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\Exception\CaseHandoverException;
use OCA\Planninq\Kernel\AppIdentity;
use OCA\Planninq\Service\CaseHandoverService;
use OCA\Planninq\Kernel\Contracts\ProjectMembershipServiceInterface;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Case handover endpoints.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
 * @spec openspec/specs/platform-baseline/spec.md#requirement-sign-in-is-the-nextcloud-sign-in-req-plb-005
 * @spec openspec/specs/platform-baseline/spec.md#requirement-the-second-factor-guards-every-planninq-route-req-plb-006
 */
class CaseHandoverController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                          $request         The request.
	 * @param CaseHandoverService               $handoverService Copies the record to the case.
	 * @param ProjectMembershipServiceInterface $membership      Reads the project's owner.
	 * @param IUserSession                      $userSession     The caller.
	 * @param IGroupManager                     $groupManager    Tells an admin.
	 * @param LoggerInterface                   $logger          The logger.
	 */
	public function __construct(
		IRequest $request,
		private CaseHandoverService $handoverService,
		private ProjectMembershipServiceInterface $membership,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Whether a handover is possible here, and the handovers made so far.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse 200 with available and handovers; 401, 403 or 404 otherwise.
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
	 */
	#[NoAdminRequired]
	public function status(string $projectId): JSONResponse {
		$refusal = $this->refusal(projectId: $projectId);
		if ($refusal !== null) {
			return $refusal;
		}

		return new JSONResponse(
			[
				'available' => $this->handoverService->isAvailable(),
				'handovers' => $this->handoverService->handovers(projectId: $projectId),
			]
		);
	}//end status()

	/**
	 * Hand the project over to its case.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse 200 with the handover record; 409 without the case app; 422 without a case link; 404 for an unreadable case.
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
	 */
	#[NoAdminRequired]
	public function handOver(string $projectId): JSONResponse {
		$refusal = $this->refusal(projectId: $projectId);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return new JSONResponse($this->handoverService->handOver(projectId: $projectId));
		} catch (CaseHandoverException $e) {
			$status = match ($e->getReason()) {
				CaseHandoverException::NO_CASE_APP    => Http::STATUS_CONFLICT,
				CaseHandoverException::CASE_NOT_FOUND => Http::STATUS_NOT_FOUND,
				default                               => Http::STATUS_UNPROCESSABLE_ENTITY,
			};
			return new JSONResponse(['error' => $e->getMessage(), 'reason' => $e->getReason()], $status);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: case handover failed', ['project' => $projectId, 'exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'The handover failed. Please try again.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end handOver()

	/**
	 * Why the caller may not hand this project over, or null when they may.
	 *
	 * Owner-or-admin guard (IDOR): any other caller is refused.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse|null
	 *
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.2
	 */
	private function refusal(string $projectId): ?JSONResponse {
		$guard = new ProjectOwnerGuard(membership: $this->membership, groupManager: $this->groupManager);
		$guard->forbiddenMessage = 'Only the project owner can hand a project over to its case.';

		$refusal = $guard->refusal(user: $this->userSession->getUser(), projectId: $projectId);
		if ($refusal?->getStatus() === Http::STATUS_FORBIDDEN) {
			$this->logger->info('Planninq: refused a case handover by someone who is not the project owner', ['project' => $projectId]);
		}

		return $refusal;
	}//end refusal()
}//end class
