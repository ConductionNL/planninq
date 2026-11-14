<?php

/**
 * Planninq Mail Intake Settings
 *
 * The admin settings of the mailbox that turns mail into tasks.
 *
 * @category Service
 * @package  OCA\Planninq\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\MailIntake;

use OCA\Planninq\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads, validates and stores the `mail_intake_*` admin keys. The password is
 * never stored here: a submitted `mail_intake_password` goes to the credential
 * broker and only the returned reference and its owner are kept.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
class MailIntakeSettings {

	public const ENABLED = 'mail_intake_enabled';
	public const HOST = 'mail_intake_host';
	public const PORT = 'mail_intake_port';
	public const ENCRYPTION = 'mail_intake_encryption';
	public const USERNAME = 'mail_intake_username';
	public const CREDENTIAL_REF = 'mail_intake_credential_ref';
	public const CREDENTIAL_OWNER = 'mail_intake_credential_owner';
	public const FOLDER = 'mail_intake_folder';
	public const ADDRESS = 'mail_intake_address';
	public const REQUIRE_AUTH = 'mail_intake_require_auth';
	public const SIZE_LIMIT_MB = 'mail_intake_size_limit_mb';

	/**
	 * The submitted field carrying a new password. Never stored.
	 *
	 * @var string
	 */
	public const PASSWORD_FIELD = 'mail_intake_password';

	/**
	 * Every key with its default.
	 *
	 * @var array<string,string>
	 */
	public const DEFAULTS = [
		self::ENABLED => '0',
		self::HOST => '',
		self::PORT => '993',
		self::ENCRYPTION => 'ssl',
		self::USERNAME => '',
		self::CREDENTIAL_REF => '',
		self::CREDENTIAL_OWNER => '',
		self::FOLDER => 'INBOX',
		self::ADDRESS => '',
		self::REQUIRE_AUTH => '1',
		self::SIZE_LIMIT_MB => '10',
	];

	/**
	 * The keys an admin may set directly; the credential keys follow a password.
	 *
	 * @var array<int,string>
	 */
	private const EDITABLE = [
		self::ENABLED,
		self::HOST,
		self::PORT,
		self::ENCRYPTION,
		self::USERNAME,
		self::FOLDER,
		self::ADDRESS,
		self::REQUIRE_AUTH,
		self::SIZE_LIMIT_MB,
	];

	/**
	 * @param IAppConfig             $appConfig   Holds the keys.
	 * @param IUserSession           $userSession The admin saving a password becomes its owner.
	 * @param MailboxCredentialStore $credentials The broker the password goes to.
	 * @param LoggerInterface        $logger      Logs rejected values and broker failures, never a password.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IUserSession $userSession,
		private readonly MailboxCredentialStore $credentials,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Every stored key with its default applied. There is no password among them.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function get(): array {
		$settings = [];
		foreach (self::DEFAULTS as $key => $default) {
			$settings[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, $default);
		}

		return $settings;
	}//end get()

	/**
	 * Store the submitted keys that are valid; invalid ones are logged and skipped.
	 * A non-empty `mail_intake_password` is handed to the broker and replaces the
	 * stored reference only when the broker accepted it.
	 *
	 * @param array<string,mixed> $data The submitted settings.
	 *
	 * @return array<int,string> The keys that were rejected.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function update(array $data): array {
		$rejected = [];
		foreach (self::EDITABLE as $key) {
			if (array_key_exists($key, $data) === false) {
				continue;
			}

			$value = $this->validated(key: $key, value: $data[$key]);
			if ($value === null) {
				$this->logger->warning('Planninq: invalid ' . $key . ' value rejected');
				$rejected[] = $key;
				continue;
			}

			$this->appConfig->setValueString(Application::APP_ID, $key, $value);
		}

		$password = ($data[self::PASSWORD_FIELD] ?? null);
		if (is_string($password) === true && $password !== '' && $this->storePassword(password: $password) === false) {
			$rejected[] = self::PASSWORD_FIELD;
		}

		return $rejected;
	}//end update()

	/**
	 * A submitted value normalised for storage, or null when it is invalid.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The submitted value.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function validated(string $key, mixed $value): ?string {
		if (is_bool($value) === true) {
			$value = (string)(int)$value;
		}

		if (is_scalar($value) === false) {
			return null;
		}

		$value = trim((string)$value);
		if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
			return null;
		}

		return match ($key) {
			self::ENABLED, self::REQUIRE_AUTH => self::flag(value: $value),
			self::PORT => self::wholeNumber(value: $value, min: 1, max: 65535),
			self::SIZE_LIMIT_MB => self::wholeNumber(value: $value, min: 1, max: 100),
			self::ENCRYPTION => self::oneOf(value: strtolower($value), allowed: ['ssl', 'tls', 'none']),
			self::HOST => self::matching(value: $value, pattern: '/^([A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?)?$/'),
			self::ADDRESS => self::address(value: $value),
			self::USERNAME => self::matching(value: $value, pattern: '/^.{0,320}$/su'),
			self::FOLDER => self::matching(value: $value, pattern: '/^.{1,255}$/su'),
			default => null,
		};
	}//end validated()

	/**
	 * Whether intake is switched on and has what it needs to read the mailbox.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function isReady(): bool {
		$settings = $this->get();

		return $settings[self::ENABLED] === '1'
			&& $settings[self::HOST] !== ''
			&& $settings[self::ADDRESS] !== ''
			&& $settings[self::CREDENTIAL_REF] !== '';
	}//end isReady()

	/**
	 * The mailbox password, resolved through the broker for the sessionless job.
	 *
	 * @return string|null The password, or null when none is stored or the broker refuses.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function password(): ?string {
		$settings = $this->get();
		if ($settings[self::CREDENTIAL_REF] === '' || $settings[self::CREDENTIAL_OWNER] === '') {
			return null;
		}

		try {
			return $this->credentials->resolve(reference: $settings[self::CREDENTIAL_REF], owner: $settings[self::CREDENTIAL_OWNER]);
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: the mail intake password could not be resolved', ['exception' => get_class($e)]);
			return null;
		}
	}//end password()

	/**
	 * The task address of a project: the mailbox address with the key as plus-suffix.
	 *
	 * @param string $projectKey The project key.
	 *
	 * @return string|null The address, or null when intake has no address or the key is empty.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-each-project-with-a-key-has-a-task-address
	 */
	public function projectAddress(string $projectKey): ?string {
		$address = $this->get()[self::ADDRESS];
		$at = strrpos($address, '@');
		if ($projectKey === '' || $at === false) {
			return null;
		}

		return substr($address, 0, $at) . '+' . $projectKey . substr($address, $at);
	}//end projectAddress()

	/**
	 * Hand a password to the broker and keep the reference and its owner.
	 *
	 * @param string $password The password.
	 *
	 * @return bool False when there is no admin session or the broker refused.
	 */
	private function storePassword(#[\SensitiveParameter] string $password): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		$settings = $this->get();
		try {
			$reference = $this->credentials->store(
				password: $password,
				owner: $user->getUID(),
				username: $settings[self::USERNAME],
				host: $settings[self::HOST]
			);
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: the mail intake password could not be stored', ['exception' => get_class($e)]);
			return false;
		}

		$this->appConfig->setValueString(Application::APP_ID, self::CREDENTIAL_REF, $reference);
		$this->appConfig->setValueString(Application::APP_ID, self::CREDENTIAL_OWNER, $user->getUID());

		return true;
	}//end storePassword()

	/**
	 * A switch as `1` or `0`.
	 *
	 * @param string $value The submitted value.
	 *
	 * @return string|null
	 */
	private static function flag(string $value): ?string {
		return match (strtolower($value)) {
			'1', 'true', 'on', 'yes' => '1',
			'0', 'false', 'off', 'no' => '0',
			default => null,
		};
	}//end flag()

	/**
	 * The value when it is one of the allowed ones.
	 *
	 * @param string            $value   The submitted value.
	 * @param array<int,string> $allowed The allowed values.
	 *
	 * @return string|null
	 */
	private static function oneOf(string $value, array $allowed): ?string {
		if (in_array($value, $allowed, true) === false) {
			return null;
		}

		return $value;
	}//end oneOf()

	/**
	 * The value when it matches the pattern.
	 *
	 * @param string $value   The submitted value.
	 * @param string $pattern The pattern.
	 *
	 * @return string|null
	 */
	private static function matching(string $value, string $pattern): ?string {
		if (preg_match($pattern, $value) !== 1) {
			return null;
		}

		return $value;
	}//end matching()

	/**
	 * A whole number in range, as a string.
	 *
	 * @param string $value The submitted value.
	 * @param int    $min   The lowest allowed.
	 * @param int    $max   The highest allowed.
	 *
	 * @return string|null
	 */
	private static function wholeNumber(string $value, int $min, int $max): ?string {
		if (preg_match('/^\d{1,6}$/', $value) !== 1 || (int)$value < $min || (int)$value > $max) {
			return null;
		}

		return (string)(int)$value;
	}//end wholeNumber()

	/**
	 * The mailbox's public address: empty, or a plain address without a plus-suffix.
	 *
	 * @param string $value The submitted value.
	 *
	 * @return string|null
	 */
	private static function address(string $value): ?string {
		if ($value === '') {
			return '';
		}

		if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || str_contains(strstr($value, '@', true), '+') === true) {
			return null;
		}

		return strtolower($value);
	}//end address()
}//end class
