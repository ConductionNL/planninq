<?php

/**
 * Tests for ProjectStatusListener: the newest status report is copied onto its project.
 *
 * Built on the real OpenRegister event classes (stubs with the same API, see
 * OpenRegisterEventStubDriftTest), the real ProjectMembershipService,
 * TaskScopeResolver and ProjectHealthService, over an in-memory ObjectService.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\ProjectStatusListener;
use OCA\Planninq\Service\ProjectHealthService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectStatusListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT = ['title' => 'Stadspark', 'status' => 'active', 'owner' => 'carol', 'members' => ['bob']];

	/**
	 * Whether each project save ran in the system-operation scope.
	 *
	 * @var array<int,bool>
	 */
	private array $savedAsSystem = [];

	protected function setUp(): void {
		parent::setUp();
		$saved = &$this->savedAsSystem;
		$this->objects = new class ($saved) extends InMemoryObjectService {
			/** @var array<int,bool> */
			private array $asSystem;

			public function __construct(array &$asSystem) {
				$this->asSystem = &$asSystem;
			}

			public function saveObject(
				array|object $object,
				?array $extend = [],
				mixed $register = null,
				mixed $schema = null,
				?string $uuid = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $silent = false,
				bool $_validation = true,
			): ?object {
				$this->asSystem[] = SystemOperationContext::isActive();
				return parent::saveObject($object, $extend, $register, $schema, $uuid, $_rbac, $_multitenancy, $silent, $_validation);
			}
		};
		$this->objects->seed('project', '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', self::PROJECT);
	}//end setUp()

	private function listener(string $actor, bool $isAdmin = false): ProjectStatusListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));

		$membership = $this->membershipService();
		$logger     = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectStatusListener(
			health: new ProjectHealthService(membership: $membership, container: $this->container(), logger: $logger),
			membership: $membership,
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: $logger
		);
	}//end listener()

	private function report(string $uuid, array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: 'projectStatusReport'));
	}//end report()

	private function project(array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', data: $data, register: '1', schema: $this->schemaId(slug: 'project'));
	}//end project()

	/**
	 * A report with every aspect on track except the ones given.
	 *
	 * @param string              $date     The report date.
	 * @param array<string,mixed> $statuses Statuses to set, keyed `status<Aspect>`.
	 *
	 * @return array<string,mixed>
	 */
	private static function reportData(string $date, array $statuses = []): array {
		$data = ['project' => '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', 'reportDate' => $date];
		foreach (ProjectHealthService::ASPECTS as $aspect) {
			$data['status' . $aspect] = 'onTrack';
		}

		return array_merge($data, $statuses);
	}//end reportData()

	private function storedProject(): array {
		return (array)$this->objects->find(id: '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', schema: 'project')->getObject();
	}//end storedProject()

	/**
	 * Task 1.2: a new report is copied onto its project, as the system, in a payload the project schema accepts.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testANewerReportIsCopiedOntoTheProject(): void {
		$this->objects->seed('projectStatusReport', 'rep-1', self::reportData(date: '2026-09-01'));
		$data  = self::reportData(date: '2026-09-28', statuses: ['statusTime' => 'atRisk', 'noteTime' => 'Permit delayed by 3 weeks']);
		$event = new ObjectCreatingEvent($this->report(uuid: 'rep-2', data: $data));

		$this->listener(actor: 'carol')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		$project = $this->storedProject();
		self::assertSame('atRisk', $project['healthTime']);
		self::assertSame('onTrack', $project['healthMoney']);
		self::assertSame('atRisk', $project['healthOverall']);
		self::assertSame('2026-09-28', $project['healthDate']);
		self::assertSame('Stadspark', $project['title'], 'the rest of the project is kept');
		self::assertSame([true], $this->savedAsSystem, 'one write, inside the system-operation scope');
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $project));
		self::assertSame([], $this->registerSchemaErrors(slug: 'projectStatusReport', payload: $data));
	}//end testANewerReportIsCopiedOntoTheProject()

	/**
	 * Task 1.2: a report dated before the newest one leaves the project alone.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testAnOlderReportDoesNotReplaceTheNewest(): void {
		$newest = self::reportData(date: '2026-09-20', statuses: ['statusQuality' => 'offTrack']);
		$this->objects->seed('projectStatusReport', 'rep-1', $newest);
		$this->listener(actor: 'carol')->handle(new ObjectCreatingEvent($this->report(uuid: 'rep-1', data: $newest)));
		self::assertSame('offTrack', $this->storedProject()['healthOverall']);

		$event = new ObjectCreatingEvent($this->report(uuid: 'rep-0', data: self::reportData(date: '2026-08-01')));
		$this->listener(actor: 'carol')->handle($event);

		self::assertFalse($event->isPropagationStopped(), 'an older report may still be written');
		self::assertSame('offTrack', $this->storedProject()['healthQuality']);
		self::assertSame('2026-09-20', $this->storedProject()['healthDate']);
	}//end testAnOlderReportDoesNotReplaceTheNewest()

	/**
	 * Task 1.2: deleting the newest report copies the one before it; deleting the last clears the health.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testDeletingTheNewestReportCopiesTheOneBefore(): void {
		$older  = self::reportData(date: '2026-09-01', statuses: ['statusMoney' => 'atRisk']);
		$newest = self::reportData(date: '2026-09-28', statuses: ['statusRisk' => 'offTrack']);
		$this->objects->seed('projectStatusReport', 'rep-1', $older);
		$this->objects->seed('projectStatusReport', 'rep-2', $newest);
		$this->objects->seed('project', '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', self::PROJECT + ['healthRisk' => 'offTrack', 'healthOverall' => 'offTrack', 'healthDate' => '2026-09-28']);

		$this->listener(actor: 'carol')->handle(new ObjectDeletingEvent($this->report(uuid: 'rep-2', data: $newest)));

		$project = $this->storedProject();
		self::assertSame('atRisk', $project['healthMoney']);
		self::assertSame('onTrack', $project['healthRisk']);
		self::assertSame('atRisk', $project['healthOverall']);
		self::assertSame('2026-09-01', $project['healthDate']);

		unset($this->objects->rows['projectStatusReport']['rep-2']);
		$this->listener(actor: 'carol')->handle(new ObjectDeletingEvent($this->report(uuid: 'rep-1', data: $older)));
		self::assertNull($this->storedProject()['healthOverall']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $this->storedProject()));
	}//end testDeletingTheNewestReportCopiesTheOneBefore()

	/**
	 * Requirement "a project owner or manager writes the report": a member who is not the owner is refused, and nothing is copied.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.1
	 */
	public function testOnlyTheOwnerOrAnAdminWritesAReport(): void {
		$event = new ObjectCreatingEvent($this->report(uuid: 'rep-1', data: self::reportData(date: '2026-09-28')));
		$this->listener(actor: 'bob')->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(ProjectStatusListener::ERROR_CODE, $event->getErrors()['code']);
		self::assertSame([], $this->savedAsSystem, 'a refused report is not copied');

		$admin = new ObjectCreatingEvent($this->report(uuid: 'rep-1', data: self::reportData(date: '2026-09-28')));
		$this->listener(actor: 'root', isAdmin: true)->handle($admin);
		self::assertFalse($admin->isPropagationStopped());
	}//end testOnlyTheOwnerOrAnAdminWritesAReport()

	/**
	 * A members-only rewrite by the membership sync is neither refused nor copied.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testAMembersOnlyRewritePasses(): void {
		$data  = self::reportData(date: '2026-09-28') + ['members' => ['bob', 'carol']];
		$event = new ObjectUpdatingEvent($this->report(uuid: 'rep-1', data: ['members' => ['carol']] + $data), $this->report(uuid: 'rep-1', data: $data));

		$this->listener(actor: 'bob')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame([], $this->savedAsSystem);
	}//end testAMembersOnlyRewritePasses()

	/**
	 * Risk 1: no person writes the health fields; an update keeps the stored ones and a create gets none.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testAPersonCannotWriteTheHealthFields(): void {
		$stored = self::PROJECT + ['healthMoney' => 'offTrack', 'healthOverall' => 'offTrack', 'healthDate' => '2026-09-28'];
		$update = new ObjectUpdatingEvent(
			$this->project(data: ['healthMoney' => 'onTrack', 'healthOverall' => 'onTrack'] + $stored),
			$this->project(data: $stored)
		);
		$this->listener(actor: 'carol')->handle($update);

		self::assertFalse($update->isPropagationStopped(), 'the settings form still saves');
		self::assertSame('offTrack', $update->getModifiedData()['healthMoney']);
		self::assertSame('offTrack', $update->getModifiedData()['healthOverall']);

		$create = new ObjectCreatingEvent($this->project(data: self::PROJECT + ['healthOverall' => 'onTrack']));
		$this->listener(actor: 'carol')->handle($create);
		self::assertNull($create->getModifiedData()['healthOverall']);

		$untouched = new ObjectUpdatingEvent($this->project(data: ['title' => 'Renamed'] + $stored), $this->project(data: $stored));
		$this->listener(actor: 'carol')->handle($untouched);
		self::assertSame([], $untouched->getModifiedData(), 'an update that leaves health alone is not modified');

		$system = new ObjectUpdatingEvent($this->project(data: ['healthMoney' => 'onTrack'] + $stored), $this->project(data: $stored));
		SystemOperationContext::run(fn () => $this->listener(actor: 'carol')->handle($system));
		self::assertSame([], $system->getModifiedData(), 'the system copy passes');
	}//end testAPersonCannotWriteTheHealthFields()
}//end class
