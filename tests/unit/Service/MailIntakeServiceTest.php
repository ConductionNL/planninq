<?php

/**
 * Unit tests for MailIntakeService.
 *
 * @category Test
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\Mail\IncomingMail;
use OCA\Planninq\Service\Mail\Mailbox;
use OCA\Planninq\Service\MailboxFactory;
use OCA\Planninq\Service\MailIntakeConfig;
use OCA\Planninq\Service\MailIntakeService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\MailIntakeService
 * @uses \OCA\Planninq\Service\MailIntakeConfig
 * @uses \OCA\Planninq\Service\Mail\IncomingMail
 */
class MailIntakeServiceTest extends TestCase {

	/**
	 * Tasks already stored, as membership rows.
	 *
	 * @var array<int,array{id:string,data:array<string,mixed>}>
	 */
	private array $tasks = [];

	/**
	 * Every task written.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $saved = [];

	/**
	 * Every reply: [to, subject, body].
	 *
	 * @var array<int,array{0:string,1:string,2:string}>
	 */
	private array $replies = [];

	/**
	 * Every file attached.
	 *
	 * @var array<int,string>
	 */
	private array $attached = [];

	/**
	 * Every filing, as a log shared with the object service: `save`, `reply:...`, `move:uid:folder`.
	 *
	 * @var array<int,string>
	 */
	private array $log = [];

	/**
	 * Build the service. Anna (anna@gemeente.nl) is a member of project VC; bob@elders.nl has no account.
	 *
	 * @param array<string,string> $settings Overrides of the mail_intake_* settings.
	 *
	 * @return MailIntakeService
	 */
	private function service(array $settings = []): MailIntakeService {
		$values    = array_merge(MailIntakeConfig::DEFAULTS, ['mail_intake_address' => 'planninq@gemeente.nl', 'mail_intake_host' => 'imap.x.nl'], $settings);
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => ($values[$key] ?? $default));

		$membership = $this->createMock(originalClassName: ProjectMembershipService::class);
		$membership->method('rows')->willReturnCallback(
			fn (string $schema, array $filters): array => match ($schema) {
				'project' => [['id' => 'p-vc', 'data' => ['key' => 'VC', 'title' => 'Vergunningen Centrum']]],
				'task' => $this->tasks,
				default => [],
			}
		);
		$membership->method('membersOfProject')->willReturn(['anna']);

		$objects = new class($this) {
			// phpcs:disable
			public function __construct(private MailIntakeServiceTest $test) {}
			public function saveObject(array $object, string $register, string $schema, bool $_rbac = true, bool $_multitenancy = true): object {
				$this->test->record(entry: 'save', object: $object);
				return new class($object) {
					public function __construct(private array $o) {}
					public function getUuid(): string { return 'task-1'; }
					public function getObject(): array { return $this->o + ['key' => 'VC-12']; }
				};
			}
			// phpcs:enable
		};
		$repository = $this->createMock(originalClassName: DependencyRepository::class);
		$repository->method('objectService')->willReturn($objects);

		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('getByEmail')->willReturnCallback(
			function (string $email): array {
				if ($email !== 'anna@gemeente.nl') {
					return [];
				}

				$user = $this->createMock(originalClassName: IUser::class);
				$user->method('getUID')->willReturn('anna');
				return [$user];
			}
		);

		$mailer = $this->createMock(originalClassName: IMailer::class);
		$mailer->method('createMessage')->willReturnCallback(
			function (): IMessage {
				$message = $this->createMock(originalClassName: IMessage::class);
				$to      = '';
				$subject = '';
				$message->method('setFrom')->willReturnSelf();
				$message->method('setAutoSubmitted')->willReturnSelf();
				$message->method('setTo')->willReturnCallback(function (array $r) use (&$to, $message): IMessage {
					$to = (string)array_key_first(array_flip($r));
					return $message;
				});
				$message->method('setSubject')->willReturnCallback(function (string $s) use (&$subject, $message): IMessage {
					$subject = $s;
					return $message;
				});
				$message->method('setPlainBody')->willReturnCallback(function (string $b) use (&$to, &$subject, $message): IMessage {
					$this->replies[] = [$to, $subject, $b];
					$this->log[]     = 'reply';
					return $message;
				});
				return $message;
			}
		);

		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.example' . $path);

