<?php

/**
 * Tests for the mail intake admin value validation.
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\MailIntakeValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MailIntakeValidator
 */
class MailIntakeValidatorTest extends TestCase {

	/**
	 * Every setting key with a value that is accepted, and what is stored.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function acceptedProvider(): array {
		return [
			'flag is lowercased' => ['mail_intake_enabled', ' TRUE ', 'true'],
			'auth flag false' => ['mail_intake_require_auth', 'false', 'false'],
			'port' => ['mail_intake_port', '993', '993'],
			'size limit' => ['mail_intake_size_limit_mb', '25', '25'],
			'encryption ssl' => ['mail_intake_encryption', 'ssl', 'ssl'],
			'encryption none' => ['mail_intake_encryption', 'none', 'none'],
			'address is lowercased' => ['mail_intake_address', 'Planninq@Gemeente.nl', 'planninq@gemeente.nl'],
			'address can be cleared' => ['mail_intake_address', '', ''],
			'host' => ['mail_intake_host', 'imap.gemeente.nl', 'imap.gemeente.nl'],
			'username' => ['mail_intake_username', 'user name', 'user name'],
			'folder' => ['mail_intake_folder', 'INBOX', 'INBOX'],
		];
	}//end acceptedProvider()

	/**
	 * A valid value is stored in its normal form.
	 *
	 * @dataProvider acceptedProvider
	 *
	 * @param string $key      The setting key.
	 * @param string $value    The submitted value.
	 * @param string $expected The stored value.
	 *
	 * @return void
	 */
	public function testAcceptsValidValues(string $key, string $value, string $expected): void {
		self::assertSame(expected: $expected, actual: (new MailIntakeValidator())->normalise(key: $key, value: $value));
	}//end testAcceptsValidValues()

	/**
	 * Every setting key with a value that is refused.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function refusedProvider(): array {
		return [
			'flag not a boolean' => ['mail_intake_enabled', 'yes'],
			'port not a number' => ['mail_intake_port', '99x'],
			'port too low' => ['mail_intake_port', '0'],
			'port too high' => ['mail_intake_port', '65536'],
			'size limit too high' => ['mail_intake_size_limit_mb', '101'],
			'encryption unknown' => ['mail_intake_encryption', 'tls'],
			'address not an email' => ['mail_intake_address', 'not-an-address'],
			'host with a space' => ['mail_intake_host', 'imap gemeente.nl'],
			'username with a control character' => ['mail_intake_username', "a\x01b"],
			'host too long' => ['mail_intake_host', str_repeat('a', 256)],
			'folder empty' => ['mail_intake_folder', ''],
			'unknown key' => ['mail_intake_other', 'x'],
		];
	}//end refusedProvider()

	/**
	 * An invalid value is refused with null.
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param string $key   The setting key.
	 * @param string $value The submitted value.
	 *
	 * @return void
	 */
	public function testRefusesInvalidValues(string $key, string $value): void {
		self::assertNull((new MailIntakeValidator())->normalise(key: $key, value: $value));
	}//end testRefusesInvalidValues()
}//end class
