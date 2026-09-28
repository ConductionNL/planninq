<?php

/**
 * Unit tests for planninq_register.json schema definitions.
 *
 * Verifies that the register JSON contains the authorization blocks
 * and owner field required to prevent cross-tenant IDOR (issues #257 and #258)
 * and that the register explicitly disables public read/write (issue #259).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Tests for planninq_register.json schema authorization and security configuration.
 */
class PlanninqRegisterSchemaTest extends TestCase {

	/**
	 * Decoded register JSON data.
	 *
	 * @var array<string,mixed>
	 */
	private array $register;

	/**
	 * Load and decode the register JSON before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$path = __DIR__ . '/../../../lib/Settings/planninq_register.json';
		self::assertFileExists(filename: $path, message: 'planninq_register.json must exist');

		$contents = (string)file_get_contents($path);
		$decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray(actual: $decoded, message: 'planninq_register.json must be valid JSON');

		$this->register = $decoded;

	}//end setUp()

	/**
	 * Register JSON must be valid JSON with the required top-level structure.
	 *
	 * @return void
	 */
	public function testRegisterJsonIsValidAndHasRequiredStructure(): void {
		self::assertArrayHasKey(key: 'components', array: $this->register);
		self::assertArrayHasKey(key: 'registers', array: $this->register['components']);
		self::assertArrayHasKey(key: 'schemas', array: $this->register['components']);
		self::assertArrayHasKey(key: 'planninq', array: $this->register['components']['registers']);

	}//end testRegisterJsonIsValidAndHasRequiredStructure()

	/**
	 * Register block must explicitly set publicRead and publicWrite to false.
	 *
	 * Fixes #259: the register must not silently rely on OR defaults.
	 * Any omission is treated as "accept the engine default", which may
	 * change across OR versions.  Explicit false is the only safe posture.
	 *
	 * @return void
	 */
	public function testRegisterBlockExplicitlyDisablesPublicAccess(): void {
		$planninq = $this->register['components']['registers']['planninq'];

		self::assertArrayHasKey(
			key: 'publicRead',
			array: $planninq,
			message: 'Register block must explicitly declare publicRead (issue #259)'
		);
		self::assertArrayHasKey(
			key: 'publicWrite',
			array: $planninq,
			message: 'Register block must explicitly declare publicWrite (issue #259)'
		);
		self::assertFalse(
			condition: $planninq['publicRead'],
			message: 'publicRead must be false — planninq is not a public register'
		);
		self::assertFalse(
			condition: $planninq['publicWrite'],
			message: 'publicWrite must be false — planninq is not a public register'
		);

	}//end testRegisterBlockExplicitlyDisablesPublicAccess()

	/**
	 * Project schema must contain an owner field for creator-based RBAC.
	 *
	 * Fixes #258: without an explicit owner field there is no way to
	 * enforce "only the creator can delete" at the OR authorization layer.
	 *
	 * @return void
	 */
	public function testProjectSchemaHasOwnerField(): void {
		$schema = $this->register['components']['schemas']['project'];

		self::assertArrayHasKey(
			key: 'properties',
			array: $schema,
			message: 'Project schema must have properties'
		);
		self::assertArrayHasKey(
			key: 'owner',
			array: $schema['properties'],
			message: 'Project schema must have an owner property (issue #258)'
		);
		self::assertSame(
			expected: 'string',
			actual: $schema['properties']['owner']['type'],
			message: 'owner property must be of type string'
		);

	}//end testProjectSchemaHasOwnerField()

	/**
	 * All schemas that carry user data must have authorization blocks.
	 *
	 * Fixes #257: without authorization blocks OR applies no row-level
	 * filter, allowing any authenticated user to read/write all objects.
	 *
	 * @return void
	 */
	public function testAllDataSchemasHaveAuthorizationBlocks(): void {
		$schemasRequiringAuth = ['project', 'task', 'column', 'plannedTimeEntry'];

		foreach ($schemasRequiringAuth as $schemaSlug) {
			self::assertArrayHasKey(
				key: $schemaSlug,
				array: $this->register['components']['schemas'],
				message: "Schema '{$schemaSlug}' must exist in register"
			);

			$schema = $this->register['components']['schemas'][$schemaSlug];

			self::assertArrayHasKey(
				key: 'authorization',
				array: $schema,
				message: "Schema '{$schemaSlug}' must have an authorization block (issue #257)"
			);

			$auth = $schema['authorization'];

			foreach (['read', 'create', 'update', 'delete'] as $action) {
				self::assertArrayHasKey(
					key: $action,
					array: $auth,
					message: "Schema '{$schemaSlug}' authorization must define '{$action}'"
				);
				self::assertNotEmpty(
					actual: $auth[$action],
					message: "Schema '{$schemaSlug}' authorization '{$action}' must not be empty"
				);
			}
		}//end foreach

	}//end testAllDataSchemasHaveAuthorizationBlocks()

