<?php

/**
 * Tests for planninq's notifier (project-data-export, task 3.1).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Notification
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

namespace OCA\Planninq\Tests\Unit\Notification;

use OCA\Planninq\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Notification\Notifier
 */
class NotifierTest extends TestCase {

	/**
	 * What the notification was given: method => value.
	 *
	 * @var array<string,mixed>
	 */
	private array $set = [];

	/**
	 * The languages asked of the l10n factory.
	 *
	 * @var array<int,string|null>
	 */
	private array $languages = [];

	/**
	 * The ready notification reads "Export of <project> is ready" and opens the zip's folder with the zip highlighted.
	 *
	 * @return void
	 */
	public function testTheReadyNotificationOpensTheZip(): void {
		$this->notifier()->prepare(
			notification: $this->notification(subject: 'project_export_ready', parameters: ['project' => 'Herinrichting Marktplein', 'path' => 'Planninq exports/HM-2026-11-14-1030.zip']),
			languageCode: 'nl'
		);

		$this->assertSame('Export of Herinrichting Marktplein is ready', $this->set['setParsedSubject']);
		$this->assertSame('files.view.index?dir=/Planninq exports&scrollto=HM-2026-11-14-1030.zip', $this->set['setLink']);
		$this->assertSame('abs:img/planninq/app-dark.svg', $this->set['setIcon']);
		$this->assertSame(['nl'], $this->languages);
	}//end testTheReadyNotificationOpensTheZip()

	/**
	 * Each failure reason reads as its own sentence.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function reasons(): array {
		return [
			'permission' => ['permission', 'Export of Herinrichting Marktplein failed: you do not have the export permission'],
			'space'      => ['space', 'Export of Herinrichting Marktplein failed: not enough storage space'],
			'other'      => ['error', 'Export of Herinrichting Marktplein failed: the export could not be written'],
		];
	}//end reasons()

	/**
	 * The failed notification names the reason and links nowhere.
	 *
	 * @param string $reason   The reason code.
	 * @param string $expected The subject.
	 *
	 * @dataProvider reasons
	 *
	 * @return void
	 */
	public function testTheFailedNotificationNamesTheReason(string $reason, string $expected): void {
		$this->notifier()->prepare(
			notification: $this->notification(subject: 'project_export_failed', parameters: ['project' => 'Herinrichting Marktplein', 'reason' => $reason]),
			languageCode: 'en'
		);

		$this->assertSame($expected, $this->set['setParsedSubject']);
		$this->assertArrayNotHasKey('setLink', $this->set);
	}//end testTheFailedNotificationNamesTheReason()

	/**
	 * Another app's notification and an unknown subject are not planninq's to render.
	 *
	 * @return void
	 */
	public function testForeignNotificationsAreRefused(): void {
		try {
			$this->notifier()->prepare(notification: $this->notification(subject: 'project_export_ready', parameters: [], app: 'deck'), languageCode: 'en');
			$this->fail('Another app\'s notification must be refused.');
		} catch (UnknownNotificationException $e) {
			$this->assertSame([], $this->set);
		}

		$this->expectException(UnknownNotificationException::class);
		$this->notifier()->prepare(notification: $this->notification(subject: 'something_else', parameters: []), languageCode: 'en');
	}//end testForeignNotificationsAreRefused()

	/**
	 * The notifier's id and name.
	 *
	 * @return void
	 */
	public function testIdAndName(): void {
		$this->assertSame('planninq', $this->notifier()->getID());
		$this->assertSame('Planninq', $this->notifier()->getName());
	}//end testIdAndName()

	/**
	 * The notifier over an l10n that substitutes like Nextcloud's.
	 *
	 * @return Notifier
	 */
	private function notifier(): Notifier {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturnCallback(
			function (string $app, ?string $language = null) use ($l): IL10N {
				$this->assertSame('planninq', $app);
				$this->languages[] = $language;
				return $l;
			}
		);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $route, array $parameters = []): string => $route . '?' . urldecode(http_build_query($parameters))
		);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $file): string => 'img/' . $app . '/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'abs:' . $url);

		return new Notifier(l10nFactory: $factory, urlGenerator: $urls);
	}//end notifier()

	/**
	 * A notification mock that records what it is given.
	 *
	 * @param string              $subject    The subject.
	 * @param array<string,mixed> $parameters The subject parameters.
	 * @param string              $app        The app.
	 *
	 * @return INotification
	 */
	private function notification(string $subject, array $parameters, string $app='planninq'): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($parameters);
		foreach (['setParsedSubject', 'setLink', 'setIcon'] as $setter) {
			$notification->method($setter)->willReturnCallback(
				function (string $value) use ($setter, $notification): INotification {
					$this->set[$setter] = $value;
					return $notification;
				}
			);
		}

		return $notification;
	}//end notification()
}//end class
