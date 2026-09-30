<?php

/**
 * Planninq BoardFilterOwnerListener
 *
 * A saved board filter belongs to the person who saved it. On create the
 * listener sets `owner` to the caller, whatever the client sent; on update it
 * puts the stored owner back. The register's update and delete rules match
 * on `owner`, so this is what keeps a shared filter out of other members'
 * hands.
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
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;

/**
 * Stamps and keeps the owner of a saved board filter.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.1
 */
class BoardFilterOwnerListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolver $scopeResolver Tells a planninq saved filter from any other object.
	 * @param IUserSession      $userSession   The caller.
	 */
	public function __construct(
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * Handle a pre-save event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.1
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$user = $this->userSession->getUser();
			if ($user !== null && $this->isBoardFilter(object: $event->getObject()) === true) {
				$event->setModifiedData(array_merge($event->getModifiedData(), ['owner' => $user->getUID()]));
			}

			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$old = $event->getOldObject();
			if ($old === null || $this->isBoardFilter(object: $event->getNewObject()) === false) {
				return;
			}

			$stored = (((array)$old->getObject())['owner'] ?? null);
			$sent   = (((array)$event->getNewObject()->getObject())['owner'] ?? null);
			if (is_string($stored) === true && $stored !== '' && $sent !== $stored) {
				$event->setModifiedData(array_merge($event->getModifiedData(), ['owner' => $stored]));
			}
		}
	}//end handle()

	/**
	 * Whether the object is a planninq saved filter.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isBoardFilter(object $object): bool {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === 'boardFilter';
	}//end isBoardFilter()
}//end class
