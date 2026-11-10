<?php

/**
 * Test stub of OpenRegister's SavePolicy.
 *
 * Same constructor, property names, types and defaults as
 * lib/Service/Object/SavePolicy.php in ConductionNL/openregister (PR #9123),
 * so planninq's saveObject() calls can be tested without OpenRegister.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service\Object
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

namespace OCA\OpenRegister\Service\Object;

/**
 * The policy switches of one object save. Every default is the behaviour a caller gets without asking.
 */
final class SavePolicy {

	/**
	 * Constructor.
	 *
	 * @param bool $silent        Skip audit trail creation and events.
	 * @param bool $validation    Validate the object against its schema.
	 * @param bool $failIfExists  Insert-only: fail rather than update when the identifier is taken.
	 * @param bool $dedupOverride Save through a blocking duplicate match.
	 */
	public function __construct(
		public readonly bool $silent=false,
		public readonly bool $validation=true,
		public readonly bool $failIfExists=false,
		public readonly bool $dedupOverride=false,
	) {
	}//end __construct()
}//end class
