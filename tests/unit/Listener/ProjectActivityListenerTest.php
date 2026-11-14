<?php

/**
 * Unit tests for ProjectActivityListener.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\ProjectActivityListener;
use OCA\Planninq\Listener\TaskScopeResolver;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Project events reach the project's people, never the person who made them.
 *
 * @covers \OCA\Planninq\Listener\ProjectActivityListener
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 *
 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
 */
class ProjectActivityListenerTest extends TestCase {

	/**
	 * What was published: subject, params, type, author, affected user.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $published = [];

	/**
	 * Archiving reaches bob, not alice who archived it (the spec's first scenario).
	 *
	 * @return void
	 */
	public function testArchivingReachesTheMembersButNotTheActor(): void {
		$before = $this->project(['status' => 'active']);
		$after = $this->project(['status' => 'archived']);

		$this->listener(actor: 'alice')->handle(new ObjectUpdatedEvent($this->entity($after), $this->entity($before)));

		$this->assertSame([['project_archived', 'bob']], $this->subjectsAndUsers());
		$this->assertSame('planninq_project', $this->published[0]['type']);
		$this->assertSame('alice', $this->published[0]['author']);
		$this->assertSame(['actor' => 'alice', 'title' => 'Vergunningen', 'objectId' => 'project-1'], $this->published[0]['params']);
	}//end testArchivingReachesTheMembersButNotTheActor()

	/**
	 * A new member reads that they were added (the spec's second scenario); so do the others.
	 *
	 * @return void
	 */
	public function testANewMemberReadsTheyWereAdded(): void {
		$before = $this->project([]);
		$after = $this->project(['members' => ['alice', 'bob', 'carla']]);

		$this->listener(actor: 'alice')->handle(new ObjectUpdatedEvent($this->entity($after), $this->entity($before)));

		$this->assertSame([['project_member_added', 'bob'], ['project_member_added', 'carla']], $this->subjectsAndUsers());
		$this->assertSame('carla', $this->published[1]['params']['member']);
	}//end testANewMemberReadsTheyWereAdded()

	/**
	 * A removed person learns of it too; restoring reaches everyone but the actor.
	 *
	 * @return void
	 */
	public function testARemovedMemberAndARestoredProject(): void {
		$before = $this->project(['status' => 'archived', 'members' => ['alice', 'bob', 'dirk']]);
		$after = $this->project(['status' => 'active']);

		$this->listener(actor: 'alice')->handle(new ObjectUpdatedEvent($this->entity($after), $this->entity($before)));

		$this->assertSame(
			[['project_restored', 'bob'], ['project_member_removed', 'bob'], ['project_member_removed', 'dirk']],
			$this->subjectsAndUsers()
		);
		$this->assertSame('dirk', $this->published[2]['params']['member']);
	}//end testARemovedMemberAndARestoredProject()

	/**
	 * Created and deleted reach the owner, managers and members, each once.
	 *
	 * @return void
	 */
	public function testCreatedAndDeletedReachEveryoneOnTheProjectOnce(): void {
		$project = $this->project(['owner' => 'olga', 'managers' => ['mia', 'bob'], 'members' => ['alice', 'bob', '']]);

		$this->listener(actor: 'alice')->handle(new ObjectCreatedEvent($this->entity($project)));
		$this->listener(actor: '')->handle(new ObjectDeletedEvent($this->entity($project)));

		$this->assertSame(
			[
				['project_created', 'olga'], ['project_created', 'mia'], ['project_created', 'bob'],
				['project_deleted', 'olga'], ['project_deleted', 'mia'], ['project_deleted', 'bob'], ['project_deleted', 'alice'],
			],
			$this->subjectsAndUsers()
		);
	}//end testCreatedAndDeletedReachEveryoneOnTheProjectOnce()

	/**
	 * An update that changes neither status nor members publishes nothing; nor does a task.
	 *
	 * @return void
	 */
	public function testAnUnrelatedChangeOrAnotherSchemaPublishesNothing(): void {
		$before = $this->project([]);
		$after = $this->project(['description' => 'New words']);

		$this->listener(actor: 'alice')->handle(new ObjectUpdatedEvent($this->entity($after), $this->entity($before)));
		$this->listener(actor: 'alice', schemaSlug: 'task')->handle(new ObjectCreatedEvent($this->entity($after)));

		$this->assertSame([], $this->published);
	}//end testAnUnrelatedChangeOrAnotherSchemaPublishesNothing()

