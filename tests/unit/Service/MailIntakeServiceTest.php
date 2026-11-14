<?php

/**
 * Unit tests for MailIntakeService: which mail becomes a task, and how.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\MailIntake\IncomingMail;
use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use OCA\Planninq\Service\MailIntake\TaskAttachments;
use OCA\Planninq\Service\MailIntakeService;
use OCA\Planninq\Service\OpenRegisterObjects;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Anna (anna@gemeente.nl) is a member of project VC, "Vergunningen Centrum".
 * The mailbox is planninq@gemeente.nl.
 *
 * @covers \OCA\Planninq\Service\MailIntakeService
 * @uses \OCA\Planninq\Service\MailIntake\IncomingMail
 */
class MailIntakeServiceTest extends TestCase {

	private const PROJECT = '0b8e2f4a-6c1d-4e3f-9a7b-5c6d7e8f9a0b';
	private const TASK = '7f1e2d3c-4b5a-4968-8776-655443322110';
	private const PASS = 'mx.gemeente.nl; spf=pass smtp.mailfrom=gemeente.nl; dkim=pass header.d=gemeente.nl';

	/**
	 * Every event in order: saves, attachments, replies, moves.
	 *
	 * @var array<int,array<int,mixed>>
	 */
	private array $events = [];

	/**
	 * The task rows the project already holds.
	 *
	 * @var array<int,array{id:string,data:array<string,mixed>}>
	 */
	private array $tasks = [];

	/**
	 * Whether saving a task fails.
	 *
	 * @var bool
	 */
	private bool $saveFails = false;

	/**
	 * What was set on the message being composed.
	 *
	 * @var array<string,mixed>
	 */
	private array $composed = [];

	/**
	 * Settings overrides.
	 *
	 * @var array<string,string>
	 */
	private array $settings = [];

	/**
	 * The service over recording doubles.
	 *
	 * @return MailIntakeService
	 */
	private function service(): MailIntakeService {
		$settings = $this->createMock(MailIntakeSettings::class);
		$settings->method('get')->willReturn($this->settings + [MailIntakeSettings::ADDRESS => 'planninq@gemeente.nl'] + MailIntakeSettings::DEFAULTS);

		$projects = $this->createMock(ProjectMembershipService::class);
		$projects->method('rows')->willReturnCallback(
			function (string $schema, array $filters): array {
				if ($schema === 'project') {
					return ($filters['key'] === 'VC') ? [['id' => self::PROJECT, 'data' => ['key' => 'VC', 'title' => 'Vergunningen Centrum']]] : [];
				}

				return ($filters['project'] === self::PROJECT) ? $this->tasks : [];
			}
		);
		$projects->method('membersOfProject')->willReturnCallback(
			static fn (string $projectId): ?array => ($projectId === self::PROJECT) ? ['anna', 'bram'] : null
		);

		$objectService = new class($this) {
			/**
			 * @param MailIntakeServiceTest $test The test recording the save.
			 */
			public function __construct(private MailIntakeServiceTest $test) {
			}

			/**
			 * @param mixed ...$args The save's named arguments.
			 *
			 * @return object
			 */
			public function saveObject(...$args): object {
				return $this->test->recordSave(args: $args);
			}
		};
		$objects = $this->createMock(OpenRegisterObjects::class);
		$objects->method('objectService')->willReturn($objectService);

		$anna = $this->createMock(IUser::class);
		$anna->method('getUID')->willReturn('anna');
		$carl = $this->createMock(IUser::class);
		$carl->method('getUID')->willReturn('carl');
		$users = $this->createMock(IUserManager::class);
		$users->method('getByEmail')->willReturnCallback(
			static fn (string $email): array => match ($email) {
				'anna@gemeente.nl' => [$anna],
				'carl@gemeente.nl' => [$carl],
				default => [],
			}
		);

		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturnCallback(fn (): IMessage => $this->message());
		$mailer->method('send')->willReturnCallback(
			function (IMessage $message): array {
				$this->events[] = ['reply', $this->composed];
				return [];
			}
		);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.gemeente.nl' . $path);

		$attachments = $this->createMock(TaskAttachments::class);
		$attachments->method('attach')->willReturnCallback(
			function (string $taskId, string $fileName, string $content): void {
				$this->events[] = ['attach', $taskId, $fileName, strlen($content)];
			}
		);

		return new MailIntakeService($settings, $projects, $objects, $users, $mailer, $urls, $attachments, $this->createMock(LoggerInterface::class));
	}//end service()

