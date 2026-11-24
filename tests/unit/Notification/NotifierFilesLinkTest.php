<?php

/**
 * Tests for the Files link and the parameters planninq's notifier falls back
 * on (project-data-export, task 3.1): a zip at the root of Files, a missing
 * path, project or reason, and another app's notification that is refused
 * before any language is loaded.
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/archive/2026-11-24-project-data-export/tasks.md#task-3.1
 */
#[CoversClass(Notifier::class)]
class NotifierFilesLinkTest extends TestCase {

	/**
	 * The route parameters each zip path opens Files with.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
	 */
	public static function paths(): array {
		return [
			'at the root'      => [['path' => 'HM-2026-11-14-1030.zip'], ['dir' => '/', 'scrollto' => 'HM-2026-11-14-1030.zip']],
			'two folders deep' => [['path' => 'Werk/Exports/HM.zip'], ['dir' => '/Werk/Exports', 'scrollto' => 'HM.zip']],
			'no path'          => [[], ['dir' => '/', 'scrollto' => '']],
		];
	}//end paths()

	/**
	 * The ready notification opens the zip's folder through the Files route,
	 * highlighting the zip by name.
	 *
	 * @param array<string, mixed>  $parameters The subject parameters.
	 * @param array<string, string> $expected   The route parameters.
	 *
	 * @return void
	 */
	#[DataProvider('paths')]
	public function testTheReadyLinkOpensTheZipsFolder(array $parameters, array $expected): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->expects($this->once())->method('linkToRouteAbsolute')->with('files.view.index', $expected)->willReturn('https://cloud/files');
		$urls->method('imagePath')->willReturn('img');
		$urls->method('getAbsoluteURL')->willReturn('https://cloud/img');
		$notification = $this->notification(subject: 'project_export_ready', parameters: $parameters);
		$notification->expects($this->once())->method('setLink')->with('https://cloud/files')->willReturnSelf();

		$notifier = new Notifier(l10nFactory: $this->factory(), urlGenerator: $urls);

		$this->assertSame($notification, $notifier->prepare(notification: $notification, languageCode: 'nl'));
	}//end testTheReadyLinkOpensTheZipsFolder()

	/**
	 * Without a project name the subject still reads, with the name left out;
	 * without a reason the failure reads as "could not be written".
	 *
	 * @return void
	 */
	public function testMissingParametersFallBack(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturn('img');
		$urls->method('getAbsoluteURL')->willReturn('icon');
		$urls->expects($this->never())->method('linkToRouteAbsolute');
		$notification = $this->notification(subject: 'project_export_failed', parameters: []);
		$notification->expects($this->once())->method('setParsedSubject')->with('Export of  failed: the export could not be written')->willReturnSelf();
		$notification->expects($this->never())->method('setLink');
		$notification->expects($this->once())->method('setIcon')->with('icon')->willReturnSelf();

		(new Notifier(l10nFactory: $this->factory(), urlGenerator: $urls))->prepare(notification: $notification, languageCode: 'en');
	}//end testMissingParametersFallBack()

	/**
	 * The icon is the app's dark icon as an absolute URL.
	 *
	 * @return void
	 */
	public function testTheIconIsTheAppsDarkIcon(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->expects($this->once())->method('imagePath')->with('planninq', 'app-dark.svg')->willReturn('/apps/planninq/img/app-dark.svg');
		$urls->expects($this->once())->method('getAbsoluteURL')->with('/apps/planninq/img/app-dark.svg')->willReturn('https://cloud/apps/planninq/img/app-dark.svg');
		$notification = $this->notification(subject: 'project_export_failed', parameters: ['project' => 'P', 'reason' => 'space']);
		$notification->expects($this->once())->method('setIcon')->with('https://cloud/apps/planninq/img/app-dark.svg')->willReturnSelf();

		(new Notifier(l10nFactory: $this->factory(), urlGenerator: $urls))->prepare(notification: $notification, languageCode: 'en');
	}//end testTheIconIsTheAppsDarkIcon()

	/**
	 * Another app's notification is refused before a language is loaded.
	 *
	 * @return void
	 */
	public function testAnotherAppsNotificationLoadsNoLanguage(): void {
		$factory = $this->createMock(IFactory::class);
		$factory->expects($this->never())->method('get');
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('deck');
		$notification->expects($this->never())->method('getSubjectParameters');

		$this->expectException(UnknownNotificationException::class);
		(new Notifier(l10nFactory: $factory, urlGenerator: $this->createMock(IURLGenerator::class)))->prepare(notification: $notification, languageCode: 'en');
	}//end testAnotherAppsNotificationLoadsNoLanguage()

	/**
	 * An l10n factory for planninq that substitutes like Nextcloud's.
	 *
	 * @return IFactory
	 */
	private function factory(): IFactory {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));
		$factory = $this->createMock(IFactory::class);
		$factory->expects($this->once())->method('get')->with('planninq')->willReturn($l);

		return $factory;
	}//end factory()

	/**
	 * A planninq notification of a subject.
	 *
	 * @param string               $subject    The subject.
	 * @param array<string, mixed> $parameters The subject parameters.
	 *
	 * @return INotification&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function notification(string $subject, array $parameters): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('planninq');
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($parameters);

		return $notification;
	}//end notification()
}//end class
