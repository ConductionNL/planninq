<?php

/**
 * SystemObjectReader tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Service\SystemObjectReader;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * One planninq object read as the system, and the code-link service that relies on it.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
class SystemObjectReaderTest extends TestCase {
	use MembershipFixture;

	private const TASK = '00000000-0000-4000-8000-000000000021';

	private const PROJECT = '00000000-0000-4000-8000-000000000022';

	/**
	 * Seed one project with one task.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', self::PROJECT, ['title' => 'Portal', 'owner' => 'olga', 'members' => ['alice']]);
		$this->objects->seed('task', self::TASK, ['title' => 'Fix login', 'project' => self::PROJECT]);
	}//end setUp()

	/**
	 * The reader under test, on the in-memory register.
	 *
	 * @return SystemObjectReader
	 */
	private function reader(): SystemObjectReader {
		return new SystemObjectReader(container: $this->container(), logger: new NullLogger());
	}//end reader()

	/**
	 * A task found by its uuid answers that uuid and its data, read with RBAC and multitenancy off.
	 *
	 * @return void
	 */
	public function testRowAnswersTheUuidAndDataReadAsTheSystem(): void {
		$row = $this->reader()->row(schema: 'task', id: self::TASK);

		$this->assertSame(expected: self::TASK, actual: $row['id']);
		$this->assertSame(expected: 'Fix login', actual: $row['data']['title']);

		$find = end($this->objects->finds);
		$this->assertSame(expected: 'planninq', actual: $find['register']);
		$this->assertSame(expected: 'task', actual: $find['schema']);
		$this->assertFalse(condition: $find['_rbac']);
		$this->assertFalse(condition: $find['_multitenancy']);
	}//end testRowAnswersTheUuidAndDataReadAsTheSystem()

	/**
	 * An empty identifier resolves nothing and asks OpenRegister nothing.
	 *
	 * @return void
	 */
	public function testAnEmptyIdentifierAsksNothing(): void {
		$this->assertNull(actual: $this->reader()->row(schema: 'task', id: ''));
		$this->assertNull(actual: $this->reader()->find(schema: 'task', id: ''));
		$this->assertSame(expected: [], actual: $this->objects->finds);
	}//end testAnEmptyIdentifierAsksNothing()

	/**
	 * An unknown identifier answers null instead of throwing.
	 *
	 * @return void
	 */
	public function testAMissingObjectAnswersNull(): void {
		$this->assertNull(actual: $this->reader()->row(schema: 'task', id: '00000000-0000-4000-8000-000000000099'));
	}//end testAMissingObjectAnswersNull()

	/**
	 * A project uuid asked for as a task does not resolve, though OpenRegister finds it in another table.
	 *
	 * @return void
	 */
	public function testAnObjectOfAnotherSchemaDoesNotResolve(): void {
		$this->assertNull(actual: $this->reader()->find(schema: 'task', id: self::PROJECT));
		$this->assertNotNull(actual: $this->reader()->find(schema: 'project', id: self::PROJECT));
	}//end testAnObjectOfAnotherSchemaDoesNotResolve()

	/**
	 * Without OpenRegister the read answers null.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheReadAnswersNull(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new \RuntimeException('not installed'));

		$reader = new SystemObjectReader(container: $container, logger: new NullLogger());

		$this->assertNull(actual: $reader->row(schema: 'task', id: self::TASK));
	}//end testWithoutOpenRegisterTheReadAnswersNull()

	/**
	 * A code link naming its task by uuid resolves that task with its project.
	 *
	 * @return void
	 */
	public function testACodeLinkResolvesItsTaskAndProject(): void {
		$task = $this->forgeLinkService()->linkedTask(data: ['task' => ' ' . self::TASK . ' ']);

		$this->assertSame(expected: self::TASK, actual: $task['id']);
		$this->assertSame(expected: self::PROJECT, actual: $task['project']);
		$this->assertSame(expected: 'Fix login', actual: $task['data']['title']);
	}//end testACodeLinkResolvesItsTaskAndProject()

	/**
	 * A code link naming a task that does not exist resolves nothing.
	 *
	 * @return void
	 */
	public function testACodeLinkToAMissingTaskResolvesNothing(): void {
		$this->assertNull(actual: $this->forgeLinkService()->linkedTask(data: ['task' => '00000000-0000-4000-8000-000000000099']));
	}//end testACodeLinkToAMissingTaskResolvesNothing()

	/**
	 * The membership reads keep working through the reader, and refuse without OpenRegister.
	 *
	 * @return void
	 */
	public function testMembershipReadsGoThroughTheReader(): void {
		$this->assertSame(expected: 'Fix login', actual: $this->membershipService()->objectData(schema: 'task', id: self::TASK)['title']);
		$this->assertSame(expected: 'olga', actual: $this->membershipService()->ownerOfProject(projectId: self::PROJECT));
		$this->assertNull(actual: $this->membershipService(openRegisterInstalled: false)->objectData(schema: 'task', id: self::TASK));
	}//end testMembershipReadsGoThroughTheReader()
}//end class
