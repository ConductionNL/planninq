<?php

/**
 * Planninq ObjectRows
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rows of an OpenRegister search read as the system, each as its uuid and
 * its data, whatever shape OpenRegister answers in: a `{results: [...]}`
 * envelope or a plain list, of entities or of arrays. Shared by the services
 * and repair steps that read planninq objects without a user.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Reads search rows as uuid and data.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
 */
final class ObjectRows {
	/**
	 * Search one schema of a register as the system and answer each row with a uuid.
	 *
	 * `searchObjectsBySlug()`, not setters plus `searchObjects()`: the latter
	 * ignores the register and schema the setters leave behind (see
	 * DependencyRepository::fetchProjectTaskIds()).
	 *
	 * @param object              $objectService OpenRegister's ObjectService.
	 * @param string              $register      The register slug.
	 * @param string              $schema        The schema slug.
	 * @param array<string,mixed> $filters       The filters.
	 *
	 * @return array<int, array{id: string, data: array<string,mixed>}> The rows found.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
	 */
	public static function search(object $objectService, string $register, string $schema, array $filters): array {
		$results = $objectService->searchObjectsBySlug(
			registerSlug: $register,
			schemaSlug: $schema,
			filters: $filters,
			_rbac: false,
			_multitenancy: false
		);

		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$rows = [];
		foreach ((array)$results as $row) {
			$id = self::id(row: $row);
			if ($id !== '') {
				$rows[] = ['id' => $id, 'data' => self::data(row: $row)];
			}
		}

		return $rows;
	}//end search()

	/**
	 * The uuid of a search row, entity or array.
	 *
	 * @param mixed $row The row.
	 *
	 * @return string The uuid, or ''.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
	 */
	public static function id(mixed $row): string {
		// `is_callable()`, not `method_exists()`: an OpenRegister entity serves
		// getUuid() through `__call()` (see LabelService::extractId()).
		if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
			return (string)($row->getUuid() ?? '');
		}

		if (is_array($row) === true) {
			return (string)($row['@self']['id'] ?? ($row['id'] ?? ''));
		}

		return '';
	}//end id()

	/**
	 * The data of a search row, entity or array.
	 *
	 * @param mixed $row The row.
	 *
	 * @return array<string,mixed> The object data.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
	 */
	public static function data(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			unset($row['@self']);
			return $row;
		}

		return [];
	}//end data()
}//end class
