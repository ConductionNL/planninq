<?php

/**
 * Planninq MailIntakeService
 *
 * Turns a mail from a project member into a task in that project's backlog:
 * picks the project from the plus-address or a `[KEY]` subject tag, checks the
 * sender and the receiving server's authentication results, creates the task
 * once per Message-ID, attaches the files, replies and files the message.
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Service\Mail\IncomingMail;
use OCA\Planninq\Service\Mail\Mailbox;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates tasks from mail.
 */
class MailIntakeService {

	/**
	 * The result of handling one message.
	 *
	 * @var string
	 */
	public const CREATED = 'created';

	/**
	 * The message already had its task; it was only filed.
	 *
	 * @var string
	 */
	public const DUPLICATE = 'duplicate';

	/**
	 * The message may not become a task.
	 *
	 * @var string
	 */
	public const REJECTED = 'rejected';

	/**
	 * The longest task title.
	 *
	 * @var int
	 */
	private const TITLE_MAX = 255;

	/**
	 * Constructor.
	 *
	 * @param MailIntakeConfig         $config      The admin's mailbox settings.
	 * @param ProjectMembershipService $membership  Finds projects and their members.
	 * @param DependencyRepository     $repository  Resolves OpenRegister's ObjectService.
	 * @param IUserManager             $users       Matches a sender to an account.
	 * @param IMailer                  $mailer      Sends the reply.
	 * @param IURLGenerator            $urls        Builds the task link.
	 * @param ContainerInterface       $container   Resolves OpenRegister's FileService.
	 * @param MailboxFactory           $mailboxes   Opens the mailbox for a batch.
	 * @param LoggerInterface          $logger      The logger.
	 */
	public function __construct(
		private MailIntakeConfig $config,
		private ProjectMembershipService $membership,
		private DependencyRepository $repository,
		private IUserManager $users,
		private IMailer $mailer,
		private IURLGenerator $urls,
		private ContainerInterface $container,
		private MailboxFactory $mailboxes,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Open the configured mailbox and handle up to $limit unseen messages.
	 *
	 * @param int $limit The most messages to handle.
	 *
	 * @return array<string,int> Count per outcome.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	public function runBatch(int $limit): array {
		$mailbox = $this->mailboxes->open();
		try {
			return $this->run(mailbox: $mailbox, limit: $limit);
		} finally {
			$mailbox->close();
		}
	}//end runBatch()

	/**
	 * Handle up to $limit unseen messages of an open mailbox.
	 *
	 * A message that fails half way stays in the folder for the next run.
	 *
	 * @param Mailbox $mailbox The open mailbox.
	 * @param int     $limit   The most messages to handle.
	 *
	 * @return array<string,int> Count per outcome.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	public function run(Mailbox $mailbox, int $limit): array {
		$counts = [self::CREATED => 0, self::DUPLICATE => 0, self::REJECTED => 0, 'failed' => 0];
		foreach (array_slice($mailbox->unseen($limit), 0, $limit) as $mail) {
			try {
				$outcome = $this->handle(mail: $mail, mailbox: $mailbox);
			} catch (\Throwable $e) {
				$this->logger->error('Planninq: a mail was not handled and stays in the inbox', ['uid' => $mail->uid, 'exception' => $e->getMessage()]);
				$outcome = 'failed';
			}

			$counts[$outcome]++;
		}

		return $counts;
	}//end run()

	/**
	 * Handle one message and file it.
	 *
	 * @param IncomingMail $mail    The message.
	 * @param Mailbox      $mailbox The mailbox it came from.
	 *
	 * @return string One of the outcome constants.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
	 */
	public function handle(IncomingMail $mail, Mailbox $mailbox): string {
		if ($mail->messageId !== '' && $this->taskForMessage(messageId: $mail->messageId) !== null) {
			$mailbox->moveTo($mail->uid, MailIntakeConfig::PROCESSED_FOLDER);
			return self::DUPLICATE;
		}

		$decision = $this->decide(mail: $mail);
		if (isset($decision['project']) === false) {
			if ($decision['reply'] !== '') {
				$this->reply(mail: $mail, body: $decision['reply']);
			}

			// Filed only after the reply went out, so a failed reply is retried.
			$mailbox->moveTo($mail->uid, MailIntakeConfig::REJECTED_FOLDER);
			return self::REJECTED;
		}

		$this->createTask(mail: $mail, project: $decision['project'], senderUid: $decision['sender']);
		// Filed only after the task is saved, so a crash in between re-reads the mail and finds the task.
		$mailbox->moveTo($mail->uid, MailIntakeConfig::PROCESSED_FOLDER);

		return self::CREATED;
	}//end handle()

	/**
	 * The project key a mail names: a plus-address on the mailbox, else a `[KEY]` subject tag.
	 *
	 * @param IncomingMail $mail The message.
	 *
	 * @return string The upper-case key, or ''.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
	 */
	public function keyOf(IncomingMail $mail): string {
		$mailbox = strtolower($this->config->get(key: 'mail_intake_address'));
		$atSign      = strrpos($mailbox, '@');
		if ($atSign !== false) {
			$local  = substr($mailbox, 0, $atSign);
			$domain = substr($mailbox, $atSign);
			foreach ($mail->recipients as $recipient) {
				$recipient = strtolower($recipient);
				if (str_ends_with($recipient, $domain) === true && str_starts_with($recipient, $local . '+') === true) {
					return strtoupper(substr($recipient, (strlen($local) + 1), (strlen($recipient) - strlen($local) - 1 - strlen($domain))));
				}
			}
		}

		if (preg_match('/^\s*(?:(?:re|fwd?)\s*:\s*)*\[([A-Za-z0-9]{2,10})\]/i', $mail->subject, $found) === 1) {
			return strtoupper($found[1]);
		}

		return '';
	}//end keyOf()

	/**
	 * The task title: the subject without reply prefixes or the `[KEY]` tag, cut to the title limit.
	 *
	 * @param string $subject The subject.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
	 */
	public function titleOf(string $subject): string {
		$title = trim((string)preg_replace('/^\s*(?:(?:re|fwd?)\s*:\s*)+/i', '', $subject));
		$title = trim((string)preg_replace('/^\s*\[[A-Za-z0-9]{2,10}\]\s*/', '', $title));
		if ($title === '') {
			$title = 'Mail without subject';
		}

		return mb_substr($title, 0, self::TITLE_MAX);
	}//end titleOf()

	/**
	 * Whether the receiving server recorded a pass for both SPF and DKIM.
	 *
	 * @param string $results The Authentication-Results header.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
	 */
	public function authenticationPassed(string $results): bool {
		return preg_match('/\bdkim=pass\b/i', $results) === 1 && preg_match('/\bspf=pass\b/i', $results) === 1;
	}//end authenticationPassed()

	/**
	 * Decide whether a mail becomes a task: the project and the sender, or why not and what to reply.
	 *
	 * @param IncomingMail $mail The message.
	 *
	 * @return array{project?:array{id:string,key:string,title:string},sender?:string,reply:string}
	 */
	private function decide(IncomingMail $mail): array {
		if ($this->config->requiresAuth() === true && $this->authenticationPassed(results: $mail->authResults) === false) {
			return ['reply' => ''];
		}

		$accounts = [];
		foreach ($this->users->getByEmail($mail->from) as $account) {
			$accounts[] = $account->getUID();
		}

		// A sender who is no Nextcloud user never gets a reply: the mailbox would mail forged addresses.
		if ($accounts === []) {
			return ['reply' => ''];
		}

		$key = $this->keyOf(mail: $mail);
		if ($key === '') {
			$address = $this->config->get(key: 'mail_intake_address');

			$hint = ' with the project key after a plus sign, or start the subject with [KEY].';

			return ['reply' => 'Your mail did not name a project. Send it to ' . $address . $hint];
		}

		$project = $this->projectByKey(key: $key);
		if ($project === null) {
			return ['reply' => 'No project has the key ' . $key . '.'];
		}

		foreach ($accounts as $uid) {
			if (in_array($uid, (array)$this->membership->membersOfProject($project['id']), true) === true) {
				return ['project' => $project, 'sender' => $uid, 'reply' => ''];
			}
		}

		// Not a member: no task and no reply, so the mailbox does not confirm the project to outsiders.
		return ['reply' => ''];
	}//end decide()

	/**
	 * The project with a key.
	 *
	 * @param string $key The upper-case key.
	 *
	 * @return array{id:string,key:string,title:string}|null
	 */
	private function projectByKey(string $key): ?array {
		foreach ($this->membership->rows('project', ['key' => $key]) as $row) {
			if (strtoupper((string)($row['data']['key'] ?? '')) === $key) {
				return ['id' => $row['id'], 'key' => $key, 'title' => (string)($row['data']['title'] ?? $key)];
			}
		}

		return null;
	}//end projectByKey()

	/**
	 * The id of the task already made from a Message-ID, or null.
	 *
	 * @param string $messageId The Message-ID.
	 *
	 * @return string|null
	 */
	private function taskForMessage(string $messageId): ?string {
		foreach ($this->membership->rows('task', ['metadata.emailMessageId' => $messageId]) as $row) {
			if (($row['data']['metadata']['emailMessageId'] ?? null) === $messageId) {
				return $row['id'];
			}
		}

		return null;
	}//end taskForMessage()

	/**
	 * Save the task, attach the files and send the confirmation.
	 *
	 * @param IncomingMail                             $mail      The message.
	 * @param array{id:string,key:string,title:string} $project   The project.
	 * @param string                                   $senderUid The sender's account.
	 *
	 * @return void
	 */
	private function createTask(IncomingMail $mail, array $project, string $senderUid): void {
		$service = $this->repository->objectService();
		$saved   = $service->saveObject(
			object: [
				'title' => $this->titleOf(subject: $mail->subject),
				'description' => $mail->body,
				'project' => $project['id'],
				'status' => 'open',
				'reporter' => $senderUid,
				'metadata' => ['emailMessageId' => $mail->messageId, 'source' => 'email'],
			],
			register: 'planninq',
			schema: 'task',
			_rbac: false,
			_multitenancy: false
		);

		$skipped = $this->attach(saved: $saved, mail: $mail);
		$data    = [];
		$taskId  = '';
		if (is_object($saved) === true) {
			$data   = (array)$saved->getObject();
			$taskId = (string)$saved->getUuid();
		}

		$taskKey = (string)($data['key'] ?? $mail->subject);

		$body = 'Task ' . $taskKey . ' was created in ' . $project['title'] . '.'
			. "\n\n" . $this->urls->getAbsoluteURL('/index.php/apps/planninq/projects/' . rawurlencode($project['id']) . '/tasks/' . rawurlencode($taskId));
		if ($skipped !== []) {
			$body .= "\n\nThese attachments were too large and were not added: " . implode(', ', $skipped) . '.';
		}

		$this->reply(mail: $mail, body: $body);
	}//end createTask()

	/**
	 * Attach the files that fit the size limit.
	 *
	 * @param mixed        $saved The saved task.
	 * @param IncomingMail $mail  The message.
	 *
	 * @return array<int,string> The names of the files left out.
	 */
	private function attach(mixed $saved, IncomingMail $mail): array {
		$skipped = [];
		$total   = 0;
		$limit   = $this->config->sizeLimitBytes();
		foreach ($mail->attachments as $file) {
			if (($total + $file['size']) > $limit) {
				$skipped[] = $file['name'];
				continue;
			}

			$total += $file['size'];
			try {
				$files = $this->container->get('OCA\\OpenRegister\\Service\\FileService');
				$files->addFile(objectEntity: $saved, fileName: $file['name'], content: $file['content']);
			} catch (\Throwable $e) {
				$this->logger->warning('Planninq: a mailed attachment was not added', ['name' => $file['name'], 'exception' => $e->getMessage()]);
				$skipped[] = $file['name'];
			}
		}

		return $skipped;
	}//end attach()

	/**
	 * Mail the sender a short answer, threaded under their message.
	 *
	 * @param IncomingMail $mail The message answered.
	 * @param string       $body The reply text.
	 *
	 * @return void
	 */
	private function reply(IncomingMail $mail, string $body): void {
		$message = $this->mailer->createMessage();
		$message->setFrom([$this->config->get(key: 'mail_intake_address') => 'Planninq']);
		$message->setTo([$mail->from]);
		$message->setSubject('Re: ' . $mail->subject);
		$message->setPlainBody($body);
		$message->setAutoSubmitted('auto-replied');
		if ($mail->messageId !== '' && method_exists($message, 'getSymfonyEmail') === true) {
			$headers = $message->getSymfonyEmail()->getHeaders();
			$headers->addIdHeader('In-Reply-To', $mail->messageId);
		}

		$this->mailer->send($message);
	}//end reply()
}//end class
