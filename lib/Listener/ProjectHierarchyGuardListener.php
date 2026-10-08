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
	 * Slug of the wiki page schema.
	 *
	 * @var string
	 */
	public const WIKI_SCHEMA = 'wikiPage';

	/**
	 * Error code of a wiki page whose parent is itself, a subpage, or on another project.
	 *
	 * @var string
	 */
	public const ERROR_WIKI_PARENT = 'planninq-wiki-parent';

	/**
	 * Error code of a custom field value that does not fit its field.
	 *
	 * @var string
	 */
	public const ERROR_FIELD = 'planninq-project-field';

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

		if ($event instanceof ObjectUpdatingEvent === true) {
			$oldData = [];
			$old     = $event->getOldObject();
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$this->route(event: $event, object: $event->getNewObject(), oldData: $oldData);
			return;
		}

		if ($event instanceof ObjectCreatingEvent === true || $event instanceof ObjectDeletingEvent === true) {
			$this->route(event: $event, object: $event->getObject(), oldData: null);
		}
	}//end handle()

	/**
	 * Send a project write to the reader derivation and a portfolio write to the fan-out.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param object                                                      $object  The object.
	 * @param array<string,mixed>|null                                    $oldData The stored object on an update.
	 *
	 * @return void
	 */
	private function route(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		$data = (array)$object->getObject();

		if ($slug === ProjectMembershipService::PROJECT_SCHEMA && $event instanceof ObjectDeletingEvent === false) {
			if ($this->refuseParent(event: $event, projectId: (string)($object->getUuid() ?? ''), data: $data, oldData: $oldData) === true) {
				return;
			}

			if ($this->refuseFields(event: $event, data: $data, oldData: $oldData) === true) {
				return;
			}

			$this->deriveReaders(event: $event, data: $data);
			return;
		}

		if ($slug === self::WIKI_SCHEMA && $event instanceof ObjectDeletingEvent === false) {
			$this->refuseWikiParent(event: $event, pageId: (string)($object->getUuid() ?? ''), data: $data, oldData: $oldData);
			return;
		}

		if ($slug !== self::PORTFOLIO_SCHEMA) {
			return;
		}

		$portfolioId = (string)($object->getUuid() ?? '');
		if ($event instanceof ObjectDeletingEvent === true) {
			$this->releaseProjects(portfolioId: $portfolioId);
			return;
		}

		if ($oldData !== null) {
			$this->onManagersChange(portfolioId: $portfolioId, data: $data, oldData: $oldData);
		}
	}//end route()

	/**
	 * Refuse a wiki page parent that is the page itself, one of its subpages or on another project.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event   The event.
	 * @param string                                  $pageId  The page UUID, empty on create.
	 * @param array<string,mixed>                     $data    The page's new data.
	 * @param array<string,mixed>|null                $oldData The stored page on an update.
	 *
	 * @return bool True when the write was refused.
	 *
	 * @spec openspec/changes/projects-wiki/tasks.md#task-1.2
	 */
	private function refuseWikiParent(ObjectCreatingEvent|ObjectUpdatingEvent $event, string $pageId, array $data, ?array $oldData): bool {
		$parentId = $this->referenceId(value: ($data['parent'] ?? null));
		if ($parentId === '' || ($oldData !== null && $this->referenceId(value: ($oldData['parent'] ?? null)) === $parentId)) {
			return false;
		}

		$message = '';
		$project = $this->referenceId(value: ($data['project'] ?? null));
		$cursor  = $parentId;
		for ($depth = 0; $depth < 50 && $cursor !== ''; $depth++) {
			if ($pageId !== '' && $cursor === $pageId) {
				$message = 'A page cannot sit under itself or one of its own subpages.';
				break;
			}

			$page = $this->membership->objectData(schema: self::WIKI_SCHEMA, id: $cursor);
			if ($page === null) {
				$message = 'The parent page does not exist.';
				break;
			}

			if ($depth === 0 && $this->referenceId(value: ($page['project'] ?? null)) !== $project) {
				$message = 'A page can only sit under a page of the same project.';
				break;
			}

			$cursor = $this->referenceId(value: ($page['parent'] ?? null));
		}

		if ($message === '') {
			return false;
		}

		$event->setErrors(['code' => self::ERROR_WIKI_PARENT, 'message' => $message, 'page' => $pageId, 'parent' => $parentId]);
		$event->stopPropagation();

		return true;
	}//end refuseWikiParent()

	/**
	 * Refuse custom field values that do not fit their fields, when the write changes them.
	 *
	 * A write that leaves the values alone is not held to a field added later,
	 * so creating or renaming a project never fails on a new required field.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event   The event.
	 * @param array<string,mixed>                     $data    The project's new data.
	 * @param array<string,mixed>|null                $oldData The stored project on an update.
	 *
	 * @return bool True when the write was refused.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	private function refuseFields(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $data, ?array $oldData): bool {
		$values = ($data['customFields'] ?? null);
		if (is_array($values) === false || $values === ($oldData['customFields'] ?? null)) {
			return false;
		}

		$stored = ($oldData['customFields'] ?? []);
		if (is_array($stored) === false) {
			$stored = [];
		}

		$problem = $this->rules->problem(values: $values, stored: $stored);
		if ($problem === null) {
			return false;
		}

		$event->setErrors(['code' => self::ERROR_FIELD, 'message' => $problem]);
		$event->stopPropagation();

		return true;
	}//end refuseFields()

	/**
	 * Refuse a new parent that makes a cycle or a chain deeper than three levels.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The event.
	 * @param string                                  $projectId The project UUID.
	 * @param array<string,mixed>                     $data      The project's new data.
	 * @param array<string,mixed>|null                $oldData   The stored project on an update.
	 *
	 * @return bool True when the write was refused.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
	 */
	private function refuseParent(ObjectCreatingEvent|ObjectUpdatingEvent $event, string $projectId, array $data, ?array $oldData): bool {
		$parentId = $this->referenceId(value: ($data['parent'] ?? null));
		if ($parentId === '' || ($oldData !== null && $this->referenceId(value: ($oldData['parent'] ?? null)) === $parentId)) {
			return false;
		}

		if ($event instanceof ObjectCreatingEvent === true) {
			$projectId = '';
		}

		$refusal = $this->rules->refusal(projectId: $projectId, parentId: $parentId);
		if ($refusal === null) {
			return false;
		}

		$error = ['code' => self::ERROR_DEPTH, 'message' => 'Projects nest three levels deep at most: programme, project and subproject.'];
		if ($refusal === ProjectRulesService::CYCLE) {
			$error = ['code' => self::ERROR_CYCLE, 'message' => 'A project cannot sit under one of its own subprojects.'];
		}

		$event->setErrors($error + ['project' => $projectId, 'parent' => $parentId]);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a project parent', ['code' => $error['code'], 'project' => $projectId]);

		return true;
	}//end refuseParent()

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
	 * @param string              $portfolioId The portfolio UUID.
	 * @param array<string,mixed> $data        The portfolio's new data.
	 * @param array<string,mixed> $oldData     The stored portfolio.
	 *
	 * @return void
	 */
	private function onManagersChange(string $portfolioId, array $data, array $oldData): void {
		$managers = $this->membership->normalise(members: ($data['managers'] ?? []));
		if ($this->membership->normalise(members: ($oldData['managers'] ?? [])) === $managers) {
			return;
		}

		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['portfolio' => $portfolioId]) as $project) {
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
