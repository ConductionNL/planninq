<?php

/**
 * Planninq Timetable Session Service
 *
 * Owns every rule of the school timetable planninq holds for the fleet
 * (decision D10: planninq owns the timetable, the integriq rostering adapter
 * delivers into it, learniq reads from it): which fields a lesson needs, when
 * two deliveries describe the same lesson, and how a timetable is read back.
 *
 * The events and the controller that expose this service are thin doors; the
 * contract they speak is `openspec/changes/school-timetable-target/contract.md`.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Upserts and lists `timetableSession` rows in the planninq register.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */
class TimetableSessionService {

	/**
	 * Contract version of the upsert result and the session shape.
	 *
	 * @var int
	 */
	public const CONTRACT_VERSION = 1;

	/**
	 * OpenRegister register slug owning the planninq schemas.
	 *
	 * @var string
	 */
	public const REGISTER = 'planninq';

	/**
	 * Schema slug of a timetable session.
	 *
	 * @var string
	 */
	public const SCHEMA = 'timetableSession';

	/**
	 * OpenRegister ObjectService FQCN, resolved at runtime so planninq carries no
	 * compile-time dependency on the openregister package (ADR-022).
	 *
	 * @var string
	 */
	private const OR_OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Seconds a publish window is widened by in the OpenRegister prefilter (offsets differ at the edges).
	 *
	 * @var int
	 */
	private const DAY = 86400;

	/**
	 * Reads ObjectService rows and shapes sessions.
	 *
	 * @var TimetableSessionRows
	 */
	private readonly TimetableSessionRows $rows;