	/**
	 * Record a task save and answer the saved task.
	 *
	 * @param array<string,mixed> $args The save's named arguments.
	 *
	 * @return object
	 */
	public function recordSave(array $args): object {
		if ($this->saveFails === true) {
			throw new RuntimeException('database gone');
		}

		$this->events[] = ['save', $args];

		return new class {
			/**
			 * @return string
			 */
			public function getUuid(): string {
				return '7f1e2d3c-4b5a-4968-8776-655443322110';
			}

			/**
			 * @return array<string,string>
			 */
			public function getObject(): array {
				return ['key' => 'VC-12'];
			}
		};
	}//end recordSave()

	/**
	 * A message double that records what was set on it.
	 *
	 * @return IMessage
	 */
	private function message(): IMessage {
		$this->composed = [];
		$message = $this->createMock(IMessage::class);
		foreach (['setTo' => 'to', 'setSubject' => 'subject', 'setPlainBody' => 'body', 'setAutoSubmitted' => 'autoSubmitted'] as $method => $field) {
			$message->method($method)->willReturnCallback(
				function (mixed $value) use ($message, $field): IMessage {
					$this->composed[$field] = $value;
					return $message;
				}
			);
		}

		return $message;
	}//end message()

	/**
	 * A mailbox double recording moves.
	 *
	 * @return MailboxClient
	 */
	private function box(): MailboxClient {
		$box = $this->createMock(MailboxClient::class);
		$box->method('move')->willReturnCallback(
			function (string $uid, string $folder): void {
				$this->events[] = ['move', $uid, $folder];
			}
		);

		return $box;
	}//end box()

	/**
	 * A mail from Anna.
	 *
	 * @param array<string,mixed> $overrides Fields to change.
	 *
	 * @return IncomingMail
	 */
	private function mail(array $overrides = []): IncomingMail {
		$fields = $overrides + [
			'uid' => '41',
			'messageId' => '<CAF1234@mail.gemeente.nl>',
			'from' => 'anna@gemeente.nl',
			'to' => ['planninq+VC@gemeente.nl'],
			'cc' => [],
			'subject' => 'Printer 2nd floor broken',
			'textBody' => "The printer on the 2nd floor jams on every job.\n\n-- \nAnna de Vries\nTeam ICT",
			'htmlBody' => '',
			'authenticationResults' => self::PASS,
			'attachments' => [['name' => 'printer.jpg', 'mimeType' => 'image/jpeg', 'content' => str_repeat('x', 2048)]],
		];

		return new IncomingMail(...$fields);
	}//end mail()

	/**
	 * The events of one kind.
	 *
	 * @param string $kind The kind.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	private function eventsOf(string $kind): array {
		return array_values(array_filter($this->events, static fn (array $event): bool => $event[0] === $kind));
	}//end eventsOf()

	/**
	 * Anna's mail becomes a backlog task with her body as description, her as reporter and the photo attached.
	 *
	 * @return void
	 */
	public function testTaskFieldsFromMail(): void {
		$outcome = $this->service()->process(mail: $this->mail(), box: $this->box());

		$this->assertSame(MailIntakeService::CREATED, $outcome);
		$saves = $this->eventsOf(kind: 'save');
		$this->assertCount(1, $saves);
		$this->assertSame(
			[
				'object' => [
					'title' => 'Printer 2nd floor broken',
					'description' => 'The printer on the 2nd floor jams on every job.',
					'project' => self::PROJECT,
					'column' => null,
					'status' => 'open',
					'reporter' => 'anna',
					'metadata' => ['emailMessageId' => '<CAF1234@mail.gemeente.nl>'],
				],
				'register' => 'planninq',
				'schema' => 'task',
				'_rbac' => false,
			],
			$saves[0][1]
		);
		$this->assertSame([['attach', self::TASK, 'printer.jpg', 2048]], $this->eventsOf(kind: 'attach'));

		$replies = $this->eventsOf(kind: 'reply');
		$this->assertCount(1, $replies);
		$this->assertSame(['anna@gemeente.nl'], $replies[0][1]['to']);
		$this->assertSame('Re: Printer 2nd floor broken', $replies[0][1]['subject']);
		$this->assertSame('auto-replied', $replies[0][1]['autoSubmitted']);
		$this->assertSame(
			"Task VC-12 was created in Vergunningen Centrum.\n\nhttps://cloud.gemeente.nl/index.php/apps/planninq/projects/" . self::PROJECT . '/tasks/' . self::TASK,
			$replies[0][1]['body']
		);
	}//end testTaskFieldsFromMail()

