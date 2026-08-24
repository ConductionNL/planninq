<?php

/**
 * Test-only stub for OpenRegister's ObjectEntity.
 *
 * TEST-ONLY. This class is reachable exclusively through the PSR-4 prefix that
 * `tests/bootstrap-unit.php` APPENDS to the PHPUnit process's autoloader, and
 * only when no Nextcloud server tree was bootstrapped. It is deliberately NOT
 * declared in composer.json's `autoload` (or `autoload-dev`) block: a PSR-4
 * entry for `OCA\OpenRegister\` in planninq's generated
 * vendor/composer/autoload_psr4.php would shadow the real OpenRegister app's
 * classes for every request that touches planninq's autoloader.
 *
 * Because the prefix is APPENDED rather than prepended, a real
 * OCA\OpenRegister\Db\ObjectEntity always wins when one is on the path.
 *
 * Mirrored from openregister/lib/Db/ObjectEntity.php. A drifted stub is worse
 * than none — update this file when the engine's signature changes.
 *
 * @category Test
 * @package  OCA\OpenRegister\Db
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

namespace OCA\OpenRegister\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Test-only stub of the OpenRegister object entity.
 */
class ObjectEntity extends Entity implements JsonSerializable {
	/**
	 * The decoded object payload.
	 *
	 * @var array<string, mixed>
	 */
	protected $object = [];

	/**
	 * Return the decoded object payload.
	 *
	 * @return array<string, mixed> The object data.
	 */
	public function getObject(): array {
		return $this->object;
	}//end getObject()

	/**
	 * Serialise the entity.
	 *
	 * @return array<string, mixed> The serialised entity.
	 */
	public function jsonSerialize(): array {
		return $this->object;
	}//end jsonSerialize()
}//end class
