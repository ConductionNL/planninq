<?php

/**
 * Planninq Activity Provider.
 *
 * Renders Planninq task lifecycle events (created / status changed / assigned /
 * due date changed / deleted) into human-readable subjects for the Nextcloud
 * Activity app, in the user's language.
 *
 * @category Activity
 * @package  OCA\Planninq\Activity
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Activity;

use OCA\Planninq\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

/**
 * Activity provider for parsing Planninq task events.
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class Provider implements IProvider {
	/**
	 * Subjects this provider knows how to render.
	 *
	 * @var string[]
	 */
	public const HANDLED_SUBJECTS = [
		'task_created',
		'task_status_changed',
		'task_assigned_activity',
		'task_due_date_changed',
		'task_deleted',
	];

	/**
	 * Constructor.
	 *
	 * @param IFactory $l10nFactory The l10n factory.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param ProviderSubjectHandler $subjectHandler The subject handler.
	 */
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private ProviderSubjectHandler $subjectHandler,
	) {
	}//end __construct()

	/**
	 * Parse an activity event into a human-readable, localized format.
	 *
	 * @param string $language The language code.
	 * @param IEvent $event The event to parse.
	 * @param ?IEvent $previousEvent The previous event or null.
	 *
	 * @return IEvent The parsed event.
	 *
	 * @throws UnknownActivityException When the event is not a Planninq task event.
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $previousEvent is mandated
	 *                   by OCP\Activity\IProvider::parse(). It exists so a
	 *                   provider can COLLAPSE consecutive related entries into
	 *                   one; Planninq renders every task event as its own entry,
	 *                   so the parameter is genuinely unread — it cannot be
	 *                   dropped without breaking the interface contract.
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new UnknownActivityException();
		}

		if (in_array($event->getSubject(), self::HANDLED_SUBJECTS, true) === false) {
			throw new UnknownActivityException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $language);

		$this->subjectHandler->applySubjectText(
			event: $event,
			l: $l,
			params: $event->getSubjectParameters()
		);

		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(appName: Application::APP_ID, file: 'app.svg')
			)
		);

		return $event;
	}//end parse()
}//end class
