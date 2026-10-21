<?php

/**
 * Planninq TaskReportReadersListener
 *
 * A report shared with "everyone who can read its projects" carries who that
 * is, because OpenRegister's rules match fields of the report itself and
 * cannot look into its projects. On every save the listener writes the users
 * (`readers`) and groups (`readerGroups`) who read at least one of the
 * report's projects: their members, managers, owner, viewers and portfolio
 * managers, and their owning, manager, member and viewer groups. A private
 * report carries empty lists. Whatever the client sent is overwritten.
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
 * @spec openspec/specs/custom-reports/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Stamps who may read a shared report.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/custom-reports/spec.md
 */
class TaskReportReadersListener implements IEventListener {

	/**
	 * The report schema slug.
	 *
	 * @var string
	 */
	public const SCHEMA = 'taskReport';

	/**
	 * The `shared` value that opens a report to the readers of its projects.
	 *
	 * @var string
	 */
	private const SHARED_WITH_READERS = 'readers';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads the report's projects.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq report from any other object.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
	) {
	}//end __construct()

	/**
	 * Stamp the reader lists on a pre-create or pre-update.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-reports/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->stamp(event: $event, object: $event->getObject());
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->stamp(event: $event, object: $event->getNewObject());
		}
	}//end handle()

	/**
	 * Overwrite the reader lists of a report when they are not what its projects say.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param object                                  $object The object being written.
	 *
	 * @return void
	 */
	private function stamp(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object): void {
		if ($this->isReport(object: $object) === false) {
			return;
		}

		$data    = (array)$object->getObject();
		$changes = [];
		foreach ($this->readersOf(data: $data) as $field => $values) {
			if (array_key_exists($field, $data) === false || $this->membership->normalise(members: $data[$field]) !== $values) {
				$changes[$field] = $values;
			}
		}

		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end stamp()

	/**
	 * The users and groups who read at least one of the report's projects, or none for a private report.
	 *
	 * @param array<string,mixed> $data The report.
	 *
	 * @return array{readers: array<int,string>, readerGroups: array<int,string>}
	 */
	private function readersOf(array $data): array {
		$users  = [];
		$groups = [];
		if (($data['shared'] ?? null) === self::SHARED_WITH_READERS) {
			foreach ($this->membership->normalise(members: ($data['projects'] ?? null)) as $projectId) {
				$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
				if ($project === null) {
					continue;
				}

				$lists  = $this->membership->childLists(project: $project);
				$users  = array_merge(
					$users,
					$lists['members'],
					$lists['viewers'],
					$this->membership->normalise(members: ($project[ProjectMembershipService::READERS_FIELD] ?? null))
				);
				$groups = array_merge($groups, $lists['memberGroups'], $lists['viewerGroups']);
			}
		}

		return [
			'readers'      => $this->membership->normalise(members: $users),
			'readerGroups' => $this->membership->normalise(members: $groups),
		];
	}//end readersOf()

	/**
	 * Whether the object is a planninq report.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isReport(object $object): bool {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		return ($slug === self::SCHEMA);
	}//end isReport()
}//end class
