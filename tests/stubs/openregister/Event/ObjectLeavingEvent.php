<?php

/**
 * Test-only stub for OpenRegister's ObjectLeavingEvent.
 *
 * TEST-ONLY: reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php. Mirrored from openregister/lib/Event/ObjectLeavingEvent.php.
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

/**
 * Dispatched before an object moves out of its register and schema.
 */
class ObjectLeavingEvent extends ObjectDeletingEvent {
}//end class
