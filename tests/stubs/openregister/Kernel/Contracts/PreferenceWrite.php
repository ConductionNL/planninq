<?php

/**
 * Test stub of OpenRegister's PreferenceWrite (openregister #9049, lib/Kernel/Contracts).
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
 * One preference write: the party (user or group id), the address and the value.
 */
final class PreferenceWrite {
	/**
	 * Constructor.
	 *
	 * @param string                    $party   The user or group id.
	 * @param PreferenceAddress         $address Where the preference lives.
	 * @param array<string, mixed>|null $value   The value, or null to clear.
	 */
	public function __construct(
		public readonly string $party,
		public readonly PreferenceAddress $address,
		public readonly ?array $value,
	) {
	}//end __construct()
}//end class
