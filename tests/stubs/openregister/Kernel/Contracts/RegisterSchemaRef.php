<?php

/**
 * Test stub of OpenRegister's RegisterSchemaRef (openregister #9103, lib/Kernel/Contracts).
 *
 * Same constructor as the real value object, so planninq's
 * searchObjectsBySlug() calls can be tested without OpenRegister.
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
 * A register and a schema, each by slug or id.
 */
final class RegisterSchemaRef {
	/**
	 * Constructor.
	 *
	 * @param string|int $register The register slug or id.
	 * @param string|int $schema   The schema slug or id.
	 */
	public function __construct(
		public readonly string|int $register,
		public readonly string|int $schema,
	) {
	}//end __construct()
}//end class
