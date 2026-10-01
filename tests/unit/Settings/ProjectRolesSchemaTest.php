<?php

/**
 * Project roles in the register (projects-members-and-roles section 2):
 * the role lists on `project`, the rules per role, the owner fields only the
 * owner or the owning group may rewrite, and the copies of the lists on every
 * project-scoped schema that OpenRegister evaluates instead of a lookup.
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
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;

/**
 * Guards the role lists and their rules.
 */
class ProjectRolesSchemaTest extends TestCase {
	use RegisterSchemaValidation;

	private const USER = '$userId';

	private const GROUPS = '$user.groups';

	/**
	 * The shipped register's schemas.
	 *
	 * @return array<string,mixed>
	 */
	private function schemas(): array {
		$register = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
		return $register['components']['schemas'];
	}//end schemas()

	/**
	 * The list fields a rule list grants through, as "field:operand".
	 *
	 * @param array<int,mixed> $rules The rules of one action.
	 *
	 * @return array<int,string>
	 */
	private static function grants(array $rules): array {
		$out = [];
		foreach ($rules as $rule) {
			foreach ((array)($rule['match'] ?? []) as $field => $condition) {
				if (is_array($condition) === true && isset($condition['$contains']) === true) {
					$out[] = $field . ':' . $condition['$contains'];
				} else if (is_string($condition) === true) {
					$out[] = $field . ':' . $condition;
				}
			}
		}

		sort($out);
		return array_values(array_unique($out));
	}//end grants()

	/**
	 * The project declares five role lists and the owning group, each an array of ids defaulting to empty.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.1
	 */
	public function testTheProjectDeclaresTheRoleLists(): void {
		$properties = $this->schemas()['project']['properties'];
		foreach (['managers', 'viewers', 'managerGroups', 'memberGroups', 'viewerGroups', 'ownerGroups'] as $name) {
			self::assertArrayHasKey($name, $properties);
			self::assertSame('array', $properties[$name]['type'], $name);
			self::assertSame('string', $properties[$name]['items']['type'], $name);
			self::assertSame([], $properties[$name]['default'], $name);
			self::assertNotEmpty($properties[$name]['title'], $name);
		}

		self::assertSame(1, $properties['ownerGroups']['maxItems'], 'a project has at most one owning group');
	}//end testTheProjectDeclaresTheRoleLists()

	/**
	 * Read for every list, update for owner and managers, delete for the owner and the owning group only.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.2
	 */
	public function testTheProjectRulesPerRole(): void {
		$auth = $this->schemas()['project']['authorization'];

		$read = self::grants($auth['read']);
		foreach (['members', 'managers', 'viewers', 'portfolioReaders'] as $list) {
			self::assertContains($list . ':' . self::USER, $read);
		}

		foreach (['managerGroups', 'memberGroups', 'viewerGroups', 'ownerGroups'] as $list) {
			self::assertContains($list . ':' . self::GROUPS, $read);
		}

		self::assertSame(
			['managerGroups:' . self::GROUPS, 'managers:' . self::USER, 'owner:' . self::USER, 'ownerGroups:' . self::GROUPS],
			self::grants($auth['update'])
		);
		self::assertSame(['owner:' . self::USER, 'ownerGroups:' . self::GROUPS], self::grants($auth['delete']), 'a manager may not delete');
	}//end testTheProjectRulesPerRole()

	/**
	 * Only the owner, the owning group or an admin may rewrite who owns the project.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.2
	 */
	public function testOnlyTheOwnerOrTheOwningGroupRewritesOwnership(): void {
		$properties = $this->schemas()['project']['properties'];
		foreach (['owner', 'ownerGroups'] as $name) {
			$rules = $properties[$name]['authorization']['update'];
			self::assertSame(['owner:' . self::USER, 'ownerGroups:' . self::GROUPS], self::grants($rules), $name);
			self::assertContains(['group' => 'admin'], $rules, $name);
		}
	}//end testOnlyTheOwnerOrTheOwningGroupRewritesOwnership()

	/**
	 * Every project-scoped schema reads for all lists and writes for owner, managers and members only.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function testScopedSchemasReadForEveryRoleAndWriteForMembers(): void {
		$schemas = $this->schemas();
		foreach (['task', 'column', 'projectPhase', 'projectLogEntry', 'risk', 'projectStatusReport', 'projectRelease', 'forgeLink'] as $slug) {
			$auth = $schemas[$slug]['authorization'];
			self::assertSame(
				['memberGroups:' . self::GROUPS, 'members:' . self::USER, 'portfolioReaders:' . self::USER, 'viewerGroups:' . self::GROUPS, 'viewers:' . self::USER],
				self::grants($auth['read']),
				$slug . ' read'
			);
			foreach (['update', 'delete'] as $action) {
				$grants = array_values(array_filter(self::grants($auth[$action]), static fn (string $g): bool => str_starts_with($g, 'source:') === false));
				self::assertSame(['memberGroups:' . self::GROUPS, 'members:' . self::USER], $grants, $slug . ' ' . $action);
			}

			foreach (['viewers', 'memberGroups', 'viewerGroups'] as $name) {
				self::assertSame(false, $schemas[$slug]['properties'][$name]['visible'] ?? null, $slug . '.' . $name . ' is kept by planninq');
			}
		}

		$time = $schemas['plannedTimeEntry']['authorization'];
		self::assertContains('viewerGroups:' . self::GROUPS, self::grants($time['read']));
		self::assertContains('viewers:' . self::USER, self::grants($time['read']));
		self::assertSame(['user:' . self::USER], self::grants($time['update']), 'time stays with the person who booked it');
	}//end testScopedSchemasReadForEveryRoleAndWriteForMembers()

	/**
	 * No rule uses `$in` with the caller's groups: OpenRegister's list query does not resolve the token there and fails closed.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.2
	 */
	public function testNoRuleComparesGroupsWithIn(): void {
		self::assertStringNotContainsString('"$in":"$user.groups"', (string)json_encode($this->schemas(), JSON_UNESCAPED_SLASHES));
	}//end testNoRuleComparesGroupsWithIn()

	/**
	 * A project with every role list, and a task carrying their copies, pass the real schemas.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.1
	 */
	public function testAProjectWithRolesValidates(): void {
		$project = [
			'title'         => 'Roles',
			'owner'         => 'alice',
			'members'       => ['bob'],
			'managers'      => ['erin'],
			'viewers'       => ['vic'],
			'managerGroups' => ['leads'],
			'memberGroups'  => ['devs'],
			'viewerGroups'  => ['audit'],
			'ownerGroups'   => ['pmo'],
		];
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $project));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'project', payload: ['ownerGroups' => ['pmo', 'devs']] + $project), 'control: two owning groups');

		$task = ['title' => 'T', 'status' => 'open', 'project' => '0000de00-0000-4000-8000-000100000001', 'members' => ['alice', 'bob', 'erin'], 'viewers' => ['vic'], 'memberGroups' => ['devs', 'leads', 'pmo'], 'viewerGroups' => ['audit']];
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $task));
		self::assertSame(['viewers', 'memberGroups', 'viewerGroups'], ProjectMembershipService::ROLE_FIELDS);
	}//end testAProjectWithRolesValidates()
}//end class
