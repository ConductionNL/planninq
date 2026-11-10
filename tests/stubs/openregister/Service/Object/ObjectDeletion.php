<?php

/**
 * Test stub of OpenRegister's ObjectDeletion.
 *
 * Mirrored from lib/Service/Object/ObjectDeletion.php in
 * ConductionNL/openregister (openregister#9099), the value object
 * ObjectService::deleteObject() takes, so planninq's deletes can be tested
 * without OpenRegister. A drifted stub is worse than none: update this file
 * when the engine changes.
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

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Object;

use OCP\IUser;

/**
 * One delete a caller asks for: the object, where to find it, who acts and whether the row is destroyed.
 */
final class ObjectDeletion {

	/**
	 * Constructor.
	 *
	 * @param string           $uuid        The UUID of the object to delete.
	 * @param ObjectFindTarget $target      The register and schema the caller named.
	 * @param IUser|null       $currentUser Explicit acting user, or null for the session user.
	 * @param bool             $permanent   Destroy the row rather than tombstone it.
	 */
	public function __construct(
		public readonly string $uuid,
		public readonly ObjectFindTarget $target=new ObjectFindTarget(),
		public readonly ?IUser $currentUser=null,
		public readonly bool $permanent=false,
	) {
	}//end __construct()

	/**
	 * Whether the caller named both a register and a schema, so the lookup is scoped to one table.
	 *
	 * @return bool
	 */
	public function isScoped(): bool {
		return $this->target->register !== null && $this->target->schema !== null;
	}//end isScoped()
}//end class
