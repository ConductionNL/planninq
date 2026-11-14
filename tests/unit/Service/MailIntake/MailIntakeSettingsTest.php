<?php

/**
 * Unit tests for MailIntakeSettings: the mailbox keys, and a password that only the broker keeps.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service\MailIntake;

use OCA\Planninq\Service\MailIntake\MailboxCredentialStore;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * An in-memory app config, an admin session and a broker double.
 *
 * @covers \OCA\Planninq\Service\MailIntake\MailIntakeSettings
 */
class MailIntakeSettingsTest extends TestCase {

	/**
	 * The stored app config values.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * The passwords handed to the broker.
	 *
	 * @var array<int,array<string,string>>
	 */
	private array $minted = [];

	/**
	 * Whether the broker refuses.
	 *
	 * @var bool
	 */
	private bool $brokerFails = false;

	/**
	 * The settings over the doubles.
	 *
	 * @param string|null $admin The session user, null for none.
	 *
	 * @return MailIntakeSettings
	 */
	private function settings(?string $admin = 'admin'): MailIntakeSettings {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->assertSame('planninq', $app);
				$this->stored[$key] = $value;
				return true;
			}
		);

		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($admin !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($admin);
		}

		$session->method('getUser')->willReturn($user);

		$credentials = $this->createMock(MailboxCredentialStore::class);
		$credentials->method('store')->willReturnCallback(
			function (string $password, string $owner, string $username, string $host): string {
				if ($this->brokerFails === true) {
					throw new RuntimeException('broker down');
				}

				$this->minted[] = compact('password', 'owner', 'username', 'host');
				return 'cred-0042';
			}
		);
		$credentials->method('resolve')->willReturnCallback(
			fn (string $reference, string $owner): ?string => ($reference === 'cred-0042' && $owner === 'admin') ? 's3cret!' : null
		);

		return new MailIntakeSettings($config, $session, $credentials, $this->createMock(LoggerInterface::class));
	}//end settings()

	/**
	 * A saved password reaches the broker only; the settings hold its reference and owner.
	 *
	 * @return void
	 */
	public function testMailIntakeStoresOnlyACredentialRef(): void {
		$settings = $this->settings();

		$rejected = $settings->update(
			[
				MailIntakeSettings::HOST => 'imap.gemeente.nl',
				MailIntakeSettings::USERNAME => 'planninq@gemeente.nl',
				MailIntakeSettings::PASSWORD_FIELD => 's3cret!',
				MailIntakeSettings::CREDENTIAL_REF => 'forged-ref',
			]
		);

		$this->assertSame([], $rejected);
		$this->assertSame([['password' => 's3cret!', 'owner' => 'admin', 'username' => 'planninq@gemeente.nl', 'host' => 'imap.gemeente.nl']], $this->minted);
		$this->assertSame('cred-0042', $this->stored[MailIntakeSettings::CREDENTIAL_REF]);
		$this->assertSame('admin', $this->stored[MailIntakeSettings::CREDENTIAL_OWNER]);
		$this->assertStringNotContainsString('s3cret!', (string)json_encode($this->stored));
		$this->assertStringNotContainsString('s3cret!', (string)json_encode($settings->get()));
		$this->assertArrayNotHasKey(MailIntakeSettings::PASSWORD_FIELD, $settings->get());

		// The job resolves it through the broker, asserting the owner.
		$this->assertSame('s3cret!', $settings->password());
	}//end testMailIntakeStoresOnlyACredentialRef()

	/**
	 * Without a session, or when the broker refuses, the stored reference stays as it was.
	 *
	 * @return void
	 */
	public function testARefusedPasswordKeepsTheOldReference(): void {
		$this->stored[MailIntakeSettings::CREDENTIAL_REF] = 'cred-old';

		$this->assertSame([MailIntakeSettings::PASSWORD_FIELD], $this->settings(admin: null)->update([MailIntakeSettings::PASSWORD_FIELD => 'x']));
		$this->brokerFails = true;
		$this->assertSame([MailIntakeSettings::PASSWORD_FIELD], $this->settings()->update([MailIntakeSettings::PASSWORD_FIELD => 'x']));

		$this->assertSame('cred-old', $this->stored[MailIntakeSettings::CREDENTIAL_REF]);
		$this->assertNull($this->settings()->password());
	}//end testARefusedPasswordKeepsTheOldReference()

	/**
	 * A port outside 1 to 65535, or not a number, is rejected and the stored one kept.
	 *
	 * @return void
	 */
	public function testMailIntakeRejectsInvalidPort(): void {
		$settings = $this->settings();

		foreach (['0', '65536', 'imap', '99.5', '-1'] as $port) {
			$this->assertSame([MailIntakeSettings::PORT], $settings->update([MailIntakeSettings::PORT => $port]));
		}

		$this->assertArrayNotHasKey(MailIntakeSettings::PORT, $this->stored);
		$this->assertSame('993', $settings->get()[MailIntakeSettings::PORT]);

		$this->assertSame([], $settings->update([MailIntakeSettings::PORT => ' 0143 ']));
		$this->assertSame('143', $this->stored[MailIntakeSettings::PORT]);
	}//end testMailIntakeRejectsInvalidPort()

	/**
	 * The other keys are validated as well.
	 *
	 * @return void
	 */
	public function testTheOtherKeysAreValidated(): void {
		$settings = $this->settings();

		$this->assertSame('1', $settings->validated(key: MailIntakeSettings::ENABLED, value: true));
		$this->assertSame('0', $settings->validated(key: MailIntakeSettings::REQUIRE_AUTH, value: 'off'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::ENABLED, value: 'maybe'));
		$this->assertSame('tls', $settings->validated(key: MailIntakeSettings::ENCRYPTION, value: 'TLS'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::ENCRYPTION, value: 'starttls'));
		$this->assertSame('planninq@gemeente.nl', $settings->validated(key: MailIntakeSettings::ADDRESS, value: 'Planninq@Gemeente.nl'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::ADDRESS, value: 'planninq+VC@gemeente.nl'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::ADDRESS, value: 'geen adres'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::HOST, value: 'imap.gemeente.nl/evil'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::FOLDER, value: "INBOX\r\nA1 LOGOUT"));
		$this->assertNull($settings->validated(key: MailIntakeSettings::FOLDER, value: ''));
		$this->assertNull($settings->validated(key: MailIntakeSettings::SIZE_LIMIT_MB, value: '101'));
		$this->assertNull($settings->validated(key: MailIntakeSettings::USERNAME, value: ['x']));
		$this->assertNull($settings->validated(key: MailIntakeSettings::CREDENTIAL_REF, value: 'cred'));
	}//end testTheOtherKeysAreValidated()

	/**
	 * Ready needs the switch on, a host, an address and a stored password; the project address is the plus-address.
	 *
	 * @return void
	 */
	public function testReadinessAndTheProjectAddress(): void {
		$settings = $this->settings();
		$this->assertFalse($settings->isReady());
		$this->assertNull($settings->projectAddress(projectKey: 'VC'));

		$this->stored = [
			MailIntakeSettings::ENABLED => '1',
			MailIntakeSettings::HOST => 'imap.gemeente.nl',
			MailIntakeSettings::ADDRESS => 'planninq@gemeente.nl',
			MailIntakeSettings::CREDENTIAL_REF => 'cred-0042',
		];
		$this->assertTrue($settings->isReady());
		$this->assertSame('planninq+VC@gemeente.nl', $settings->projectAddress(projectKey: 'VC'));
		$this->assertNull($settings->projectAddress(projectKey: ''));

		$this->stored[MailIntakeSettings::ENABLED] = '0';
		$this->assertFalse($settings->isReady());
	}//end testReadinessAndTheProjectAddress()
}//end class
