<?php

/**
 * Planninq ProjectHierarchyGuardListener
 *
 * Keeps a project's `portfolioReaders` equal to the managers of its portfolio,
 * so a portfolio manager reads every project in the portfolio without being a
 * member (projects-grouping-hierarchy-fields, design decision 2).
 *
 * WHY A DERIVED LIST
 * ------------------
 * OpenRegister matches read rules on the object's own fields; it cannot follow
 * `project.portfolio` to the portfolio's managers. So the managers are copied
 * onto the project, and from there onto every project-scoped object by
 * ProjectMemberAccessListener, exactly as `members` is.
 *
 * WHAT IT DOES
 * ------------
 * - Project create or update from a person: `portfolioReaders` is set to the
 *   managers of the project's portfolio, whatever the client sent.
 * - Portfolio update that changes the managers: every project in it, and each
 *   project's objects, get the new list.
 * - Portfolio delete: its projects leave it, and their readers list empties.
 *
 * Writes it makes itself run in OpenRegister's system-operation scope, which
 * this listener trusts. All handling is on pre-events (ADR-078, gate-61).
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\ProjectRulesService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Derives portfolio readers on projects and keeps them in step with the portfolio.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
 */
class ProjectHierarchyGuardListener implements IEventListener {

	/**
	 * Slug of the portfolio schema.
	 *
	 * @var string
	 */
	public const PORTFOLIO_SCHEMA = 'projectPortfolio';

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Error code of a parent that makes a project its own ancestor.
	 *
	 * @var string
	 */
	public const ERROR_CYCLE = 'planninq-project-cycle';

	/**
	 * Error code of a parent that makes the chain deeper than three levels.
	 *
	 * @var string
	 */
	public const ERROR_DEPTH = 'planninq-project-too-deep';

	/**
	 * Error code of a custom field value that does not fit its field.
	 *
	 * @var string
	 */
	public const ERROR_FIELD = 'planninq-project-field';

	/**
	 * Error code of a parent that does not exist or that the caller cannot read; the two are not told apart.
	 *
	 * @var string
	 */
	public const ERROR_PARENT = 'planninq-project-parent-unknown';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads portfolios and projects, writes as the system.
	 * @param FinanceLineService       $finance       Keeps the access copies on the projects' finance lines.
	 * @param ProjectRulesService      $rules         Checks a new parent and custom field values.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq schema from any other object.
	 * @param ContainerInterface       $container     Resolves OpenRegister's ObjectService for the system write.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private FinanceLineService $finance,
		private ProjectRulesService $rules,
		private TaskScopeResolver $scopeResolver,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Route a pre-save or pre-delete event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function handle(Event $event): void {
		if ($this->isSystemOperation() === true) {
			return;
		}

		$write = $this->writeOf(event: $event);
		if ($write !== null) {
			$this->route(write: $write);
		}
	}//end handle()

	/**
	 * The write an event carries: event, schema slug, uuid, new data and, on an update, the stored data.
	 *
	 * @param Event $event The event.
	 *
	 * @return array<string,mixed>|null Null for any other event.
	 */
	private function writeOf(Event $event): ?array {
		if ($event instanceof ObjectUpdatingEvent === true) {
			return $this->describe(event: $event, object: $event->getNewObject()) + ['old' => (array)$event->getOldObject()?->getObject()];
		}

		if ($event instanceof ObjectCreatingEvent === true || $event instanceof ObjectDeletingEvent === true) {
			return $this->describe(event: $event, object: $event->getObject()) + ['old' => null];
		}

		return null;
	}//end writeOf()

	/**
	 * The event with the written object's schema slug, uuid and data.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event  The event.
	 * @param object                                                      $object The written object.
	 *
	 * @return array<string,mixed>
	 */
	private function describe(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object): array {
		return [
			'event' => $event,
			'slug'  => $this->scopeResolver->planninqSchemaSlug(
				registerId: (string)($object->getRegister() ?? ''),
				schemaId: (string)($object->getSchema() ?? '')
			),
			'uuid'  => (string)($object->getUuid() ?? ''),
			'data'  => (array)$object->getObject(),
		];
	}//end describe()

	/**
	 * Send a project write to the guard and a portfolio write to the fan-out.
	 *
	 * @param array<string,mixed> $write The write.
	 *
	 * @return void
	 */
	private function route(array $write): void {
		if ($write['slug'] === ProjectMembershipService::PROJECT_SCHEMA && $write['event'] instanceof ObjectDeletingEvent === false) {
			$this->guardProject(write: $write);
			return;
		}

		if ($write['slug'] === self::PORTFOLIO_SCHEMA) {
			$this->followPortfolio(write: $write);
		}
	}//end route()

