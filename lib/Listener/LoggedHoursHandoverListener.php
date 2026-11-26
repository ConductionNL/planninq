<?php

/**
 * Planninq LoggedHoursHandoverListener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Logged hours live once, on humaniq's `TimeEntry`. Planninq's own pages
 * write them there and create the `plannedTimeEntry` already pointing at the
 * humaniq entry. A client that creates a `plannedTimeEntry` with the hours on
 * it instead (the portal's "Log time" action, the plain objects API) would
 * otherwise leave those hours where no report or payroll run reads them, and
 * where planninq's own pages no longer look.
 *
 * After such a create this listener books the hours on humaniq with the same
 * mover the MoveLoggedHoursToHumaniq repair step uses, and points the row at
 * the humaniq entry. A row that already references one, carries no hours, or
 * is created while humaniq is not enabled is left alone; the repair step
 * moves the last kind once humaniq is there.
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
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\Planninq\Kernel\Contracts\TaskScopeResolverInterface;
use OCA\Planninq\Service\LoggedHoursMover;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Books the hours of a newly created planninq time entry on humaniq.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
class LoggedHoursHandoverListener implements IEventListener {

	/**
	 * The app that owns the hours.
	 *
	 * @var string
	 */
	public const HOURS_APP = 'humaniq';

	/**
	 * OpenRegister's ObjectService, resolved at runtime.
	 *
	 * @var string
	 */
	private const OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolverInterface $scopeResolver Tells a planninq time entry from any other object.
	 * @param LoggedHoursMover           $mover         Books one row's hours on humaniq.
	 * @param IAppManager                $appManager    Tells whether humaniq is enabled.
	 * @param ContainerInterface         $container     Resolves OpenRegister's ObjectService.
	 * @param LoggerInterface            $logger        Records a handover that could not start.
	 */
	public function __construct(
		private TaskScopeResolverInterface $scopeResolver,
		private LoggedHoursMover $mover,
		private IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Move the hours of a just-created planninq time entry to humaniq.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
	 */
	public function handle(Event $event): void {
		$row = $this->rowOf(event: $event);
		if ($row === null || $this->mover->hasHoursToMove(data: $row['data']) === false) {
			return;
		}

		if ($this->appManager->isEnabledForAnyone(self::HOURS_APP) === false) {
			return;
		}

		try {
			$objectService = $this->container->get(self::OBJECT_SERVICE);
		} catch (Throwable $exception) {
			$this->logger->error(
				'Planninq: OpenRegister could not be reached to move the hours of a new time entry to humaniq; the next upgrade tries again',
				['object' => $row['id'], 'exception' => $exception->getMessage()]
			);
			return;
		}

		$this->mover->move(objectService: $objectService, row: $row);
	}//end handle()

	/**
	 * The id and data of the created planninq time entry, or null for any other event or object.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return array{id: string, data: array<string,mixed>}|null
	 */
	private function rowOf(Event $event): ?array {
		if ($event instanceof ObjectCreatedEvent === false) {
			return null;
		}

		$object = $event->getObject();
		$slug   = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		$id     = (string)($object->getUuid() ?? '');
		if ($slug !== LoggedHoursMover::SCHEMA || $id === '') {
			return null;
		}

		return ['id' => $id, 'data' => (array)$object->getObject()];
	}//end rowOf()
}//end class
