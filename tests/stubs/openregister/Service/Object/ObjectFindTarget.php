<?php

/**
 * Test stub of OpenRegister's ObjectFindTarget.
 *
 * Mirrored from lib/Service/Object/ObjectFindTarget.php in
 * ConductionNL/openregister (openregister#9099: both arguments default to
 * null), so planninq's ObjectDeletion calls can be tested without OpenRegister.
 * A drifted stub is worse than none: update this file when the engine changes.
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

use OCA\OpenRegister\Db\Register;
use OCA\OpenRegister\Db\Schema;

/**
 * The register and schema one ObjectService call names, as the caller gave them.
 */
final class ObjectFindTarget {

	/**
	 * Constructor.
	 *
	 * @param Register|string|int|null $register The register object or its ID/UUID.
	 * @param Schema|string|int|null   $schema   The schema object or its ID/UUID.
	 */
	public function __construct(
		public readonly Register|string|int|null $register=null,
		public readonly Schema|string|int|null $schema=null,
	) {
	}//end __construct()
}//end class
