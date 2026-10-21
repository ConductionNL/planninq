<?php

/**
 * Test-only stub for OpenRegister's ObjectUpdatingEvent.
 *
 * TEST-ONLY. See tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/ObjectUpdatingEvent.php, the FULL
 * surface and nothing more.
 *
 * 🔴 THIS CLASS HAS NO `getObject()`. The real event carries `getNewObject()`
 * and `getOldObject()` only. learniq#984 called `getObject()` on it, its unit
 * test built a hand-made double that HAD the method, the gate was green, and
 * every object update on the instance answered 500 until learniq#1046. Adding
 * `getObject()` here would make the same mistake pass again.
 * StubDriftTest compares this file with the real class when the openregister
 * source sits beside this app.
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

	/**
	 * Whether propagation has been stopped.
	 *
	 * @var boolean
	 */
	private bool $propagationStopped = false;

	/**
	 * Validation errors contributed by listeners.
	 *
	 * @var array<int|string,mixed>
	 */
	private array $errors = [];

	/**
	 * Data modified by listeners.
	 *
	 * @var array<string,mixed>
	 */
	private array $modifiedData = [];

	/**
	 * Constructor.
	 *
	 * @param ObjectEntity $newObject The object as it will be saved.
	 * @param ObjectEntity|null $oldObject The object as it is stored now.
	 */
	public function __construct(
		private ObjectEntity $newObject,
		private ?ObjectEntity $oldObject = null,
	) {
		parent::__construct();

	}//end __construct()

	/**
	 * Return the object as it will be saved.
	 *
	 * @return ObjectEntity The new object.
	 */
	public function getNewObject(): ObjectEntity {
		return $this->newObject;
	}//end getNewObject()

	/**
	 * Return the object as it is stored now.
	 *
	 * @return ObjectEntity|null The old object, or null when unavailable.
	 */
	public function getOldObject(): ?ObjectEntity {
		return $this->oldObject;
	}//end getOldObject()

	/**
	 * Whether propagation has been stopped.
	 *
	 * @return boolean True when stopped.
	 */
	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}//end isPropagationStopped()

	/**
	 * Stop propagation, vetoing the update.
	 *
	 * @return void
	 */
	public function stopPropagation(): void {
		$this->propagationStopped = true;

	}//end stopPropagation()

	/**
	 * Record validation errors.
	 *
	 * @param array<int|string,mixed> $errors The errors.
	 *
	 * @return void
	 */
	public function setErrors(array $errors): void {
		$this->errors = $errors;

	}//end setErrors()

	/**
	 * Return recorded validation errors.
	 *
	 * @return array<int|string,mixed> The errors.
	 */
	public function getErrors(): array {
		return $this->errors;
	}//end getErrors()

	/**
	 * Record data modified by a listener.
	 *
	 * @param array<string,mixed> $data The modified data.
	 *
	 * @return void
	 */
	public function setModifiedData(array $data): void {
		$this->modifiedData = $data;

	}//end setModifiedData()

	/**
	 * Return data modified by listeners.
	 *
	 * @return array<string,mixed> The modified data.
	 */
	public function getModifiedData(): array {
		return $this->modifiedData;
	}//end getModifiedData()
}//end class