	/**
	 * Project read authorization must include a members-based match rule.
	 *
	 * This is the core of the IDOR fix: a non-member must not be able to
	 * list projects they do not belong to.
	 *
	 * @return void
	 */
	public function testProjectAuthorizationEnforcesMembershipForRead(): void {
		$auth = $this->register['components']['schemas']['project']['authorization'];

		$hasMembersReadRule = false;
		foreach ($auth['read'] as $rule) {
			if (is_array($rule) === true && isset($rule['match']['members']) === true) {
				$hasMembersReadRule = true;
				break;
			}
		}

		self::assertTrue(
			condition: $hasMembersReadRule,
			message: 'Project read authorization must include a members-based row-filter rule (issue #257)'
		);

	}//end testProjectAuthorizationEnforcesMembershipForRead()

	/**
	 * Project update authorization must include an owner-based match rule.
	 *
	 * Security intent (wave-3 fix): only the project owner (or admin) may
	 * mutate project metadata. Non-owner members use the server-side
	 * leaveProject proxy (C3) for the sole member-write they are permitted.
	 *
	 * @return void
	 */
	public function testProjectUpdateAuthorizationEnforcesOwner(): void {
		$auth = $this->register['components']['schemas']['project']['authorization'];
		$updateRules = $auth['update'];

		$hasOwnerRule = false;
		foreach ($updateRules as $rule) {
			if (is_array($rule) === true && isset($rule['match']['owner']) === true) {
				$hasOwnerRule = true;
				break;
			}
		}

		self::assertTrue(
			condition: $hasOwnerRule,
			message: 'Project update authorization must restrict to the project owner (wave-3 C3 fix)'
		);

	}//end testProjectUpdateAuthorizationEnforcesOwner()

	/**
	 * Project delete authorization must include an owner-based match rule.
	 *
	 * Only the project creator (owner) or an admin may delete a project.
	 *
	 * @return void
	 */
	public function testProjectDeleteAuthorizationEnforcesOwner(): void {
		$auth = $this->register['components']['schemas']['project']['authorization'];
		$deleteRules = $auth['delete'];

		$hasOwnerRule = false;
		foreach ($deleteRules as $rule) {
			if (is_array($rule) === true && isset($rule['match']['owner']) === true) {
				$hasOwnerRule = true;
				break;
			}
		}

		self::assertTrue(
			condition: $hasOwnerRule,
			message: 'Project delete authorization must include an owner-based row-filter rule (issues #257 + #258)'
		);

	}//end testProjectDeleteAuthorizationEnforcesOwner()

	/**
	 * Time entry authorization must restrict update and delete to the logging user.
	 *
	 * @return void
	 */
	public function testTimeEntryAuthorizationRestrictsWriteToOwningUser(): void {
		$auth = $this->register['components']['schemas']['plannedTimeEntry']['authorization'];

		foreach (['update', 'delete'] as $action) {
			$hasUserRule = false;
			foreach ($auth[$action] as $rule) {
				if (is_array($rule) === true && isset($rule['match']['user']) === true) {
					$hasUserRule = true;
					break;
				}
			}

			self::assertTrue(
				condition: $hasUserRule,
				message: "timeEntry {$action} authorization must restrict to the logging user"
			);
		}

	}//end testTimeEntryAuthorizationRestrictsWriteToOwningUser()