	/**
	 * The plus-suffix on the mailbox's domain names the project, in any case, also from Cc.
	 *
	 * @return void
	 */
	public function testPlusAddressPicksProject(): void {
		$this->assertSame('VC', MailIntakeService::projectKey(mail: $this->mail(['to' => ['Planninq+vc@Gemeente.NL']]), mailbox: 'planninq@gemeente.nl'));
		$this->assertSame('VC', MailIntakeService::projectKey(mail: $this->mail(['to' => ['team@gemeente.nl'], 'cc' => ['planninq+VC@gemeente.nl']]), mailbox: 'planninq@gemeente.nl'));
		// Another domain or another mailbox name does not count.
		$this->assertNull(MailIntakeService::projectKey(mail: $this->mail(['to' => ['planninq+VC@elders.nl'], 'subject' => 'Hallo']), mailbox: 'planninq@gemeente.nl'));
		$this->assertNull(MailIntakeService::projectKey(mail: $this->mail(['to' => ['other+VC@gemeente.nl'], 'subject' => 'Hallo']), mailbox: 'planninq@gemeente.nl'));

		$this->assertSame(MailIntakeService::CREATED, $this->service()->process(mail: $this->mail(['to' => ['PLANNINQ+vc@gemeente.nl']]), box: $this->box()));
		$this->assertSame(self::PROJECT, $this->eventsOf(kind: 'save')[0][1]['object']['project']);
	}//end testPlusAddressPicksProject()

	/**
	 * Without a plus-address a leading [KEY] tag names the project, and is left out of the title.
	 *
	 * @return void
	 */
	public function testSubjectTagPicksProject(): void {
		$mail = $this->mail(['to' => ['planninq@gemeente.nl'], 'subject' => 'Re: [vc] Printer 2nd floor broken']);
		$this->assertSame('VC', MailIntakeService::projectKey(mail: $mail, mailbox: 'planninq@gemeente.nl'));

		$this->assertSame(MailIntakeService::CREATED, $this->service()->process(mail: $mail, box: $this->box()));
		$this->assertSame('Printer 2nd floor broken', $this->eventsOf(kind: 'save')[0][1]['object']['title']);
	}//end testSubjectTagPicksProject()

	/**
	 * A sender with no Nextcloud account: no task, filed as rejected, no reply.
	 *
	 * @return void
	 */
	public function testNonMemberSenderIsRejectedWithoutReply(): void {
		$outcome = $this->service()->process(mail: $this->mail(['from' => 'onbekend@elders.nl']), box: $this->box());

		$this->assertSame(MailIntakeService::REJECTED, $outcome);
		$this->assertSame([['move', '41', 'Rejected']], $this->events);
	}//end testNonMemberSenderIsRejectedWithoutReply()

	/**
	 * A Nextcloud user who is not a member of the project: no task and no reply either.
	 *
	 * @return void
	 */
	public function testAUserOutsideTheProjectIsRejectedWithoutReply(): void {
		$outcome = $this->service()->process(mail: $this->mail(['from' => 'carl@gemeente.nl']), box: $this->box());

		$this->assertSame(MailIntakeService::REJECTED, $outcome);
		$this->assertSame([['move', '41', 'Rejected']], $this->events);
	}//end testAUserOutsideTheProjectIsRejectedWithoutReply()

	/**
	 * With the check on, a DKIM failure or a missing header is rejected; with it off the mail is accepted.
	 *
	 * @return void
	 */
	public function testFailedDkimIsRejected(): void {
		$failed = $this->mail(['authenticationResults' => 'mx.gemeente.nl; spf=pass smtp.mailfrom=gemeente.nl; dkim=fail header.d=gemeente.nl']);
		$this->assertSame(MailIntakeService::REJECTED, $this->service()->process(mail: $failed, box: $this->box()));
		$this->assertSame([['move', '41', 'Rejected']], $this->events);

		$this->assertFalse(MailIntakeService::authenticated(header: null));
		$this->assertFalse(MailIntakeService::authenticated(header: 'mx; dkim=pass'));
		$this->assertFalse(MailIntakeService::authenticated(header: 'mx; spf=pass; dkim=pass; dkim=fail'));
		$this->assertTrue(MailIntakeService::authenticated(header: self::PASS));

		$this->events = [];
		$this->settings = [MailIntakeSettings::REQUIRE_AUTH => '0'];
		$this->assertSame(MailIntakeService::CREATED, $this->service()->process(mail: $failed, box: $this->box()));
	}//end testFailedDkimIsRejected()

