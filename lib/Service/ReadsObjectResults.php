<?php

/**
 * Planninq ReadsObjectResults
 *
 * Reads the rows of an ObjectService result set and the data of each row,
 * which is an OpenRegister entity or a plain array.
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
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * The rows of an ObjectService result set and the data of each row.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
trait ReadsObjectResults {

	/**
	 * Normalise an ObjectService result set (paginated array with a `results`
	 * key, plain list, or list of entity objects) to a plain list of rows.
	 *
	 * @param mixed $results The raw ObjectService return value.
	 *
	 * @return array<int,mixed>
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
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
	 * Extract the object data array from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
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
}//end trait
