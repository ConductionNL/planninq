<?php

/**
 * In-memory stand-in for OpenRegister's ObjectService, for membership tests.
 *
 * Extends {@see ObjectServiceDouble}, so every method keeps OpenRegister's
 * parameter NAMES: a named argument the real service lacks fails here too. The
 * bodies keep rows in memory and record every write and search, so a test can
 * assert what reached OpenRegister rather than what a mock was told to return.
 *
 * Filter semantics mirror `MagicSearchHandler::applyObjectFilters()` for the two
 * shapes planninq sends: a scalar is an equality test, a list is `IN`.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\OpenRegister\Db\ObjectEntity;

/**
 * Rows by schema slug and uuid, with a record of every call.
 */
class InMemoryObjectService extends ObjectServiceDouble {

	/**
	 * Stored rows: schema slug => uuid => data.
	 *
	 * @var array<string, array<string, array<string,mixed>>>
	 */
	public array $rows = [];

	/**
	 * Every saveObject() call, in order.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	public array $saves = [];

	/**
	 * Every find() call, in order.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	public array $finds = [];

	/**
	 * Every searchObjectsBySlug() call, in order.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	public array $searches = [];

	/**
	 * Uuids whose saveObject() throws, to exercise per-row failure handling.
	 *
	 * @var array<int,string>
	 */
	public array $failingSaves = [];

	/**
	 * Store a row.
	 *
	 * @param string $schema The schema slug.
	 * @param string $uuid The uuid.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return void
	 */
	public function seed(string $schema, string $uuid, array $data): void {
		$this->rows[$schema][$uuid] = $data;

	}//end seed()

	/**
	 * {@inheritDoc}
	 */
	public function find(
		int|string $id,
		?array $_extend = [],
		bool $files = false,
		mixed $register = null,
		mixed $schema = null,
		bool $_rbac = true,
		bool $_multitenancy = true,
		bool $_render = true,
		bool $_audit = true,
	): ?object {
		$this->finds[] = [
			'id' => (string)$id,
			'register' => $register,
			'schema' => $schema,
			'_rbac' => $_rbac,
			'_multitenancy' => $_multitenancy,
		];

		$data = ($this->rows[(string)$schema][(string)$id] ?? null);
		if ($data === null) {
			throw new \RuntimeException('Object not found: ' . $schema . '/' . $id);
		}

		return self::entity(uuid: (string)$id, data: $data);
	}//end find()

	/**
	 * {@inheritDoc}
	 */
	public function searchObjectsBySlug(
		string $registerSlug,
		string $schemaSlug,
		array $filters = [],
		bool $_rbac = true,
		bool $_multitenancy = true,
	): array|int {
		$this->searches[] = [
			'register' => $registerSlug,
			'schema' => $schemaSlug,
			'filters' => $filters,
			'_rbac' => $_rbac,
			'_multitenancy' => $_multitenancy,
		];

		$found = [];
		foreach (($this->rows[$schemaSlug] ?? []) as $uuid => $data) {
			if ($this->matches(data: $data, filters: $filters) === true) {
				$found[] = self::entity(uuid: (string)$uuid, data: $data);
			}
		}

		return $found;
	}//end searchObjectsBySlug()

	/**
	 * {@inheritDoc}
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
		$this->saves[] = [
			'object' => $object,
			'register' => $register,
			'schema' => $schema,
			'uuid' => $uuid,
			'_rbac' => $_rbac,
			'_multitenancy' => $_multitenancy,
			'silent' => $silent,
			'_validation' => $_validation,
		];

		if (in_array((string)$uuid, $this->failingSaves, true) === true) {
			throw new \RuntimeException('save refused for ' . $uuid);
		}

		// A create (no uuid) gets one, as OpenRegister gives it.
		if ($uuid === null || $uuid === '') {
			$uuid = sprintf('00000000-0000-4000-8000-%012d', count($this->saves));
		}

		$this->rows[(string)$schema][(string)$uuid] = (array)$object;

		return self::entity(uuid: (string)$uuid, data: (array)$object);
	}//end saveObject()

	/**
	 * Build an ObjectEntity whose magic accessors are real methods.
	 *
	 * ObjectEntity's getUuid/getRegister/getSchema are `__call` accessors, which
	 * PHPUnit cannot configure; a subclass declaring them is what the other
	 * listener tests in this suite use too.
	 *
	 * @param string $uuid The uuid.
	 * @param array<string,mixed> $data The object data.
	 * @param string $register The register id.
	 * @param string $schema The schema id.
	 *
	 * @return ObjectEntity The entity.
	 */
	public static function entity(string $uuid, array $data, string $register = '', string $schema = ''): ObjectEntity {
		return new class($uuid, $data, $register, $schema) extends ObjectEntity {
			// phpcs:disable
			public function __construct(
				private string $u,
				private array $d,
				private string $reg,
				private string $sch,
			) {
			}
			public function getObject(): array {
				return $this->d;
			}
			public function getUuid(): ?string {
				return $this->u;
			}
			public function getRegister(): ?string {
				return $this->reg;
			}
			public function getSchema(): ?string {
				return $this->sch;
			}
			// phpcs:enable
		};
	}//end entity()

	/**
	 * Whether a row satisfies every filter.
	 *
	 * @param array<string,mixed> $data The row.
	 * @param array<string,mixed> $filters The filters.
	 *
	 * @return bool
	 */
	private function matches(array $data, array $filters): bool {
		foreach ($filters as $key => $expected) {
			$actual = ($data[$key] ?? null);
			if (is_array($expected) === true) {
				if (in_array($actual, $expected, true) === false) {
					return false;
				}

				continue;
			}

			if ($actual !== $expected) {
				return false;
			}
		}

		return true;
	}//end matches()
}//end class
