<?php

/**
 * Tests for the intake mail settings reader.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\MailIntakeConfig;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MailIntakeConfig
 */
class MailIntakeConfigTest extends TestCase {

	/**
	 * A reader over the given stored values.
	 *
	 * @param array<string,string> $stored The stored values.
	 *
	 * @return MailIntakeConfig
	 */
	private function config(array $stored = []): MailIntakeConfig {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => ($stored[$key] ?? $default)
		);

		return new MailIntakeConfig(appConfig: $appConfig);
	}//end config()

	/**
	 * A stored value wins, otherwise the default applies.
	 *
	 * @return void
	 */
	public function testGetFallsBackToTheDefault(): void {
		self::assertSame('993', $this->config()->get(key: 'mail_intake_port'));
		self::assertSame('143', $this->config(['mail_intake_port' => '143'])->get(key: 'mail_intake_port'));
		self::assertSame('', $this->config()->get(key: 'unknown_key'));
	}//end testGetFallsBackToTheDefault()

	/**
	 * Intake is on only with the switch, a host and an address.
	 *
	 * @return void
	 */
	public function testIsEnabledNeedsTheSwitchAHostAndAnAddress(): void {
		$on = ['mail_intake_enabled' => 'true', 'mail_intake_host' => 'imap.x.nl', 'mail_intake_address' => 'p@x.nl'];

		self::assertTrue($this->config($on)->isEnabled());
		self::assertFalse($this->config()->isEnabled());
		self::assertFalse($this->config(['mail_intake_host' => ''] + $on)->isEnabled());
		self::assertFalse($this->config(['mail_intake_address' => ''] + $on)->isEnabled());
		self::assertFalse($this->config(['mail_intake_enabled' => 'false'] + $on)->isEnabled());
	}//end testIsEnabledNeedsTheSwitchAHostAndAnAddress()

	/**
	 * SPF/DKIM is required unless switched off; the size limit is in bytes.
	 *
	 * @return void
	 */
	public function testAuthAndSizeLimit(): void {
		self::assertTrue($this->config()->requiresAuth());
		self::assertFalse($this->config(['mail_intake_require_auth' => 'false'])->requiresAuth());
		self::assertSame(10 * 1048576, $this->config()->sizeLimitBytes());
		self::assertSame(2 * 1048576, $this->config(['mail_intake_size_limit_mb' => '2'])->sizeLimitBytes());
	}//end testAuthAndSizeLimit()

	/**
	 * The plus address is built from the mailbox address and the project key.
	 *
	 * @return void
	 */
	public function testAddressForBuildsAPlusAddress(): void {
		$config = $this->config(['mail_intake_address' => 'planninq@gemeente.nl']);

		self::assertSame('planninq+VC@gemeente.nl', $config->addressFor(key: 'VC'));
		self::assertSame('', $config->addressFor(key: ''));
		self::assertSame('', $this->config()->addressFor(key: 'VC'));
		self::assertSame('', $this->config(['mail_intake_address' => 'no-at-sign'])->addressFor(key: 'VC'));
	}//end testAddressForBuildsAPlusAddress()
}//end class
