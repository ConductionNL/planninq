<?php

/**
 * Planninq ReleaseShipGuardListener
 *
 * A release is shipped through planninq's ship action, which moves its
 * unfinished tasks and stamps `releasedAt` with the server's time as one
 * system write. A person's own create or update of a release can therefore
 * neither mark it released nor set or back-date `releasedAt`: those values are
 * put back to what is stored (planned and empty on a create).
 *
 * No session (occ, cron, a repair step) and OpenRegister system operations pass.
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
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;

/**
 * Keeps a release's shipped state to the ship action.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
 */
class ReleaseShipGuardListener implements IEventListener {

	/**
	 * The status only the ship action sets.
	 *
	 * @var string
	 */
	private const RELEASED = 'released';

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolver $scopeResolver Tells a planninq release from any other object.
	 * @param IUserSession      $userSession   Tells a person's write from occ, cron and repair steps.
	 */
	public function __construct(
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * Put back a person's change to the shipped state.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
	 */
	public function handle(Event $event): void {
		if ($this->byPerson() === false) {
			return;
		}

		if ($event instanceof ObjectCreatingEvent === true) {
			$this->keep(event: $event, object: $event->getObject(), stored: ['status' => 'planned', 'releasedAt' => null]);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->keep(event: $event, object: $event->getNewObject(), stored: (array)$event->getOldObject()?->getObject());
		}
	}//end handle()

	/**
	 * Restore `releasedAt`, and `status` when it moves into released.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param object                                  $object The release being written.
	 * @param array<string,mixed>                     $stored The stored release, or the defaults of a new one.
	 *
	 * @return void
	 */
	private function keep(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, array $stored): void {
		$isRelease = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === 'projectRelease';
		if ($isRelease === false) {
			return;
		}

		$data    = (array)$object->getObject();
		$changes = [];
		if (($data['releasedAt'] ?? null) !== ($stored['releasedAt'] ?? null)) {
			$changes['releasedAt'] = ($stored['releasedAt'] ?? null);
		}

		if (($data['status'] ?? null) === self::RELEASED && ($stored['status'] ?? null) !== self::RELEASED) {
			$changes['status'] = ($stored['status'] ?? 'planned');
		}

		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end keep()

	/**
	 * Whether a signed-in person writes, not occ, cron or a system operation.
	 *
	 * @return bool
	 */
	private function byPerson(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return ($this->userSession->getUser() !== null && (class_exists($class) === false || $class::isActive() === false));
	}//end byPerson()
}//end class
