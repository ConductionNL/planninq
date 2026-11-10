<?php

/**
 * Test-only stub for OpenRegister's WriteVetoState.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Event/WriteVetoState.php, including where each
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

/**
 * Propagation, errors and modified data of a vetoable pre-write event.
 */
trait WriteVetoState {
	/**
	 * Whether a listener stopped propagation.
	 *
	 * @var boolean
	 */
	private bool $propagationStopped = false;

	/**
	 * Validation errors set by a listener.
	 *
	 * @var array<mixed>
	 */
	private array $errors = [];

	/**
	 * Data a listener changed.
	 *
	 * @var array<mixed>
	 */
	private array $modifiedData = [];

	/**
	 * Whether propagation was stopped.
	 *
	 * @return bool
	 */
	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}//end isPropagationStopped()

	/**
	 * Stop propagation, which vetoes the write.
	 *
	 * @return void
	 */
	public function stopPropagation(): void {
		$this->propagationStopped = true;
	}//end stopPropagation()

	/**
	 * Set the errors.
	 *
	 * @param array<mixed> $errors The errors.
	 *
	 * @return void
	 */
	public function setErrors(array $errors): void {
		$this->errors = $errors;
	}//end setErrors()

	/**
	 * The errors.
	 *
	 * @return array<mixed>
	 */
	public function getErrors(): array {
		return $this->errors;
	}//end getErrors()

	/**
	 * Set the modified data.
	 *
	 * @param array<mixed> $data The data.
	 *
	 * @return void
	 */
	public function setModifiedData(array $data): void {
		$this->modifiedData = $data;
	}//end setModifiedData()

	/**
	 * The modified data.
	 *
	 * @return array<mixed>
	 */
	public function getModifiedData(): array {
		return $this->modifiedData;
	}//end getModifiedData()
}//end trait
