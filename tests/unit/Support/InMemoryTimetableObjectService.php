<?php

/**
 * In-memory stand-in for OpenRegister's ObjectService, for the timetable tests.
 *
 * Kept apart from InMemoryObjectService (the membership tests' double, which
 * landed at the same time) because the timetable tests need two switches it
 * does not have: `ignoreFilters` and `failSaves`.
 *
 * Extends the signature-faithful {@see ObjectServiceDouble} so every named
 * argument production code passes is checked against OpenRegister's real
 * parameter names, and adds just enough behaviour to store and search rows:
 * scalar filters match exactly, `gte` / `lte` arrays compare as strings the
 * way a database column would, and `_`-prefixed query options are ignored.
 *
 * `ignoreFilters` makes the search return every stored row, which is how a
 * test proves that production code re-checks what OpenRegister answered.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Support;

require_once __DIR__ . '/ObjectServiceDouble.php';

/**
 * Stores saved objects per schema in memory.
 */
class InMemoryTimetableObjectService extends ObjectServiceDouble {

	/**
	 * Stored rows, keyed by schema slug then object id.
	 *
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	public array $rows = [];

	/**
	 * Every saveObject() call, in order.
	 *
	 * @var array<int,array{schema:mixed,uuid:?string,rbac:bool,object:array<string,mixed>}>
	 */
	public array $saves = [];

	/**
	 * Every searchObjectsBySlug() call, in order.
	 *
	 * @var array<int,array{schema:string,filters:array<string,mixed>,rbac:bool}>
	 */
	public array $searches = [];

	/**
	 * When true, searches return every stored row of the schema.
	 *
	 * @var bool
	 */
	public bool $ignoreFilters = false;

	/**
	 * When true, saveObject() throws.
	 *
	 * @var bool
	 */
	public bool $failSaves = false;

	/**
	 * Number of clearCurrents() calls.
	 *
	 * @var int
	 */
	public int $clears = 0;

	/**
	 * Counter for generated ids.
	 *
	 * @var int
	 */
	private int $sequence = 0;

	/**
	 * Seed one stored row.
	 *
	 * @param string              $schema The schema slug.
	 * @param string              $id     The object id.
	 * @param array<string,mixed> $data   The object data.
	 *
	 * @return void
	 */
	public function seed(string $schema, string $id, array $data): void {
		$this->rows[$schema][$id] = $data;
	}//end seed()

	/**
	 * Drop the current register and schema.
	 *
	 * @return void
	 */
	public function clearCurrents(): void {
		$this->clears++;
	}//end clearCurrents()

	/**
	 * Persist an object in memory.
	 *
	 * @param array|object $object        The object data.
	 * @param array|null   $extend        Unused.
	 * @param mixed        $register      Unused.
	 * @param mixed        $schema        The schema slug.
	 * @param string|null  $uuid          The id to update, or null to create.
	 * @param bool         $_rbac         Recorded.
	 * @param bool         $_multitenancy Unused.
	 * @param bool         $silent        Unused.
	 * @param bool         $_validation   Unused.
	 *
	 * @return object|null
	 */
	public function saveObject(
		array|object $object,
		?array $extend = [],
		mixed $register = null,
		mixed $schema = null,
		?string $uuid = null,
		bool $_rbac = true,
		bool $_multitenancy = true,
		bool $silent = false,
		bool $_validation = true,
	): ?object {
		if ($this->failSaves === true) {
			throw new \RuntimeException('save refused');
		}

		$data = (array)$object;
		$this->saves[] = ['schema' => $schema, 'uuid' => $uuid, 'rbac' => $_rbac, 'object' => $data];

		$id = $uuid;
		if ($id === null) {
			$this->sequence++;
			$id = sprintf('00000000-0000-4000-a000-%012d', $this->sequence);
		}

		$this->rows[(string)$schema][$id] = $data;

		return new class($id, $data) {
			/**
			 * Constructor.
			 *
			 * @param string              $uuid The id.
			 * @param array<string,mixed> $data The data.
			 */
			public function __construct(
				private string $uuid,
				private array $data,
			) {
			}

			/**
			 * The id.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				return $this->uuid;
			}

			/**
			 * The data.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return $this->data;
			}
		};
	}//end saveObject()

	/**
	 * Search stored rows of one schema.
	 *
	 * @param string $registerSlug  Unused.
	 * @param string $schemaSlug    The schema slug.
	 * @param array  $filters       Filters.
	 * @param bool   $_rbac         Recorded.
	 * @param bool   $_multitenancy Unused.
	 *
	 * @return array|int
	 */
	public function searchObjectsBySlug(
		string $registerSlug,
		string $schemaSlug,
		array $filters = [],
		bool $_rbac = true,
		bool $_multitenancy = true,
	): array|int {
		$this->searches[] = ['schema' => $schemaSlug, 'filters' => $filters, 'rbac' => $_rbac];

		$found = [];
		foreach ($this->rows[$schemaSlug] ?? [] as $id => $data) {
			if ($this->ignoreFilters === false && $this->matches(data: $data, filters: $filters) === false) {
				continue;
			}

			$found[] = array_merge($data, ['@self' => ['id' => $id]]);
		}

		if ($this->ignoreFilters === false && isset($filters['_limit']) === true) {
			$found = array_slice($found, 0, (int)$filters['_limit']);
		}

		return $found;
	}//end searchObjectsBySlug()

	/**
	 * Whether a row satisfies the filters.
	 *
	 * @param array<string,mixed> $data    The row.
	 * @param array<string,mixed> $filters The filters.
	 *
	 * @return bool
	 */
	private function matches(array $data, array $filters): bool {
		foreach ($filters as $key => $expected) {
			if (str_starts_with((string)$key, '_') === true || $key === '@self') {
				continue;
			}

			$actual = $data[$key] ?? null;
			if (is_array($expected) === true) {
				if (isset($expected['gte']) === true && (string)$actual < (string)$expected['gte']) {
					return false;
				}

				if (isset($expected['lte']) === true && (string)$actual > (string)$expected['lte']) {
					return false;
				}

				continue;
			}

			if ($actual !== $expected) {
				return false;
			}
		}//end foreach

		return true;
	}//end matches()
}//end class