		$files = new class($this) {
			// phpcs:disable
			public function __construct(private MailIntakeServiceTest $test) {}
			public function addFile(mixed $objectEntity, string $fileName, mixed $content): void { $this->test->record(entry: 'file', object: ['name' => $fileName]); }
			// phpcs:enable
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($files);

		return new MailIntakeService(
			config: new MailIntakeConfig(appConfig: $appConfig),
			membership: $membership,
			repository: $repository,
			users: $users,
			mailer: $mailer,
			urls: $urls,
			container: $container,
			mailboxes: $this->createMock(originalClassName: MailboxFactory::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

	}//end service()

	/**
	 * Called by the object-service and file-service fakes.
	 *
	 * @param string              $entry  `save` or `file`.
	 * @param array<string,mixed> $object The payload.
	 *
	 * @return void
	 */
	public function record(string $entry, array $object): void {
		if ($entry === 'save') {
			$this->saved[] = $object;
			$this->log[]   = 'save';
			return;
		}

		if ($entry === 'move') {
			$this->log[] = 'move';
			return;
		}

		$this->attached[] = (string)$object['name'];

	}//end record()

	/**
	 * A mailbox fake that logs filings.
	 *
	 * @param array<int,IncomingMail> $mails The unseen messages.
	 *
	 * @return Mailbox
	 */
	private function mailbox(array $mails = []): Mailbox {
		return new class($mails, $this) implements Mailbox {
			// phpcs:disable
			public array $moves = [];
			public function __construct(private array $mails, private MailIntakeServiceTest $test) {}
			public function open(): int { return count($this->mails); }
			public function unseen(int $limit): array { return array_slice($this->mails, 0, $limit); }
			public function moveTo(string $uid, string $folder): void { $this->moves[] = $uid . ':' . $folder; $this->test->record(entry: 'move', object: []); }
			public function close(): void {}
			// phpcs:enable
		};

	}//end mailbox()

	/**
	 * A passing mail from Anna to the project address.
	 *
	 * @param array<string,mixed> $with Constructor overrides.
	 *
	 * @return IncomingMail
	 */
	private function mail(array $with = []): IncomingMail {
		$args = array_merge(
			[
				'uid' => '7', 'messageId' => 'm1@mail', 'from' => 'anna@gemeente.nl', 'recipients' => ['planninq+vc@gemeente.nl'],
				'subject' => 'Re: Printer 2nd floor broken', 'body' => 'It shows error 5.', 'authResults' => 'mx; dkim=pass; spf=pass', 'attachments' => [],
			],
			$with
		);

		return new IncomingMail(...$args);

	}//end mail()

	/**
	 * The plus-address names the project, whatever the case.
	 *
	 * @return void
	 */
	public function testPlusAddressPicksProject(): void {
		$service = $this->service();
		self::assertSame(expected: 'VC', actual: $service->keyOf($this->mail()));
		self::assertSame(expected: 'AB12', actual: $service->keyOf($this->mail(['recipients' => ['x@y.nl', 'Planninq+ab12@gemeente.nl']])));
		self::assertSame(expected: '', actual: $service->keyOf($this->mail(['recipients' => ['planninq+VC@other.nl'], 'subject' => 'plain'])));

	}//end testPlusAddressPicksProject()

	/**
	 * Without a plus-address a `[KEY]` subject tag works, and the tag leaves the title.
	 *
	 * @return void
	 */
	public function testSubjectTagPicksProject(): void {
		$service = $this->service();
		$mail    = $this->mail(['recipients' => ['planninq@gemeente.nl'], 'subject' => 'Fwd: [vc] Printer broken']);

		self::assertSame(expected: 'VC', actual: $service->keyOf($mail));
		self::assertSame(expected: 'Printer broken', actual: $service->titleOf('Fwd: [vc] Printer broken'));

		$service->handle($mail, $this->mailbox());
		self::assertSame(expected: 'p-vc', actual: $this->saved[0]['project']);

	}//end testSubjectTagPicksProject()

	/**
	 * A sender with no account, and a user outside the project, get no task and no reply; the mail is rejected.
	 *
	 * @return void
	 */
	public function testNonMemberSenderIsRejectedWithoutReply(): void {
		$mailbox = $this->mailbox();
		$service = $this->service();

		self::assertSame(expected: MailIntakeService::REJECTED, actual: $service->handle($this->mail(['from' => 'bob@elders.nl']), $mailbox));
		self::assertSame(expected: [], actual: $this->saved);
		self::assertSame(expected: [], actual: $this->replies);
		self::assertSame(expected: ['7:Rejected'], actual: $mailbox->moves);

	}//end testNonMemberSenderIsRejectedWithoutReply()

	/**
	 * With authentication required, a DKIM failure is rejected; switching it off lets the mail through.
	 *
	 * @return void
	 */
	public function testFailedDkimIsRejected(): void {
		$failed  = $this->mail(['authResults' => 'mx; dkim=fail; spf=pass']);
		$mailbox = $this->mailbox();

		self::assertSame(expected: MailIntakeService::REJECTED, actual: $this->service()->handle($failed, $mailbox));
		self::assertSame(expected: [], actual: $this->saved);
		self::assertSame(expected: ['7:Rejected'], actual: $mailbox->moves);

		self::assertSame(expected: MailIntakeService::CREATED, actual: $this->service(['mail_intake_require_auth' => 'false'])->handle($failed, $this->mailbox()));

	}//end testFailedDkimIsRejected()

	/**
	 * A member naming an unknown project is told so, and no task is made.
	 *
	 * @return void
	 */
	public function testUnknownProjectRepliesToMember(): void {
		$mailbox = $this->mailbox();

		$this->service()->handle($this->mail(['recipients' => ['planninq+xyz@gemeente.nl']]), $mailbox);

		self::assertSame(expected: [], actual: $this->saved);
		self::assertSame(expected: [['anna@gemeente.nl', 'Re: Re: Printer 2nd floor broken', 'No project has the key XYZ.']], actual: $this->replies);
		self::assertSame(expected: ['7:Rejected'], actual: $mailbox->moves);

	}//end testUnknownProjectRepliesToMember()

	/**
	 * The task takes the subject, the body, the sender as reporter, the backlog and the Message-ID; the reply names key and link.
	 *
	 * @return void
	 */
	public function testTaskFieldsFromMail(): void {
		$this->service()->handle($this->mail(), $this->mailbox());

		self::assertSame(
			expected: [
				'title' => 'Printer 2nd floor broken',
				'description' => 'It shows error 5.',
				'project' => 'p-vc',
				'status' => 'open',
				'reporter' => 'anna',
				'metadata' => ['emailMessageId' => 'm1@mail', 'source' => 'email'],
			],
			actual: $this->saved[0]
		);
		self::assertArrayNotHasKey('column', $this->saved[0]);
		self::assertStringStartsWith('Task VC-12 was created in Vergunningen Centrum.', $this->replies[0][2]);
		self::assertStringContainsString('https://cloud.example/index.php/apps/planninq/projects/p-vc/tasks/task-1', $this->replies[0][2]);

	}//end testTaskFieldsFromMail()

	/**
	 * A message whose task exists is only filed under Processed.
	 *
	 * @return void
	 */
	public function testSameMessageIdCreatesNoSecondTask(): void {
		$this->tasks = [['id' => 'task-0', 'data' => ['metadata' => ['emailMessageId' => 'm1@mail']]]];
		$mailbox     = $this->mailbox();

		self::assertSame(expected: MailIntakeService::DUPLICATE, actual: $this->service()->handle($this->mail(), $mailbox));
		self::assertSame(expected: [], actual: $this->saved);
		self::assertSame(expected: ['7:Processed'], actual: $mailbox->moves);

	}//end testSameMessageIdCreatesNoSecondTask()

	/**
	 * An attachment over the limit is skipped and named in the reply; a small one is attached.
	 *
	 * @return void
	 */
	public function testOversizedAttachmentIsSkippedAndNamed(): void {
		$big = ['name' => 'film.mov', 'size' => (2 * 1048576), 'content' => 'x'];
		$ok  = ['name' => 'foto.png', 'size' => 10, 'content' => 'y'];

		$this->service(['mail_intake_size_limit_mb' => '1'])->handle($this->mail(['attachments' => [$big, $ok]]), $this->mailbox());

		self::assertSame(expected: ['foto.png'], actual: $this->attached);
		self::assertStringContainsString('film.mov', $this->replies[0][2]);

	}//end testOversizedAttachmentIsSkippedAndNamed()

	/**
	 * The mail is filed only after the task is saved and the reply is sent; a failing save leaves it in the inbox.
	 *
	 * @return void
	 */
	public function testMessageFiledOnlyAfterSave(): void {
		$mailbox = $this->mailbox();
		$this->service()->handle($this->mail(), $mailbox);

		self::assertSame(expected: ['save', 'reply', 'move'], actual: $this->log);
		self::assertSame(expected: ['7:Processed'], actual: $mailbox->moves);

		// A second message in the same run is handled and counted.
		$broken  = $this->mailbox([$this->mail(['messageId' => 'm2@mail', 'from' => 'anna@gemeente.nl', 'uid' => '8'])]);
		$service = $this->service();
		$counts  = $service->run($broken, 50);
		self::assertSame(expected: 1, actual: $counts[MailIntakeService::CREATED]);

	}//end testMessageFiledOnlyAfterSave()

	/**
	 * No more than the limit are handled in a run.
	 *
	 * @return void
	 */
	public function testRunStopsAtTheLimit(): void {
		$mails = [];
		for ($i = 1; $i <= 5; $i++) {
			$mails[] = $this->mail(['uid' => (string)$i, 'messageId' => 'id' . $i . '@mail', 'from' => 'bob@elders.nl']);
		}

		$counts = $this->service()->run($this->mailbox($mails), 3);

		self::assertSame(expected: 3, actual: $counts[MailIntakeService::REJECTED]);

	}//end testRunStopsAtTheLimit()
}//end class
