<?php

/**
 * Planninq Seeded Random
 *
 * A small deterministic number generator (Park and Miller), so a run with the same
 * seed makes the same choices on every PHP version and never touches the global mt_rand state.
 *
 * @category Timetabling
 * @package  OCA\Planninq\Timetabling
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

namespace OCA\Planninq\Timetabling;

/**
 * Deterministic pseudo-random numbers from a seed.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
 */
final class SeededRandom {

	/**
	 * The modulus, 2^31 - 1.
	 */
	private const MODULUS = 2147483647;

	/**
	 * The current state, 1 to MODULUS - 1.
	 *
	 * @var integer
	 */
	private int $state;

	/**
	 * Constructor.
	 *
	 * @param int $seed Any integer.
	 *
	 * @return void
	 */
	public function __construct(int $seed) {
		$this->state = ((abs($seed) % (self::MODULUS - 1)) + 1);
	}//end __construct()

	/**
	 * A whole number from 0 to below the maximum.
	 *
	 * @param int $max The number of choices, 1 or more.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
	 */
	public function below(int $max): int {
		return ($this->next() % max(1, $max));
	}//end below()

	/**
	 * A number from 0 to below 1.
	 *
	 * @return float
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
	 */
	public function fraction(): float {
		return (($this->next() - 1) / (self::MODULUS - 1));
	}//end fraction()

	/**
	 * The next state.
	 *
	 * @return int
	 */
	private function next(): int {
		$this->state = (($this->state * 48271) % self::MODULUS);
		return $this->state;
	}//end next()
}//end class
