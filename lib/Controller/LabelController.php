<?php

/**
 * Planninq Label Controller
 *
 * Admin-only endpoints for the two label operations that need server logic
 * beyond OpenRegister object CRUD (ADR-022 / gate-17):
 *   - usage listing: every label with its per-label task usage count;
 *   - cascade delete: remove the label's UUID from every referencing task and
 *     then delete the label object (server-authoritative, idempotent).
 *
 * Both endpoints are admin-only. They deliberately carry NO @NoAdminRequired
 * attribute, so Nextcloud's SecurityMiddleware enforces the admin requirement
 * (NotAdminException → 403) before the controller body runs; an explicit
 * isCurrentUserAdmin() body check provides defence-in-depth.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
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

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\LabelService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for app-wide label management (admin-only).
 *
 * @spec openspec/specs/admin-user-settings.md
 */
class LabelController extends Controller {
	/**
	 * Constructor for the LabelController.
	 *
	 * @param IRequest $request The request object.
	 * @param LabelService $labelService The label domain service.
	 * @param SettingsService $settingsService The settings service (admin check).
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private LabelService $labelService,
		private SettingsService $settingsService,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * List every app-wide label with its task usage count.
	 *
	 * Admin-only: the NC admin middleware (no @NoAdminRequired) enforces the
	 * requirement, and the explicit isCurrentUserAdmin() check is defence-in-depth.
	 *
	 * @return JSONResponse 200 with `{labels: [...]}`; 403 for non-admins.
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function index(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(
				['error' => 'Admin privileges required to manage labels.'],
				Http::STATUS_FORBIDDEN
			);
		}

		return new JSONResponse(['labels' => $this->labelService->listWithUsage()]);
	}//end index()

	/**
	 * Delete a label with a server-side cascade over `task.labels`.
	 *
	 * Admin-only (same posture as index()). The cascade removes the label's UUID
	 * from every referencing task before deleting the label object, and is
	 * idempotent on re-run.
	 *
	 * @param string $id UUID of the label to delete.
	 *
	 * @return JSONResponse 200 with `{success, tasksUpdated}`; 403 for non-admins;
	 *                      503 when OpenRegister is unavailable; 500 on cascade error.
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function destroy(string $id): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(
				['error' => 'Admin privileges required to manage labels.'],
				Http::STATUS_FORBIDDEN
			);
		}

		try {
			$result = $this->labelService->deleteWithCascade(labelId: $id);
		} catch (\RuntimeException $e) {
			return new JSONResponse(
				['error' => $e->getMessage()],
				Http::STATUS_SERVICE_UNAVAILABLE
			);
		} catch (\Throwable $e) {
			return new JSONResponse(
				['error' => 'Failed to delete label.'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}

		return new JSONResponse(
			[
				'success' => true,
				'tasksUpdated' => $result['tasksUpdated'],
			]
		);

	}//end destroy()
}//end class
