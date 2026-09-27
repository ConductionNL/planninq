<?php

/**
 * Planninq Timetable Session Query
 *
 * One validated read of the school timetable: whose lessons (a cohort, a
 * group code, a teacher account or a teacher code), in which window, how many
 * at most, and whether cancelled lessons count. Built from the contract's
 * criteria; refuses criteria that name nobody or a window that ends before it
 * starts. It produces the bounded OpenRegister filter and re-checks every row
 * OpenRegister answers, so a read never depends on every filter being applied.
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
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use InvalidArgumentException;

/**
 * A validated, bounded timetable read.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */
class TimetableSessionQuery {

	/**
	 * Rows a read returns when the caller names no limit.
	 *
	 * @var int
	 */
	public const DEFAULT_LIMIT = 500;

	/**
	 * Hard ceiling on the rows one read returns (ADR-058 bounded queries).
	 *
	 * @var int
	 */
	public const MAX_LIMIT = 1000;

	/**
	 * The criteria keys that name whose timetable is read. At least one is required.
	 *
	 * @var string[]
	 */
	public const IDENTITY_KEYS = ['cohortId', 'groupReference', 'teacherUserId', 'teacherReference'];

	/**
	 * Seconds the window is widened by in the OpenRegister prefilter.
	 *
	 * @var int
	 */
	private const WINDOW_MARGIN = 86400;

	/**
	 * The identity filters, by criteria key.
	 *
	 * @var array<string,string>
	 */
	private array $identity;

	/**
	 * Window start as a Unix timestamp, or null when open.
	 *
	 * @var int|null
	 */
	private ?int $from;

	/**
	 * Window end as a Unix timestamp, or null when open.
	 *
	 * @var int|null
	 */
	private ?int $to;

	/**
	 * The row limit.
	 *
	 * @var int
	 */
	private int $limit;

	/**
	 * Whether cancelled lessons are included.
	 *
	 * @var bool
	 */
	private bool $includeCancelled;

	/**
	 * Validate the contract's criteria.
	 *
	 * @param array<string,mixed> $criteria cohortId, groupReference, teacherUserId, teacherReference,
	 *                                      from, to, limit, includeCancelled.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When no identity filter is given or the window is invalid.
	 */
	public function __construct(array $criteria) {
		$this->identity = $this->parseIdentity(criteria: $criteria);
		$this->from = $this->parseMoment(value: ($criteria['from'] ?? null), name: 'from');
		$this->to = $this->parseMoment(value: ($criteria['to'] ?? null), name: 'to');
		if ($this->from !== null && $this->to !== null && $this->from > $this->to) {
			throw new InvalidArgumentException('The window starts after it ends.');
		}

		$this->limit = $this->parseLimit(value: ($criteria['limit'] ?? null));
		$this->includeCancelled = true;
		if (array_key_exists('includeCancelled', $criteria) === true) {
			$this->includeCancelled = filter_var($criteria['includeCancelled'], FILTER_VALIDATE_BOOLEAN);
		}
	}//end __construct()

	/**
	 * The row limit.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function limit(): int {
		return $this->limit;
	}//end limit()

	/**
	 * The bounded OpenRegister filter for this read.
	 *
	 * The window goes to OpenRegister one day wider on each side. A stored time
	 * keeps the offset it was delivered with and a column compares strings, so
	 * `09:30+02:00` and `07:30Z` would disagree by the offset at the edges. The
	 * wide window is a coarse prefilter; matches() applies the exact one.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function filters(): array {
		$filters = $this->identity;
		if ($this->to !== null) {
			$filters['startsAt'] = ['lte' => date(DATE_ATOM, ($this->to + self::WINDOW_MARGIN))];
		}

		if ($this->from !== null) {
			$filters['endsAt'] = ['gte' => date(DATE_ATOM, ($this->from - self::WINDOW_MARGIN))];
		}

		$filters['_limit'] = $this->limit;
		$filters['_order'] = ['startsAt' => 'ASC'];

		return $filters;
	}//end filters()

	/**
	 * Whether a session in read shape belongs in this read.
	 *
	 * @param array<string,mixed> $session The session.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function matches(array $session): bool {
		if ($this->matchesIdentity(session: $session) === false) {
			return false;
		}

		if ($this->includeCancelled === false && ($session['status'] ?? '') === 'cancelled') {
			return false;
		}

		return $this->overlapsWindow(start: strtotime((string)($session['startsAt'] ?? '')), end: strtotime((string)($session['endsAt'] ?? '')));
	}//end matches()

	/**
	 * Whether the session carries every identity filter of this read.
	 *
	 * @param array<string,mixed> $session The session.
	 *
	 * @return bool
	 */
	private function matchesIdentity(array $session): bool {
		foreach ($this->identity as $key => $value) {
			if ((string)($session[$key] ?? '') !== $value) {
				return false;
			}
		}

		return true;
	}//end matchesIdentity()

	/**
	 * Whether a lesson between two moments overlaps the window.
	 *
	 * @param int|false $start Lesson start, or false when unreadable.
	 * @param int|false $end   Lesson end, or false when unreadable.
	 *
	 * @return bool
	 */
	private function overlapsWindow(int|false $start, int|false $end): bool {
		if ($start === false || $end === false) {
			return false;
		}

		if ($this->to !== null && $start > $this->to) {
			return false;
		}

		return $this->from === null || $end >= $this->from;
	}//end overlapsWindow()

	/**
	 * Collect the identity filters the criteria name.
	 *
	 * @param array<string,mixed> $criteria The criteria.
	 *
	 * @return array<string,string>
	 *
	 * @throws InvalidArgumentException When none is named.
	 */
	private function parseIdentity(array $criteria): array {
		$identity = [];
		foreach (self::IDENTITY_KEYS as $key) {
			$value = $criteria[$key] ?? null;
			if (is_scalar($value) === true && trim((string)$value) !== '') {
				$identity[$key] = trim((string)$value);
			}
		}

		if ($identity === []) {
			throw new InvalidArgumentException('Name a cohortId, groupReference, teacherUserId or teacherReference to read a timetable.');
		}

		return $identity;
	}//end parseIdentity()

	/**
	 * Parse the limit, defaulting and capping it.
	 *
	 * @param mixed $value The raw limit.
	 *
	 * @return int
	 */
	private function parseLimit(mixed $value): int {
		if (is_numeric($value) === false) {
			return self::DEFAULT_LIMIT;
		}

		return max(1, min(self::MAX_LIMIT, (int)$value));
	}//end parseLimit()

	/**
	 * Parse an optional ISO 8601 moment.
	 *
	 * @param mixed  $value The raw value.
	 * @param string $name  The criteria key, for the error message.
	 *
	 * @return int|null The Unix timestamp, or null when absent.
	 *
	 * @throws InvalidArgumentException When the value is present and unparseable.
	 */
	private function parseMoment(mixed $value, string $name): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		$stamp = false;
		if (is_string($value) === true) {
			$stamp = strtotime($value);
		}

		if ($stamp === false) {
			throw new InvalidArgumentException("The {$name} value is not a date and time.");
		}

		return $stamp;
	}//end parseMoment()
}//end class
