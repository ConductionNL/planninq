<?php

/**
 * Test stub of OpenRegister's CredentialMint, with the signatures planninq calls.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Credential;

final class CredentialMint {
	public function __construct(
		public readonly string $name,
		public readonly string $provider,
		public readonly string $owner,
		public readonly array $metadata = [],
	) {
	}
}
