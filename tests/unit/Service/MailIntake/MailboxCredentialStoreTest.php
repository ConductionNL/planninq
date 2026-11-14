<?php

/**
 * Unit tests for MailboxCredentialStore and TaskAttachments: what they hand to OpenRegister.
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

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Service\Credential\CredentialBrokerService;
use OCA\OpenRegister\Service\Credential\CredentialMint;
use OCA\OpenRegister\Service\Credential\CredentialMintOptions;
use OCA\OpenRegister\Service\File\FileTarget;
use OCA\OpenRegister\Service\File\FileUpload;
use OCA\OpenRegister\Service\FileService;
use OCA\Planninq\Service\MailIntake\MailboxCredentialStore;
use OCA\Planninq\Service\MailIntake\TaskAttachments;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The broker and the file service are doubles behind the container.
 *
 * @covers \OCA\Planninq\Service\MailIntake\MailboxCredentialStore
 * @covers \OCA\Planninq\Service\MailIntake\TaskAttachments
 */
class MailboxCredentialStoreTest extends TestCase {

	/**
	 * A container answering one service.
	 *
	 * @param string $class   The service's class.
	 * @param object $service The service.
	 *
	 * @return ContainerInterface
	 */
	private function container(string $class, object $service): ContainerInterface {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with($class)->willReturn($service);

		return $container;
	}//end container()

	/**
	 * The password is minted as an app-injected basic credential only planninq may use; its uuid is the reference.
	 *
	 * @return void
	 */
	public function testThePasswordIsMintedForPlanninqOnly(): void {
		$broker = $this->createMock(CredentialBrokerService::class);
		$broker->expects($this->once())->method('mintCredential')->willReturnCallback(
			function (CredentialMint $mint, CredentialMintOptions $options): ObjectEntity {
				$this->assertSame('Planninq mail intake', $mint->name);
				$this->assertSame('generic-basic', $mint->provider);
				$this->assertSame('admin', $mint->owner);
				$this->assertSame(['username' => 'planninq@gemeente.nl', 'host' => 'imap.gemeente.nl'], $mint->metadata);
				$this->assertSame(['planninq'], $options->allowedApps);
				$this->assertSame('s3cret!', $options->secret());
				$this->assertSame('personal', $options->scope);

				return new class extends ObjectEntity {
					/**
					 * @return string
					 */
					public function getUuid(): string {
						return 'cred-0042';
					}
				};
			}
		);

		$store = new MailboxCredentialStore($this->container(class: CredentialBrokerService::class, service: $broker));

		$this->assertSame('cred-0042', $store->store(password: 's3cret!', owner: 'admin', username: 'planninq@gemeente.nl', host: 'imap.gemeente.nl'));
	}//end testThePasswordIsMintedForPlanninqOnly()

	/**
	 * The job resolves the password as planninq, asserting the owner.
	 *
	 * @return void
	 */
	public function testThePasswordIsResolvedAsPlanninqForTheOwner(): void {
		$broker = $this->createMock(CredentialBrokerService::class);
		$broker->expects($this->once())->method('resolveInjectable')->with('cred-0042', 'planninq', 'admin')->willReturn('s3cret!');

		$store = new MailboxCredentialStore($this->container(class: CredentialBrokerService::class, service: $broker));

		$this->assertSame('s3cret!', $store->resolve(reference: 'cred-0042', owner: 'admin'));
	}//end testThePasswordIsResolvedAsPlanninqForTheOwner()

	/**
	 * An attachment is added to the task's files, unshared.
	 *
	 * @return void
	 */
	public function testAnAttachmentIsAddedUnshared(): void {
		$files = $this->createMock(FileService::class);
		$files->expects($this->once())->method('addFile')->willReturnCallback(
			function (FileTarget $target, FileUpload $upload): mixed {
				$this->assertSame('task-1', $target->object);
				$this->assertSame('printer.jpg', $upload->fileName);
				$this->assertSame('JPEGDATA', $upload->content);
				$this->assertFalse($upload->share);
				return null;
			}
		);

		(new TaskAttachments($this->container(class: FileService::class, service: $files)))->attach(taskId: 'task-1', fileName: 'printer.jpg', content: 'JPEGDATA');
	}//end testAnAttachmentIsAddedUnshared()
}//end class
