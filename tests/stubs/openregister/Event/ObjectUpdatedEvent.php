<?php

/**
 * Test-only stub for OpenRegister's ObjectUpdatedEvent.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/ObjectUpdatedEvent.php.
 *
 * @category Test
 * @package  OCA\OpenRegister\Event
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Event;

use OCA\OpenRegister\Db\ObjectEntity;
use OCP\EventDispatcher\Event;

/**
 * Test-only stub of the object-updated event.
 */
class ObjectUpdatedEvent extends Event {
	/**
	 * Constructor.
	 *
	 * @param ObjectEntity $newObject The updated object.
	 * @param ObjectEntity|null $oldObject The object as it was before the update.
	 */
	public function __construct(
		private ObjectEntity $newObject,
		private ?ObjectEntity $oldObject = null,
	) {
		parent::__construct();

	}//end __construct()

	/**
	 * Return the updated object.
	 *
	 * @return ObjectEntity The updated object.
	 */
	public function getObject(): ObjectEntity {
		return $this->newObject;
	}//end getObject()

	/**
	 * Return the updated object.
	 *
	 * @return ObjectEntity The updated object.
	 */
	public function getNewObject(): ObjectEntity {
		return $this->newObject;
	}//end getNewObject()

	/**
	 * Return the object as it was before the update.
	 *
	 * @return ObjectEntity|null The previous object, or null when unavailable.
	 */
	public function getOldObject(): ?ObjectEntity {
		return $this->oldObject;
	}//end getOldObject()
}//end class
