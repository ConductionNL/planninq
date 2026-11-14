<?php

/**
 * Test stub of OpenRegister's CredentialBrokerService, with the signatures planninq calls.
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

use OCA\OpenRegister\Db\ObjectEntity;

class CredentialBrokerService {
	public function resolveInjectable(string $credentialId, string $appId, ?string $actingUserId = null, ?string $actingOrganisationId = null): ?string {
		return null;
	}

	public function mintCredential(CredentialMint $mint, CredentialMintOptions $options): ObjectEntity {
		return new ObjectEntity();
	}
}
