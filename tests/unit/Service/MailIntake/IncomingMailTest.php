<?php

/**
 * Unit tests for IncomingMail: a message's parts as the intake reads them.
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
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MailIntake\IncomingMail
 */
class IncomingMailTest extends TestCase {

	/**
	 * Every part is taken over as given.
	 *
	 * @return void
	 */
	public function testEveryPartIsTakenOver(): void {
		$attachment = ['name' => 'plan.pdf', 'mimeType' => 'application/pdf', 'content' => '%PDF'];
		$mail       = new IncomingMail(
			uid: '17',
			message: [
				'messageId' => '<a@b>',
				'from' => 'mia@gemeente.nl',
				'to' => ['taken+hm@gemeente.nl'],
				'cc' => ['jan@gemeente.nl', 'eva@gemeente.nl'],
				'subject' => 'Bankjes plaatsen',
				'textBody' => 'Graag voor vrijdag.',
				'htmlBody' => '<p>Graag voor vrijdag.</p>',
				'authenticationResults' => 'mx.gemeente.nl; dkim=pass',
				'attachments' => [$attachment],
			]
		);

		$this->assertSame('17', $mail->uid);
		$this->assertSame('<a@b>', $mail->messageId);
		$this->assertSame('mia@gemeente.nl', $mail->from);
		$this->assertSame(['taken+hm@gemeente.nl'], $mail->to);
		$this->assertSame(['jan@gemeente.nl', 'eva@gemeente.nl'], $mail->cc);
		$this->assertSame('Bankjes plaatsen', $mail->subject);
		$this->assertSame('Graag voor vrijdag.', $mail->textBody);
		$this->assertSame('<p>Graag voor vrijdag.</p>', $mail->htmlBody);
		$this->assertSame('mx.gemeente.nl; dkim=pass', $mail->authenticationResults);
		$this->assertSame([$attachment], $mail->attachments);
	}//end testEveryPartIsTakenOver()

	/**
	 * A missing part is empty, and the authentication results are null.
	 *
	 * @return void
	 */
	public function testAMissingPartIsEmpty(): void {
		$mail = new IncomingMail(uid: '1');

		$this->assertSame('', $mail->messageId);
		$this->assertSame('', $mail->from);
		$this->assertSame([], $mail->to);
		$this->assertSame([], $mail->cc);
		$this->assertSame('', $mail->subject);
		$this->assertSame('', $mail->textBody);
		$this->assertSame('', $mail->htmlBody);
		$this->assertNull($mail->authenticationResults);
		$this->assertSame([], $mail->attachments);
	}//end testAMissingPartIsEmpty()

	/**
	 * A list part that is not a list reads as empty; scalar parts are cast to text.
	 *
	 * @return void
	 */
	public function testPartsOfTheWrongShapeAreNormalised(): void {
		$mail = new IncomingMail(
			uid: '2',
			message: [
				'messageId' => 42,
				'to' => 'taken@gemeente.nl',
				'cc' => null,
				'attachments' => 'plan.pdf',
				'authenticationResults' => 0,
			]
		);

		$this->assertSame('42', $mail->messageId);
		$this->assertSame([], $mail->to);
		$this->assertSame([], $mail->cc);
		$this->assertSame([], $mail->attachments);
		$this->assertSame('0', $mail->authenticationResults);
	}//end testPartsOfTheWrongShapeAreNormalised()
}//end class