	/**
	 * Refuse a bad parent, then bad custom field values; otherwise derive the readers.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return void
	 */
	private function guardProject(array $write): void {
		if ($this->refuseParent(write: $write) === true || $this->refuseFields(write: $write) === true) {
			return;
		}

		$this->deriveReaders(event: $write['event'], data: $write['data']);
	}//end guardProject()

	/**
	 * Release a deleted portfolio's projects, or hand changed managers to them.
	 *
	 * @param array<string,mixed> $write The portfolio write.
	 *
	 * @return void
	 */
	private function followPortfolio(array $write): void {
		if ($write['event'] instanceof ObjectDeletingEvent === true) {
			$this->releaseProjects(portfolioId: $write['uuid']);
			return;
		}

		if ($write['old'] !== null) {
			$this->onManagersChange(write: $write);
		}
	}//end followPortfolio()

	/**
	 * Refuse custom field values that do not fit their fields, when the write changes them.
	 *
	 * A write that leaves the values alone is not held to a field added later,
	 * so creating or renaming a project never fails on a new required field.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return bool True when the write was refused.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	private function refuseFields(array $write): bool {
		$values = $this->changedFields(write: $write);
		if ($values === null) {
			return false;
		}

		$problem = $this->rules->problem(values: $values, stored: $this->storedFields(old: $write['old']));
		if ($problem === null) {
			return false;
		}

		return $this->refuse(event: $write['event'], error: ['code' => self::ERROR_FIELD, 'message' => $problem]);
	}//end refuseFields()

	/**
	 * The custom field values a write sends, when they are a list that differs from the stored one.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return array<mixed>|null
	 */
	private function changedFields(array $write): ?array {
		$values = ($write['data']['customFields'] ?? null);
		if (is_array($values) === false || $values === ($write['old']['customFields'] ?? null)) {
			return null;
		}

		return $values;
	}//end changedFields()

	/**
	 * The stored custom field values, empty when there are none or they are not a list.
	 *
	 * @param array<string,mixed>|null $old The stored project on an update.
	 *
	 * @return array<mixed>
	 */
	private function storedFields(?array $old): array {
		$stored = ($old['customFields'] ?? []);
		if (is_array($stored) === false) {
			return [];
		}

		return $stored;
	}//end storedFields()

	/**
	 * Refuse a new parent that makes a cycle or a chain deeper than three levels.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return bool True when the write was refused.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
	 */
	private function refuseParent(array $write): bool {
		$parentId = $this->newParent(write: $write);
		if ($parentId === '') {
			return false;
		}

		$error = $this->parentError(projectId: $this->targetId(write: $write), parentId: $parentId);
		if ($error === null) {
			return false;
		}

		$this->refuse(event: $write['event'], error: $error);
		if ($error['code'] !== self::ERROR_PARENT) {
			$this->logger->info('Planninq: refused a project parent', ['code' => $error['code'], 'project' => $error['project']]);
		}

		return true;
	}//end refuseParent()

	/**
	 * Refuse the write with an error and stop the other listeners.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The event.
	 * @param array<string,string>                    $error The error.
	 *
	 * @return bool Always true.
	 */
	private function refuse(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $error): bool {
		$event->setErrors($error);
		$event->stopPropagation();

		return true;
	}//end refuse()

	/**
	 * The parent a write sets, empty when it sets none or keeps the stored one.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return string
	 */
	private function newParent(array $write): string {
		$parentId = $this->referenceId(value: ($write['data']['parent'] ?? null));
		if ($parentId !== '' && $write['old'] !== null && $this->referenceId(value: ($write['old']['parent'] ?? null)) === $parentId) {
			return '';
		}

		return $parentId;
	}//end newParent()

	/**
	 * The project the parent check runs for: empty on a create, the uuid otherwise.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return string
	 */
	private function targetId(array $write): string {
		if ($write['event'] instanceof ObjectCreatingEvent === true) {
			return '';
		}

		return $write['uuid'];
	}//end targetId()

