<?php

/**
 * Tests for FinanceLineListener: a project's money stays with its owner, its
 * portfolio managers and the finance import.
 *
 * Built on the real OpenRegister event classes (stubs with the same API, see
 * OpenRegisterEventStubDriftTest), the real FinanceLineService,
 * ProjectMembershipService and TaskScopeResolver, over an in-memory
 * ObjectService. Every stamped payload is run through the validator
 * OpenRegister uses, against the real financeLine schema.
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
use OCA\Planninq\Listener\FinanceLineListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FinanceLineListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT_ID = '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e';

	private const PORTFOLIO_ID = '7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f';

	private const PROJECT = [
		'title'            => 'Omgevingsvisie',
		'status'           => 'active',
		'owner'            => 'carol',
		'members'          => ['bob'],
		'key'              => 'OMG',
		'portfolio'        => self::PORTFOLIO_ID,
		'portfolioReaders' => ['mia'],
	];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', self::PROJECT_ID, self::PROJECT);
	}//end setUp()

	/**
	 * @param string            $actor  The caller, '' for no session.
	 * @param array<int,string> $groups The caller's groups besides admin.
	 */
	private function listener(string $actor, bool $isAdmin = false, array $groups = []): FinanceLineListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));
		$groupManager->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => ($uid === $actor && in_array($group, $groups, true) === true));

		$membership = $this->membershipService();
		$logger     = $this->createMock(originalClassName: LoggerInterface::class);

		return new FinanceLineListener(
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			membership: $membership,
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groupManager,
			logger: $logger
		);
	}//end listener()

	private function line(string $uuid, array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: 'financeLine'));
	}//end line()

	private static function manual(array $extra = []): array {
		return array_merge(
			['project' => self::PROJECT_ID, 'category' => 'Materials', 'kind' => 'actual', 'amount' => 1500, 'date' => '2026-09-29', 'source' => 'manual'],
			$extra
		);
	}//end manual()

	private static function imported(array $extra = []): array {
		return array_merge(
			['projectKey' => 'OMG', 'kind' => 'actual', 'amount' => 1200, 'externalRef' => 'FIN-778', 'source' => 'import', 'category' => 'Hired staff'],
			$extra
		);
	}//end imported()

	/**
	 * Task 1.1: the owner's line carries who may read it (owner and portfolio
	 * managers, not the members), who may change it, and the portfolio.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function testTheOwnersLineIsStampedForTheOwnerAndThePortfolioManagers(): void {
		$event = new ObjectCreatingEvent($this->line(uuid: 'line-1', data: self::manual()));

		$this->listener(actor: 'carol')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		$stamped = $event->getModifiedData();
		self::assertSame(['carol', 'mia'], $stamped['financeReaders']);
		self::assertNotContains('bob', $stamped['financeReaders'], 'a plain member does not read money');
		self::assertSame('carol', $stamped['projectOwner']);
		self::assertSame(self::PORTFOLIO_ID, $stamped['portfolio']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'financeLine', payload: array_merge(self::manual(), $stamped)));
	}//end testTheOwnersLineIsStampedForTheOwnerAndThePortfolioManagers()

	/**
	 * Task 1.1: a member who is not the owner cannot add money to the project.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function testAMemberCannotAddALine(): void {
		$event = new ObjectCreatingEvent($this->line(uuid: 'line-1', data: self::manual()));

		$this->listener(actor: 'bob')->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(FinanceLineListener::ERROR_NOT_OWNER, $event->getErrors()['code']);
	}//end testAMemberCannotAddALine()

	/**
	 * Task 1.1: the owner cannot move a line into a project someone else owns.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function testTheOwnerCannotMoveALineIntoAnotherOwnersProject(): void {
		$this->objects->seed('project', 'other', ['title' => 'Elders', 'status' => 'active', 'owner' => 'dave']);
		$old   = self::manual();
		$event = new ObjectUpdatingEvent($this->line(uuid: 'line-1', data: self::manual(['project' => 'other'])), $this->line(uuid: 'line-1', data: $old));

		$this->listener(actor: 'carol')->handle($event);

		self::assertTrue($event->isPropagationStopped());
	}//end testTheOwnerCannotMoveALineIntoAnotherOwnersProject()

	/**
	 * Task 3.1: the finance import's line lands on the project whose key is its project number.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.1
	 */
	public function testAnImportedLineLandsOnTheProjectWithItsKey(): void {
		$event = new ObjectCreatingEvent($this->line(uuid: 'line-1', data: self::imported()));

		$this->listener(actor: 'integriq', groups: [FinanceLineService::IMPORT_GROUP])->handle($event);

		self::assertFalse($event->isPropagationStopped());
		$stamped = $event->getModifiedData();
		self::assertSame(self::PROJECT_ID, $stamped['project']);
		self::assertSame(['carol', 'mia'], $stamped['financeReaders']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'financeLine', payload: array_merge(self::imported(), $stamped)));
	}//end testAnImportedLineLandsOnTheProjectWithItsKey()

	/**
	 * Task 3.1: a line whose project number matches no project is kept, visible to admins and the import only.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.1
	 */
	public function testAnUnmatchedImportedLineIsKeptWithoutReaders(): void {
		$event = new ObjectCreatingEvent($this->line(uuid: 'line-1', data: self::imported(['projectKey' => 'OMGV'])));

		$this->listener(actor: 'integriq', groups: [FinanceLineService::IMPORT_GROUP])->handle($event);

		self::assertFalse($event->isPropagationStopped());
		$stamped = $event->getModifiedData();
		self::assertArrayNotHasKey('project', $stamped);
		self::assertSame([], $stamped['financeReaders'] ?? []);
		self::assertNull($stamped['projectOwner'] ?? null);
	}//end testAnUnmatchedImportedLineIsKeptWithoutReaders()

	/**
	 * Task 3.1: sending the same finance line again is refused, naming the line to update.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.1
	 */
	public function testASecondLineWithTheSameFinanceIdIsRefusedNamingTheFirst(): void {
		$this->objects->seed('financeLine', 'line-1', self::imported(['project' => self::PROJECT_ID]));
		$event = new ObjectCreatingEvent($this->line(uuid: 'line-2', data: self::imported(['amount' => 1250])));

		$this->listener(actor: 'integriq', groups: [FinanceLineService::IMPORT_GROUP])->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(FinanceLineListener::ERROR_DUPLICATE, $event->getErrors()['code']);
		self::assertSame('line-1', $event->getErrors()['conflictingObject']);

		$update = new ObjectUpdatingEvent(
			$this->line(uuid: 'line-1', data: self::imported(['project' => self::PROJECT_ID, 'amount' => 1250])),
			$this->line(uuid: 'line-1', data: self::imported(['project' => self::PROJECT_ID]))
		);
		$this->listener(actor: 'integriq', groups: [FinanceLineService::IMPORT_GROUP])->handle($update);
		self::assertFalse($update->isPropagationStopped(), 'updating the line itself is not a duplicate');
	}//end testASecondLineWithTheSameFinanceIdIsRefusedNamingTheFirst()

	/**
	 * Task 2.2: the owner cannot change or remove a line from the finance system, nor write one.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
	 */
	public function testTheOwnerCannotChangeOrRemoveAnImportedLine(): void {
		$stored = self::imported(['project' => self::PROJECT_ID]);
		$update = new ObjectUpdatingEvent($this->line(uuid: 'line-1', data: array_merge($stored, ['amount' => 1])), $this->line(uuid: 'line-1', data: $stored));
		$this->listener(actor: 'carol')->handle($update);
		self::assertTrue($update->isPropagationStopped());
		self::assertSame(FinanceLineListener::ERROR_IMPORTED, $update->getErrors()['code']);

		$relabel = new ObjectUpdatingEvent($this->line(uuid: 'line-1', data: array_merge($stored, ['source' => 'manual'])), $this->line(uuid: 'line-1', data: $stored));
		$this->listener(actor: 'carol')->handle($relabel);
		self::assertTrue($relabel->isPropagationStopped(), 'relabelling an imported line as manual is still an edit of it');

		$delete = new ObjectDeletingEvent($this->line(uuid: 'line-1', data: $stored));
		$this->listener(actor: 'carol')->handle($delete);
		self::assertTrue($delete->isPropagationStopped());

		$create = new ObjectCreatingEvent($this->line(uuid: 'line-2', data: self::imported(['externalRef' => 'FIN-9'])));
		$this->listener(actor: 'carol')->handle($create);
		self::assertTrue($create->isPropagationStopped(), 'only the import writes lines marked as imported');
	}//end testTheOwnerCannotChangeOrRemoveAnImportedLine()

	/**
	 * Task 3.2: an admin assigns an unmatched line to a project, and it takes the project's readers.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.2
	 */
	public function testAnAdminAssignsAnUnmatchedLine(): void {
		$stored = self::imported(['projectKey' => 'OMGV', 'financeReaders' => [], 'projectOwner' => null, 'portfolio' => null]);
		$event  = new ObjectUpdatingEvent($this->line(uuid: 'line-1', data: array_merge($stored, ['project' => self::PROJECT_ID])), $this->line(uuid: 'line-1', data: $stored));

		$this->listener(actor: 'admin', isAdmin: true)->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['carol', 'mia'], $event->getModifiedData()['financeReaders']);
	}//end testAnAdminAssignsAnUnmatchedLine()

	/**
	 * Task 3.3: a rewrite of only the kept copies passes, whoever changed the project.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	public function testARewriteOfOnlyTheKeptCopiesPasses(): void {
		$stored = self::imported(['project' => self::PROJECT_ID, 'financeReaders' => ['carol'], 'projectOwner' => 'carol', 'portfolio' => null]);
		$event  = new ObjectUpdatingEvent(
			$this->line(uuid: 'line-1', data: array_merge($stored, ['financeReaders' => ['carol', 'mia'], 'portfolio' => self::PORTFOLIO_ID])),
			$this->line(uuid: 'line-1', data: $stored)
		);

		$this->listener(actor: 'carol')->handle($event);

		self::assertFalse($event->isPropagationStopped());
	}//end testARewriteOfOnlyTheKeptCopiesPasses()
}//end class