	/**
	 * A member mailing a key no project has is told so, and the mail is rejected.
	 *
	 * @return void
	 */
	public function testUnknownProjectRepliesToMember(): void {
		$outcome = $this->service()->process(mail: $this->mail(['to' => ['planninq+XYZ@gemeente.nl']]), box: $this->box());

		$this->assertSame(MailIntakeService::REJECTED, $outcome);
		$this->assertSame([], $this->eventsOf(kind: 'save'));
		$replies = $this->eventsOf(kind: 'reply');
		$this->assertCount(1, $replies);
		$this->assertSame('No project has the key XYZ.', $replies[0][1]['body']);
		$this->assertSame(['move', '41', 'Rejected'], end($this->events));

		// A mail naming no project at all gets a hint.
		$this->events = [];
		$this->service()->process(mail: $this->mail(['to' => ['planninq@gemeente.nl']]), box: $this->box());
		$this->assertStringStartsWith('Your mail names no project.', $this->eventsOf(kind: 'reply')[0][1]['body']);
	}//end testUnknownProjectRepliesToMember()

	/**
	 * A message whose task exists is only filed: no second task, no second reply.
	 *
	 * @return void
	 */
	public function testSameMessageIdCreatesNoSecondTask(): void {
		$this->tasks = [
			['id' => 'other', 'data' => ['metadata' => ['emailMessageId' => '<other@mail.gemeente.nl>']]],
			['id' => self::TASK, 'data' => ['metadata' => ['emailMessageId' => '<CAF1234@mail.gemeente.nl>']]],
		];

		$outcome = $this->service()->process(mail: $this->mail(), box: $this->box());

		$this->assertSame(MailIntakeService::DUPLICATE, $outcome);
		$this->assertSame([['move', '41', 'Processed']], $this->events);
	}//end testSameMessageIdCreatesNoSecondTask()

	/**
	 * An attachment over the size limit is skipped and named in the reply; the rest is attached.
	 *
	 * @return void
	 */
	public function testOversizedAttachmentIsSkippedAndNamed(): void {
		$this->settings = [MailIntakeSettings::SIZE_LIMIT_MB => '1'];
		$mail = $this->mail(
			[
				'attachments' => [
					['name' => 'scan.pdf', 'mimeType' => 'application/pdf', 'content' => str_repeat('x', 2 * 1024 * 1024)],
					['name' => 'log.txt', 'mimeType' => 'text/plain', 'content' => 'error 42'],
				],
			]
		);

		$this->service()->process(mail: $mail, box: $this->box());

		$this->assertSame([['attach', self::TASK, 'log.txt', 8]], $this->eventsOf(kind: 'attach'));
		$this->assertStringEndsWith("\n\nThese attachments were too large and were not attached: scan.pdf", $this->eventsOf(kind: 'reply')[0][1]['body']);
	}//end testOversizedAttachmentIsSkippedAndNamed()

	/**
	 * The message is filed only after its task is saved; a failed save leaves it in the inbox.
	 *
	 * @return void
	 */
	public function testMessageFiledOnlyAfterSave(): void {
		$this->service()->process(mail: $this->mail(), box: $this->box());
		$this->assertSame(['save', 'attach', 'reply', 'move'], array_map(static fn (array $event): string => $event[0], $this->events));
		$this->assertSame(['move', '41', 'Processed'], end($this->events));

		$this->events = [];
		$this->saveFails = true;
		try {
			$this->service()->process(mail: $this->mail(), box: $this->box());
			$this->fail('A failed save must not be swallowed');
		} catch (RuntimeException $e) {
			$this->assertSame('database gone', $e->getMessage());
		}

		$this->assertSame([], $this->events);
	}//end testMessageFiledOnlyAfterSave()

	/**
	 * Titles lose reply prefixes and tags and are cut to 255; descriptions come from HTML when there is no text.
	 *
	 * @return void
	 */
	public function testTitleAndDescription(): void {
		$this->assertSame('Printer kapot', MailIntakeService::title(subject: 'RE: Fwd: AW:  Printer   kapot '));
		$this->assertSame('(no subject)', MailIntakeService::title(subject: '[VC] '));
		$this->assertSame(255, mb_strlen(MailIntakeService::title(subject: str_repeat('é', 300))));

		$html = $this->mail(['textBody' => '', 'htmlBody' => '<p>Regel &amp; een</p><p>Regel twee<br>drie</p>']);
		$this->assertSame("Regel & een\nRegel twee\ndrie", MailIntakeService::description(mail: $html));
	}//end testTitleAndDescription()
}//end class
