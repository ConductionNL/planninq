<?php

/**
 * Planix Timeline Controller
 *
 * Read-only HTTP surface for the per-project Gantt / timeline view. Returns a
 * project's tasks laid out for a time axis — each with its `startDate`,
 * `dueDate`, and `duration` (read from the task's `estimatedDuration`) plus
 * status — together with the project's existing dependency links so the
 * frontend can draw the edges. Dateless tasks are returned flagged
 * "unscheduled" rather than dropped.
 *
 * All reads go through the OpenRegister ObjectService (ADR-022), so the
 * response is RBAC/tenancy-scoped: a caller who cannot read the project sees
 * none of its tasks. The controller introduces NO new schema, NO new storage,
 * and NO scheduling engine — it is a pure read surface over data that the
 * `task` schema and the archived `task-dependencies` capability already store.
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
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
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
 * Controller for the read-only project timeline.
 *
 * `forProject()` declares `#[NoAdminRequired]` (any authenticated user) and
 * `#[NoCSRFRequired]` (a GET read with no state change). Authorisation is not
 * an admin shortcut: the project is fetched through ObjectService with RBAC on,
 * so a caller without access to the project gets a 403 and no task data
 * (gate-5 route-auth, gate-7 no-admin-idor — the RBAC find IS the guard).
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */
class TimelineController extends Controller {

	/**
	 * OpenRegister register slug owning the planix schemas.
	 *
	 * @var string
	 */
	private const REGISTER = 'planix';

