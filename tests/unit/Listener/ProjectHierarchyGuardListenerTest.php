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
use OCA\Planninq\Service\ProjectRulesService;
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
			rules: new ProjectRulesService(membership: $membership),
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

	/**
	 * A project write that sets a parent, as the event the listener receives.
	 *
	 * @param string              $uuid   The project.
	 * @param array<string,mixed> $stored The stored project, [] for a create.
	 * @param string|null         $parent The new parent.
	 */
	private function parentWrite(string $uuid, array $stored, ?string $parent): ObjectCreatingEvent|ObjectUpdatingEvent {
		$data = array_merge($stored === [] ? ['title' => 'Nieuw', 'status' => 'active', 'owner' => 'carol'] : $stored, ['parent' => $parent]);
		if ($stored === []) {
			return new ObjectCreatingEvent($this->entity(slug: 'project', uuid: $uuid, data: $data));
		}

		return new ObjectUpdatingEvent($this->entity(slug: 'project', uuid: $uuid, data: $data), $this->entity(slug: 'project', uuid: $uuid, data: $stored));
	}//end parentWrite()

	/**
	 * Task 2.1 and scenario "A cycle is refused": a project cannot sit under itself or one of its own subprojects.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
	 */
	public function testACycleIsRefused(): void {
		$this->objects->seed('project', 'prog', ['title' => 'Programma Wonen', 'status' => 'active', 'owner' => 'carol']);
		$this->objects->seed('project', 'child', ['title' => 'Woningbouw', 'status' => 'active', 'owner' => 'carol', 'parent' => 'prog']);

		$self = $this->parentWrite(uuid: 'prog', stored: $this->stored(slug: 'project', uuid: 'prog'), parent: 'prog');
		$this->listener()->handle($self);
		self::assertTrue($self->isPropagationStopped(), 'a project under itself');
		self::assertSame('A project cannot sit under one of its own subprojects.', $self->getErrors()['message']);

		$loop = $this->parentWrite(uuid: 'prog', stored: $this->stored(slug: 'project', uuid: 'prog'), parent: 'child');
		$this->listener()->handle($loop);
		self::assertTrue($loop->isPropagationStopped(), 'a project under its own subproject');
		self::assertSame(ProjectHierarchyGuardListener::ERROR_CYCLE, $loop->getErrors()['code']);
	}//end testACycleIsRefused()

	/**
	 * Task 2.1: projects nest three levels deep at most, counting the subtree that moves along.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
	 */
	public function testAFourthLevelIsRefused(): void {
		$this->objects->seed('project', 'l1', ['title' => 'Programma', 'status' => 'active', 'owner' => 'carol']);
		$this->objects->seed('project', 'l2', ['title' => 'Project', 'status' => 'active', 'owner' => 'carol', 'parent' => 'l1']);
		$this->objects->seed('project', 'l3', ['title' => 'Deelproject', 'status' => 'active', 'owner' => 'carol', 'parent' => 'l2']);
		$this->objects->seed('project', 'loose', ['title' => 'Los', 'status' => 'active', 'owner' => 'carol']);
		$this->objects->seed('project', 'loose-child', ['title' => 'Los kind', 'status' => 'active', 'owner' => 'carol', 'parent' => 'loose']);

		$fourth = $this->parentWrite(uuid: 'new', stored: [], parent: 'l3');
		$this->listener()->handle($fourth);
		self::assertTrue($fourth->isPropagationStopped(), 'a new project under a third level');
		self::assertSame(ProjectHierarchyGuardListener::ERROR_DEPTH, $fourth->getErrors()['code']);

		$subtree = $this->parentWrite(uuid: 'loose', stored: $this->stored(slug: 'project', uuid: 'loose'), parent: 'l2');
		$this->listener()->handle($subtree);
		self::assertTrue($subtree->isPropagationStopped(), 'a project whose own child would land on a fourth level');

		$fine = $this->parentWrite(uuid: 'loose', stored: $this->stored(slug: 'project', uuid: 'loose'), parent: 'l1');
		$this->listener()->handle($fine);
		self::assertFalse($fine->isPropagationStopped(), 'three levels with the child that moves along');
		$payload = array_merge($this->stored(slug: 'project', uuid: 'loose'), ['parent' => self::PROJECT], $fine->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $payload));

		$unchanged = $this->parentWrite(uuid: 'l3', stored: $this->stored(slug: 'project', uuid: 'l3'), parent: 'l2');
		$this->listener()->handle($unchanged);
		self::assertFalse($unchanged->isPropagationStopped(), 'an unchanged parent is not checked again');

		$cleared = $this->parentWrite(uuid: 'l3', stored: $this->stored(slug: 'project', uuid: 'l3'), parent: null);
		$this->listener()->handle($cleared);
		self::assertFalse($cleared->isPropagationStopped(), 'taking a project out of its parent');
	}//end testAFourthLevelIsRefused()

	/**
	 * The admin's project fields for the custom field tests.
	 */
	private function seedFields(): void {
		$fields = [
			['key' => 'beleidsveld', 'label' => 'Beleidsveld', 'type' => 'choice', 'options' => ['Wonen', 'Mobiliteit', 'Economie'], 'required' => true],
			['key' => 'contractwaarde', 'label' => 'Contractwaarde', 'type' => 'number'],
			['key' => 'startbesluit', 'label' => 'Startbesluit', 'type' => 'date'],
			['key' => 'wethouder', 'label' => 'Wethouder', 'type' => 'person'],
			['key' => 'subsidie', 'label' => 'Subsidie', 'type' => 'boolean'],
			['key' => 'dossier', 'label' => 'Dossier', 'type' => 'text'],
		];
		foreach ($fields as $i => $field) {
			$this->objects->seed('projectField', 'field-' . $i, $field + ['appliesTo' => 'project', 'order' => $i]);
		}
	}//end seedFields()

	/**
	 * A project update that sends custom field values.
	 *
	 * @param array<string,mixed> $values The values.
	 */
	private function fieldWrite(array $values): ObjectUpdatingEvent {
		$old = $this->stored(slug: 'project', uuid: self::PROJECT);

		return new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: array_merge($old, ['customFields' => $values])),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);
	}//end fieldWrite()

	/**
	 * Task 3.2: values of the right type pass, and the payload is one the project schema accepts.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	public function testCustomFieldsOfTheRightTypePass(): void {
		$this->seedFields();
		$values = ['beleidsveld' => 'Wonen', 'contractwaarde' => 125000.5, 'startbesluit' => '2026-10-01', 'wethouder' => 'mia', 'subsidie' => false, 'dossier' => 'Z-2026-12'];
		$event  = $this->fieldWrite(values: $values);

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		$payload = array_merge($this->stored(slug: 'project', uuid: self::PROJECT), ['customFields' => $values], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $payload));
	}//end testCustomFieldsOfTheRightTypePass()

	/**
	 * Task 3.2 and scenario "A wrong value type is refused by the server": each type refuses a wrong value, naming the field.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	public function testAWrongValueTypeIsRefusedNamingTheField(): void {
		$this->seedFields();
		$wrong = [
			'Contractwaarde' => ['contractwaarde' => 'veel'],
			'Startbesluit'   => ['startbesluit' => '1 oktober'],
			'Beleidsveld'    => ['beleidsveld' => 'Cultuur'],
			'Wethouder'      => ['wethouder' => ['mia']],
			'Subsidie'       => ['subsidie' => 'ja'],
			'Dossier'        => ['dossier' => 12],
		];
		foreach ($wrong as $label => $values) {
			$event = $this->fieldWrite(values: array_merge(['beleidsveld' => 'Wonen'], $values));
			$this->listener()->handle($event);
			self::assertTrue($event->isPropagationStopped(), $label . ' refused');
			self::assertSame(ProjectHierarchyGuardListener::ERROR_FIELD, $event->getErrors()['code']);
			self::assertStringContainsString($label, $event->getErrors()['message']);
		}
	}//end testAWrongValueTypeIsRefusedNamingTheField()

	/**
	 * Task 3.2: an empty required field and an unknown key are refused; a write without custom fields is not checked.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	public function testARequiredFieldAndAnUnknownKeyAreRefused(): void {
		$this->seedFields();

		$empty = $this->fieldWrite(values: ['beleidsveld' => '', 'dossier' => 'Z-1']);
		$this->listener()->handle($empty);
		self::assertTrue($empty->isPropagationStopped());
		self::assertSame('Beleidsveld is required.', $empty->getErrors()['message']);

		$unknown = $this->fieldWrite(values: ['beleidsveld' => 'Wonen', 'kleur' => 'rood']);
		$this->listener()->handle($unknown);
		self::assertTrue($unknown->isPropagationStopped());
		self::assertStringContainsString('kleur', $unknown->getErrors()['message']);

		$this->objects->seed('project', self::PROJECT, array_merge($this->stored(slug: 'project', uuid: self::PROJECT), ['customFields' => ['beleidsveld' => 'Wonen', 'verwijderd' => 'x']]));
		$kept = $this->fieldWrite(values: ['beleidsveld' => 'Mobiliteit', 'verwijderd' => 'x']);
		$this->listener()->handle($kept);
		self::assertFalse($kept->isPropagationStopped(), 'a stored value of a removed field may stay as it is');

		$old     = $this->stored(slug: 'project', uuid: self::PROJECT);
		$renamed = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::PROJECT, data: ['title' => 'Renamed'] + $old),
			$this->entity(slug: 'project', uuid: self::PROJECT, data: $old)
		);
		$this->listener()->handle($renamed);
		self::assertFalse($renamed->isPropagationStopped(), 'a write that leaves the custom fields alone is not held to a new required field');
	}//end testARequiredFieldAndAnUnknownKeyAreRefused()

	/**
	 * A wiki page write that sets a parent, as the event the listener receives.
	 *
	 * @param string      $uuid    The page.
	 * @param string      $project The page's project.
	 * @param string|null $parent  The new parent.
	 * @param bool        $stored  Whether the page exists already.
	 */
	private function wikiWrite(string $uuid, string $project, ?string $parent, bool $stored): ObjectCreatingEvent|ObjectUpdatingEvent {
		$data = ['title' => 'Pagina', 'project' => $project, 'parent' => $parent];
		if ($stored === false) {
			return new ObjectCreatingEvent($this->entity(slug: 'wikiPage', uuid: $uuid, data: $data));
		}

		return new ObjectUpdatingEvent($this->entity(slug: 'wikiPage', uuid: $uuid, data: $data), $this->entity(slug: 'wikiPage', uuid: $uuid, data: ['title' => 'Pagina', 'project' => $project]));
	}//end wikiWrite()

	/**
	 * Task 1.2: a page cannot sit under itself, under its own subpage or under another project's page.
	 *
	 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
	 */
	public function testAWikiPageParentIsGuarded(): void {
		$this->objects->seed('wikiPage', 'root', ['title' => 'Root', 'project' => self::PROJECT]);
		$this->objects->seed('wikiPage', 'sub', ['title' => 'Sub', 'project' => self::PROJECT, 'parent' => 'root']);
		$this->objects->seed('wikiPage', 'foreign', ['title' => 'Elders', 'project' => 'other']);

		$self = $this->wikiWrite(uuid: 'root', project: self::PROJECT, parent: 'root', stored: true);
		$this->listener()->handle($self);
		self::assertTrue($self->isPropagationStopped(), 'a page under itself');

		$loop = $this->wikiWrite(uuid: 'root', project: self::PROJECT, parent: 'sub', stored: true);
		$this->listener()->handle($loop);
		self::assertTrue($loop->isPropagationStopped(), 'a page under its own subpage');
		self::assertSame(ProjectHierarchyGuardListener::ERROR_WIKI_PARENT, $loop->getErrors()['code']);

		$other = $this->wikiWrite(uuid: 'new', project: self::PROJECT, parent: 'foreign', stored: false);
		$this->listener()->handle($other);
		self::assertTrue($other->isPropagationStopped(), 'a page under another project\'s page');

		$fine = $this->wikiWrite(uuid: 'new', project: self::PROJECT, parent: 'sub', stored: false);
		$this->listener()->handle($fine);
		self::assertFalse($fine->isPropagationStopped(), 'a page under a subpage of its own project');
	}//end testAWikiPageParentIsGuarded()
}//end class
