<?php

/**
 * Unit tests for Provider::parse() refusals.
 *
 * The golden test covers the subjects the provider renders; this covers the two
 * events it refuses with UnknownActivityException — another app's event and a
 * subject it does not handle — which the Activity app relies on to pass the
 * event to the next provider.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Activity
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Activity;

use OCA\Planninq\Activity\Provider;
use OCA\Planninq\Activity\ProviderSubjectHandler;
use OCA\Planninq\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Provider refusals.
 *
 * @covers \OCA\Planninq\Activity\Provider
 * @uses   \OCA\Planninq\Activity\ProviderSubjectHandler
 */
class ProviderRefusalTest extends TestCase {

	/**
	 * A provider whose l10n factory must never be asked, since a refusal is decided first.
	 *
	 * @return Provider
	 */
	private function provider(): Provider {
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->expects($this->never())->method('get');

		return new Provider(
			l10nFactory: $factory,
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class),
			subjectHandler: new ProviderSubjectHandler()
		);

	}//end provider()

	/**
	 * An event carrying a subject, for the given app.
	 *
	 * @param string $app     The event's app.
	 * @param string $subject The event's subject.
	 *
	 * @return IEvent
	 */
	private function event(string $app, string $subject): IEvent {
		$event = $this->createMock(originalClassName: IEvent::class);
		$event->method('getApp')->willReturn($app);
		$event->method('getSubject')->willReturn($subject);

		return $event;

	}//end event()

	/**
	 * An event from another app is refused before the subject is read.
	 *
	 * @return void
	 */
	public function testAnotherAppIsRefused(): void {
		$this->expectException(UnknownActivityException::class);

		$this->provider()->parse('en', $this->event(app: 'files', subject: 'task_created'));

	}//end testAnotherAppIsRefused()

	/**
	 * A planninq event with a subject the provider does not handle is refused.
	 *
	 * @return void
	 */
	public function testAnUnhandledSubjectIsRefused(): void {
		$this->expectException(UnknownActivityException::class);

		$this->provider()->parse('en', $this->event(app: Application::APP_ID, subject: 'task_archived'));

	}//end testAnUnhandledSubjectIsRefused()
}//end class
