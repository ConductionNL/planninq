<?php

/**
 * Tests for ProjectReviewListener: a reviewed request records who reviewed it, and an approved one gets its board.
 *
 * Built on the real OpenRegister event classes (stubs with the same API) and
 * the real TaskScopeResolver; the stamped payload is validated against the
 * real project schema fragment.
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
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\ProjectReviewListener;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectReviewListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT = '11111111-1111-4111-8111-111111111111';

	private BoardColumnService&MockObject $columns;

	/**
	 * Whether the columns were created inside a system operation.
	 *
	 * @var array<int,bool>
	 */
	private array $columnCalls = [];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->columns = $this->createMock(originalClassName: BoardColumnService::class);
		$this->columns->method('createDefaultColumns')->willReturnCallback(function (): array {
			$this->columnCalls[] = SystemOperationContext::isActive();
			return [];
		});
	}//end setUp()

	private function listener(): ProjectReviewListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('olga');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-09-29T10:00:00+00:00'));

		return new ProjectReviewListener(
			scopeResolver: $this->scopeResolver(),
			columns: $this->columns,
			userSession: $session,
			timeFactory: $time,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	private function entity(array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: self::PROJECT, data: $data, register: '1', schema: $this->schemaId(slug: 'project'));
	}//end entity()

	private function request(): array {
		return ['title' => 'Portaal', 'status' => 'requested', 'owner' => 'rik', 'members' => ['rik'], 'requestReason' => 'Residents ask for it'];
	}//end request()

	/**
	 * Scenario "A reviewer rejects a request with a reason": the review is stamped in the same save.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	public function testAReviewRecordsWhoAndWhen(): void {
		$old   = $this->request();
		$event = new ObjectUpdatingEvent(
			$this->entity(data: ['status' => 'rejected', 'reviewNote' => 'Fits in the existing portal project', 'reviewedBy' => 'rik'] + $old),
			$this->entity(data: $old)
		);
		$this->listener()->handle($event);

		self::assertSame('olga', $event->getModifiedData()['reviewedBy']);
		self::assertSame('2026-09-29T10:00:00+00:00', $event->getModifiedData()['reviewedAt']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: array_merge($old, ['status' => 'rejected', 'reviewNote' => 'Fits in the existing portal project'], $event->getModifiedData())));
	}//end testAReviewRecordsWhoAndWhen()

	/**
	 * A save that does not review a request changes nothing.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	public function testOtherSavesAreLeftAlone(): void {
		$old   = ['status' => 'active'] + $this->request();
		$event = new ObjectUpdatingEvent($this->entity(data: ['status' => 'archived'] + $old), $this->entity(data: $old));
		$this->listener()->handle($event);
		self::assertSame([], $event->getModifiedData());

		$this->listener()->handle(new ObjectUpdatedEvent($this->entity(data: ['status' => 'archived'] + $old), $this->entity(data: $old)));
		self::assertSame([], $this->columnCalls);
	}//end testOtherSavesAreLeftAlone()

	/**
	 * Scenario "A reviewer approves a request": the approved project gets the default columns, written as the system.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	public function testAnApprovedRequestGetsItsBoard(): void {
		$old = $this->request();
		$this->listener()->handle(new ObjectUpdatedEvent($this->entity(data: ['status' => 'active'] + $old), $this->entity(data: $old)));
		self::assertSame([true], $this->columnCalls, 'once, inside a system operation, so the column guard lets a reviewer through');

		$this->listener()->handle(new ObjectUpdatedEvent($this->entity(data: ['status' => 'rejected'] + $old), $this->entity(data: $old)));
		self::assertSame([true], $this->columnCalls, 'a rejected request gets no board');
	}//end testAnApprovedRequestGetsItsBoard()
}//end class
