<?php

/**
 * Planninq Mail Correspondent
 *
 * The people side of the mail intake: which Nextcloud user a sender is, the
 * reply that goes back to them, and the link to the task it names.
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

use OCA\Planninq\Service\MailIntakeService;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\Headers\AutoSubmitted;
use OCP\Mail\IMailer;

/**
 * Finds a sender's account and replies to them.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
class MailCorrespondent {

	/**
	 * @param IUserManager  $users  Finds the sender's account by email.
	 * @param IMailer       $mailer Sends the reply.
	 * @param IURLGenerator $urls   Builds the task link.
	 */
	public function __construct(
		private readonly IUserManager $users,
		private readonly IMailer $mailer,
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * The uid of the one Nextcloud user with this email address.
	 *
	 * @param string $address The sender's address.
	 *
	 * @return string|null Null for no user, or for an address that more than one user carries.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-mail-that-may-not-create-a-task-is-set-aside
	 */
	public function senderUid(string $address): ?string {
		if ($address === '') {
			return null;
		}

		$users = $this->users->getByEmail($address);
		if (count($users) !== 1) {
			return null;
		}

		return reset($users)->getUID();
	}//end senderUid()

	/**
	 * Send an automatic reply to the sender of a message.
	 *
	 * @param IncomingMail $mail The message replied to.
	 * @param string       $body The reply text.
	 *
	 * @return void
	 *
	 * @throws \Throwable When the mailer cannot send it.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function reply(IncomingMail $mail, string $body): void {
		$message = $this->mailer->createMessage();
		$message->setTo([$mail->from]);
		$message->setSubject('Re: ' . MailIntakeService::title(subject: $mail->subject));
		$message->setPlainBody($body);
		$message->setAutoSubmitted(AutoSubmitted::VALUE_AUTO_REPLIED);
		$this->mailer->send($message);
	}//end reply()

	/**
	 * The absolute link to a task in its project.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $taskId    The task uuid.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function taskLink(string $projectId, string $taskId): string {
		return $this->urls->getAbsoluteURL('/index.php/apps/planninq/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId));
	}//end taskLink()
}//end class
