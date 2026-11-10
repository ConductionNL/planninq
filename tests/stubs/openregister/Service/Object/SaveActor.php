<?php

/**
 * Test stub of OpenRegister's SaveActor.
 *
 * Same constructor, property names, types and defaults as
 * lib/Service/Object/SaveActor.php in ConductionNL/openregister (PR #9123),
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

use OCP\IUser;

/**
 * On whose behalf one object save is written, and who owns a row it creates.
 */
final class SaveActor {

	/**
	 * Constructor.
	 *
	 * @param IUser|null  $currentUser Explicit acting user; null resolves the session user.
	 * @param string|null $owner       An explicit owner for a new object.
	 * @param bool        $unowned     Stamp the system identity as owner even when a user session exists.
	 */
	public function __construct(
		public readonly ?IUser $currentUser=null,
		public readonly ?string $owner=null,
		public readonly bool $unowned=false,
	) {
	}//end __construct()
}//end class
