<?php

/**
 * Test-only stub for OpenRegister's ObjectCreatedEvent.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/ObjectCreatedEvent.php.
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
 * Test-only stub of the object-created event.
 */
class ObjectCreatedEvent extends Event
{
    /**
     * Constructor.
     *
     * @param ObjectEntity $object The created object.
     */
    public function __construct(private ObjectEntity $object)
    {
        parent::__construct();

    }//end __construct()

    /**
     * Return the created object.
     *
     * @return ObjectEntity The created object.
     */
    public function getObject(): ObjectEntity
    {
        return $this->object;

    }//end getObject()
}//end class
