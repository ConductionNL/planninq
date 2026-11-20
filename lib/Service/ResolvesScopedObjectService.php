<?php

/**
 * Planninq ResolvesScopedObjectService
 *
 * Resolves OpenRegister's ObjectService from the container, cleared of any
 * register or schema an earlier caller left on the shared instance.
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
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use RuntimeException;
use Throwable;

/**
 * OpenRegister's ObjectService, cleared of an earlier caller's scope.
 *
 * The using class holds a `$container` (Psr\Container\ContainerInterface) and
 * the constant OR_OBJECT_SERVICE with the ObjectService's container id.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */
trait ResolvesScopedObjectService {

	/**
	 * Resolve OpenRegister's ObjectService, cleared of any earlier caller's scope.
	 *
	 * @return object
	 *
	 * @throws RuntimeException When OpenRegister is not available.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
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
}//end trait
