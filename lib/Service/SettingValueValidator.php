<?php

/**
 * Planninq Setting Value Validator
 *
 * Checks and normalises the values submitted on the admin settings form.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Pure checks on submitted setting values: lists of names and whole numbers.
 *
 * Holds no state and reads no configuration, so SettingsService keeps the
 * storing and this class keeps the shape rules.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
final class SettingValueValidator {

	/**
	 * Validate a list of names: a non-empty JSON array of non-empty strings.
	 *
	 * @param string $raw Raw value submitted by the client
	 *
	 * @return string|null Normalised JSON string, or null on validation failure
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function nonEmptyNames(string $raw): ?string {
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || count($decoded) === 0) {
			return null;
		}

		$normalised = [];
		foreach ($decoded as $item) {
			if (is_string($item) === false || trim($item) === '') {
				return null;
			}

			$normalised[] = trim($item);
		}

		$encoded = json_encode($normalised);
		if ($encoded === false) {
			return null;
		}

		return $encoded;
	}//end nonEmptyNames()

	/**
	 * Validate a list of distinct names: non-empty, none repeated (case and spaces ignored).
	 *
	 * @param string $raw Raw value submitted by the client
	 *
	 * @return string|null Normalised JSON string, or null when the list is refused
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.2
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function distinctNames(string $raw): ?string {
		$normalised = $this->nonEmptyNames(raw: $raw);
		if ($normalised === null) {
			return null;
		}

		$names = (array)json_decode($normalised, true);
		$seen  = array_unique(array_map(static fn (string $name): string => mb_strtolower($name), $names));
		if (count($seen) !== count($names)) {
			return null;
		}

		return $normalised;
	}//end distinctNames()

	/**
	 * Validate a possibly empty list of distinct names of at most `$maxLength` characters.
	 *
	 * @param string $raw       Raw value submitted by the client
	 * @param int    $maxLength The longest accepted name.
	 *
	 * @return string|null Normalised JSON string, or null when the list is refused
	 *
	 * @spec openspec/changes/archive/2026-11-24-time-timer-and-work-type/tasks.md#task-2.1
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function optionalNames(string $raw, int $maxLength): ?string {
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || array_is_list($decoded) === false) {
			return null;
		}

		if ($decoded === []) {
			return '[]';
		}

		foreach ($decoded as $name) {
			if (is_string($name) === false || mb_strlen(trim($name)) > $maxLength) {
				return null;
			}
		}

		return $this->distinctNames(raw: $raw);
	}//end optionalNames()

	/**
	 * A whole number from a settings form within [min, max], or null.
	 *
	 * @param string $raw The submitted value.
	 * @param int    $min Lowest accepted value.
	 * @param int    $max Highest accepted value.
	 *
	 * @return int|null
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function wholeNumber(string $raw, int $min, int $max): ?int {
		$trimmed = trim($raw);
		if ($trimmed === '' || preg_match('/^\d+$/', $trimmed) !== 1) {
			return null;
		}

		$number = (int)$trimmed;
		if ($number < $min || $number > $max) {
			return null;
		}

		return $number;
	}//end wholeNumber()
}//end class
