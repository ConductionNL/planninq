<?php

/**
 * Tests for BoardColumnService: default columns and the one-off column placement.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BoardColumnServiceTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT = '11111111-1111-4111-8111-111111111111';

	protected function setUp(): void {
		parent::setUp();
		// Assigns a UUID to a create, as OpenRegister does.
		$this->objects = new class extends InMemoryObjectService {
			private int $next = 0;
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
				if ($uuid === null) {
					$this->next++;
					$uuid = sprintf('00000000-0000-4000-8000-%012d', $this->next);
				}
				return parent::saveObject($object, $extend, $register, $schema, $uuid, $_rbac, $_multitenancy, $silent, $_validation);
			}
		};
		$this->objects->seed('project', self::PROJECT, ['title' => 'Vergunningen', 'owner' => 'carol', 'members' => ['carol']]);
	}//end setUp()

	private function service(string $defaultColumns = '["Intake","Work","Closed"]'): BoardColumnService {
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('getAdminSettings')->willReturn(['default_columns' => $defaultColumns]);

		return new BoardColumnService(
			container: $this->container(),
			membership: $this->membershipService(),
			settings: $settings,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			appManager: $this->openRegisterInstalled()
		);
	}//end service()

	/**
	 * Scenario "The admin changed the defaults": Intake, Work and Closed, with Closed the done column.
	 */
	public function testAdminDefaultsBecomeColumnsWithTheLastAsDone(): void {
		$saved = $this->service()->createDefaultColumns(projectId: self::PROJECT);

		self::assertSame(['Intake', 'Work', 'Closed'], array_column($saved, 'title'));
		self::assertSame(['active', 'active', 'done'], array_column($saved, 'type'));
		self::assertSame(['open', 'in_progress', 'done'], array_column($saved, 'status'));
		self::assertSame([0, 1, 2], array_column($saved, 'order'));
		self::assertCount(3, $this->objects->rows['column']);
	}//end testAdminDefaultsBecomeColumnsWithTheLastAsDone()

	public function testEmptySettingFallsBackToFourColumns(): void {
		$saved = $this->service(defaultColumns: '[]')->createDefaultColumns(projectId: self::PROJECT);

		self::assertSame(['To do', 'In progress', 'Review', 'Done'], array_column($saved, 'title'));
		self::assertSame('done', $saved[3]['type']);
	}//end testEmptySettingFallsBackToFourColumns()

	/**
	 * The exact payload written into OpenRegister validates against the real column schema.
	 */
	public function testEveryColumnPayloadValidatesAgainstTheRegisterSchema(): void {
		$this->service()->createDefaultColumns(projectId: self::PROJECT);

		foreach ($this->objects->saves as $save) {
			self::assertSame('column', $save['schema']);
			self::assertSame([], $this->registerSchemaErrors(slug: 'column', payload: $save['object']));
		}
	}//end testEveryColumnPayloadValidatesAgainstTheRegisterSchema()

	/**
	 * Control: the validator refuses what the register refuses, so a green above means something.
	 */
	public function testTheValidatorRefusesAColumnTheRegisterRefuses(): void {
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'column', payload: ['title' => 'x', 'project' => self::PROJECT, 'order' => 0, 'type' => 'weird']));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'column', payload: ['title' => 'x', 'project' => self::PROJECT, 'order' => 0, 'status' => 'finished']));
	}//end testTheValidatorRefusesAColumnTheRegisterRefuses()

	/**
	 * Scenario "An upgraded board": every status lands in the matching column, and a rerun writes nothing.
	 */
	public function testAssignColumnsPlacesTasksByStatusAndIsIdempotent(): void {
		$statuses = ['open', 'in_progress', 'blocked', 'done', 'cancelled'];
		foreach ($statuses as $i => $status) {
			$this->objects->seed('task', 'task-' . $status, ['title' => 'T' . $i, 'status' => $status, 'project' => self::PROJECT]);
		}

		$first = $this->service()->assignColumns(projectId: self::PROJECT);

		self::assertSame(['created' => 3, 'assigned' => 4], $first);
		$byTitle = [];
		foreach ($this->objects->rows['column'] as $id => $column) {
			$byTitle[$column['title']] = $id;
		}

		$tasks = $this->objects->rows['task'];
		self::assertSame($byTitle['Intake'], $tasks['task-open']['column']);
		self::assertSame($byTitle['Work'], $tasks['task-in_progress']['column']);
		self::assertSame($byTitle['Intake'], $tasks['task-blocked']['column'], 'no blocked column: the first active one');
		self::assertSame($byTitle['Closed'], $tasks['task-done']['column']);
		self::assertArrayNotHasKey('column', $tasks['task-cancelled'], 'a cancelled task stays in the backlog');

		foreach ($this->objects->saves as $save) {
			if ($save['schema'] === 'task') {
				self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $save['object']));
			}
		}

		$savesBefore = count($this->objects->saves);
		$second      = $this->service()->assignColumns(projectId: self::PROJECT);
		self::assertSame(['created' => 0, 'assigned' => 0], $second);
		self::assertCount($savesBefore, $this->objects->saves, 'the second run writes nothing');
	}//end testAssignColumnsPlacesTasksByStatusAndIsIdempotent()

	public function testAssignAllWalksEveryProject(): void {
		$this->objects->seed('task', 'task-1', ['title' => 'A', 'status' => 'open', 'project' => self::PROJECT]);

		$totals = $this->service()->assignAll();

		self::assertSame(['projects' => 1, 'created' => 3, 'assigned' => 1], $totals);
	}//end testAssignAllWalksEveryProject()
	private function openRegisterInstalled(bool $installed = true): IAppManager {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturnCallback(static fn (string $app): bool => ($app === 'openregister' && $installed === true));
		return $appManager;
	}//end openRegisterInstalled()
}//end class
