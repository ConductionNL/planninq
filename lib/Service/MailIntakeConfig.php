<?php

/**
 * Planninq MailIntakeConfig
 *
 * The admin's mailbox settings for task intake, read from app config.
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

use OCA\Planninq\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Reads the `mail_intake_*` admin keys. The password is never here: only the
 * credential reference that OpenRegister's broker resolves.
 */
class MailIntakeConfig {

	/**
	 * The keys with their defaults; every value is a string, as IAppConfig stores it.
	 *
	 * @var array<string,string>
	 */
	public const DEFAULTS = [
		'mail_intake_enabled' => 'false',
		'mail_intake_host' => '',
		'mail_intake_port' => '993',
		'mail_intake_encryption' => 'ssl',
		'mail_intake_username' => '',
		'mail_intake_credential_ref' => '',
		'mail_intake_folder' => 'INBOX',
		'mail_intake_address' => '',
		'mail_intake_require_auth' => 'true',
		'mail_intake_size_limit_mb' => '10',
	];

	/**
	 * The folder a task was made from is filed in.
	 *
	 * @var string
	 */
	public const PROCESSED_FOLDER = 'Processed';

	/**
	 * The folder mail that may not become a task is filed in.
	 *
	 * @var string
	 */
	public const REJECTED_FOLDER = 'Rejected';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig The app config.
	 */
	public function __construct(private IAppConfig $appConfig) {
	}//end __construct()

	/**
	 * One stored value, or its default.
	 *
	 * @param string $key A key of DEFAULTS.
	 *
	 * @return string
	 */
	public function get(string $key): string {
		return $this->appConfig->getValueString(Application::APP_ID, $key, (self::DEFAULTS[$key] ?? ''));
	}//end get()

	/**
	 * Whether intake is switched on and a mailbox is filled in.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->get(key: 'mail_intake_enabled') === 'true'
			&& $this->get(key: 'mail_intake_host') !== ''
			&& $this->get(key: 'mail_intake_address') !== '';
	}//end isEnabled()

	/**
	 * Whether SPF and DKIM must have passed.
	 *
	 * @return bool
	 */
	public function requiresAuth(): bool {
		return $this->get(key: 'mail_intake_require_auth') !== 'false';
	}//end requiresAuth()

	/**
	 * The most bytes of attachments one message may bring.
	 *
	 * @return int
	 */
	public function sizeLimitBytes(): int {
		return ((int)$this->get(key: 'mail_intake_size_limit_mb') * 1048576);
	}//end sizeLimitBytes()

	/**
	 * The address mail for a project key goes to, e.g. planninq+VC@gemeente.nl.
	 *
	 * @param string $key The project key.
	 *
	 * @return string '' when no address is set.
	 */
	public function addressFor(string $key): string {
		$address = $this->get(key: 'mail_intake_address');
		$atSign      = strrpos($address, '@');
		if ($atSign === false || $key === '') {
			return '';
		}

		return substr($address, 0, $atSign) . '+' . $key . substr($address, $atSign);
	}//end addressFor()
}//end class
