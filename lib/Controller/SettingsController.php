<?php

/**
 * Planninq Settings Controller
 *
 * Controller for managing Planninq application settings.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\NotificationSwitchService;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Service\WorkingCalendarService;
use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\RiskScaleService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableGridService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for managing Planninq application settings.
 *
 * @spec openspec/specs/admin-user-settings.md
 */
class SettingsController extends Controller {
	/**
	 * Constructor for the SettingsController.
	 *
	 * @param IRequest $request The request object
	 * @param SettingsService $settingsService The settings service
	 * @param RegisterImportService $registerImport The register import service
	 * @param IUserSession $userSession The user session
	 * @param RiskScaleService $riskScale Finds the risks a smaller risk scale would strand
	 * @param TimetableGridService $timetableGrid The timetable week grid and generator budget
	 * @param NotificationSwitchService $switches The user's notification switches (collaboration-notifications)
	 * @param TaskCalendarExportService $taskExport The user's export to Nextcloud Tasks (planning-calendar)
	 * @param WorkingCalendarService $workingCalendar The working weekdays and non-working days (planning-timeline-editing)
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private SettingsService $settingsService,
		private RegisterImportService $registerImport,
		private IUserSession $userSession,
		private RiskScaleService $riskScale,
		private TimetableGridService $timetableGrid,
		private NotificationSwitchService $switches,
		private TaskCalendarExportService $taskExport,
		private WorkingCalendarService $workingCalendar,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Retrieve all current settings.
	 *
	 * Returns app configuration including an isAdmin flag consumed by
	 * the frontend. Any authenticated user may read settings.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-1.2
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.1
	 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-1.1
	 */
	public function index(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated.'], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(
			array_merge(
				$this->settingsService->getSettings(),
				$this->timetableGrid->settings(),
				$this->workingCalendar->settings(),
				$this->switches->values($user->getUID()),
				$this->taskExport->values(userId: $user->getUID())
			)
		);
	}//end index()

	/**
	 * Update settings with provided data. Only admin users may write settings.
	 *
	 * Admin access is enforced by both the NC admin middleware (no @NoAdminRequired)
	 * and the explicit isCurrentUserAdmin() body check (defence-in-depth).
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-1.2
	 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-1.1
	 */
	public function create(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(
				['error' => 'Admin privileges required to modify settings.'],
				Http::STATUS_FORBIDDEN
			);
		}

		$data = $this->request->getParams();
		if (array_key_exists(RiskScaleService::CONFIG_KEY, $data) === true) {
			$scale = $this->riskScale->normalise(raw: (string)$data[RiskScaleService::CONFIG_KEY]);
			$refused = $this->refuseRiskScale(scale: $scale);
			if ($refused !== null) {
				return $refused;
			}

			// Store the normalised form: trimmed labels, integer levels.
			$data[RiskScaleService::CONFIG_KEY] = (string)json_encode($scale);
		}

		$this->timetableGrid->save(data: $data);
		$this->workingCalendar->save(data: $data);
		$config = array_merge($this->settingsService->updateSettings($data), $this->timetableGrid->settings(), $this->workingCalendar->settings());

		return new JSONResponse(
			[
				'success' => true,
				'config' => $config,
			]
		);
	}//end create()

	/**
	 * The refusal of a risk scale that is malformed or that risks still exceed, or null to go on.
	 *
	 * @param array<string,mixed>|null $scale The normalised scale, or null when it did not parse.
	 *
	 * @return JSONResponse|null
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	private function refuseRiskScale(?array $scale): ?JSONResponse {
		if ($scale === null) {
			return new JSONResponse(
				['error' => 'risk-scale-invalid', 'message' => 'The risk scale is not valid.'],
				Http::STATUS_BAD_REQUEST
			);
		}

		$conflict = $this->riskScale->conflict(levels: (int)$scale['levels']);
		if ($conflict === null) {
			return null;
		}

		return new JSONResponse(
			[
				'error' => 'risk-scale-in-use',
				'message' => $this->riskScale->refusal(count: $conflict['count'], level: $conflict['level']),
				'count' => $conflict['count'],
				'level' => $conflict['level'],
			],
			Http::STATUS_CONFLICT
		);
	}//end refuseRiskScale()

	/**
	 * Update app settings (PUT /api/settings).
	 *
	 * The canonical AppHost route table declares BOTH `settings#create` (POST)
	 * and `settings#update` (PUT) against /api/settings, and planninq implemented
	 * only the POST. `PUT /api/settings` therefore resolved to a method that
	 * does not exist — a DECLARED route with no target, which is exactly what
	 * gate-14's `method-not-found-on-target-controller` finding names, and what
	 * a client following the published route table would have hit.
	 *
	 * Same semantics and the same admin gate as create(): the settings document
	 * is replaced wholesale either way, so this delegates rather than
	 * duplicating the guard.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
	 */
	public function update(): JSONResponse {
		return $this->create();
	}//end update()

	/**
	 * Update the current user's personal settings (notification toggles).
	 *
	 * Available to any authenticated user for their own per-user preferences
	 * (stored via OCP\IConfig + written through to the OpenRegister notification
	 * override). Distinct from create(), which is admin-only IAppConfig.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.1
	 */
	public function updateUser(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated.'], Http::STATUS_UNAUTHORIZED);
		}

		$data = $this->request->getParams();
		$this->switches->apply(userId: $user->getUID(), data: $data);
		$this->taskExport->apply(userId: $user->getUID(), data: $data);
		$config = array_merge(
			$this->settingsService->updateUserSettings($user->getUID(), $data),
			$this->switches->values($user->getUID()),
			$this->taskExport->values(userId: $user->getUID())
		);

		return new JSONResponse(
			[
				'success' => true,
				'config' => $config,
			]
		);
	}//end updateUser()

	/**
	 * Re-import the configuration from planninq_register.json.
	 *
	 * Forces a fresh import regardless of version, auto-configuring
	 * all schema and register IDs from the import result.
	 * Only admin users may trigger this operation.
	 *
	 * Admin access is enforced by both the NC admin middleware (no @NoAdminRequired)
	 * and the explicit isCurrentUserAdmin() body check (defence-in-depth).
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
	 */
	public function load(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(
				['error' => 'Admin privileges required to trigger configuration import.'],
				Http::STATUS_FORBIDDEN
			);
		}

		$result = $this->registerImport->reload();

		return new JSONResponse($result);
	}//end load()
}//end class
