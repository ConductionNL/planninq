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

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Lifecycle\PhaseConcludingDocumentGuard;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;

/**
 * Tests for planninq_register.json schema authorization and security configuration.
 */
class PlanninqRegisterSchemaTest extends TestCase {
	use RegisterSchemaValidation;

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
	 * A phase closes only through the transition that runs the concluding
	 * document guard, and the register asks for an OpenRegister that runs
	 * `requires` guards on update. The two ship together.
	 *
	 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-1.1
	 *
	 * @return void
	 */
	public function testPhaseLifecycleRequiresConcludingDocumentGuard(): void {
		$phase = $this->register['components']['schemas']['projectPhase'];
		$lifecycle = ($phase['x-openregister-lifecycle'] ?? null);
		self::assertIsArray($lifecycle, 'projectPhase declares x-openregister-lifecycle');
		self::assertSame('status', $lifecycle['field']);
		self::assertSame('open', $lifecycle['initial']);

		$moves = [];
		foreach ($lifecycle['transitions'] as $action => $transition) {
			foreach ($transition['from'] as $from) {
				$moves[$from.'->'.$transition['to']] = [$action, ($transition['requires'] ?? null)];
			}
		}

		ksort($moves);
		self::assertSame(
			[
				'cancelled->in_progress' => ['reopen', null],
				'completed->in_progress' => ['reopen', null],
				'in_progress->cancelled' => ['cancel', null],
				'in_progress->completed' => ['complete', PhaseConcludingDocumentGuard::class],
				'open->cancelled' => ['cancel', null],
				'open->completed' => ['complete', PhaseConcludingDocumentGuard::class],
				'open->in_progress' => ['start', null],
			],
			$moves
		);

		self::assertSame(['open', 'in_progress', 'completed', 'cancelled'], $phase['properties']['status']['enum']);
		self::assertSame('>=v1.1.7', $this->register['x-openregister']['openregister'], 'the constraint names the first OpenRegister that runs requires guards on update');

		$document = $phase['properties']['concludingDocument'];
		self::assertSame('string', $document['type']);
		self::assertTrue($document['nullable']);

		$base = ['title' => 'Initiatie', 'project' => '6f1d6c0e-1b2a-4c3d-8e9f-0a1b2c3d4e5f', 'status' => 'completed'];
		self::assertSame([], $this->registerSchemaErrors(slug: 'projectPhase', payload: $base + ['concludingDocument' => '4711']));
		self::assertSame([], $this->registerSchemaErrors(slug: 'projectPhase', payload: $base + ['concludingDocument' => null]));

	}//end testPhaseLifecycleRequiresConcludingDocumentGuard()

