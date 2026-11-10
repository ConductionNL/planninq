<?php

/**
 * Test stub of OpenRegister's PreferenceAddress (openregister #9049, lib/Kernel/Contracts).
 *
 * Same constructor as the real value object, so planninq's preference reads
 * and writes can be tested without OpenRegister.
 *
 * @category Test
 * @package  OCA\OpenRegister\Kernel\Contracts
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Kernel\Contracts;

/**
 * Where a preference lives: schema slug, notification key and optional scope.
 */
final class PreferenceAddress {
	/**
	 * Constructor.
	 *
	 * @param string      $schemaSlug      The schema slug.
	 * @param string      $notificationKey The notification rule key.
	 * @param string|null $scope           The scope, or null for the global one.
	 */
	public function __construct(
		public readonly string $schemaSlug,
		public readonly string $notificationKey,
		public readonly ?string $scope = null,
	) {
	}//end __construct()
}//end class