	/**
	 * A failure is logged and swallowed, so the write itself never fails.
	 *
	 * @return void
	 */
	public function testAFailureIsLoggedAndSwallowed(): void {
		$resolver = $this->createMock(TaskScopeResolver::class);
		$resolver->method('isObjectOfSchema')->willThrowException(new \RuntimeException('lookup failed'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'Planninq: project activity listener skipped an event',
			['exception' => 'lookup failed']
		);

		(new ProjectActivityListener($this->createMock(IActivityManager::class), $this->createMock(IUserSession::class), $resolver, $logger))
			->handle(new ObjectCreatedEvent($this->entity($this->project([]))));
	}//end testAFailureIsLoggedAndSwallowed()

	/**
	 * The published entries as [subject, affected user].
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	private function subjectsAndUsers(): array {
		return array_map(static fn (array $entry): array => [$entry['subject'], $entry['affected']], $this->published);
	}//end subjectsAndUsers()

	/**
	 * The project "Vergunningen" of alice and bob, with overrides.
	 *
	 * @param array<string, mixed> $overrides Fields to change
	 *
	 * @return array<string, mixed>
	 */
	private function project(array $overrides): array {
		return array_merge(['title' => 'Vergunningen', 'status' => 'active', 'owner' => 'alice', 'members' => ['alice', 'bob']], $overrides);
	}//end project()

	/**
	 * A project object with this data.
	 *
	 * @param array<string, mixed> $data The data
	 *
	 * @return ObjectEntity
	 */
	private function entity(array $data): ObjectEntity {
		return new class($data) extends ObjectEntity {
			// phpcs:disable
			public function __construct(private array $d) {
			}
			public function getObject(): array {
				return $this->d;
			}
			public function getRegister(): ?string {
				return 'register-1';
			}
			public function getSchema(): ?string {
				return 'schema-1';
			}
			public function getUuid(): ?string {
				return 'project-1';
			}
			// phpcs:enable
		};
	}//end entity()

	/**
	 * The listener for this actor, over a resolver that reads the given schema slug.
	 *
	 * @param string $actor      The signed-in user, '' for none
	 * @param string $schemaSlug The schema the object is in
	 *
	 * @return ProjectActivityListener
	 */
	private function listener(string $actor, string $schemaSlug = 'project'): ProjectActivityListener {
		$captured = [];
		$activityEvent = $this->createMock(IEvent::class);
		foreach (['setApp', 'setTimestamp', 'setObject'] as $method) {
			$activityEvent->method($method)->willReturn($activityEvent);
		}

		$activityEvent->method('setType')->willReturnCallback(function (string $type) use ($activityEvent, &$captured): IEvent {
			$captured['type'] = $type;
			return $activityEvent;
		});
		$activityEvent->method('setAuthor')->willReturnCallback(function (string $author) use ($activityEvent, &$captured): IEvent {
			$captured['author'] = $author;
			return $activityEvent;
		});
		$activityEvent->method('setSubject')->willReturnCallback(function (string $subject, array $params = []) use ($activityEvent, &$captured): IEvent {
			$captured['subject'] = $subject;
			$captured['params'] = $params;
			return $activityEvent;
		});
		$activityEvent->method('setAffectedUser')->willReturnCallback(function (string $user) use ($activityEvent, &$captured): IEvent {
			$captured['affected'] = $user;
			return $activityEvent;
		});

		$manager = $this->createMock(IActivityManager::class);
		$manager->method('generateEvent')->willReturn($activityEvent);
		$manager->method('publish')->willReturnCallback(function () use (&$captured): void {
			$this->published[] = $captured;
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);

		$mapper = static fn (string $slug): object => new class($slug) {
			public function __construct(private string $slug) {
			}
			public function find($id, mixed ...$flags): object {
				return new class($this->slug) {
					public function __construct(private string $slug) {
					}
					public function getSlug(): string {
						return $this->slug;
					}
				};
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(static fn (string $id): object => match ($id) {
			'OCA\\OpenRegister\\Db\\RegisterMapper' => $mapper('planninq'),
			'OCA\\OpenRegister\\Db\\SchemaMapper' => $mapper($schemaSlug),
		});

		return new ProjectActivityListener(
			$manager,
			$session,
			new TaskScopeResolver($container, $this->createMock(LoggerInterface::class)),
			$this->createMock(LoggerInterface::class)
		);
	}//end listener()
}//end class