	/**
	 * The row rules: normalising, validating and comparing rows.
	 *
	 * @var TimetableRowRules
	 */
	private readonly TimetableRowRules $rules;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService by name.
	 * @param LoggerInterface    $logger    PSR logger.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
		$this->rows = new TimetableSessionRows();
		$this->rules = new TimetableRowRules(rows: $this->rows);
	}//end __construct()

	/**
	 * Upsert one batch of lessons from one source.
	 *
	 * A row matches a stored session only when both `sourceSystem` and
	 * `externalRef` are equal. An unchanged match is counted and not saved, a
	 * changed match is updated in place, and no match creates a session. Rows
	 * are validated before anything is written; a key that appears again later
	 * in the batch is rejected for its earlier occurrences, so the last wins.
	 *
	 * @param string                  $sourceSystem The source every row is stored under.
	 * @param array<int|string,mixed> $sessions     Rows in the contract's session shape.
	 *
	 * @return array<string,mixed> The contract's upsert result.
	 *
	 * @throws InvalidArgumentException When the source system is empty.
	 * @throws RuntimeException         When OpenRegister is not available.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
	 */
	public function upsert(string $sourceSystem, array $sessions): array {
		$sourceSystem = $this->requiredSource(sourceSystem: $sourceSystem, refusal: 'A timetable delivery needs a source system.');

		$result = [
			'contractVersion' => self::CONTRACT_VERSION,
			'sourceSystem' => $sourceSystem,
			'processed' => count($sessions),
			'created' => 0,
			'updated' => 0,
			'unchanged' => 0,
			'rejected' => [],
			'sessionIds' => [],
		];

		$accepted = $this->acceptRows(sessions: array_values($sessions), sourceSystem: $sourceSystem, result: $result);
		if ($accepted === []) {
			return $result;
		}

		$objectService = $this->objectService();
		foreach ($accepted as $row) {
			$this->upsertRow(objectService: $objectService, row: $row, result: $result);
		}

		return $result;
	}//end upsert()

	/**
	 * Publish the drafts of one source that overlap a window: each becomes scheduled.
	 *
	 * Each draft is saved whole (its stored fields with the new status), so no
	 * field is nulled on the way. Reached only through the admin endpoint.
	 *
	 * @param string $sourceSystem The source whose drafts are published.
	 * @param string $from         ISO 8601 window start.
	 * @param string $to           ISO 8601 window end.
	 *
	 * @return array{contractVersion:int,sourceSystem:string,published:int,failed:array<int,string>}
	 *
	 * @throws InvalidArgumentException When the source is empty or the window is missing or reversed.
	 * @throws RuntimeException         When OpenRegister is not available.
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-an-admin-publishes-the-drafts-of-one-source-in-a-date-window
	 */
	public function publish(string $sourceSystem, string $from, string $to): array {
		$sourceSystem = $this->requiredSource(sourceSystem: $sourceSystem, refusal: 'Name the source system whose drafts to publish.');
		[$start, $end] = $this->window(from: $from, to: $to);

		$result = ['contractVersion' => self::CONTRACT_VERSION, 'sourceSystem' => $sourceSystem, 'published' => 0, 'failed' => []];

		$objectService = $this->objectService();
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SCHEMA,
			filters: [
				'sourceSystem' => $sourceSystem,
				'status' => 'draft',
				'startsAt' => ['lte' => date(DATE_ATOM, ($end + self::DAY))],
				'endsAt' => ['gte' => date(DATE_ATOM, ($start - self::DAY))],
				'_limit' => TimetableSessionQuery::MAX_LIMIT,
			],
			_rbac: false
		);

		foreach ($this->draftsInWindow(results: $results, window: [$start, $end]) as [$id, $data]) {
			$lesson = array_merge(
				$this->rules->storedFields(data: $data),
				['status' => 'scheduled', 'importedAt' => (new DateTimeImmutable())->format(DATE_ATOM)]
			);
			if ($this->save(objectService: $objectService, data: $lesson, uuid: $id) === null) {
				$result['failed'][] = $id;
				continue;
			}

			$result['published']++;
		}

		return $result;
	}//end publish()

	/**
	 * The trimmed source system, refused when it is empty.
	 *
	 * @param string $sourceSystem The source system as given.
	 * @param string $refusal      The message when it is empty.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When the source system is empty.
	 */
	private function requiredSource(string $sourceSystem, string $refusal): string {
		$sourceSystem = trim($sourceSystem);
		if ($sourceSystem === '') {
			throw new InvalidArgumentException($refusal);
		}

		return $sourceSystem;
	}//end requiredSource()

	/**
	 * The publish window as two timestamps, refused when unreadable or reversed.
	 *
	 * @param string $from ISO 8601 window start.
	 * @param string $to   ISO 8601 window end.
	 *
	 * @return array{0:int,1:int}
	 *
	 * @throws InvalidArgumentException When the window is missing or reversed.
	 */
	private function window(string $from, string $to): array {
		$start = strtotime($from);
		$end   = strtotime($to);
		if ($start === false || $end === false || $start > $end) {
			throw new InvalidArgumentException('Give the window to publish as from and to, the start before the end.');
		}

		return [$start, $end];
	}//end window()

	/**
	 * The stored drafts that overlap the window, as id and data pairs.
	 *
	 * @param mixed              $results The OpenRegister search answer.
	 * @param array{0:int,1:int} $window  The window start and end.
	 *
	 * @return array<int,array{0:string,1:array<string,mixed>}>
	 */
	private function draftsInWindow(mixed $results, array $window): array {
		$drafts = [];
		foreach ($this->rows->listOf(results: $results) as $row) {
			$data = $this->rows->dataOf(row: $row);
			$id   = $this->rows->idOf(row: $row);
			if ($id !== '' && $this->rows->isDraftInWindow(data: $data, start: $window[0], end: $window[1]) === true) {
				$drafts[] = [$id, $data];
			}
		}

		return $drafts;
	}//end draftsInWindow()

	/**
	 * List the sessions of a cohort, group or teacher for the signed-in caller.
	 *
	 * The read runs with OpenRegister RBAC on, as the current user, so the
	 * schema's read rule decides which rows come back: the timetable group and
	 * admins read every lesson, a teacher reads the lessons that name them, and
	 * nobody else reads any (planninq#711). This is the HTTP path.
	 *
	 * @param array<string,mixed> $criteria The contract's query criteria.
	 *
	 * @return array<int,array<string,mixed>> Sessions in the contract's read shape.
	 *
	 * @throws InvalidArgumentException When no identity filter is given or the window is invalid.
	 * @throws RuntimeException         When OpenRegister is not available.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
	 */
	public function list(array $criteria): array {
		return $this->read(criteria: $criteria, rbac: true);
	}//end list()

	/**
	 * List the sessions of a cohort, group or teacher for another app.
	 *
	 * Reached only through {@see \OCA\Planninq\Listener\TimetableSessionsQueryListener},
	 * which answers an in-process PHP event that only server code can
	 * dispatch. The read runs with RBAC off: the requesting app has already
	 * decided that its user may see this cohort or teacher (learniq resolves a
	 * learner's cohorts from its own membership before it asks), and planninq
	 * holds no membership it could check that against. Reading as the caller
	 * here would take a learner's own lessons away once the schema no longer
	 * grants every signed-in user (planninq#711).
	 *
	 * @param array<string,mixed> $criteria The contract's query criteria.
	 *
	 * @return array<int,array<string,mixed>> Sessions in the contract's read shape.
	 *
	 * @throws InvalidArgumentException When no identity filter is given or the window is invalid.
	 * @throws RuntimeException         When OpenRegister is not available.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function listForApp(array $criteria): array {
		return $this->read(criteria: $criteria, rbac: false);
	}//end listForApp()

	/**
	 * Read, re-check, sort and bound the sessions matching the criteria.
	 *
	 * A session is included when it overlaps the window. Results are sorted by
	 * start time and bounded by the limit.
	 *
	 * @param array<string,mixed> $criteria The contract's query criteria.
	 * @param bool                $rbac     Whether OpenRegister applies the schema's read rule for the current user.
	 *
	 * @return array<int,array<string,mixed>> Sessions in the contract's read shape.
	 *
	 * @throws InvalidArgumentException When no identity filter is given or the window is invalid.
	 * @throws RuntimeException         When OpenRegister is not available.
	 */
	private function read(array $criteria, bool $rbac): array {
		$query = new TimetableSessionQuery(criteria: $criteria);

		$objectService = $this->objectService();
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SCHEMA,
			filters: $query->filters(),
			_rbac: $rbac
		);

		$sessions = [];
		foreach ($this->rows->listOf(results: $results) as $row) {
			$session = $this->rows->toReadShape(row: $row);
			if ($session !== null && $query->matches(session: $session) === true) {
				$sessions[] = $session;
			}
		}

		usort(
			$sessions,
			static fn (array $left, array $right): int => ((int)strtotime((string)$left['startsAt'])) <=> ((int)strtotime((string)$right['startsAt']))
		);

		return array_slice($sessions, 0, $query->limit());
	}//end read()

	/**
	 * Validate every row and drop the earlier occurrences of a repeated key.
	 *
	 * Rejections are appended to the result as they are found; the rows that
	 * survive are returned in batch order, already normalised.
	 *
	 * @param array<int,mixed>    $sessions     The raw rows.
	 * @param string              $sourceSystem The batch's source system.
	 * @param array<string,mixed> $result       The result being built (by reference).
	 *
	 * @return array<int,array<string,mixed>> The normalised rows to write.
	 */
	private function acceptRows(array $sessions, string $sourceSystem, array &$result): array {
		$valid = [];
		foreach ($sessions as $index => $raw) {
			$checked = $this->rules->checked(raw: $raw, sourceSystem: $sourceSystem);
			if (isset($checked['rejection']) === true) {
				$result['rejected'][] = $checked['rejection'];
				continue;
			}

			$valid[$index] = $checked['row'];
		}

		$lastIndexByRef = array_flip(array_map(static fn (array $row): string => $row['externalRef'], $valid));

		$accepted = [];
		foreach ($valid as $index => $row) {
			if ($lastIndexByRef[$row['externalRef']] !== $index) {
				$result['rejected'][] = $this->rules->rejection(
					externalRef: $row['externalRef'],
					code: 'duplicate-external-ref',
					message: 'The same source id appears again later in this delivery; the later row is used.'
				);
				continue;
			}

			$accepted[] = $row;
		}

		return $accepted;
	}//end acceptRows()

	/**
	 * Create, update or skip one validated row.
	 *
	 * @param object              $objectService The OpenRegister ObjectService.
	 * @param array<string,mixed> $row           The normalised row.
	 * @param array<string,mixed> $result        The result being built (by reference).
	 *
	 * @return void
	 */
	private function upsertRow(object $objectService, array $row, array &$result): void {
		$existing = $this->findExisting(objectService: $objectService, sourceSystem: $row['sourceSystem'], externalRef: $row['externalRef']);

		$kept = null;
		if ($existing !== null) {
			$kept = $this->keptAs(stored: $existing['data'], row: $row);
		}

		if ($kept === 'already-published') {
			$result['rejected'][] = $this->rules->rejection(
				externalRef: $row['externalRef'],
				code: 'already-published',
				message: 'This lesson is already published; a later delivery cannot turn it back into a draft.'
			);
			return;
		}

		if ($kept === 'unchanged') {
			$result['unchanged']++;
			$result['sessionIds'][] = $existing['id'];
			return;
		}

		$write   = $this->writeOf(existing: $existing, row: $row);
		$savedId = $this->save(objectService: $objectService, data: $write['data'], uuid: $write['uuid']);
		if ($savedId === null) {
			$result['rejected'][] = $this->rules->rejection(
				externalRef: $row['externalRef'],
				code: 'save-failed',
				message: 'OpenRegister did not store this lesson.'
			);
			return;
		}

		$result[$write['counter']]++;
		$result['sessionIds'][] = $savedId;
	}//end upsertRow()

	/**
	 * Why a stored match is kept as it is: a published lesson a draft would replace, or no change at all.
	 *
	 * @param array<string,mixed> $stored The stored match's data.
	 * @param array<string,mixed> $row    The normalised row.
	 *
	 * @return string|null 'already-published', 'unchanged', or null when the row is written.
	 */
	private function keptAs(array $stored, array $row): ?string {
		if ($this->rules->wouldUnpublish(stored: $stored, incoming: $row) === true) {
			return 'already-published';
		}

		if ($this->rules->isUnchanged(stored: $stored, incoming: $row) === true) {
			return 'unchanged';
		}

		return null;
	}//end keptAs()

	/**
	 * The data to save for a row, the id to save it under and the counter it adds to.
	 *
	 * An update keeps the stored fields the row does not carry. A lesson without a
	 * title takes its subject, and one without a status is scheduled.
	 *
	 * @param array{id:string,data:array<string,mixed>}|null $existing The stored match, or null.
	 * @param array<string,mixed>                            $row      The normalised row.
	 *
	 * @return array{data:array<string,mixed>,uuid:string|null,counter:string}
	 */
	private function writeOf(?array $existing, array $row): array {
		$write = ['data' => $row, 'uuid' => null, 'counter' => 'created'];
		if ($existing !== null) {
			$write = ['data' => array_merge($this->rules->storedFields(data: $existing['data']), $row), 'uuid' => $existing['id'], 'counter' => 'updated'];
		}

		if (($write['data']['title'] ?? '') === '') {
			$write['data']['title'] = $write['data']['subject'];
		}

		$write['data']['status']     = ($write['data']['status'] ?? 'scheduled');
		$write['data']['importedAt'] = (new DateTimeImmutable())->format(DATE_ATOM);

		return $write;
	}//end writeOf()

	/**
	 * Find the stored session for a (source, occurrence id) pair.
	 *
	 * The filter is re-checked in PHP: a stored row only counts as the match
	 * when both key fields are equal, so an ignored filter can never make the
	 * upsert overwrite another lesson.
	 *
	 * @param object $objectService The OpenRegister ObjectService.
	 * @param string $sourceSystem  The source system.
	 * @param string $externalRef   The occurrence id.
	 *
	 * @return array{id:string,data:array<string,mixed>}|null The match, or null.
	 */
	private function findExisting(object $objectService, string $sourceSystem, string $externalRef): ?array {
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SCHEMA,
			filters: [
				'externalRef' => $externalRef,
				'sourceSystem' => $sourceSystem,
				'_limit' => 2,
			],
			_rbac: false
		);

		$matches = $this->keyMatches(results: $results, key: ['externalRef' => $externalRef, 'sourceSystem' => $sourceSystem]);
		if (count($matches) > 1) {
			$this->logger->warning(
				'[TimetableSessionService] More than one session stored for {source}/{ref}; updating the first.',
				['source' => $sourceSystem, 'ref' => $externalRef]
			);
		}

		return ($matches[0] ?? null);
	}//end findExisting()

	/**
	 * The stored rows whose key fields all equal the key, as id and data.
	 *
	 * @param mixed                $results The OpenRegister search answer.
	 * @param array<string,string> $key     The key fields and their values.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 */
	private function keyMatches(mixed $results, array $key): array {
		$matches = [];
		foreach ($this->rows->listOf(results: $results) as $row) {
			$data = $this->rows->dataOf(row: $row);
			$id   = $this->rows->idOf(row: $row);
			if ($id !== '' && $this->sameValues(data: $data, key: $key) === true) {
				$matches[] = ['id' => $id, 'data' => $data];
			}
		}

		return $matches;
	}//end keyMatches()

	/**
	 * Whether the data holds every key field with exactly its value.
	 *
	 * @param array<string,mixed>  $data The stored data.
	 * @param array<string,string> $key  The key fields and their values.
	 *
	 * @return bool
	 */
	private function sameValues(array $data, array $key): bool {
		foreach ($key as $field => $value) {
			if (($data[$field] ?? null) !== $value) {
				return false;
			}
		}

		return true;
	}//end sameValues()

	/**
	 * Save one session and return its id, or null when OpenRegister refused.
	 *
	 * Written with RBAC off: this service is reached only through planninq's
	 * own listener (in-process server code) or the admin-only endpoint.
	 *
	 * @param object              $objectService The OpenRegister ObjectService.
	 * @param array<string,mixed> $data          The session data.
	 * @param string|null         $uuid          The stored id to update, or null to create.
	 *
	 * @return string|null
	 */
	private function save(object $objectService, array $data, ?string $uuid): ?string {
		try {
			$saved = $objectService->saveObject(
				object: $data,
				register: self::REGISTER,
				schema: self::SCHEMA,
				uuid: $uuid,
				_rbac: false
			);
		} catch (Throwable $e) {
			$this->logger->error(
				'[TimetableSessionService] Saving {ref} failed: {message}',
				['ref' => ($data['externalRef'] ?? ''), 'message' => $e->getMessage()]
			);
			return null;
		}

		if ($saved === null) {
			return null;
		}

		$id = $this->rows->idOf(row: $saved);
		if ($id !== '') {
			return $id;
		}

		return $uuid;
	}//end save()

	/**
	 * Resolve OpenRegister's ObjectService, cleared of any earlier caller's scope.
	 *
	 * @return object
	 *
	 * @throws RuntimeException When OpenRegister is not available.
	 */
	private function objectService(): object {
		try {
			$objectService = $this->container->get(self::OR_OBJECT_SERVICE);
		} catch (Throwable $e) {
			throw new RuntimeException('OpenRegister is not available.', 0, $e);
		}

		// The ObjectService is shared per request; drop any register or schema
		// an earlier caller left on it (openregister#2820, see TimelineController).
		if (method_exists($objectService, 'clearCurrents') === true) {
			$objectService->clearCurrents();
		}

		return $objectService;
	}//end objectService()

}//end class
