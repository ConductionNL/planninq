<?php

/**
 * Planninq MailCredentialStore
 *
 * Keeps the intake mailbox password in OpenRegister's credential broker.
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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Hands the password to the broker and resolves a reference back at run time.
 * Planninq keeps only the reference (ADR-064).
 */
class MailCredentialStore {

	/**
	 * The broker class, resolved at run time so a missing OpenRegister never breaks bootstrap.
	 *
	 * @var string
	 */
	private const BROKER = 'OCA\\OpenRegister\\Service\\Credential\\CredentialBrokerService';

	/**
	 * The inject-only provider for a plain password.
	 *
	 * @var string
	 */
	private const PROVIDER = 'generic-password';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves the broker.
	 * @param LoggerInterface    $logger    The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Store a password and return its reference.
	 *
	 * @param string $password The password.
	 * @param string $existing The reference to replace, or ''.
	 *
	 * @return string|null The reference, or null when the broker is unavailable or refused it.
	 */
	public function store(string $password, string $existing = ''): ?string {
		try {
			$ref = $this->container->get(self::BROKER)->mint(
				provider: self::PROVIDER,
				name: 'planninq mail intake',
				secret: $password,
				scope: 'organisation',
				credentialId: $existing
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: the mail intake password was not stored', ['exception' => $e->getMessage()]);
			return null;
		}

		return $this->refOf(minted: $ref);
	}//end store()

	/**
	 * The password behind a reference, or null when it cannot be resolved.
	 *
	 * @param string $ref The credential reference.
	 *
	 * @return string|null
	 */
	public function resolve(string $ref): ?string {
		if ($ref === '') {
			return null;
		}

		try {
			$secret = $this->container->get(self::BROKER)->resolveInjectable(credentialRef: $ref, appId: 'planninq');
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: the mail intake password could not be resolved', ['exception' => $e->getMessage()]);
			return null;
		}

		if (is_array($secret) === true) {
			$secret = ($secret['secret'] ?? $secret['password'] ?? null);
		}

		if (is_string($secret) === true && $secret !== '') {
			return $secret;
		}

		return null;
	}//end resolve()

	/**
	 * The reference string out of whatever the broker returned.
	 *
	 * @param mixed $minted The broker's return value.
	 *
	 * @return string|null
	 */
	private function refOf(mixed $minted): ?string {
		if (is_array($minted) === true) {
			$minted = ($minted['credentialRef'] ?? $minted['id'] ?? $minted['uuid'] ?? null);
		}

		if (is_object($minted) === true && method_exists($minted, 'getUuid') === true) {
			$minted = $minted->getUuid();
		}

		if (is_string($minted) === true && $minted !== '') {
			return $minted;
		}

		return null;
	}//end refOf()
}//end class
