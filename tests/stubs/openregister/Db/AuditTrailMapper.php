<?php

/**
 * Test-only stub for OpenRegister's AuditTrailMapper.
 *
 * TEST-ONLY, loaded through the PSR-4 prefix tests/bootstrap-unit.php appends
 * (see ObjectEntity.php beside it). Mirrored from
 * openregister/lib/Db/AuditTrailMapper.php at development 9a4e28e9a9: only the
 * method planninq calls, with its exact signature. A drifted stub is worse
 * than none; update this file when the engine's signature changes.
 *
 * @category Test
 * @package  OCA\OpenRegister\Db
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

namespace OCA\OpenRegister\Db;

/**
 * The audit trail mapper, reduced to the history read.
 */
class AuditTrailMapper {

	/**
	 * The change history of one object, oldest first, purged rows excluded.
	 *
	 * @param string $objectUuid The object.
	 * @param int    $limit      Most rows to read.
	 *
	 * @return array<int, array{created: string, changed: array}> The changes.
	 */
	public function findChangesForObject(string $objectUuid, int $limit = 1000): array {
		return [];
	}//end findChangesForObject()
}//end class
