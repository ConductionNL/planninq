<?php

/**
 * Test-only stub for OpenRegister's LeafAvailability.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Service/Integration/LeafAvailability.php: the
 * constructor's named parameters, as public readonly fields.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service\Integration
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

namespace OCA\OpenRegister\Service\Integration;

final class LeafAvailability {
	public function __construct(
		public readonly ?string $requiredApp = null,
		public readonly ?string $requiresPermission = null,
	) {
	}
}
