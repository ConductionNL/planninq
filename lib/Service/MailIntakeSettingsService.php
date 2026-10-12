<?php

/**
 * Planninq MailIntakeSettingsService
 *
 * The admin-side handling of the mail intake settings: validation, visibility and the mailbox password.
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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates, hides and stores the `mail_intake_*` settings.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
 */
class MailIntakeSettingsService {

	/**
	 * The default value of every `mail_intake_*` setting.
	 *
	 * @var array<string,string>
	 */
	public const DEFAULTS = MailIntakeConfig::DEFAULTS;

	/**
	 * Constructor.
	 *
	 * @param IAppConfig         $appConfig The app config interface.
	 * @param ContainerInterface $container The container the credential broker is resolved from.
	 * @param LoggerInterface    $logger    The logger.
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether a settings key belongs to mail intake.
	 *
	 * @param string $key The setting key.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
	 */
	public function handles(string $key): bool {
		return str_starts_with($key, 'mail_intake_') === true;
	}//end handles()

	/**
	 * A submitted `mail_intake_*` value as it is stored, or null to refuse it.
	 *
	 * @param string $key   The setting key.
	 * @param string $value The submitted value.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
	 */
	public function normalise(string $key, string $value): ?string {
		return (new MailIntakeValidator())->normalise(key: $key, value: $value);
	}//end normalise()

	/**
	 * The admin settings a non-admin may see: the mailbox connection details are for admins only.
	 *
	 * @param array<string,string> $settings The admin settings.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
	 */
	public function hideConnectionDetails(array $settings): array {
		$shown = ['mail_intake_enabled', 'mail_intake_address'];
		foreach (array_keys(MailIntakeConfig::DEFAULTS) as $key) {
			if (in_array($key, $shown, true) === false) {
				unset($settings[$key]);
			}
		}

		return $settings;
	}//end hideConnectionDetails()

	/**
	 * Store a submitted mailbox password in the credential broker and keep only its reference.
	 *
	 * @param array<string,mixed> $settings The submitted settings.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
	 */
	public function storePassword(array $settings): void {
		$password = ($settings['mail_intake_password'] ?? null);
		if (is_string($password) === false || $password === '') {
			return;
		}

		$existing = $this->appConfig->getValueString(Application::APP_ID, 'mail_intake_credential_ref', '');
		$ref      = (new MailCredentialStore(container: $this->container, logger: $this->logger))->store(password: $password, existing: $existing);
		if ($ref === null) {
			$this->logger->warning('Planninq: the mail intake password was not stored; no credential reference was written');
			return;
		}

		$this->appConfig->setValueString(Application::APP_ID, 'mail_intake_credential_ref', $ref);
	}//end storePassword()
}//end class
