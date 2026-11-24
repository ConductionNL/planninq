<?php

/**
 * Tests for the BackfillProjectTemplateFlag repair step.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Repair
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Repair\BackfillProjectTemplateFlag;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BackfillProjectTemplateFlagTest extends TestCase {
	use MembershipFixture;

	/**
	 * Seed four projects: no flag, a NULL flag, a template and a non-template.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-old', ['title' => 'Old', 'owner' => 'alice']);
		$this->objects->seed('project', 'proj-null', ['title' => 'Null', 'owner' => 'alice', 'isTemplate' => null]);
		$this->objects->seed('project', 'proj-tpl', ['title' => 'Template', 'owner' => 'alice', 'isTemplate' => true]);
		$this->objects->seed('project', 'proj-new', ['title' => 'New', 'owner' => 'alice', 'isTemplate' => false]);
	}//end setUp()

	/**
	 * The step under test.
	 *
	 * @param bool $openRegisterInstalled Whether OpenRegister is installed.
	 *
	 * @return BackfillProjectTemplateFlag
	 */
	private function step(bool $openRegisterInstalled = true): BackfillProjectTemplateFlag {
		return new BackfillProjectTemplateFlag(
			container: $this->container(),
			membership: $this->membershipService(openRegisterInstalled: $openRegisterInstalled),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end step()

	/**
	 * A project without a flag, or with NULL, gets false; a set flag is never touched.
	 *
	 * @return void
	 */
	public function testOnlyProjectsWithoutAFlagAreWrittenFalse(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertFalse(condition: $this->objects->rows['project']['proj-old']['isTemplate']);
		self::assertFalse(condition: $this->objects->rows['project']['proj-null']['isTemplate']);
		self::assertTrue(condition: $this->objects->rows['project']['proj-tpl']['isTemplate']);
		self::assertSame(expected: ['proj-null', 'proj-old'], actual: $this->savedUuids());
		self::assertSame(expected: 'Old', actual: $this->objects->rows['project']['proj-old']['title']);
		foreach ($this->objects->saves as $save) {
			self::assertSame(expected: 'project', actual: $save['schema']);
			self::assertFalse(condition: $save['_rbac']);
			self::assertTrue(condition: $save['silent']);
		}
	}//end testOnlyProjectsWithoutAFlagAreWrittenFalse()

	/**
	 * A second run writes nothing: the step is safe on every upgrade.
	 *
	 * @return void
	 */
	public function testASecondRunWritesNothing(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));
		$this->objects->saves = [];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testASecondRunWritesNothing()

	/**
	 * One refused save is logged and does not stop the others, and run() never throws.
	 *
	 * @return void
	 */
	public function testARefusedSaveDoesNotStopTheRest(): void {
		$this->objects->failingSaves = ['proj-old'];
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects(self::once())->method('info')->with('Marked 1 project(s) as not a template.');

		$this->step()->run($output);

		self::assertFalse(condition: $this->objects->rows['project']['proj-null']['isTemplate']);
		self::assertArrayNotHasKey(key: 'isTemplate', array: $this->objects->rows['project']['proj-old']);
	}//end testARefusedSaveDoesNotStopTheRest()

	/**
	 * Without OpenRegister the step warns and writes nothing.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterItSkips(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects(self::once())->method('warning');

		$this->step(openRegisterInstalled: false)->run($output);

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testWithoutOpenRegisterItSkips()

	/**
	 * @return array<int,string> The uuids written, sorted.
	 */
	private function savedUuids(): array {
		$uuids = array_map(static fn (array $save): string => (string)$save['uuid'], $this->objects->saves);
		sort($uuids);
		return $uuids;
	}//end savedUuids()
}//end class
