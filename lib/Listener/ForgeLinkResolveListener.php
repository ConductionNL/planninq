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
 * `source` says who wrote the link: `integriq` only for a write without a
 * person behind it (the integration's webhook, cron or a system operation).
 * A person's link is always `manual`, on create and on update, so nobody can
 * add a link that looks like the integration's and that no member can edit
 * or remove afterwards.
 *
 * Integriq has no text functions to cut a key out of a commit message, so it
 * may put the whole forge text in `taskKey`; the first key in it that belongs
 * to a task wins, and the link keeps that key.
 *
 * The membership gate (ProjectMemberAccessListener) reads the project from
 * the task through the same ForgeLinkService::linkedTask(), so the
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
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ForgeLinkService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
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
	private const SCHEMA = ForgeLinkService::SCHEMA;

	/**
	 * The `source` of a link the integration writes.
	 *
	 * @var string
	 */
	private const INTEGRATION = 'integriq';

	/**
	 * The source of a link a person adds.
	 *
	 * @var string
	 */
	private const MANUAL = 'manual';

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ForgeLinkService         $forgeLinks    Finds the task and the repeats of a link.
	 * @param TaskScopeResolver        $scopeResolver Tells a code link from any other object.
	 * @param LoggerInterface          $logger        The logger.
	 * @param IUserSession             $userSession   Tells a person's write from the integration's.
	 */
	public function __construct(
		private ForgeLinkService $forgeLinks,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
		private IUserSession $userSession,
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
		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->keepSource(event: $event);
			return;
		}

		if ($event instanceof ObjectCreatingEvent === false || $this->isLink(object: $event->getObject()) === false) {
			return;
		}

		$object = $event->getObject();

		$data = (array)$object->getObject();
		$task = $this->forgeLinks->linkedTask(data: $data);
		if ($task === null) {
			$this->refuse(event: $event, message: 'No task has this key.', code: self::ERROR_NO_TASK, data: $data);
			return;
		}

		$source  = $this->sourceFor(sent: ($data['source'] ?? null));
		$repeats = $this->forgeLinks->repeats(taskId: $task['id'], externalId: trim((string)($data['externalId'] ?? '')));
		if ($repeats !== [] && $source !== self::INTEGRATION) {
			$this->refuse(event: $event, message: 'This link is already on the task.', code: self::ERROR_DUPLICATE, data: $data);
			return;
		}

		// A later forge event about the same item replaces the integration's
		// link, so its state follows the forge and no second link appears.
		foreach ($repeats as $repeat) {
			$this->forgeLinks->remove(id: $repeat['id']);
		}

		$changes = [
			'task'    => $task['id'],
			'project' => $task['project'],
			'taskKey' => (string)($task['data']['key'] ?? ''),
		];
		if (($data['source'] ?? null) !== $source) {
			$changes['source'] = $source;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
	}//end handle()

	/**
	 * Put back a link's stored source when a person's update sends another one.
	 *
	 * @param ObjectUpdatingEvent $event The event.
	 *
	 * @return void
	 */
	private function keepSource(ObjectUpdatingEvent $event): void {
		$old = $event->getOldObject();
		if ($old === null || $this->isLink(object: $event->getNewObject()) === false || $this->byPerson() === false) {
			return;
		}

		$stored = (((array)$old->getObject())['source'] ?? null);
		if ((((array)$event->getNewObject()->getObject())['source'] ?? null) !== $stored) {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['source' => $stored]));
		}
	}//end keepSource()

	/**
	 * The source a new link gets: `manual` for a person, the sent one (if known) for the integration.
	 *
	 * @param mixed $sent The source the client sent.
	 *
	 * @return string
	 */
	private function sourceFor(mixed $sent): string {
		if ($sent === self::INTEGRATION && $this->byPerson() === false) {
			return self::INTEGRATION;
		}

		return self::MANUAL;
	}//end sourceFor()

	/**
	 * Whether a signed-in person writes, not the integration, cron or a system operation.
	 *
	 * @return bool
	 */
	private function byPerson(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return ($this->userSession->getUser() !== null && (class_exists($class) === false || $class::isActive() === false));
	}//end byPerson()

	/**
	 * Whether the object is a planninq code link.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isLink(object $object): bool {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === self::SCHEMA;
	}//end isLink()

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
