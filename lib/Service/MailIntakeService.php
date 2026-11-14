<?php

/**
 * Planninq Mail Intake Service
 *
 * Turns one mail from the intake mailbox into a task, or sets it aside.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\Service\MailIntake\IncomingMail;
use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MailCorrespondent;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use OCA\Planninq\Service\MailIntake\TaskAttachments;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * The project comes from the plus-suffix of a To or Cc address on the
 * mailbox's domain, or from a `[KEY]` subject tag. The sender must be the
 * one Nextcloud user with that email address and a member of the project,
 * and, when the admin requires it, the receiving server must report SPF and
 * DKIM as passed. Only then is a task created, in the project's backlog.
 *
 * A message is filed only after its outcome is final (design D5): to
 * `Processed` after its task is saved, to `Rejected` after its reply (if any)
 * is sent. Anything that fails in between leaves it in the inbox for the
 * next run, where the Message-ID check keeps it from becoming a second task.
 * Unknown senders never get a reply (design D3).
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
class MailIntakeService {

	public const CREATED = 'created';
	public const DUPLICATE = 'duplicate';
	public const REJECTED = 'rejected';

	public const PROCESSED_FOLDER = 'Processed';
	public const REJECTED_FOLDER = 'Rejected';

	/**
	 * The longest task title.
	 *
	 * @var int
	 */
	private const TITLE_LENGTH = 255;

	/**
	 * Reply prefixes stripped from the subject, repeated.
	 *
	 * @var string
	 */
	private const PREFIXES = '/^\s*(?:(?:re|fwd?|aw|wg|antw)\s*:\s*)+/i';

	/**
	 * A leading `[KEY]` tag, after any reply prefixes.
	 *
	 * @var string
	 */
	private const SUBJECT_TAG = '/^\s*(?:(?:re|fwd?|aw|wg|antw)\s*:\s*)*\[([A-Za-z0-9]{2,10})\]\s*/i';

	/**
	 * @param MailIntakeSettings       $settings    The mailbox address, the auth requirement and the size limit.
	 * @param ProjectMembershipService $projects    Reads projects, members and tasks as the system.
	 * @param OpenRegisterObjects      $objects     Saves the task.
	 * @param MailCorrespondent        $sender      Finds the sender's account and replies to them.
	 * @param TaskAttachments          $attachments Stores the attachments on the task.
	 * @param LoggerInterface          $logger      Logs outcomes without message content.
	 */
	public function __construct(
		private readonly MailIntakeSettings $settings,
		private readonly ProjectMembershipService $projects,
		private readonly OpenRegisterObjects $objects,
		private readonly MailCorrespondent $sender,
		private readonly TaskAttachments $attachments,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Turn one message into a task, or set it aside, and file it.
	 *
	 * @param IncomingMail  $mail The message.
	 * @param MailboxClient $box  The mailbox it came from, to file it in.
	 *
	 * @return string CREATED, DUPLICATE or REJECTED.
	 *
	 * @throws Throwable When saving the task or filing the message fails; the message then stays in the inbox.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-mail-that-may-not-create-a-task-is-set-aside
	 */
	public function process(IncomingMail $mail, MailboxClient $box): string {
		$verdict = $this->verdict(mail: $mail);
		if (isset($verdict['reason']) === true) {
			$box->move(uid: $mail->uid, folder: self::REJECTED_FOLDER);
			$this->logger->info('Planninq: a mail was not turned into a task', ['reason' => $verdict['reason']]);
			return self::REJECTED;
		}

		if ($this->alreadyCreated(projectId: $verdict['project']['id'], messageId: $mail->messageId) === true) {
			$box->move(uid: $mail->uid, folder: self::PROCESSED_FOLDER);
			return self::DUPLICATE;
		}

		$task = $this->createTask(mail: $mail, origin: $verdict);
		$task['skipped'] = $this->attach(mail: $mail, taskId: $task['id']);
		$this->reply(mail: $mail, body: $this->confirmationText(task: $task, project: $verdict['project']));
		$box->move(uid: $mail->uid, folder: self::PROCESSED_FOLDER);

		return self::CREATED;
	}//end process()

	/**
	 * Whether a message may become a task, and in which project.
	 *
	 * A mail naming no known project gets a reply first; unknown senders never do.
	 *
	 * @param IncomingMail $mail The message.
	 *
	 * @return array{reason:string}|array{project:array{id:string,data:array<string,mixed>},reporter:string,key:string}
	 *         Why it is set aside, or the project, the sender's uid and the key.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-mail-that-may-not-create-a-task-is-set-aside
	 */
	private function verdict(IncomingMail $mail): array {
		$settings = $this->settings->get();

		$sender = $this->sender->senderUid(address: $mail->from);
		if ($sender === null) {
			return ['reason' => 'unknown sender'];
		}

		if (self::authenticationFails(mail: $mail, required: $settings[MailIntakeSettings::REQUIRE_AUTH] === '1') === true) {
			return ['reason' => 'sender authentication did not pass'];
		}

		$key = self::projectKey(mail: $mail, mailbox: $settings[MailIntakeSettings::ADDRESS]);
		$project = $this->projectByKey(key: (string)$key);
		if ($project === null) {
			$this->reply(mail: $mail, body: self::unknownProjectText(key: $key));
			return ['reason' => 'no project'];
		}

		if (in_array($sender, ($this->projects->membersOfProject(projectId: $project['id']) ?? []), true) === false) {
			return ['reason' => 'sender is not a member'];
		}

		return ['project' => $project, 'reporter' => $sender, 'key' => (string)$key];
	}//end verdict()

	/**
	 * Whether the admin requires sender authentication and the message lacks it.
	 *
	 * @param IncomingMail $mail     The message.
	 * @param bool         $required Whether SPF and DKIM must have passed.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-mail-that-may-not-create-a-task-is-set-aside
	 */
	private static function authenticationFails(IncomingMail $mail, bool $required): bool {
		return $required === true && self::authenticated(header: $mail->authenticationResults) === false;
	}//end authenticationFails()

	/**
	 * The project key a message is addressed to: the plus-suffix of a To or Cc
	 * address on the mailbox's domain, else a leading `[KEY]` subject tag.
	 *
	 * @param IncomingMail $mail    The message.
	 * @param string       $mailbox The mailbox's own address.
	 *
	 * @return string|null The key, upper-case, or null when the message names none.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-each-project-with-a-key-has-a-task-address
	 */
	public static function projectKey(IncomingMail $mail, string $mailbox): ?string {
		$key = self::addressedKey(mail: $mail, mailbox: $mailbox);
		if ($key !== null) {
			return $key;
		}

		if (preg_match(self::SUBJECT_TAG, $mail->subject, $match) === 1) {
			return strtoupper($match[1]);
		}

		return null;
	}//end projectKey()

	/**
	 * The plus-suffix of a To or Cc address on the mailbox's own local part and domain.
	 *
	 * @param IncomingMail $mail    The message.
	 * @param string       $mailbox The mailbox's own address.
	 *
	 * @return string|null The key, upper-case, or null.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-each-project-with-a-key-has-a-task-address
	 */
	private static function addressedKey(IncomingMail $mail, string $mailbox): ?string {
		$at = strrpos($mailbox, '@');
		if ($at === false) {
			return null;
		}

		$pattern = '/^' . preg_quote(strtolower(substr($mailbox, 0, $at)), '/') . '\+([a-z0-9]{2,10})@'
			. preg_quote(strtolower(substr($mailbox, $at + 1)), '/') . '$/';
		foreach (array_merge($mail->to, $mail->cc) as $address) {
			if (preg_match($pattern, strtolower(trim($address)), $match) === 1) {
				return strtoupper($match[1]);
			}
		}

		return null;
	}//end addressedKey()

	/**
	 * Whether the receiving server reported both SPF and DKIM as passed.
	 *
	 * @param string|null $header The Authentication-Results header.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-mail-that-may-not-create-a-task-is-set-aside
	 */
	public static function authenticated(?string $header): bool {
		if ($header === null) {
			return false;
		}

		return preg_match('/\bdkim\s*=\s*pass\b/i', $header) === 1
			&& preg_match('/\bspf\s*=\s*pass\b/i', $header) === 1
			&& preg_match('/\b(?:dkim|spf)\s*=\s*(?:fail|softfail|neutral|none|temperror|permerror)\b/i', $header) !== 1;
	}//end authenticated()

	/**
	 * The task title: the subject without reply prefixes or a `[KEY]` tag, at most 255 characters.
	 *
	 * @param string $subject The subject.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public static function title(string $subject): string {
		$title = (string)preg_replace(self::SUBJECT_TAG, '', $subject);
		$title = trim((string)preg_replace(self::PREFIXES, '', $title));
		$title = trim((string)preg_replace('/\s+/u', ' ', $title));
		if ($title === '') {
			return '(no subject)';
		}

		return mb_substr($title, 0, self::TITLE_LENGTH);
	}//end title()

	/**
	 * The task description: the plain-text body (the HTML one as text when
	 * there is none), without what follows a signature marker.
	 *
	 * @param IncomingMail $mail The message.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public static function description(IncomingMail $mail): string {
		$body = $mail->textBody;
		if (trim($body) === '' && $mail->htmlBody !== '') {
			$body = (string)preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $mail->htmlBody);
			$body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		$body = str_replace(["\r\n", "\r"], "\n", $body);
		$marker = strpos($body, "\n-- \n");
		if ($marker !== false) {
			$body = substr($body, 0, $marker);
		}

		return trim((string)preg_replace("/\n{3,}/", "\n\n", $body));
	}//end description()

	/**
	 * The project with this key.
	 *
	 * @param string $key The upper-case key, empty when the message names none.
	 *
	 * @return array{id:string,data:array<string,mixed>}|null
	 */
	private function projectByKey(string $key): ?array {
		if ($key === '') {
			return null;
		}

		foreach ($this->projects->rows(schema: 'project', filters: ['key' => $key]) as $row) {
			if (strtoupper((string)($row['data']['key'] ?? '')) === $key) {
				return $row;
			}
		}

		return null;
	}//end projectByKey()

	/**
	 * Whether a task of this project already came from this message.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $messageId The Message-ID.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	private function alreadyCreated(string $projectId, string $messageId): bool {
		if ($messageId === '') {
			return false;
		}

		foreach ($this->projects->rows(schema: 'task', filters: ['project' => $projectId]) as $row) {
			if (($row['data']['metadata']['emailMessageId'] ?? null) === $messageId) {
				return true;
			}
		}

		return false;
	}//end alreadyCreated()

	/**
	 * Save the task in the project's backlog, as the system, after the membership check above.
	 *
	 * @param IncomingMail                                                                         $mail   The message.
	 * @param array{project:array{id:string,data:array<string,mixed>},reporter:string,key:string} $origin The project, the sender's uid and the key.
	 *
	 * @return array{id:string,key:string} The task's uuid and key.
	 *
	 * @throws RuntimeException When OpenRegister is not available or answers no uuid.
	 */
	private function createTask(IncomingMail $mail, array $origin): array {
		$objectService = $this->objects->objectService();
		if ($objectService === null) {
			throw new RuntimeException('OpenRegister is not available');
		}

		$metadata = [];
		if ($mail->messageId !== '') {
			$metadata['emailMessageId'] = $mail->messageId;
		}

		$saved = $objectService->saveObject(
			object: [
				'title' => self::title(subject: $mail->subject),
				'description' => self::description(mail: $mail),
				'project' => $origin['project']['id'],
				'column' => null,
				'status' => 'open',
				'reporter' => $origin['reporter'],
				'metadata' => $metadata,
			],
			register: ProjectMembershipService::REGISTER,
			schema: 'task',
			_rbac: false
		);

		$uuid = (string)$saved->getUuid();
		if ($uuid === '') {
			throw new RuntimeException('The task was saved without a uuid');
		}

		$this->logger->info('Planninq: a task was created from mail', ['task' => $uuid, 'project' => $origin['key']]);

		return ['id' => $uuid, 'key' => (string)($saved->getObject()['key'] ?? '')];
	}//end createTask()

	/**
	 * Attach the attachments that fit within the size limit; a failing one is skipped too.
	 *
	 * @param IncomingMail $mail   The message.
	 * @param string       $taskId The task uuid.
	 *
	 * @return array<int,string> The names of the attachments that were skipped.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	private function attach(IncomingMail $mail, string $taskId): array {
		$budget = (max(1, (int)$this->settings->get()[MailIntakeSettings::SIZE_LIMIT_MB]) * 1024 * 1024);
		$skipped = [];
		foreach ($mail->attachments as $attachment) {
			$size = strlen($attachment['content']);
			if ($size > $budget) {
				$skipped[] = $attachment['name'];
				continue;
			}

			try {
				$this->attachments->attach(taskId: $taskId, fileName: $attachment['name'], content: $attachment['content']);
				$budget -= $size;
			} catch (Throwable $e) {
				$this->logger->warning('Planninq: a mail attachment could not be stored', ['task' => $taskId, 'exception' => get_class($e)]);
				$skipped[] = $attachment['name'];
			}
		}

		return $skipped;
	}//end attach()

	/**
	 * Reply to the sender; a failing reply is logged and does not undo the outcome.
	 *
	 * @param IncomingMail $mail The message replied to.
	 * @param string       $body The reply text.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	private function reply(IncomingMail $mail, string $body): void {
		try {
			$this->sender->reply(mail: $mail, body: $body);
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: the mail intake reply could not be sent', ['exception' => get_class($e)]);
		}
	}//end reply()

	/**
	 * The confirmation: the task's key and project, the link, and any skipped attachments.
	 *
	 * @param array{id:string,key:string,skipped:array<int,string>} $task    The task, with the skipped attachments.
	 * @param array{id:string,data:array<string,mixed>}            $project The project.
	 *
	 * @return string
	 */
	private function confirmationText(array $task, array $project): string {
		$name = (string)($project['data']['title'] ?? '');
		$label = 'A task';
		if ($task['key'] !== '') {
			$label = 'Task ' . $task['key'];
		}

		$text = $label . ' was created in ' . $name . ".\n\n"
			. $this->sender->taskLink(projectId: $project['id'], taskId: $task['id']);
		if ($task['skipped'] !== []) {
			$text .= "\n\nThese attachments were too large and were not attached: " . implode(', ', $task['skipped']);
		}

		return $text;
	}//end confirmationText()

	/**
	 * The reply to a member whose mail names no known project.
	 *
	 * @param string|null $key The key the mail named, if any.
	 *
	 * @return string
	 */
	private static function unknownProjectText(?string $key): string {
		if ($key === null) {
			return 'Your mail names no project. Send it to the project\'s task address, or start the subject with the project key in brackets, like [VC].';
		}

		return 'No project has the key ' . $key . '.';
	}//end unknownProjectText()
}//end class
