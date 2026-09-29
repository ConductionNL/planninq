<?php

/**
 * Tests for ProjectHierarchyGuardListener: portfolio managers become the readers of its projects.
 *
 * Built on the real OpenRegister event classes (stubs with the same API),
 * the real ProjectMembershipService, TaskScopeResolver and
 * ProjectMemberAccessListener, over an in-memory ObjectService.
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
use OCA\Planninq\Listener\ProjectHierarchyGuardListener;
use OCA\Planninq\Listener\ProjectMemberAccessListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectHierarchyGuardListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const RUIMTE = '11111111-1111-4111-8111-111111111111';
	private const DIENST = '22222222-2222-4222-8222-222222222222';
	private const PROJECT = '33333333-3333-4333-8333-333333333333';
	private const TASK = '44444444-4444-4444-8444-444444444444';

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('projectPortfolio', self::RUIMTE, ['title' => 'Ruimte', 'managers' => ['mia']]);
		$this->objects->seed('projectPortfolio', self::DIENST, ['title' => 'Dienstverlening', 'managers' => ['dirk', 'eva']]);
		$this->objects->seed('project', self::PROJECT, ['title' => 'Omgevingsvisie', 'status' => 'active', 'owner' => 'carol', 'members' => ['bob'], 'portfolio' => self::RUIMTE, 'portfolioReaders' => ['mia']]);
		$this->objects->seed('task', self::TASK, ['title' => 'Draft', 'status' => 'todo', 'project' => self::PROJECT, 'members' => ['bob', 'carol'], 'portfolioReaders' => ['mia']]);
	}//end setUp()

	private function listener(): ProjectHierarchyGuardListener {
		$membership = $this->membershipService();
		$logger     = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectHierarchyGuardListener(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			scopeResolver: $this->scopeResolver(),
			container: $this->container(),
			logger: $logger
		);
	}//end listener()

	private function entity(string $slug, string $uuid, array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $slug));
	}//end entity()

	private function stored(string $slug, string $uuid): array {
		return (array)$this->objects->find(id: $uuid, schema: $slug)->getObject();
	}//end stored()

	/**
	 * Scenario "A project manager moves a project into a portfolio": the new portfolio's managers become its readers.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testMovingAProjectTakesTheNewPortfoliosManagers(): void {
		$old = $this->stored(slug: 'project', uuid: self::PROJECT);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: ['portfolio' => self::DIENST] + $old),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);

		$this->listener()->handle($event);

		self::assertSame(['dirk', 'eva'], $event->getModifiedData()['portfolioReaders']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: array_merge($old, ['portfolio' => self::DIENST], $event->getModifiedData())));
	}//end testMovingAProjectTakesTheNewPortfoliosManagers()

	/**
	 * Scenario "A client cannot write the derived readers list".
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testAClientSentReadersListIsReplaced(): void {
		$old = $this->stored(slug: 'project', uuid: self::PROJECT);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: ['portfolioReaders' => ['bob']] + $old),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);
		$this->listener()->handle($event);
		self::assertSame(['mia'], $event->getModifiedData()['portfolioReaders']);

		$create = new ObjectCreatingEvent($this->entity(slug: 'project', uuid: 'new', data: ['title' => 'X', 'status' => 'active', 'portfolioReaders' => ['bob']]));
		$this->listener()->handle($create);
		self::assertSame([], $create->getModifiedData()['portfolioReaders'], 'no portfolio, no readers');

		$untouched = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: ['title' => 'Renamed'] + $old),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);
		$this->listener()->handle($untouched);
		self::assertSame([], $untouched->getModifiedData(), 'an update that keeps the portfolio changes nothing');
	}//end testAClientSentReadersListIsReplaced()

	/**
	 * A manager change reaches every project of the portfolio and their objects; the right ends for a removed manager.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testAManagerChangeRewritesTheProjectsAndTheirObjects(): void {
		$old = $this->stored(slug: 'projectPortfolio', uuid: self::RUIMTE);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: ['managers' => ['noor']] + $old),
			$this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: $old)
		);

		$this->listener()->handle($event);

		self::assertSame(['noor'], $this->stored(slug: 'project', uuid: self::PROJECT)['portfolioReaders']);
		self::assertSame(['noor'], $this->stored(slug: 'task', uuid: self::TASK)['portfolioReaders'], 'mia no longer reads the task');
		self::assertSame(['bob', 'carol'], $this->stored(slug: 'task', uuid: self::TASK)['members'], 'members are untouched');
	}//end testAManagerChangeRewritesTheProjectsAndTheirObjects()

	/**
	 * Deleting a portfolio takes its projects out of it and ends the readers' right.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testDeletingAPortfolioReleasesItsProjects(): void {
		$this->listener()->handle(new ObjectDeletingEvent($this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: $this->stored(slug: 'projectPortfolio', uuid: self::RUIMTE))));

		$project = $this->stored(slug: 'project', uuid: self::PROJECT);
		self::assertNull($project['portfolio']);
		self::assertSame([], $project['portfolioReaders']);
		self::assertSame([], $this->stored(slug: 'task', uuid: self::TASK)['portfolioReaders']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $project));
	}//end testDeletingAPortfolioReleasesItsProjects()

	/**
	 * The system scope, in which the listener writes, is trusted.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testASystemWriteIsTrusted(): void {
		$old = $this->stored(slug: 'project', uuid: self::PROJECT);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: ['portfolioReaders' => ['noor']] + $old),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);
		SystemOperationContext::run(fn () => $this->listener()->handle($event));
		self::assertSame([], $event->getModifiedData());
	}//end testASystemWriteIsTrusted()

	/**
	 * Requirement "Portfolio managers read every project in their portfolio": a new task is stamped with the readers.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function testANewTaskCarriesThePortfolioReaders(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('bob');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$access = new ProjectMemberAccessListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$event = new ObjectCreatingEvent($this->entity(slug: 'task', uuid: 'new-task', data: ['title' => 'New', 'status' => 'todo', 'project' => self::PROJECT]));
		$access->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['mia'], $event->getModifiedData()['portfolioReaders']);
		self::assertSame(['bob', 'carol'], $event->getModifiedData()['members']);

		$mia = $this->createMock(originalClassName: IUser::class);
		$mia->method('getUID')->willReturn('mia');
		$miaSession = $this->createMock(originalClassName: IUserSession::class);
		$miaSession->method('getUser')->willReturn($mia);
		$readOnly = new ProjectMemberAccessListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $miaSession,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$byReader = new ObjectCreatingEvent($this->entity(slug: 'task', uuid: 'new-task-2', data: ['title' => 'New', 'status' => 'todo', 'project' => self::PROJECT]));
		$readOnly->handle($byReader);
		self::assertTrue($byReader->isPropagationStopped(), 'a portfolio manager reads but does not add tasks');
	}//end testANewTaskCarriesThePortfolioReaders()

	/**
	 * Task 3.3: a manager change and a deleted portfolio reach the project's finance lines too.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	public function testPortfolioChangesReachTheFinanceLines(): void {
		$this->objects->seed('financeLine', 'fl-1', ['project' => self::PROJECT, 'kind' => 'actual', 'amount' => 10, 'financeReaders' => ['carol', 'mia'], 'projectOwner' => 'carol', 'portfolio' => self::RUIMTE]);
		$old = $this->stored(slug: 'projectPortfolio', uuid: self::RUIMTE);
		$this->listener()->handle(
			new ObjectUpdatingEvent(
				$this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: ['managers' => ['noor']] + $old),
				$this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: $old)
			)
		);
		self::assertSame(['carol', 'noor'], $this->stored(slug: 'financeLine', uuid: 'fl-1')['financeReaders'], 'mia no longer sees the money');

		$this->listener()->handle(new ObjectDeletingEvent($this->entity(slug: 'projectPortfolio', uuid: self::RUIMTE, data: $old)));
		$line = $this->stored(slug: 'financeLine', uuid: 'fl-1');
		self::assertSame(['carol'], $line['financeReaders']);
		self::assertNull($line['portfolio'], 'a released project takes its lines out of the portfolio totals');
	}//end testPortfolioChangesReachTheFinanceLines()
}//end class
