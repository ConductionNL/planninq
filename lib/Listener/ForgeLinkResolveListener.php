<?php

/**
 * Planninq ForgeLinkResolveListener
 *
 * A code link (`forgeLink`) names its task by key when the integration
 * writes it (integriq only knows the text of a commit message), or by id
 * when a person adds it on the task page. Before the link is stored this
 * listener finds the task, and writes the task, the task's project and the
 * task's key onto the link, whatever the client sent for them. It refuses a
 * link whose task does not resolve, and a second link added by hand with the
 * same forge id on the same task. A repeat from the integration (a later event
 * about the same merge request) replaces the older link instead, so the state
 * follows the forge, a webhook retry adds nothing, and the forge never sees a
 * refused delivery.
 *
 * Integriq has no text functions to cut a key out of a commit message, so it
 * may put the whole forge text in `taskKey`; the first key in it that belongs
 * to a task wins, and the link keeps that key.
 *
 * The membership gate (ProjectMemberAccessListener) reads the project from
 * the task through the same ProjectMembershipService::linkedTask(), so the
 * order in which the two run does not matter.
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
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Resolves the task and project of a new code link, or refuses it.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
 */
class ForgeLinkResolveListener implements IEventListener {

	/**
	 * Error code of a link whose task does not resolve.
	 *
	 * @var string
	 */
	public const ERROR_NO_TASK = 'planninq-forge-link-no-task';

	/**
	 * Error code of a second link to the same forge item on one task.
	 *
	 * @var string
	 */
	public const ERROR_DUPLICATE = 'planninq-forge-link-duplicate';

	/**
	 * The schema this listener acts on.
	 *
	 * @var string
	 */
	private const SCHEMA = 'forgeLink';

	/**
	 * The `source` of a link the integration writes.
	 *
	 * @var string
	 */
	private const INTEGRATION = 'integriq';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Finds the task and reads planninq objects as the system.
	 * @param TaskScopeResolver        $scopeResolver Tells a code link from any other object.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Resolve or refuse a new code link.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === false) {
			return;
		}

		$object = $event->getObject();
		$slug   = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== self::SCHEMA) {
			return;
		}

		$data = (array)$object->getObject();
		$task = $this->membership->linkedTask(data: $data);
		if ($task === null) {
			$this->refuse(event: $event, message: 'No task has this key.', code: self::ERROR_NO_TASK, data: $data);
			return;
		}

		$externalId = trim((string)($data['externalId'] ?? ''));
		if ($externalId !== '') {
			$repeats = $this->membership->rows(schema: self::SCHEMA, filters: ['task' => $task['id'], 'externalId' => $externalId]);
			if ($repeats !== [] && ($data['source'] ?? '') !== self::INTEGRATION) {
				$this->refuse(event: $event, message: 'This link is already on the task.', code: self::ERROR_DUPLICATE, data: $data);
				return;
			}

			// A later forge event about the same item replaces the integration's
			// link, so its state follows the forge and no second link appears.
			foreach ($repeats as $repeat) {
				$this->membership->removeObject(schema: self::SCHEMA, id: $repeat['id']);
			}
		}

		$changes = [
			'task'    => $task['id'],
			'project' => $this->membership->projectIdFor(schemaSlug: 'task', data: $task['data']),
			'taskKey' => (string)($task['data']['key'] ?? ''),
		];
		if (in_array(($data['source'] ?? null), ['manual', self::INTEGRATION], true) === false) {
			$changes['source'] = 'manual';
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
	}//end handle()

	/**
	 * Refuse the create; OpenRegister answers it with HTTP 422.
	 *
	 * @param ObjectCreatingEvent $event   The event.
	 * @param string              $message What went wrong.
	 * @param string              $code    The error code a client branches on.
	 * @param array<string,mixed> $data    The link as sent.
	 *
	 * @return void
	 */
	private function refuse(ObjectCreatingEvent $event, string $message, string $code, array $data): void {
		$event->setErrors(['message' => $message, 'code' => $code, 'taskKey' => (string)($data['taskKey'] ?? '')]);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a code link', ['code' => $code, 'taskKey' => ($data['taskKey'] ?? null)]);
	}//end refuse()
}//end class
