<?php

/**
 * Planninq Notifier
 *
 * Renders planninq's own Nextcloud notifications: a project export that is
 * ready in the requester's Files, which opens the export folder with the zip
 * highlighted, and one that failed, with the reason.
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
 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Notification;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\BackgroundJob\ExportProjectJob;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the project export notifications.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
 */
class Notifier implements INotifier {

	/**
	 * Constructor
	 *
	 * @param IFactory      $l10nFactory  The l10n factory.
	 * @param IURLGenerator $urlGenerator Builds the Files link and the icon.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
	 */
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The notifier id.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
	 */
	public function getID(): string {
		return Application::APP_ID;
	}//end getID()

	/**
	 * The notifier's human-readable name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
	 */
	public function getName(): string {
		return 'Planninq';
	}//end getName()

	/**
	 * Render a planninq notification in the user's language.
	 *
	 * @param INotification $notification The notification.
	 * @param string        $languageCode The user's language.
	 *
	 * @return INotification
	 *
	 * @throws UnknownNotificationException For another app's or an unknown subject.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-3.1
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l          = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$parameters = $notification->getSubjectParameters();
		$project    = (string)($parameters['project'] ?? '');

		switch ($notification->getSubject()) {
			case 'project_export_ready':
				$notification->setParsedSubject($l->t('Export of %s is ready', [$project]));
				$notification->setLink($this->filesLink(path: (string)($parameters['path'] ?? '')));
				break;
			case 'project_export_failed':
				$notification->setParsedSubject(
					$l->t('Export of %1$s failed: %2$s', [$project, $this->reason(l: $l, reason: (string)($parameters['reason'] ?? ''))])
				);
				break;
			default:
				throw new UnknownNotificationException();
		}

		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));

		return $notification;
	}//end prepare()

	/**
	 * The Files link that opens the zip's folder with the zip highlighted.
	 *
	 * @param string $path The zip's path in the user's Files.
	 *
	 * @return string
	 */
	private function filesLink(string $path): string {
		$slash = strrpos($path, '/');
		$dir   = '/';
		$name  = $path;
		if ($slash !== false) {
			$dir  = '/' . substr($path, 0, $slash);
			$name = substr($path, $slash + 1);
		}

		return $this->urlGenerator->linkToRouteAbsolute('files.view.index', ['dir' => $dir, 'scrollto' => $name]);
	}//end filesLink()

	/**
	 * The failure reason in the user's language.
	 *
	 * @param IL10N  $l      The user's l10n.
	 * @param string $reason One of ExportProjectJob's REASON_* constants.
	 *
	 * @return string
	 */
	private function reason(IL10N $l, string $reason): string {
		return match ($reason) {
			ExportProjectJob::REASON_PERMISSION => $l->t('you do not have the export permission'),
			ExportProjectJob::REASON_SPACE      => $l->t('not enough storage space'),
			default                             => $l->t('the export could not be written'),
		};
	}//end reason()
}//end class
