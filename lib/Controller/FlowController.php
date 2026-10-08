<?php

/**
 * Planninq Flow Controller
 *
 * Read-only endpoints for the Flow tab and the portfolio flow page: the
 * cumulative flow (tasks per column per day) and the lead and cycle time of
 * finished tasks, replayed from OpenRegister's audit trail server-side by
 * FlowHistoryService. Writes nothing.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\FlowHistoryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The flow of a project or a portfolio.
 *
 * Both methods are `#[NoAdminRequired]` reads. The guard is the RBAC find:
 * the project (or portfolio) is read through ObjectService with RBAC on, and
 * a caller who cannot read it gets 403 before any task or history is read
 * (gate-7 no-admin-idor). A portfolio covers only the projects the caller
 * can read, because those are found with RBAC on as well.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
 */
class FlowController extends Controller {

	/**
	 * Most projects in one portfolio request, like the portfolio timeline.
	 *
	 * @var int
	 */
	public const MAX_PROJECTS = 50;

	private const REGISTER = 'planninq';

	private const OR_OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request     The request
	 * @param IUserSession       $userSession The signed-in user
	 * @param ContainerInterface $container   Resolves OpenRegister's ObjectService
	 * @param FlowHistoryService $flow        The flow reader
	 * @param LoggerInterface    $logger      Logger
	 */
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private ContainerInterface $container,
		private FlowHistoryService $flow,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The flow of one project: `GET /api/projects/{projectId}/flow?from=&to=`.
	 *
	 * @param string      $projectId The project's UUID
	 * @param string|null $from      First day, Y-m-d (default: 29 days before `to`)
	 * @param string|null $to        Last day, Y-m-d (default: today)
	 *
	 * @return JSONResponse 200 with the flow; 400 on a bad or too long window;
	 *                      401 signed out; 403 when the project is not readable;
	 *                      503 without OpenRegister.
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function forProject(string $projectId, ?string $from = null, ?string $to = null): JSONResponse {
		$objectService = $this->begin();
		if ($objectService instanceof JSONResponse) {
			return $objectService;
		}

		$window = $this->flow->window(from: $from, to: $to);
		if ($window === null) {
			return $this->badWindow();
		}

		if ($this->canReadObject(objectService: $objectService, schema: 'project', id: $projectId) === null) {
			return new JSONResponse(['error' => 'Project not found or not accessible.'], Http::STATUS_FORBIDDEN);
		}

		$flow = $this->flow->forProject(objectService: $objectService, projectId: $projectId, from: $window[0], to: $window[1]);
		$flow['window'] = ['from' => $window[0]->format('Y-m-d'), 'to' => $window[1]->format('Y-m-d')];

		return new JSONResponse($flow);
	}//end forProject()

	/**
	 * The flow of every project in a portfolio the caller can read:
	 * `GET /api/portfolios/{portfolioId}/flow?from=&to=`.
	 *
	 * @param string      $portfolioId The portfolio's UUID
	 * @param string|null $from        First day, Y-m-d
	 * @param string|null $to          Last day, Y-m-d
	 *
	 * @return JSONResponse 200 with per-project flows and one combined
	 *                      summary; 400, 401, 403 and 503 as forProject().
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function forPortfolio(string $portfolioId, ?string $from = null, ?string $to = null): JSONResponse {
		$objectService = $this->begin();
		if ($objectService instanceof JSONResponse) {
			return $objectService;
		}

		$window = $this->flow->window(from: $from, to: $to);
		if ($window === null) {
			return $this->badWindow();
		}

		if ($this->canReadObject(objectService: $objectService, schema: 'projectPortfolio', id: $portfolioId) === null) {
			return new JSONResponse(['error' => 'Portfolio not found or not accessible.'], Http::STATUS_FORBIDDEN);
		}

		$projects = $this->portfolioProjects(objectService: $objectService, portfolioId: $portfolioId);
		$flows = [];
		$finished = [];
		foreach (array_slice($projects, 0, self::MAX_PROJECTS) as $project) {
			$flow = $this->flow->forProject(objectService: $objectService, projectId: $project['id'], from: $window[0], to: $window[1]);
			$flow['title'] = $project['title'];
			$finished = array_merge($finished, $flow['finished']);
			$flows[] = $flow;
		}

		return new JSONResponse(
			[
				'portfolioId' => $portfolioId,
				'window' => ['from' => $window[0]->format('Y-m-d'), 'to' => $window[1]->format('Y-m-d')],
				'projects' => $flows,
				'skipped' => max(0, count($projects) - self::MAX_PROJECTS),
				'summary' => $this->flow->summarise(finished: $finished),
			]
		);
	}//end forPortfolio()

	/**
	 * A signed-in user and OpenRegister's ObjectService, or the error answer.
	 *
	 * @return object|JSONResponse
	 */
	private function begin(): object {
		if ($this->userSession->getUser() === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$objectService = $this->container->get(self::OR_OBJECT_SERVICE);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		// Drop a register or schema an earlier caller left on the shared
		// service (openregister#2820), as TimelineController does.
		if (method_exists($objectService, 'clearCurrents') === true) {
			$objectService->clearCurrents();
		}

		return $objectService;
	}//end begin()

	/**
	 * Whether the caller can read an object: the object read with RBAC on, or
	 * null when it is missing or not readable.
	 *
	 * @param object $objectService OpenRegister's ObjectService
	 * @param string $schema        Schema slug
	 * @param string $id            The object's UUID
	 *
	 * @return mixed
	 */
	private function canReadObject(object $objectService, string $schema, string $id): mixed {
		try {
			return $objectService->find(id: $id, register: self::REGISTER, schema: $schema, _rbac: true);
		} catch (\Throwable $e) {
			return null;
		}
	}//end canReadObject()

	/**
	 * The portfolio's projects the caller can read, as id and title.
	 *
	 * @param object $objectService OpenRegister's ObjectService
	 * @param string $portfolioId   The portfolio
	 *
	 * @return array<int,array{id: string, title: string}>
	 */
	private function portfolioProjects(object $objectService, string $portfolioId): array {
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: 'project',
			filters: ['portfolio' => $portfolioId, '_limit' => FlowHistoryService::MAX_ROWS]
		);
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$projects = [];
		foreach ((array)$results as $row) {
			$project = $this->flow->plainRow(result: $row);
			if ($project['id'] !== '') {
				$projects[] = ['id' => $project['id'], 'title' => (string)($project['title'] ?? '')];
			}
		}

		return $projects;
	}//end portfolioProjects()

	/**
	 * The answer to a bad window.
	 *
	 * @return JSONResponse
	 */
	private function badWindow(): JSONResponse {
		return new JSONResponse(
			['error' => 'Give from and to as YYYY-MM-DD, from before to, at most ' . FlowHistoryService::MAX_DAYS . ' days apart.'],
			Http::STATUS_BAD_REQUEST
		);
	}//end badWindow()
}//end class
