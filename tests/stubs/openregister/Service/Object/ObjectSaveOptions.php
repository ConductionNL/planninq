<?php

/**
 * Test stub of OpenRegister's ObjectSaveOptions.
 *
 * Same constructor, property names, types and defaults as
 * lib/Service/Object/ObjectSaveOptions.php in ConductionNL/openregister (PR #9123),
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
 * How one object save runs: target, uuid, extend, uploaded files, policy and actor.
 *
 * No strict_types, as in OpenRegister: callers pass request values through it.
 */
final class ObjectSaveOptions {

	/**
	 * Constructor.
	 *
	 * @param ObjectFindTarget $target        The register and schema the object is saved in.
	 * @param string|null      $uuid          The UUID of the object to update, null to create.
	 * @param array|null       $extend        Properties to extend the rendered result with.
	 * @param array|null       $uploadedFiles Uploaded files from multipart/form-data.
	 * @param SavePolicy       $policy        Events, validation, insert-only and duplicate-override choices.
	 * @param SaveActor        $actor         The acting user and the owner the row is stamped with.
	 */
	public function __construct(
		public readonly ObjectFindTarget $target=new ObjectFindTarget(),
		public readonly ?string $uuid=null,
		public readonly ?array $extend=[],
		public readonly ?array $uploadedFiles=null,
		public readonly SavePolicy $policy=new SavePolicy(),
		public readonly SaveActor $actor=new SaveActor(),
	) {
	}//end __construct()
}//end class