	/**
	 * A phase carries the declared `metadata` catch-all a task has, so a phase
	 * imported from Microsoft Project keeps its Project UID and the re-import
	 * can find it again. The schema version moves with it.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.3
	 *
	 * @return void
	 */
	public function testPhaseHasMetadata(): void {
		$phase    = $this->register['components']['schemas']['projectPhase'];
		$metadata = ($phase['properties']['metadata'] ?? null);
		self::assertIsArray($metadata, 'projectPhase declares metadata');
		self::assertSame('object', $metadata['type']);
		self::assertSame([], $metadata['default']);
		self::assertTrue(version_compare($phase['version'], '0.5.0', '>='), 'projectPhase version moves past 0.4.0');

		$payload = [
			'title'    => 'Ruwbouw',
			'project'  => '6f1d6c0e-1b2a-4c3d-8e9f-0a1b2c3d4e5f',
			'metadata' => ['msProjectUid' => '1', 'msProjectFile' => 'Renovatie stadhuis.xml', 'msProjectSaveVersion' => '14'],
		];
		self::assertSame([], $this->registerSchemaErrors(slug: 'projectPhase', payload: $payload));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'projectPhase', payload: ['metadata' => 'uid 1'] + $payload));

	}//end testPhaseHasMetadata()

	/**
	 * A project records each handover to its case: when, by whom, to which
	 * case, and every file with its SHA-256, plus the files that failed.
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
	 *
	 * @return void
	 */
	public function testProjectRecordsCaseHandovers(): void {
		$project   = $this->register['components']['schemas']['project'];
		$handovers = ($project['properties']['caseHandovers'] ?? null);
		self::assertIsArray($handovers, 'project declares caseHandovers');
		self::assertSame('array', $handovers['type']);
		self::assertSame(['date', 'by', 'case', 'files', 'failures'], $handovers['items']['required']);

		$record = [
			'date'     => '2026-09-29T10:00:00+00:00',
			'by'       => 'olga',
			'case'     => '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d',
			'files'    => [['name' => 'plan.pdf', 'sha256' => hash('sha256', 'x'), 'size' => 1]],
			'failures' => [['name' => 'tekening.pdf', 'reason' => 'checksum']],
		];
		$base    = ['title' => 'Renovatie stadhuis', 'status' => 'active', 'owner' => 'olga'];
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $base + ['caseHandovers' => [$record]]));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'project', payload: $base + ['caseHandovers' => [['by' => 'olga']]]));

	}//end testProjectRecordsCaseHandovers()

	/**
	 * The project carries the task counter; a task key and a project key validate.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.1
	 */
	public function testProjectCarriesTheTaskCounter(): void {
		$counter = ($this->register['components']['schemas']['project']['properties']['nextTaskNumber'] ?? null);
		self::assertIsArray($counter, 'project declares nextTaskNumber');
		self::assertSame('integer', $counter['type']);
		self::assertSame(1, $counter['minimum']);

		$base = ['title' => 'Vergunningen Centrum', 'status' => 'active', 'owner' => 'carol', 'key' => 'VERG'];
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $base + ['nextTaskNumber' => 43]));
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $base + ['nextTaskNumber' => null]));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'project', payload: $base + ['nextTaskNumber' => 0]));
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: ['title' => 'Check the zoning plan', 'status' => 'open', 'key' => 'VERG-42']));

	}//end testProjectCarriesTheTaskCounter()

	/**
	 * A task names one responsible person and may be shared with more.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-1.1
	 */
	public function testTaskIsSharedWithAListOfPeople(): void {
		$shared = ($this->register['components']['schemas']['task']['properties']['sharedWith'] ?? null);
		self::assertIsArray($shared, 'task declares sharedWith');
		self::assertSame('array', $shared['type']);
		self::assertSame('string', $shared['items']['type']);

		$base = ['title' => 'Draft the permit letter', 'status' => 'open', 'assignedTo' => 'bram'];
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $base + ['sharedWith' => ['anna', 'carla']]));
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $base + ['sharedWith' => []]));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'task', payload: $base + ['sharedWith' => 'anna']), 'control: one string is not a list');
	}//end testTaskIsSharedWithAListOfPeople()

	/**
	 * A task keeps a checklist of small steps: each item an id, a text and a done flag.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-1.1
	 */
	public function testTaskKeepsAChecklist(): void {
		$checklist = ($this->register['components']['schemas']['task']['properties']['checklist'] ?? null);
		self::assertIsArray($checklist, 'task declares checklist');
		self::assertSame('array', $checklist['type']);
		self::assertSame(['id', 'text', 'done'], $checklist['items']['required']);

		$base = ['title' => 'Prepare the council decision', 'status' => 'open'];
		$item = ['id' => 'c1', 'text' => 'Collect the advice', 'done' => false];
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $base + ['checklist' => [$item, ['id' => 'c2', 'text' => 'Send it', 'done' => true]]]));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'task', payload: $base + ['checklist' => [['text' => 'No id']]]), 'control: an item needs its id and done flag');
	}//end testTaskKeepsAChecklist()

	/**
	 * The project status moves only through declared transitions; approve and
	 * reject belong to reviewers, archive and restore to whoever may update.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.1
	 */
	public function testProjectStatusMovesThroughDeclaredTransitions(): void {
		$project   = $this->register['components']['schemas']['project'];
		$lifecycle = ($project['x-openregister-lifecycle'] ?? null);
		self::assertIsArray($lifecycle, 'project declares x-openregister-lifecycle');
		self::assertSame('status', $lifecycle['field']);
		self::assertSame('active', $lifecycle['initial']);
		self::assertSame(['active', 'archived', 'completed', 'cancelled', 'requested', 'rejected'], $project['properties']['status']['enum']);

		$moves = [];
		foreach ($lifecycle['transitions'] as $action => $transition) {
			foreach ($transition['from'] as $from) {
				$moves[$from.'->'.$transition['to']] = [$action, ($transition['authorization'] ?? null)];
			}
		}

		ksort($moves);
		self::assertSame(
			[
				'active->archived' => ['archive', null],
				'active->cancelled' => ['cancel', null],
				'active->completed' => ['complete', null],
				'archived->active' => ['restore', null],
				'cancelled->active' => ['restore', null],
				'completed->active' => ['restore', null],
				'completed->archived' => ['archive', null],
				'requested->active' => ['approve', ['admin']],
				'requested->rejected' => ['reject', ['admin']],
			],
			$moves
		);

		foreach (['requestReason', 'reviewedBy', 'reviewedAt', 'reviewNote'] as $field) {
			self::assertArrayHasKey($field, $project['properties'], $field);
		}

		$base = ['title' => 'Portaal', 'owner' => 'rik', 'status' => 'requested', 'requestReason' => 'Residents ask for it', 'reviewedBy' => 'olga', 'reviewedAt' => '2026-09-29T10:00:00+00:00', 'reviewNote' => 'Fits in the existing portal project'];
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $base));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'project', payload: ['status' => 'pending'] + $base));

	}//end testProjectStatusMovesThroughDeclaredTransitions()

	/**
	 * A reject asks for the reason, and the requester is told the outcome of approve and reject.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.5
	 */
	public function testProjectRequestsNotifyTheRequester(): void {
		$project = $this->register['components']['schemas']['project'];
		self::assertSame([['field' => 'reviewNote', 'required' => true]], $project['x-openregister-lifecycle']['transitions']['reject']['inputs']);

		$rules = ($project['x-openregister-notifications'] ?? []);
		foreach (['projectRequestApproved' => 'approve', 'projectRequestRejected' => 'reject'] as $key => $action) {
			self::assertArrayHasKey($key, $rules);
			self::assertSame(['type' => 'transition', 'action' => $action], $rules[$key]['trigger']);
			self::assertSame([['kind' => 'field', 'field' => 'owner']], $rules[$key]['recipients']);
			self::assertSame(['nc-notification'], $rules[$key]['channels']);
			self::assertArrayHasKey('en', $rules[$key]['subject']);
			self::assertArrayHasKey('nl', $rules[$key]['subject']);
		}

	}//end testProjectRequestsNotifyTheRequester()

	/**
	 * A fresh install creates the five default labels and nothing else (ADR-111 rule 3).
	 *
	 * The sample projects, columns, tasks and time entries moved to the example
	 * data the setup wizard loads on request.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/platform-demo-data/tasks.md#task-1.2
	 */
	public function testInstallSeedsOnlyDefaultLabels(): void {
		$objects = $this->register['components']['objects'];
		self::assertSame(['label'], array_values(array_unique(array_map(static fn (array $o): string => $o['@self']['schema'], $objects))));
		self::assertSame(['Bug', 'Feature', 'Docs', 'Design', 'Infrastructure'], array_column($objects, 'title'));
		foreach ($objects as $label) {
			self::assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $label['color']);
		}

	}//end testInstallSeedsOnlyDefaultLabels()

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
	 * The register MUST declare exactly the fifteen expected schemas.
	 *
	 * Adds `projectPhase` to the previous exact set of six, when planninq took
	 * over the project work breakdown structure pipelinq had built, and
	 * `timetableSession` when planninq became the school timetable owner
	 * (school-timetable-target, decision D10), `projectLogEntry` and `risk`
	 * (projects-overview-logs-risks) and `projectStatusReport`
	 * (portfolio-status-overview) and `portfolio`
	 * (projects-grouping-hierarchy-fields) and `projectRelease`
	 * (backlog-releases-roadmap). `example` must not be present.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-1.3
	 */
	public function testRegisterDeclaresExactlyFifteenSchemas(): void {
		$expected = ['task', 'project', 'projectPhase', 'column', 'plannedTimeEntry', 'label', 'dependency', 'timetableSession', 'projectLogEntry', 'risk', 'projectStatusReport', 'projectPortfolio', 'financeLine', 'projectField', 'projectRelease'];

		$listed = $this->register['components']['registers']['planninq']['schemas'];
		sort($listed);
		$sortedExpected = $expected;
		sort($sortedExpected);
		self::assertSame(
			expected: $sortedExpected,
			actual: $listed,
			message: 'register schema list must be exactly the fifteen expected schemas'
		);

		$defined = array_keys($this->register['components']['schemas']);
		sort($defined);
		self::assertSame(
			expected: $sortedExpected,
			actual: $defined,
			message: 'components.schemas must define exactly the fifteen expected schemas'
		);

		self::assertArrayNotHasKey(
			key: 'example',
			array: $this->register['components']['schemas'],
			message: 'placeholder example schema must not be present'
		);

	}//end testRegisterDeclaresExactlyFifteenSchemas()

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

		self::assertSame(expected: ['draft', 'scheduled', 'cancelled'], actual: $session['properties']['status']['enum']);
		self::assertSame(expected: 'scheduled', actual: $session['properties']['status']['default']);
		self::assertSame(expected: 'date-time', actual: $session['properties']['startsAt']['format']);
		self::assertSame(expected: 'date-time', actual: $session['properties']['endsAt']['format']);
		self::assertContains(needle: 'timetableSession', haystack: $this->register['components']['registers']['planninq']['schemas']);

	}//end testTimetableSessionSchemaDeclaresTheSessionShape()

	/**
	 * The timetable group, the named teacher and admins read a lesson; only
	 * admins write one directly (planninq#711).
	 *
	 * No rule may admit every signed-in user: that let a pupil list every
	 * group's lessons over the object API, round learniq's visibility rules.
	 * Imports reach the schema through planninq's own service, not through a
	 * user's write rights, and another app reads through the query event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001
	 */
	public function testTimetableSessionIsReadByTheTimetableGroupTheTeacherAndAdmins(): void {
		$session = $this->register['components']['schemas']['timetableSession'];
		$auth = $session['authorization'];

		self::assertSame(
			expected: [
				['group' => 'planninq-timetable', 'match' => ['status' => ['$in' => ['scheduled', 'cancelled']]]],
				['group' => 'authenticated', 'match' => ['teacherUserId' => '$userId']],
				['group' => 'admin'],
			],
			actual: $auth['read'],
			message: 'timetableSession read is the timetable group (published lessons, timetable-draft-review), the teacher the lesson names, and admins'
		);

		foreach ($auth['read'] as $rule) {
			$unconditional = ($rule === 'authenticated') || (is_array($rule) === true && ($rule['group'] ?? null) === 'authenticated' && empty($rule['match']) === true);
			self::assertFalse(condition: $unconditional, message: 'no read rule may admit every signed-in user (planninq#711)');
		}

		foreach (['create', 'update', 'delete'] as $action) {
			self::assertSame(expected: ['admin'], actual: $auth[$action], message: "timetableSession {$action} must be admin-only");
		}

		self::assertTrue(version_compare($session['version'], '0.2.0', '>='), 'timetableSession version moves past 0.1.0 with the new read rule');
		self::assertTrue(version_compare($this->register['info']['version'], '0.20.0', '>='), 'register version moves past 0.19.0 so the import applies the new rule');

	}//end testTimetableSessionIsReadByTheTimetableGroupTheTeacherAndAdmins()

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
	 * The read rule for a portfolio's managers (projects-grouping-hierarchy-fields).
	 *
	 * @var array<string,mixed>
	 */
	private const READER_RULE = [
		'group' => 'authenticated',
		'match' => ['portfolioReaders' => ['$contains' => '$userId']],
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
		foreach (['task', 'column', 'projectPhase', 'plannedTimeEntry', 'projectLogEntry', 'risk', 'projectStatusReport', 'projectRelease'] as $slug) {
			$schema = $this->register['components']['schemas'][$slug];
			$members = ($schema['properties']['members'] ?? null);

			self::assertIsArray(actual: $members, message: "{$slug} has a members property");
			self::assertSame(expected: 'array', actual: $members['type'], message: $slug);
			self::assertSame(expected: ['type' => 'string'], actual: $members['items'], message: $slug);
			self::assertFalse(condition: $members['visible'], message: "{$slug}.members is hidden");
			self::assertArrayNotHasKey(key: 'format', array: $members, message: $slug);
			self::assertNotContains(needle: 'members', haystack: ($schema['required'] ?? []), message: $slug);

			$readers = ($schema['properties']['portfolioReaders'] ?? null);
			self::assertIsArray(actual: $readers, message: "{$slug} has a portfolioReaders property");
			self::assertSame(expected: ['type' => 'string'], actual: $readers['items'], message: $slug);
			self::assertFalse(condition: $readers['visible'], message: "{$slug}.portfolioReaders is hidden");
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
		foreach (['task', 'column', 'projectPhase', 'projectLogEntry', 'risk', 'projectStatusReport', 'projectRelease'] as $slug) {
			$authorization = $this->register['components']['schemas'][$slug]['authorization'];

			self::assertSame(
				expected: [self::MEMBER_RULE, self::READER_RULE, ['group' => 'admin']],
				actual: $authorization['read'],
				message: "{$slug}.read: members, the portfolio's managers, and admins"
			);
			foreach (['update', 'delete'] as $action) {
				self::assertSame(
					expected: [self::MEMBER_RULE, ['group' => 'admin']],
					actual: $authorization[$action],
					message: "{$slug}.{$action}: portfolio managers read only"
				);
			}
		}

		self::assertSame(
			expected: [
				['group' => 'authenticated', 'match' => ['user' => '$userId']],
				self::MEMBER_RULE,
				self::READER_RULE,
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
		foreach (['task', 'column', 'projectPhase', 'projectLogEntry', 'risk', 'projectStatusReport', 'projectRelease'] as $slug) {
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
	/**
	 * A risk has likelihood, impact, owner, response, countermeasures and a score the server calculates.
	 *
	 * `score` is a materialised `x-openregister-calculations` entry, so
	 * OpenRegister's CalculationOnSaveListener overwrites whatever score a
	 * client sends with likelihood times impact.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.1
	 */
	public function testRiskSchemaCalculatesItsScore(): void {
		$schema = $this->register['components']['schemas']['risk'];
		$properties = $schema['properties'];

		self::assertSame(expected: ['title', 'project', 'likelihood', 'impact'], actual: $schema['required']);
		foreach (['likelihood', 'impact'] as $axis) {
			self::assertSame(expected: 'integer', actual: $properties[$axis]['type'], message: $axis);
			self::assertSame(expected: 1, actual: $properties[$axis]['minimum'], message: $axis);
			self::assertSame(expected: 5, actual: $properties[$axis]['maximum'], message: $axis);
		}

		self::assertSame(expected: ['open', 'mitigating', 'closed', 'occurred'], actual: $properties['status']['enum']);
		self::assertSame(expected: ['avoid', 'reduce', 'transfer', 'accept'], actual: $properties['response']['enum']);
		self::assertSame(expected: 'integer', actual: $properties['score']['type']);
		self::assertSame(expected: 'date', actual: $properties['reviewDate']['format']);
		self::assertArrayHasKey(key: 'owner', array: $properties);
		self::assertArrayHasKey(key: 'countermeasures', array: $properties);

		self::assertSame(
			expected: [
				'score' => [
					'type' => 'integer',
					'materialise' => true,
					'expression' => ['*' => [['prop' => 'likelihood'], ['prop' => 'impact']]],
				],
			],
			actual: $schema['x-openregister-calculations']
		);

	}//end testRiskSchemaCalculatesItsScore()
	/**
	 * A status report sets six aspects, each with a note, and the server calculates the overall status.
	 *
	 * `overall` is a materialised `x-openregister-calculations` entry: the worst
	 * of the six, so an overall a client sends is overwritten on save.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.1
	 */
	public function testProjectStatusReportSchemaCalculatesTheOverallStatus(): void {
		$schema = $this->register['components']['schemas']['projectStatusReport'];
		$properties = $schema['properties'];
		$aspects = ['Money', 'Organisation', 'Time', 'Information', 'Quality', 'Risk'];

		self::assertSame(
			expected: array_merge(['project', 'reportDate'], array_map(static fn (string $a): string => 'status' . $a, $aspects)),
			actual: $schema['required']
		);
		self::assertSame(expected: 'project', actual: $properties['project']['$ref']);
		self::assertSame(expected: 'date', actual: $properties['reportDate']['format']);
		foreach ($aspects as $aspect) {
			self::assertSame(expected: ['onTrack', 'atRisk', 'offTrack'], actual: $properties['status' . $aspect]['enum'], message: $aspect);
			self::assertSame(expected: 'string', actual: $properties['note' . $aspect]['type'], message: $aspect);
		}

		self::assertSame(expected: ['onTrack', 'atRisk', 'offTrack'], actual: $properties['overall']['enum']);
		$calculation = $schema['x-openregister-calculations']['overall'];
		self::assertTrue(condition: $calculation['materialise']);
		self::assertSame(expected: 'string', actual: $calculation['type']);

		$anyIs = static fn (string $value): array => [
			'or' => array_map(
				static fn (string $a): array => ['eq' => [['prop' => 'status' . $a], ['lit' => $value]]],
				$aspects
			),
		];
		self::assertSame(
			expected: [
				'if' => [
					$anyIs('offTrack'),
					['lit' => 'offTrack'],
					['if' => [$anyIs('atRisk'), ['lit' => 'atRisk'], ['lit' => 'onTrack']]],
				],
			],
			actual: $calculation['expression']
		);

	}//end testProjectStatusReportSchemaCalculatesTheOverallStatus()

	/**
	 * A project carries the newest report's statuses and date, nullable, for the portfolio roll-up.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function testProjectCarriesTheHealthOfItsNewestReport(): void {
		$properties = $this->register['components']['schemas']['project']['properties'];
		foreach (['Money', 'Organisation', 'Time', 'Information', 'Quality', 'Risk', 'Overall'] as $aspect) {
			$field = $properties['health' . $aspect];
			self::assertSame(expected: ['onTrack', 'atRisk', 'offTrack'], actual: $field['enum'], message: $aspect);
			self::assertTrue(condition: $field['nullable'], message: $aspect);
		}

		self::assertSame(expected: 'date', actual: $properties['healthDate']['format']);
		self::assertNotContains(needle: 'healthOverall', haystack: $this->register['components']['schemas']['project']['required']);

	}//end testProjectCarriesTheHealthOfItsNewestReport()
	/**
	 * A portfolio has a title, managers and an order; every signed-in user reads it, admins create it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.1
	 */
	public function testPortfolioSchemaAndTheProjectReaderRule(): void {
		$portfolio = $this->register['components']['schemas']['projectPortfolio'];
		self::assertSame(expected: ['title'], actual: $portfolio['required']);
		self::assertSame(expected: ['type' => 'string'], actual: $portfolio['properties']['managers']['items']);
		self::assertSame(expected: 'integer', actual: $portfolio['properties']['order']['type']);
		self::assertArrayHasKey(key: 'riskScale', array: $portfolio['properties']);
		self::assertSame(expected: [['group' => 'authenticated'], ['group' => 'admin']], actual: $portfolio['authorization']['read']);
		self::assertSame(expected: ['admin'], actual: $portfolio['authorization']['create']);
		self::assertSame(
			expected: [['group' => 'authenticated', 'match' => ['managers' => ['$contains' => '$userId']]], ['group' => 'admin']],
			actual: $portfolio['authorization']['update']
		);
		self::assertSame(expected: ['admin'], actual: $portfolio['authorization']['delete']);

		$project = $this->register['components']['schemas']['project'];
		self::assertSame(expected: 'projectPortfolio', actual: $project['properties']['portfolio']['$ref']);
		self::assertTrue(condition: $project['properties']['portfolio']['nullable']);
		self::assertFalse(condition: $project['properties']['portfolioReaders']['visible']);
		self::assertContains(needle: self::READER_RULE, haystack: $project['authorization']['read']);
		self::assertNotContains(needle: self::READER_RULE, haystack: $project['authorization']['update']);

	}//end testPortfolioSchemaAndTheProjectReaderRule()

	/**
	 * A finance line holds one amount of one kind in one category, readable by
	 * the project owner, the portfolio managers, the import group and admins,
	 * and never by a plain member. The payloads the Finance tab and the import
	 * write pass the validator OpenRegister runs.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 *
	 * @return void
	 */
	public function testFinanceLineSchemaAndItsRules(): void {
		$schema = $this->register['components']['schemas']['financeLine'];
		self::assertSame(expected: ['kind', 'amount'], actual: $schema['required']);
		self::assertSame(expected: ['budget', 'commitment', 'actual', 'forecast'], actual: $schema['properties']['kind']['enum']);
		self::assertSame(expected: ['manual', 'import'], actual: $schema['properties']['source']['enum']);
		self::assertSame(expected: 'manual', actual: $schema['properties']['source']['default']);
		foreach (['financeReaders', 'projectOwner'] as $hidden) {
			self::assertFalse(condition: $schema['properties'][$hidden]['visible'], message: "{$hidden} is kept by planninq");
			self::assertArrayNotHasKey(key: 'format', array: $schema['properties'][$hidden]);
		}

		$readers = ['group' => 'authenticated', 'match' => ['financeReaders' => ['$contains' => '$userId']]];
		$owner   = ['group' => 'authenticated', 'match' => ['projectOwner' => '$userId']];
		$import  = ['group' => 'planninq-finance-import'];
		self::assertSame(expected: [$readers, $import, ['group' => 'admin']], actual: $schema['authorization']['read']);
		self::assertNotContains(needle: self::MEMBER_RULE, haystack: $schema['authorization']['read'], message: 'members do not read money');
		self::assertSame(expected: [['group' => 'authenticated'], ['group' => 'admin']], actual: $schema['authorization']['create']);
		foreach (['update', 'delete'] as $action) {
			self::assertSame(expected: [$owner, $import, ['group' => 'admin']], actual: $schema['authorization'][$action], message: $action);
		}

		$manual = ['project' => '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', 'category' => 'Materials', 'kind' => 'actual', 'amount' => 1500, 'date' => '2026-09-29', 'description' => 'Bricks', 'source' => 'manual', 'phase' => null];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'financeLine', payload: $manual));
		$imported = ['project' => null, 'projectKey' => 'OMG', 'kind' => 'actual', 'amount' => 1200.5, 'externalRef' => 'FIN-778', 'source' => 'import', 'category' => 'Hired staff'];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'financeLine', payload: $imported));
		self::assertNotSame(expected: [], actual: $this->registerSchemaErrors(slug: 'financeLine', payload: ['kind' => 'spent', 'amount' => 1]));

		$project = $this->register['components']['schemas']['project'];
		self::assertSame(expected: ['none', 'fixedPrice', 'hourly'], actual: $project['properties']['billingModel']['enum']);
		$terms = ['title' => 'Stadspark', 'status' => 'active', 'billable' => true, 'billingModel' => 'fixedPrice', 'budgetAmount' => 56000, 'budgetHours' => 400, 'hourlyRate' => 0, 'startDate' => '2026-10-01', 'endDate' => null];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'project', payload: $terms));

	}//end testFinanceLineSchemaAndItsRules()

	/**
	 * A project field has a key, label and type from the six the change names;
	 * admins write fields and everyone signed in reads them; projects carry the
	 * values in customFields.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.1
	 *
	 * @return void
	 */
	public function testProjectFieldSchemaAndTheProjectValues(): void {
		$field = $this->register['components']['schemas']['projectField'];
		self::assertSame(expected: ['key', 'label', 'type'], actual: $field['required']);
		self::assertSame(expected: ['text', 'number', 'date', 'choice', 'person', 'boolean'], actual: $field['properties']['type']['enum']);
		self::assertSame(expected: ['admin'], actual: $field['authorization']['create']);
		self::assertSame(expected: ['admin'], actual: $field['authorization']['update']);
		self::assertSame(expected: ['admin'], actual: $field['authorization']['delete']);
		self::assertContains(needle: ['group' => 'authenticated'], haystack: $field['authorization']['read']);

		$choice = ['key' => 'beleidsveld', 'label' => 'Beleidsveld', 'type' => 'choice', 'options' => ['Wonen', 'Mobiliteit', 'Economie'], 'required' => true, 'order' => 1, 'appliesTo' => 'project'];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectField', payload: $choice));
		self::assertNotSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectField', payload: ['key' => 'Beleids veld', 'label' => 'X', 'type' => 'choice']));
		self::assertNotSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectField', payload: ['key' => 'x', 'label' => 'X', 'type' => 'colour']));

		self::assertSame(expected: 'object', actual: $this->register['components']['schemas']['project']['properties']['customFields']['type']);
	}//end testProjectFieldSchemaAndTheProjectValues()

	/**
	 * A lesson can be delivered as a draft, and the schema version moved with it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-a-draft-lesson-is-readable-only-by-the-teacher-it-names-and-by-admins
	 */
	public function testTimetableSessionStatusAllowsDraft(): void {
		$status = $this->register['components']['schemas']['timetableSession']['properties']['status'];
		self::assertSame(expected: ['draft', 'scheduled', 'cancelled'], actual: $status['enum']);
		self::assertSame(expected: 'scheduled', actual: $status['default']);
		self::assertSame(expected: 'Draft', actual: $status['x-enum-labels']['draft']);
		self::assertSame(expected: '0.3.0', actual: $this->register['components']['schemas']['timetableSession']['version']);

		$lesson = ['externalRef' => 'zm-1', 'sourceSystem' => 'roster-zermelo', 'subject' => 'Wiskunde', 'title' => 'Wiskunde', 'startsAt' => '2026-10-05T09:00:00+02:00', 'endsAt' => '2026-10-05T09:50:00+02:00', 'status' => 'draft'];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableSession', payload: $lesson));
		self::assertNotSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableSession', payload: array_merge($lesson, ['status' => 'concept'])));
	}//end testTimetableSessionStatusAllowsDraft()

	/**
	 * The timetable group reads published lessons only; the named teacher and admins read drafts too.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-a-draft-lesson-is-readable-only-by-the-teacher-it-names-and-by-admins
	 */
	public function testDraftReadRuleNamesTheTeacher(): void {
		self::assertSame(
			expected: [
				['group' => 'planninq-timetable', 'match' => ['status' => ['$in' => ['scheduled', 'cancelled']]]],
				['group' => 'authenticated', 'match' => ['teacherUserId' => '$userId']],
				['group' => 'admin'],
			],
			actual: $this->register['components']['schemas']['timetableSession']['authorization']['read']
		);
		self::assertSame(expected: ['admin'], actual: $this->register['components']['schemas']['timetableSession']['authorization']['update']);
	}//end testDraftReadRuleNamesTheTeacher()

	/**
	 * A release belongs to one project, which OpenRegister's members rule scopes.
	 *
	 * It carries the hidden members list the other project-scoped schemas carry
	 * (asserted by the two scoped-schema tests above), a status that starts at
	 * planned, and nullable dates. A payload with a null release date and a
	 * released one pass the real validator; an unknown status does not.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-1.1
	 */
	public function testReleaseSchemaIsProjectScoped(): void {
		$schema = $this->register['components']['schemas']['projectRelease'];

		self::assertSame(expected: ['title', 'project'], actual: $schema['required']);
		self::assertSame(expected: 'project', actual: $schema['properties']['project']['$ref']);
		self::assertSame(expected: ['planned', 'released', 'archived'], actual: $schema['properties']['status']['enum']);
		self::assertSame(expected: 'planned', actual: $schema['properties']['status']['default']);
		self::assertContains(needle: 'projectRelease', haystack: \OCA\Planninq\Service\ProjectMembershipService::SCOPED_SCHEMAS);

		$planned = ['title' => 'Version 2.0', 'project' => '00000000-0000-4000-8000-000000000001', 'releaseDate' => '2026-12-01', 'startDate' => null, 'status' => 'planned', 'releasedAt' => null];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectRelease', payload: $planned));

		$released = ['status' => 'released', 'releasedAt' => '2026-12-01T10:00:00+00:00'] + $planned;
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectRelease', payload: $released));

		self::assertNotSame(expected: [], actual: $this->registerSchemaErrors(slug: 'projectRelease', payload: ['status' => 'shipped'] + $planned));

	}//end testReleaseSchemaIsProjectScoped()

	/**
	 * A task points at no release or at one release.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-1.2
	 */
	public function testTaskReleaseReferenceIsNullable(): void {
		$release = $this->register['components']['schemas']['task']['properties']['release'];

		self::assertSame(expected: 'projectRelease', actual: $release['$ref']);
		self::assertTrue(condition: $release['nullable']);
		self::assertSame(expected: 'string', actual: $release['type']);

		$task = ['title' => 'Export to CSV', 'project' => '00000000-0000-4000-8000-000000000001', 'release' => null];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'task', payload: $task));
		$task['release'] = '00000000-0000-4000-8000-000000000002';
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'task', payload: $task));

	}//end testTaskReleaseReferenceIsNullable()
}//end class