	/**
	 * OpenRegister ObjectService FQCN, resolved at runtime so planix carries no
	 * compile-time dependency on the openregister package (ADR-022).
	 *
	 * @var string
	 */
	private const OR_OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor for the TimelineController.
	 *
	 * @param IRequest $request The request object.
	 * @param IUserSession $userSession The current user session.
	 * @param ContainerInterface $container The DI container (resolves OR ObjectService at runtime).
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * Return a project's tasks laid out for a time axis, plus its dependency links.
	 *
	 * The response splits tasks into `tasks` (those carrying a `startDate` or
	 * `dueDate`, optionally windowed by `from`/`to`) and `unscheduled` (dateless
	 * tasks, never dropped). `dependencies` echoes the existing stored edges
	 * whose blocker task belongs to this project — the timeline renders what
	 * `task-dependencies` already persists and never creates a new edge.
	 *
	 * @param string $projectId The OR UUID of the project.
	 * @param string|null $from Optional ISO date lower bound for the window.
	 * @param string|null $to Optional ISO date upper bound for the window.
	 *
	 * @return JSONResponse 200 with the timeline payload; 401 if unauthenticated;
	 *                      403 if the caller cannot access the project; 503 if OR
	 *                      is unavailable.
	 *
	 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function forProject(string $projectId, ?string $from = null, ?string $to = null): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$objectService = $this->container->get(self::OR_OBJECT_SERVICE);
		} catch (\Throwable $e) {
			$this->logger->error('Planix: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		// RBAC gate: read the project through ObjectService with RBAC on. A
		// caller who cannot see the project gets null here → 403 with no tasks,
		// satisfying "a caller MUST NOT see tasks of a project they cannot access".
		$objectService->setRegister(self::REGISTER);
		$objectService->setSchema('project');
		$project = $objectService->find(id: $projectId);
		if ($project === null) {
			return new JSONResponse(
				['error' => 'Project not found or not accessible.'],
				Http::STATUS_FORBIDDEN
			);
		}

		$tasks = $this->fetchProjectTasks(objectService: $objectService, projectId: $projectId);

		$scheduled = [];
		$unscheduled = [];
		$taskIdSet = [];
		foreach ($tasks as $task) {
			$row = $this->timelineRow(task: $task);
			$taskIdSet[$row['id']] = true;

			if ($row['startDate'] === null && $row['dueDate'] === null) {
				unset($row['startDate'], $row['dueDate'], $row['duration']);
				$unscheduled[] = $row;
				continue;
			}

			if ($this->withinWindow(row: $row, from: $from, to: $to) === false) {
				continue;
			}

			$scheduled[] = $row;
		}

		$dependencies = $this->fetchProjectDependencies(objectService: $objectService, taskIdSet: $taskIdSet);

		return new JSONResponse(
			[
				'projectId' => $projectId,
				'window' => ['from' => $from, 'to' => $to],
				'tasks' => $scheduled,
				'unscheduled' => $unscheduled,
				'dependencies' => $dependencies,
			]
		);

	}//end forProject()

	/**
	 * Fetch every task in a project as a plain data array.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $projectId UUID of the project.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchProjectTasks(object $objectService, string $projectId): array {
		// `searchObjectsBySlug()`, not `searchObjects()`.
		//
		// This call used to be `setRegister()/setSchema()` followed by
		// `searchObjects(filters: [...])`, and it was broken twice over:
		//
		// 1. `ObjectService::searchObjects()` has no `$filters` parameter — its
		// signature is `searchObjects(array $query = [], …)`. PHP therefore
		// raised `Unknown named parameter $filters` and the whole endpoint
		// returned a 500. The Timeline view rendered "Could not load the
		// timeline / An unexpected error occurred." on every project.
		//
		// 2. Even without that, `searchObjects()` does NOT read the register or
		// schema left on the service by `setRegister()/setSchema()`. It logs
		// `[MagicMapper] searchObjects() called without register/schema
		// context` and matches nothing, so the fix could not have been to drop
		// the argument name.
		//
		// `searchObjectsBySlug()` is the supported slug-aware entry point: it
		// resolves both slugs to numeric IDs, merges them into the `@self` block
		// and delegates to `searchObjects()`. Direct keys such as `project` stay
		// at the top level and hit the object-JSON filter path.
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: 'task',
			filters: ['project' => $projectId]
		);

		$tasks = [];
		foreach ($this->normaliseResults(results: $results) as $row) {
			$data = $this->extractData(row: $row);
			$data['id'] = $this->extractId(row: $row);
			if ($data['id'] !== '') {
				$tasks[] = $data;
			}
		}

		return $tasks;
	}//end fetchProjectTasks()

	/**
	 * Fetch the existing dependency edges whose blocker task belongs to the
	 * project (its task-id set). Reads — never re-derives or persists — the
	 * links that `task-dependencies` already stores.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param array<string,bool> $taskIdSet Set of the project's task UUIDs.
	 *
	 * @return array<int,array<string,string>> Edges as {id, blocker, blocked}.
	 */
	private function fetchProjectDependencies(object $objectService, array $taskIdSet): array {
		if ($taskIdSet === []) {
			return [];
		}

		// See fetchProjectTasks(): `searchObjects()` ignores the register/schema
		// left on the service by `setRegister()/setSchema()` and logs
		// `[MagicMapper] searchObjects() called without register/schema context`
		// before matching nothing. This one did not 500 — it silently returned
		// an empty edge set, so every timeline rendered with no dependency
		// arrows and nothing said so.
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: 'dependency'
		);

		$edges = [];
		foreach ($this->normaliseResults(results: $results) as $row) {
			$data = $this->extractData(row: $row);
			$blocker = (string)($data['blocker'] ?? '');
			$blocked = (string)($data['blocked'] ?? '');
			if (isset($taskIdSet[$blocker]) === false) {
				continue;
			}

			$edges[] = [
				'id' => $this->extractId(row: $row),
				'blocker' => $blocker,
				'blocked' => $blocked,
			];
		}

		return $edges;
	}//end fetchProjectDependencies()

	/**
	 * Project a raw task array onto the timeline row shape.
	 *
	 * `duration` is read from the task's `estimatedDuration` field (the schema
	 * carries no separate `duration` property); empty date strings collapse to
	 * null so the scheduled/unscheduled split is unambiguous.
	 *
	 * @param array<string,mixed> $task The raw task data.
	 *
	 * @return array<string,mixed>
	 */
	private function timelineRow(array $task): array {
		return [
			'id' => (string)($task['id'] ?? ''),
			'title' => (string)($task['title'] ?? ''),
			'status' => (string)($task['status'] ?? ''),
			'priority' => (string)($task['priority'] ?? ''),
			'startDate' => $this->nullableDate(value: ($task['startDate'] ?? null)),
			'dueDate' => $this->nullableDate(value: ($task['dueDate'] ?? null)),
			'duration' => ($task['estimatedDuration'] ?? null),
			'percentComplete' => ($task['percentComplete'] ?? null),
		];

	}//end timelineRow()

	/**
	 * Normalise a date-ish value to a non-empty string or null.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string|null
	 */
	private function nullableDate(mixed $value): ?string {
		if (is_string($value) === true && $value !== '') {
			return $value;
		}

		return null;
	}//end nullableDate()

	/**
	 * Decide whether a scheduled row overlaps the requested [from, to] window.
	 *
	 * A missing bound is open-ended. A task with only one of start/due is
	 * treated as a point on that single date. When neither bound is supplied
	 * every scheduled row passes.
	 *
	 * @param array<string,mixed> $row The timeline row (start/due may be null).
	 * @param string|null $from Lower bound (ISO date) or null.
	 * @param string|null $to Upper bound (ISO date) or null.
	 *
	 * @return bool
	 */
	private function withinWindow(array $row, ?string $from, ?string $to): bool {
		if ($from === null && $to === null) {
			return true;
		}

		$start = ($row['startDate'] ?? $row['dueDate']);
		$end = ($row['dueDate'] ?? $row['startDate']);

		// A task that starts after the window's end does not overlap.
		if ($to !== null && $start !== null && $start > $to) {
			return false;
		}

		// A task that ends before the window's start does not overlap.
		if ($from !== null && $end !== null && $end < $from) {
			return false;
		}

		return true;
	}//end withinWindow()

	/**
	 * Normalise an ObjectService result set (paginated `results` array, plain
	 * list, or list of entity objects) to a plain list of rows.
	 *
	 * @param mixed $results The raw ObjectService return value.
	 *
	 * @return array<int,mixed>
	 */
	private function normaliseResults(mixed $results): array {
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			return (array)$results['results'];
		}

		if (is_array($results) === true) {
			return $results;
		}

		return [];
	}//end normaliseResults()

	/**
	 * Extract the object UUID from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return string
	 */
	private function extractId(mixed $row): string {
		if (is_object($row) === true) {
			// `is_callable()`, NOT `method_exists()`.
			//
			// OpenRegister's ObjectEntity extends \OCP\AppFramework\Db\Entity,
			// which implements every property accessor through `__call()`. It
			// declares no `getUuid()` and — despite appearances — no `getId()`
			// either; `\OCP\AppFramework\Db\Entity` has neither. `method_exists()`
			// does not see magic methods, so BOTH branches were skipped, the
			// array branch below does not apply to an object, and this helper
			// returned '' for every entity it was ever handed.
			//
			// The damage was silent: `forProject()` drops tasks whose extracted
			// id is empty, so the timeline reported `tasks: []` / `unscheduled:
			// []` for a project full of dated tasks and rendered its empty
			// state. `is_callable()` resolves through `__call()`.
			foreach (['getUuid', 'getId'] as $getter) {
				if (is_callable([$row, $getter]) === false) {
					continue;
				}

				try {
					$value = $row->$getter();
				} catch (\Throwable $e) {
					continue;
				}

				if ($value !== null && (string)$value !== '') {
					return (string)$value;
				}
			}//end foreach
		}//end if

		if (is_array($row) === true) {
			if (isset($row['@self']['id']) === true) {
				return (string)$row['@self']['id'];
			}

			return (string)($row['id'] ?? '');
		}

		return '';
	}//end extractId()

	/**
	 * Extract the object data array from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return array<string,mixed>
	 */
	private function extractData(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			return $row;
		}

		return [];
	}//end extractData()
}//end class
