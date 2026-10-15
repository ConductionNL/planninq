<?php

/**
 * Planninq EmailOptInRecipientResolver
 *
 * The recipient of planninq's declared email rules: the task's assignee, but
 * only when they switched "Also send these to me by email" on, kept the
 * matching in-app switch on, and have an email address. OpenRegister calls
 * it on every dispatch of an `email` rule whose recipient is
 * `{kind: expression, resolver: <subclass FQCN>}`, so the switch is checked
 * each time, not when the rule was written.
 *
 * @category Notification
 * @package  OCA\Planninq\Notification
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Notification;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Service\Notification\RecipientResolverInterface;
use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\NotificationSwitchService;
use OCP\IConfig;
use OCP\IUserManager;

/**
 * The assignee, when they asked for mail about this kind of notification.
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */
abstract class EmailOptInRecipientResolver implements RecipientResolverInterface {

	/**
	 * Constructor.
	 *
	 * @param IConfig      $config      The per-user switches.
	 * @param IUserManager $userManager The assignee's account, for the email address.
	 */
	public function __construct(
		private IConfig $config,
		private IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * The user value of the in-app switch this mail follows; `false` silences the mail.
	 *
	 * @return string
	 */
	abstract protected function inAppSwitch(): string;

	/**
	 * The assignee's uid, or nobody.
	 *
	 * @param ObjectEntity         $object  The task.
	 * @param array<string, mixed> $context Trigger extras (unused).
	 *
	 * @return array<int, string>
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The interface passes the trigger extras; the switches alone decide.
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	public function resolve(ObjectEntity $object, array $context): array {
		$uid = ($object->getObject()['assignedTo'] ?? null);
		if (is_string($uid) === false || $uid === '') {
			return [];
		}

		$byEmail = $this->config->getUserValue($uid, Application::APP_ID, NotificationSwitchService::EMAIL_KEY, 'false');
		$inApp   = $this->config->getUserValue($uid, Application::APP_ID, $this->inAppSwitch(), 'true');
		if ($byEmail !== 'true' || $inApp === 'false') {
			return [];
		}

		$user = $this->userManager->get($uid);
		if ($user === null || (string)$user->getEMailAddress() === '') {
			return [];
		}

		return [$uid];
	}//end resolve()
}//end class