	/**
	 * The task schema MUST declare a canonical-dialect taskDueSoon rule.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#2
	 */
	public function testTaskSchemaHasDueSoonNotificationRule(): void {
		$task = $this->register['components']['schemas']['task'];

		self::assertArrayHasKey(
			key: 'x-openregister-notifications',
			array: $task,
			message: 'task schema must declare x-openregister-notifications'
		);

		$notifications = $task['x-openregister-notifications'];
		self::assertArrayHasKey(
			key: 'taskDueSoon',
			array: $notifications,
			message: 'task schema must declare the taskDueSoon rule'
		);

		$rule = $notifications['taskDueSoon'];

		// Trigger: scheduled, hourly, with a withinNext dueDate + notEquals status filter.
		self::assertSame(expected: 'scheduled', actual: $rule['trigger']['type']);
		self::assertSame(expected: 3600, actual: $rule['trigger']['intervalSec']);
		self::assertSame(
			expected: 'withinNext',
			actual: $rule['trigger']['filter']['dueDate']['operator'],
			message: 'taskDueSoon must use the withinNext date operator on dueDate'
		);
		self::assertSame(
			expected: 'notEquals',
			actual: $rule['trigger']['filter']['status']['operator']
		);
		self::assertSame(expected: 'done', actual: $rule['trigger']['filter']['status']['value']);

		// Enabled, canonical plural arrays, and field-recipient on the real
		// assignee field (assignedTo).
		self::assertTrue(condition: $rule['enabled']);
		self::assertSame(expected: ['nc-notification'], actual: $rule['channels']);
		self::assertIsArray(actual: $rule['recipients']);
		self::assertSame(expected: 'field', actual: $rule['recipients'][0]['kind']);
		self::assertSame(expected: 'assignedTo', actual: $rule['recipients'][0]['field']);

		// Bilingual subject with the {{title}} placeholder; English source key.
		self::assertArrayHasKey(key: 'en', array: $rule['subject']);
		self::assertArrayHasKey(key: 'nl', array: $rule['subject']);
		self::assertStringContainsString(needle: '{{title}}', haystack: $rule['subject']['en']);
		self::assertStringContainsString(needle: '{{title}}', haystack: $rule['subject']['nl']);

	}//end testTaskSchemaHasDueSoonNotificationRule()

	/**
	 * The recipient field MUST name a property that exists on the task schema.
	 *
	 * Guards against the spec's `assignee` typo: the real field is `assignedTo`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#2
	 */
	public function testDueSoonRecipientFieldExistsOnSchema(): void {
		$task = $this->register['components']['schemas']['task'];
		$rule = $task['x-openregister-notifications']['taskDueSoon'];
		$fieldName = $rule['recipients'][0]['field'];

		self::assertArrayHasKey(
			key: $fieldName,
			array: $task['properties'],
			message: "taskDueSoon recipient field '{$fieldName}' must exist on the task schema"
		);

	}//end testDueSoonRecipientFieldExistsOnSchema()

	/**
	 * The register MUST declare exactly the nine expected schemas.
	 *
	 * Adds `projectPhase` to the previous exact set of six, when planninq took
	 * over the project work breakdown structure pipelinq had built, and
	 * `timetableSession` when planninq became the school timetable owner
	 * (school-timetable-target, decision D10). `example` must not be present.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/task-dependencies/specs/register-schemas/spec.md
	 */
	public function testRegisterDeclaresExactlyNineSchemas(): void {
		$expected = ['task', 'project', 'projectPhase', 'column', 'plannedTimeEntry', 'label', 'dependency', 'timetableSession', 'projectLogEntry'];

		$listed = $this->register['components']['registers']['planninq']['schemas'];
		sort($listed);
		$sortedExpected = $expected;
		sort($sortedExpected);
		self::assertSame(
			expected: $sortedExpected,
			actual: $listed,
			message: 'register schema list must be exactly the nine expected schemas'
		);

		$defined = array_keys($this->register['components']['schemas']);
		sort($defined);
		self::assertSame(
			expected: $sortedExpected,
			actual: $defined,
			message: 'components.schemas must define exactly the nine expected schemas'
		);

		self::assertArrayNotHasKey(
			key: 'example',
			array: $this->register['components']['schemas'],
			message: 'placeholder example schema must not be present'
		);

	}//end testRegisterDeclaresExactlyNineSchemas()

	/**
	 * The dependency schema MUST require blocker + blocked as UUID strings.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/task-dependencies/specs/register-schemas/spec.md
	 */
	public function testDependencySchemaHasRequiredUuidFields(): void {
		self::assertArrayHasKey(
			key: 'dependency',
			array: $this->register['components']['schemas'],
			message: 'dependency schema must be declared'
		);

		$dependency = $this->register['components']['schemas']['dependency'];

		self::assertContains(
			needle: 'blocker',
			haystack: $dependency['required'],
			message: 'dependency must require blocker'
		);
		self::assertContains(
			needle: 'blocked',
			haystack: $dependency['required'],
			message: 'dependency must require blocked'
		);

		foreach (['blocker', 'blocked'] as $field) {
			self::assertSame(
				expected: 'string',
				actual: $dependency['properties'][$field]['type'],
				message: "{$field} must be a string"
			);
			self::assertSame(
				expected: 'uuid',
				actual: $dependency['properties'][$field]['format'],
				message: "{$field} must be format uuid"
			);
		}

	}//end testDependencySchemaHasRequiredUuidFields()

