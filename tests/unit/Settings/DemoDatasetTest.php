<?php

/**
 * Holds the curated example data to planninq's own rules.
 *
 * The import path does not run planninq's services, so these tests do: every
 * reference resolves inside the dataset, every dependency joins two different
 * tasks of one project and closes no cycle (the real DependencyGraph), and every
 * object, fitted to an admin and a load day by the real DemoDataService,
 * passes its schema in lib/Settings/planninq_register.json under the validator
 * OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/platform-demo-data/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

use DateTimeImmutable;
use OCA\Planninq\Service\DemoDataService;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The example dataset is consistent and valid.
 */
class DemoDatasetTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The decoded mock descriptor.
	 *
	 * @var array<string,mixed>
	 */
	private array $mock;

	/**
	 * The decoded real register.
	 *
	 * @var array<string,mixed>
	 */
	private array $register;

	/**
	 * Read both descriptors.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$dir            = __DIR__ . '/../../../lib/Settings/';
		$this->mock     = json_decode((string)file_get_contents($dir . 'planninq_mock_register.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->register = json_decode((string)file_get_contents($dir . 'planninq_register.json'), true, 512, JSON_THROW_ON_ERROR);
	}//end setUp()

	/**
	 * The example objects.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function objects(): array {
		return $this->mock['components']['objects'];
	}//end objects()

	/**
	 * The example objects of one schema, by id.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function bySchema(string $schema): array {
		$out = [];
		foreach ($this->objects() as $object) {
			if ($object['@self']['schema'] === $schema) {
				$out[$object['@self']['id']] = $object;
			}
		}

		return $out;
	}//end bySchema()

	/**
	 * Every object has a fixed id and a demo- slug, and ids are unique.
	 *
	 * @return void
	 */
	public function testEveryObjectHasAFixedIdAndADemoSlug(): void {
		$ids = [];
		foreach ($this->objects() as $object) {
			self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', ($object['@self']['id'] ?? ''));
			self::assertStringStartsWith('demo-', ($object['@self']['slug'] ?? ''));
			$ids[] = $object['@self']['id'];
		}

		self::assertSame(count($ids), count(array_unique($ids)));
	}//end testEveryObjectHasAFixedIdAndADemoSlug()

	/**
	 * Every uuid value points at an object the dataset contains, of the schema the property names.
	 *
	 * @return void
	 */
	public function testEveryReferenceResolves(): void {
		$schemaOf = [];
		foreach ($this->objects() as $object) {
			$schemaOf[$object['@self']['id']] = $object['@self']['schema'];
		}

		$checked = 0;
		foreach ($this->objects() as $object) {
			$slug       = $object['@self']['schema'];
			$properties = $this->register['components']['schemas'][$slug]['properties'];
			foreach ($object as $name => $value) {
				if ($name === '@self' || isset($properties[$name]) === false) {
					continue;
				}

				$property = $properties[$name];
				$isUuid   = (($property['format'] ?? '') === 'uuid');
				$isList   = (($property['items']['format'] ?? '') === 'uuid');
				if ($isUuid === false && $isList === false) {
					continue;
				}

				foreach ((array)$value as $id) {
					if ($id === null) {
						continue;
					}

					$where = $object['@self']['slug'] . '.' . $name;
					self::assertArrayHasKey($id, $schemaOf, $where . ' points at ' . $id . ', which the example data does not contain');
					if (isset($property['$ref']) === true) {
						self::assertSame($property['$ref'], $schemaOf[$id], $where . ' points at the wrong kind of object');
					}

					$checked++;
				}
			}//end foreach
		}//end foreach

		self::assertGreaterThan(50, $checked);
	}//end testEveryReferenceResolves()

	/**
	 * Every project's custom field values use a field the example data defines.
	 *
	 * @return void
	 */
	public function testCustomFieldValuesUseDefinedFields(): void {
		$keys = array_column($this->bySchema('projectField'), 'key');
		foreach ($this->bySchema('project') as $project) {
			foreach (array_keys(($project['customFields'] ?? [])) as $key) {
				self::assertContains($key, $keys);
			}
		}
	}//end testCustomFieldValuesUseDefinedFields()

	/**
	 * Every dependency joins two different tasks of one project.
	 *
	 * @return void
	 */
	public function testDependenciesJoinDifferentTasksOfOneProject(): void {
		$tasks = $this->bySchema('task');
		$edges = $this->bySchema('dependency');
		self::assertGreaterThanOrEqual(3, count($edges));
		foreach ($edges as $edge) {
			self::assertNotSame($edge['blocker'], $edge['blocked']);
			self::assertSame($tasks[$edge['blocker']]['project'], $tasks[$edge['blocked']]['project']);
		}
	}//end testDependenciesJoinDifferentTasksOfOneProject()

	/**
	 * Adding the edges one by one never closes a cycle, by the rule DependencyService applies.
	 *
	 * @return void
	 */
	public function testDependenciesFormNoCycle(): void {
		$graph = new DependencyGraph();
		$added = [];
		foreach ($this->bySchema('dependency') as $edge) {
			$pair = ['blocker' => $edge['blocker'], 'blocked' => $edge['blocked'], 'type' => $edge['type']];
			self::assertNull($graph->cyclePath(edges: $added, blocker: $pair['blocker'], blocked: $pair['blocked']));
			$added[] = $pair;
		}
	}//end testDependenciesFormNoCycle()

	/**
	 * Every schema of the register carries at least three example objects (ADR-111 rule 1).
	 *
	 * @return void
	 */
	public function testAtLeastThreeObjectsPerSchema(): void {
		foreach (array_keys($this->register['components']['schemas']) as $schema) {
			self::assertGreaterThanOrEqual(3, count($this->bySchema($schema)), $schema);
		}
	}//end testAtLeastThreeObjectsPerSchema()

	/**
	 * A task sits in a column of its own project whose status matches the task's.
	 *
	 * @return void
	 */
	public function testTasksSitInAColumnOfTheirProjectWithTheirStatus(): void {
		$columns = $this->bySchema('column');
		foreach ($this->bySchema('task') as $task) {
			$column = $columns[$task['column']];
			self::assertSame($task['project'], $column['project']);
			self::assertSame($column['status'], $task['status'], $task['@self']['slug']);
		}
	}//end testTasksSitInAColumnOfTheirProjectWithTheirStatus()

	/**
	 * The descriptor names its anchor day and uses the placeholder, never an invented account.
	 *
	 * @return void
	 */
	public function testDescriptorNamesItsAnchorAndNoAccount(): void {
		self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', ($this->mock['x-openregister']['anchorDate'] ?? ''));
		foreach ($this->bySchema('project') as $project) {
			self::assertSame(DemoDataService::OPERATOR_PLACEHOLDER, $project['owner']);
			self::assertSame([DemoDataService::OPERATOR_PLACEHOLDER], $project['members']);
		}

		foreach ($this->bySchema('task') as $task) {
			self::assertContains(($task['assignedTo'] ?? DemoDataService::OPERATOR_PLACEHOLDER), [DemoDataService::OPERATOR_PLACEHOLDER]);
		}
	}//end testDescriptorNamesItsAnchorAndNoAccount()

	/**
	 * Every example object, fitted to an admin and a load day, passes its schema.
	 *
	 * @return void
	 */
	public function testEveryFittedObjectPassesItsSchema(): void {
		$service = new DemoDataService(
			$this->createMock(IAppManager::class),
			$this->createMock(ContainerInterface::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IUserSession::class),
			$this->createMock(ITimeFactory::class),
		);
		$fitted  = $service->prepare(data: $this->mock, operator: 'admin', today: new DateTimeImmutable('2026-10-05'));

		foreach ($fitted['components']['objects'] as $object) {
			$payload = $object;
			unset($payload['@self']);
			$errors = $this->registerSchemaErrors(slug: $object['@self']['schema'], payload: $payload);
			self::assertSame([], $errors, $object['@self']['slug']);
		}
	}//end testEveryFittedObjectPassesItsSchema()
}//end class
