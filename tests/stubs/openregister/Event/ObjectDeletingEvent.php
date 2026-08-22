<?php

/**
 * Test-only stub for OpenRegister's ObjectDeletingEvent.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/ObjectDeletingEvent.php.
 *
 * ⚠️ The FULL surface is mirrored deliberately, not just the one method planninq
 * calls. A stub narrower than the real class makes an incompatible usage legal
 * here and fatal in CI, and `StoppableEventInterface` is load-bearing: this is
 * a PRE-event, so stopping propagation VETOES the delete. A stub that silently
 * dropped it would let a listener "veto" in a way that compiles and does
 * nothing.
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
 * Dispatched before an object is deleted.
 */
class ObjectDeletingEvent extends Event implements StoppableEventInterface {

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
	 * @param ObjectEntity $object The object being deleted.
	 */
	public function __construct(
		private ObjectEntity $object,
	) {
		parent::__construct();

	}//end __construct()

	/**
	 * Return the object being deleted.
	 *
	 * @return ObjectEntity The object being deleted.
	 */
	public function getObject(): ObjectEntity {
		return $this->object;
	}//end getObject()

	/**
	 * Whether propagation has been stopped.
	 *
	 * @return boolean True when stopped.
	 */
	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}//end isPropagationStopped()

	/**
	 * Stop propagation, vetoing the delete.
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
