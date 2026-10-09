<?php

/**
 * Planninq MailIntakeValidator
 *
 * Checks the `mail_intake_*` values an admin submits and returns the form in
 * which they are stored. The credential reference is never taken from the
 * client: it only comes from storing a password through the broker.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Validation of the mail intake admin values.
 */
class MailIntakeValidator {

	/**
	 * A submitted value as it is stored, or null to refuse it.
	 *
	 * @param string $key   The setting key.
	 * @param string $value The submitted value.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
	 */
	public function normalise(string $key, string $value): ?string {
		$value = trim($value);

		return match ($key) {
			'mail_intake_enabled', 'mail_intake_require_auth' => $this->choice(raw: strtolower($value), choices: ['true', 'false']),
			'mail_intake_port' => $this->wholeNumber(raw: $value, min: 1, max: 65535),
			'mail_intake_size_limit_mb' => $this->wholeNumber(raw: $value, min: 1, max: 100),
			'mail_intake_encryption' => $this->choice(raw: $value, choices: ['ssl', 'none']),
			'mail_intake_address' => $this->address(raw: $value),
			'mail_intake_host', 'mail_intake_username', 'mail_intake_folder' => $this->text(raw: $value, key: $key),
			default => null,
		};
	}//end normalise()

	/**
	 * The value when it is one of the choices, else null.
	 *
	 * @param string            $raw     The submitted value.
	 * @param array<int,string> $choices The accepted values.
	 *
	 * @return string|null
	 */
	private function choice(string $raw, array $choices): ?string {
		if (in_array($raw, $choices, true) === true) {
			return $raw;
		}

		return null;
	}//end choice()

	/**
	 * A whole number in range, as a string, or null.
	 *
	 * @param string $raw The submitted value.
	 * @param int    $min Lowest accepted value.
	 * @param int    $max Highest accepted value.
	 *
	 * @return string|null
	 */
	private function wholeNumber(string $raw, int $min, int $max): ?string {
		if (preg_match('/^\d+$/', $raw) !== 1) {
			return null;
		}

		$number = (int)$raw;
		if ($number < $min || $number > $max) {
			return null;
		}

		return (string)$number;
	}//end wholeNumber()

	/**
	 * The mailbox address, or '' to clear it, or null when it is no email address.
	 *
	 * @param string $raw The submitted value.
	 *
	 * @return string|null
	 */
	private function address(string $raw): ?string {
		if ($raw === '') {
			return '';
		}

		if (filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
			return null;
		}

		return strtolower($raw);
	}//end address()

	/**
	 * A host, login or folder name: no control characters; a host has no spaces; the folder may not be empty.
	 *
	 * @param string $raw The submitted value.
	 * @param string $key The setting key.
	 *
	 * @return string|null
	 */
	private function text(string $raw, string $key): ?string {
		if (preg_match('/[\x00-\x1f\x7f]/', $raw) === 1 || mb_strlen($raw) > 255) {
			return null;
		}

		if ($key === 'mail_intake_host' && preg_match('/\s/', $raw) === 1) {
			return null;
		}

		if ($key === 'mail_intake_folder' && $raw === '') {
			return null;
		}

		return $raw;
	}//end text()
}//end class
