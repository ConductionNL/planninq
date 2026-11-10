<?php

/**
 * Test-only stub for OpenRegister's ObjectUpdatingEvent.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/ObjectUpdatingEvent.php, including where each
 * public method is declared: OpenRegisterEventStubDriftTest compares the
 * methods each file declares with the real file of the same name.
 *
 * The veto surface (StoppableEventInterface plus the WriteVetoState trait) is
 * load-bearing: stopping propagation of a pre-write event VETOES the write,
 * which OpenRegister answers as 422. A stub that dropped it would let a
 * listener "veto" in a way that compiles and does nothing.
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
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Dispatched before an object is updated.
 */
class ObjectUpdatingEvent extends Event implements StoppableEventInterface {
	use WriteVetoState;

	/**
	 * Constructor.
	 *
	 * @param ObjectEntity      $newObject The object as it will be saved.
	 * @param ObjectEntity|null $oldObject The object as stored, if known.
	 */
	public function __construct(private readonly ObjectEntity $newObject, private readonly ?ObjectEntity $oldObject = null) {
		parent::__construct();
	}//end __construct()

	/**
	 * The object as stored.
	 *
	 * @return ObjectEntity|null
	 */
	public function getOldObject(): ?ObjectEntity {
		return $this->oldObject;
	}//end getOldObject()

	/**
	 * The object as it will be saved.
	 *
	 * @return ObjectEntity
	 */
	public function getNewObject(): ObjectEntity {
		return $this->newObject;
	}//end getNewObject()
}//end class
