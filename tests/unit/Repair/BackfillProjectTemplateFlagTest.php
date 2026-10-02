<?php

/**
 * Tests for BackfillProjectTemplateFlag: a project stored before templates
 * existed gets `isTemplate: false`, so the dashboard figures still count it.
 *
 * @category Test
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Repair\BackfillProjectTemplateFlag;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
class BackfillProjectTemplateFlagTest extends TestCase {
	use MembershipFixture;

	public function testOnlyProjectsWithoutAValueAreMarkedAndARerunWritesNothing(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'old', ['title' => 'Old', 'status' => 'active']);
		$this->objects->seed('project', 'tpl', ['title' => 'Template', 'status' => 'active', 'isTemplate' => true]);
		$this->objects->seed('project', 'new', ['title' => 'New', 'status' => 'active', 'isTemplate' => false]);

		$step = new BackfillProjectTemplateFlag(
			membership: $this->membershipService(),
			container: $this->container(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		self::assertSame(1, $step->backfill());
		self::assertFalse($this->objects->rows['project']['old']['isTemplate']);
		self::assertTrue($this->objects->rows['project']['tpl']['isTemplate'], 'a template stays a template');
		self::assertSame(['old'], array_column($this->objects->saves, 'uuid'));
		self::assertFalse($this->objects->saves[0]['_rbac']);
		self::assertTrue($this->objects->saves[0]['silent']);
		self::assertSame(0, $step->backfill(), 'a second run writes nothing');
	}//end testOnlyProjectsWithoutAValueAreMarkedAndARerunWritesNothing()
}//end class