	/**
	 * The dependency schema MUST carry an authorization block for all actions.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function testDependencySchemaHasAuthorizationBlock(): void {
		$dependency = $this->register['components']['schemas']['dependency'];

		self::assertArrayHasKey(
			key: 'authorization',
			array: $dependency,
			message: 'dependency schema must have an authorization block'
		);

		foreach (['read', 'create', 'update', 'delete'] as $action) {
			self::assertArrayHasKey(
				key: $action,
				array: $dependency['authorization'],
				message: "dependency authorization must define '{$action}'"
			);
			self::assertNotEmpty(
				actual: $dependency['authorization'][$action],
				message: "dependency authorization '{$action}' must not be empty"
			);
		}

	}//end testDependencySchemaHasAuthorizationBlock()

	/**
	 * The timetableSession schema MUST carry the upsert key and the lesson
	 * times as required fields, and a two-value status with a default.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001
	 */
	public function testTimetableSessionSchemaDeclaresTheSessionShape(): void {
		$schemas = $this->register['components']['schemas'];
		self::assertArrayHasKey(key: 'timetableSession', array: $schemas);

		$session = $schemas['timetableSession'];
		self::assertSame(
			expected: ['externalRef', 'sourceSystem', 'subject', 'startsAt', 'endsAt'],
			actual: $session['required']
		);

		foreach (['title', 'groupReference', 'cohortId', 'teacherReference', 'teacherUserId', 'roomReference', 'roomLabel', 'importedAt'] as $optional) {
			self::assertArrayHasKey(key: $optional, array: $session['properties'], message: "timetableSession must declare {$optional}");
		}

		self::assertSame(expected: ['scheduled', 'cancelled'], actual: $session['properties']['status']['enum']);
		self::assertSame(expected: 'scheduled', actual: $session['properties']['status']['default']);
		self::assertSame(expected: 'date-time', actual: $session['properties']['startsAt']['format']);
		self::assertSame(expected: 'date-time', actual: $session['properties']['endsAt']['format']);
		self::assertContains(needle: 'timetableSession', haystack: $this->register['components']['registers']['planninq']['schemas']);

	}//end testTimetableSessionSchemaDeclaresTheSessionShape()

	/**
	 * Any signed-in user reads a timetable; only admins write one directly.
	 * Imports reach the schema through planninq's own service, not through
	 * a user's write rights.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001
	 */
	public function testTimetableSessionAuthorizationReadsForSignedInWritesForAdmins(): void {
		$auth = $this->register['components']['schemas']['timetableSession']['authorization'];

		$readGroups = array_map(
			static fn (mixed $rule): string => is_array($rule) === true ? (string)($rule['group'] ?? '') : (string)$rule,
			$auth['read']
		);
		self::assertContains(needle: 'authenticated', haystack: $readGroups);

		foreach (['create', 'update', 'delete'] as $action) {
			self::assertSame(expected: ['admin'], actual: $auth[$action], message: "timetableSession {$action} must be admin-only");
		}

	}//end testTimetableSessionAuthorizationReadsForSignedInWritesForAdmins()

	/**
	 * Demo data MUST cover the timetableSession schema with rows that carry
	 * every required field (ADR-111 rule 1, gate 101).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001
	 */
	public function testMockRegisterCarriesTimetableSessionDemoRows(): void {
		$path = __DIR__ . '/../../../lib/Settings/planninq_mock_register.json';
		$mock = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

		$rows = array_values(
			array_filter(
				$mock['components']['objects'],
				static fn (array $row): bool => ($row['@self']['schema'] ?? '') === 'timetableSession'
			)
		);
		self::assertGreaterThanOrEqual(expected: 1, actual: count($rows));

		$required = $this->register['components']['schemas']['timetableSession']['required'];
		foreach ($rows as $row) {
			foreach ($required as $field) {
				self::assertNotEmpty(actual: $row[$field] ?? null, message: "demo session {$row['@self']['slug']} lacks {$field}");
			}
		}

	}//end testMockRegisterCarriesTimetableSessionDemoRows()

	/**
	 * The member rule the four project-scoped schemas match on (planninq#681).
	 *
	 * @var array<string,mixed>
	 */
	private const MEMBER_RULE = [
		'group' => 'authenticated',
		'match' => ['members' => ['$contains' => '$userId']],
	];

