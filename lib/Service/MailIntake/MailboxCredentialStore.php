<?php

/**
 * Planninq Mailbox Credential Store
 *
 * Keeps the intake mailbox's password in OpenRegister's credential broker.
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

use OCA\OpenRegister\Service\Credential\CredentialBrokerService;
use OCA\OpenRegister\Service\Credential\CredentialMint;
use OCA\OpenRegister\Service\Credential\CredentialMintOptions;
use OCA\Planninq\AppInfo\Application;
use Psr\Container\ContainerInterface;

/**
 * The password goes to the broker as an app-injected HTTP Basic credential
 * (`generic-basic`) that only planninq may resolve; planninq keeps the
 * returned reference and the owning admin, never the password (ADR-064).
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
class MailboxCredentialStore {

	/**
	 * The broker provider for an app-injected password.
	 *
	 * @var string
	 */
	public const PROVIDER = 'generic-basic';

	/**
	 * @param ContainerInterface $container Resolves OpenRegister's credential broker.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * Store a mailbox password with the broker.
	 *
	 * @param string $password The password; it is handed to the broker only.
	 * @param string $owner    The admin who saved it, the credential's owner.
	 * @param string $username The mailbox login, kept as connection metadata.
	 * @param string $host     The IMAP host, kept as connection metadata.
	 *
	 * @return string The credential reference.
	 *
	 * @throws \Throwable When the broker is unavailable or refuses the credential.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function store(#[\SensitiveParameter] string $password, string $owner, string $username, string $host): string {
		$entity = $this->broker()->mintCredential(
			new CredentialMint(
				name: 'Planninq mail intake',
				provider: self::PROVIDER,
				owner: $owner,
				metadata: ['username' => $username, 'host' => $host]
			),
			new CredentialMintOptions(allowedApps: [Application::APP_ID], secret: $password)
		);

		return (string)$entity->getUuid();
	}//end store()

	/**
	 * Resolve the stored password, as the intake job does without a session.
	 *
	 * @param string $reference The credential reference.
	 * @param string $owner     The credential's owner, asserted for the sessionless job.
	 *
	 * @return string|null The password, or null when the broker holds none for it.
	 *
	 * @throws \Throwable When the broker refuses.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function resolve(string $reference, string $owner): ?string {
		return $this->broker()->resolveInjectable(
			credentialId: $reference,
			appId: Application::APP_ID,
			actingUserId: $owner
		);
	}//end resolve()

	/**
	 * OpenRegister's credential broker.
	 *
	 * @return CredentialBrokerService
	 */
	private function broker(): CredentialBrokerService {
		return $this->container->get(CredentialBrokerService::class);
	}//end broker()
}//end class
