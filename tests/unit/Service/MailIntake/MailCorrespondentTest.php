<?php

/**
 * Unit tests for MailCorrespondent: who a sender is, the reply and the task link.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service\MailIntake;

use OCA\Planninq\Service\MailIntake\IncomingMail;
use OCA\Planninq\Service\MailIntake\MailCorrespondent;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\Headers\AutoSubmitted;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MailIntake\MailCorrespondent
 * @uses \OCA\Planninq\Service\MailIntake\IncomingMail
 * @uses \OCA\Planninq\Service\MailIntakeService
 */
class MailCorrespondentTest extends TestCase {

	/**
	 * A correspondent over the given users and mailer.
	 *
	 * @param array<string,array<int,IUser>> $usersByEmail The accounts per address.
	 * @param IMailer|null                   $mailer       The mailer.
	 *
	 * @return MailCorrespondent
	 */
	private function correspondent(array $usersByEmail = [], ?IMailer $mailer = null): MailCorrespondent {
		$users = $this->createMock(IUserManager::class);
		$users->method('getByEmail')->willReturnCallback(static fn (string $email): array => ($usersByEmail[$email] ?? []));
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.gemeente.nl' . $path);

		return new MailCorrespondent($users, ($mailer ?? $this->createMock(IMailer::class)), $urls);
	}//end correspondent()

	/**
	 * Only an address exactly one account carries names a sender.
	 *
	 * @return void
	 */
	public function testOnlyAnAddressOfOneAccountNamesTheSender(): void {
		$anna = $this->createMock(IUser::class);
		$anna->method('getUID')->willReturn('anna');
		$twin = $this->createMock(IUser::class);
		$correspondent = $this->correspondent(['anna@gemeente.nl' => [$anna], 'shared@gemeente.nl' => [$anna, $twin]]);

		$this->assertSame('anna', $correspondent->senderUid(address: 'anna@gemeente.nl'));
		$this->assertNull($correspondent->senderUid(address: 'shared@gemeente.nl'));
		$this->assertNull($correspondent->senderUid(address: 'nobody@gemeente.nl'));
		$this->assertNull($correspondent->senderUid(address: ''));
	}//end testOnlyAnAddressOfOneAccountNamesTheSender()

	/**
	 * The reply goes to the sender, as an automatic reply, with the cleaned subject.
	 *
	 * @return void
	 */
	public function testTheReplyGoesToTheSenderAsAnAutomaticReply(): void {
		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['anna@gemeente.nl']);
		$message->expects($this->once())->method('setSubject')->with('Re: Printer broken');
		$message->expects($this->once())->method('setPlainBody')->with('Task VC-3 was created.');
		$message->expects($this->once())->method('setAutoSubmitted')->with(AutoSubmitted::VALUE_AUTO_REPLIED);
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects($this->once())->method('send')->with($message);

		$this->correspondent(mailer: $mailer)->reply(
			mail: new IncomingMail('7', ['from' => 'anna@gemeente.nl', 'subject' => 'RE: [VC] Printer broken']),
			body: 'Task VC-3 was created.'
		);
	}//end testTheReplyGoesToTheSenderAsAnAutomaticReply()

	/**
	 * The task link is absolute and encodes its ids.
	 *
	 * @return void
	 */
	public function testTheTaskLinkIsAbsolute(): void {
		$this->assertSame(
			'https://cloud.gemeente.nl/index.php/apps/planninq/projects/p%201/tasks/t-2',
			$this->correspondent()->taskLink(projectId: 'p 1', taskId: 't-2')
		);
	}//end testTheTaskLinkIsAbsolute()
}//end class