	/**
	 * Task, column, phase and planned time entry carry a hidden, system-kept members list.
	 *
	 * `visible: false` hides it from every form, widget and table, which is the
	 * intent: planninq writes it, nobody edits it. No `format`, which OpenRegister
	 * would treat as a breaking change, and not required, so a legacy row still
	 * validates before the back-fill reaches it.
	 *
	 * @return void
	 */
	public function testProjectScopedSchemasCarryAHiddenMembersList(): void {
		foreach (['task', 'column', 'projectPhase', 'plannedTimeEntry', 'projectLogEntry'] as $slug) {
			$schema = $this->register['components']['schemas'][$slug];
			$members = ($schema['properties']['members'] ?? null);

			self::assertIsArray(actual: $members, message: "{$slug} has a members property");
			self::assertSame(expected: 'array', actual: $members['type'], message: $slug);
			self::assertSame(expected: ['type' => 'string'], actual: $members['items'], message: $slug);
			self::assertFalse(condition: $members['visible'], message: "{$slug}.members is hidden");
			self::assertArrayNotHasKey(key: 'format', array: $members, message: $slug);
			self::assertNotContains(needle: 'members', haystack: ($schema['required'] ?? []), message: $slug);
		}

	}//end testProjectScopedSchemasCarryAHiddenMembersList()

	/**
	 * Read, update and delete of task, column and phase match the denormalised members list.
	 *
	 * The admin rule stays. The former rule matched `project` against a `$lookup`
	 * OpenRegister never evaluated, so it matched nothing.
	 *
	 * @return void
	 */
	public function testProjectScopedSchemasMatchTheMembersList(): void {
		foreach (['task', 'column', 'projectPhase', 'projectLogEntry'] as $slug) {
			$authorization = $this->register['components']['schemas'][$slug]['authorization'];

			foreach (['read', 'update', 'delete'] as $action) {
				self::assertSame(
					expected: [self::MEMBER_RULE, ['group' => 'admin']],
					actual: $authorization[$action],
					message: "{$slug}.{$action}"
				);
			}
		}

		self::assertSame(
			expected: [
				['group' => 'authenticated', 'match' => ['user' => '$userId']],
				self::MEMBER_RULE,
				['group' => 'admin'],
			],
			actual: $this->register['components']['schemas']['plannedTimeEntry']['authorization']['read'],
			message: 'plannedTimeEntry: own entries, the project members, and admins'
		);

	}//end testProjectScopedSchemasMatchTheMembersList()

	/**
	 * Create on task, column and phase carries no `match`.
	 *
	 * OpenRegister checks `create` before the object exists
	 * (`ObjectService::checkSavePermissions()` passes no object), so any `match`
	 * on create is evaluated against an empty object and refuses every member.
	 * ProjectMemberAccessListener checks membership of the target project on
	 * ObjectCreatingEvent instead.
	 *
	 * @return void
	 */
	public function testProjectScopedCreateIsGatedByTheListenerNotByAMatch(): void {
		foreach (['task', 'column', 'projectPhase', 'projectLogEntry'] as $slug) {
			self::assertSame(
				expected: [['group' => 'authenticated'], ['group' => 'admin']],
				actual: $this->register['components']['schemas'][$slug]['authorization']['create'],
				message: "{$slug}.create"
			);
		}

	}//end testProjectScopedCreateIsGatedByTheListenerNotByAMatch()
	/**
	 * A project log entry has its type, title, date, body, status, attendees and actions.
	 *
	 * The author and the time are OpenRegister's own object metadata, so the
	 * schema declares no author or created property a client could fill in.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.1
	 */
	public function testProjectLogEntrySchemaDeclaresTheLogShape(): void {
		$schema = $this->register['components']['schemas']['projectLogEntry'];
		$properties = $schema['properties'];

		self::assertSame(expected: ['title', 'project', 'type', 'date'], actual: $schema['required']);
		self::assertSame(expected: ['issue', 'lesson', 'meeting', 'decision'], actual: $properties['type']['enum']);
		self::assertSame(expected: ['open', 'closed'], actual: $properties['status']['enum']);
		self::assertSame(expected: 'date', actual: $properties['date']['format']);
		self::assertSame(expected: 'project', actual: $properties['project']['$ref']);
		self::assertSame(expected: ['type' => 'string'], actual: $properties['attendees']['items']);
		self::assertSame(expected: ['type' => 'string', 'format' => 'uuid'], actual: $properties['actions']['items']);
		self::assertArrayNotHasKey(key: 'author', array: $properties);
		self::assertArrayNotHasKey(key: 'created', array: $properties);
		self::assertArrayHasKey(key: 'x-enum-labels', array: $properties['type']);

	}//end testProjectLogEntrySchemaDeclaresTheLogShape()
}//end class
