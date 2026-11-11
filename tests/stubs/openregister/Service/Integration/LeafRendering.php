<?php

/**
 * Test-only stub for OpenRegister's LeafRendering.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Service/Integration/LeafRendering.php: the
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

final class LeafRendering {
	/**
	 * @param array<int, string> $surfaces Surfaces.
	 */
	public function __construct(
		public readonly array $surfaces = [],
		public readonly ?string $referenceType = null,
		public readonly string $renderMode = 'component',
		public readonly ?string $loadStrategy = null,
	) {
	}
}