	/**
	 * The error for a parent the caller cannot read, or that makes a cycle or too deep a chain.
	 *
	 * @param string $projectId The project UUID, empty on a create.
	 * @param string $parentId  The new parent UUID.
	 *
	 * @return array<string,string>|null Null when the parent is fine.
	 */
	private function parentError(string $projectId, string $parentId): ?array {
		// Checked first, so the cycle and depth answers never describe a project the caller cannot see.
		if ($this->rules->callerReads(projectId: $parentId) === false) {
			return ['code' => self::ERROR_PARENT, 'message' => 'The parent project does not exist or you cannot see it.', 'project' => $projectId];
		}

		$refusal = $this->rules->refusal(projectId: $projectId, parentId: $parentId);
		if ($refusal === null) {
			return null;
		}

		$error = ['code' => self::ERROR_DEPTH, 'message' => 'Projects nest three levels deep at most: programme, project and subproject.'];
		if ($refusal === ProjectRulesService::CYCLE) {
			$error = ['code' => self::ERROR_CYCLE, 'message' => 'A project cannot sit under one of its own subprojects.'];
		}

		return $error + ['project' => $projectId, 'parent' => $parentId];
	}//end parentError()

	/**
	 * Set a project's readers to the managers of its portfolio.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The event.
	 * @param array<string,mixed>                     $data  The project's new data.
	 *
	 * @return void
	 */
	private function deriveReaders(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $data): void {
		$readers = $this->managersOf(portfolioId: $this->referenceId(value: ($data['portfolio'] ?? null)));
		if ($this->membership->normalise(members: ($data[ProjectMembershipService::READERS_FIELD] ?? [])) === $readers) {
			return;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), [ProjectMembershipService::READERS_FIELD => $readers]));
	}//end deriveReaders()

	/**
	 * Hand a portfolio's new managers to every project in it and to their objects.
	 *
	 * @param array<string,mixed> $write The portfolio update.
	 *
	 * @return void
	 */
	private function onManagersChange(array $write): void {
		$managers = $this->membership->normalise(members: ($write['data']['managers'] ?? []));
		if ($this->membership->normalise(members: ($write['old']['managers'] ?? [])) === $managers) {
			return;
		}

		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['portfolio' => $write['uuid']]) as $project) {
			$this->writeProject(projectId: $project['id'], data: array_merge($project['data'], [ProjectMembershipService::READERS_FIELD => $managers]));
		}
	}//end onManagersChange()

	/**
	 * Take every project out of a portfolio that is being deleted.
	 *
	 * @param string $portfolioId The portfolio UUID.
	 *
	 * @return void
	 */
	private function releaseProjects(string $portfolioId): void {
		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['portfolio' => $portfolioId]) as $project) {
			$this->writeProject(
				projectId: $project['id'],
				data: array_merge($project['data'], ['portfolio' => null, ProjectMembershipService::READERS_FIELD => []])
			);
		}
	}//end releaseProjects()

	/**
	 * Write a project as the system and copy its readers onto its objects.
	 *
	 * The system write raises no post-event, so the objects are brought in step here.
	 *
	 * @param string              $projectId The project UUID.
	 * @param array<string,mixed> $data      The project's new data.
	 *
	 * @return void
	 */
	private function writeProject(string $projectId, array $data): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			$this->logger->warning('Planninq: OpenRegister has no system-operation scope; portfolio readers were not updated', ['project' => $projectId]);
			return;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$class::run(
				static fn () => $objectService->saveObject(
					object: $data,
					register: ProjectMembershipService::REGISTER,
					schema: ProjectMembershipService::PROJECT_SCHEMA,
					uuid: $projectId,
					_rbac: false,
					_multitenancy: false,
					silent: true,
					_validation: false
				)
			);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: could not update the portfolio readers of a project', ['project' => $projectId, 'exception' => $e->getMessage()]);
			return;
		}

		$field   = ProjectMembershipService::READERS_FIELD;
		$written = $this->membership->syncProjectMembers(projectId: $projectId, members: ($data[$field] ?? []), field: $field);
		$written += $this->finance->syncProject(projectId: $projectId);
		$this->logger->info('Planninq: portfolio readers updated on a project and its objects', ['project' => $projectId, 'written' => $written]);
	}//end writeProject()

	/**
	 * The managers of a portfolio, sorted and distinct; empty for none or an unknown portfolio.
	 *
	 * @param string $portfolioId The portfolio UUID.
	 *
	 * @return array<int,string>
	 */
	private function managersOf(string $portfolioId): array {
		$portfolio = $this->membership->objectData(schema: self::PORTFOLIO_SCHEMA, id: $portfolioId);

		return $this->membership->normalise(members: ($portfolio['managers'] ?? []));
	}//end managersOf()

	/**
	 * A reference value as a UUID string.
	 *
	 * @param mixed $value A UUID string or a resolved object.
	 *
	 * @return string
	 */
	private function referenceId(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['@self']['id'] ?? ''));
		}

		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end referenceId()

	/**
	 * Whether OpenRegister runs this write as a trusted system operation.
	 *
	 * @return bool
	 */
	private function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()
}//end class
