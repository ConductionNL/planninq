<?php

/**
 * Tests for the timetable generator's input builder (timetabling-generator task 2.1).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Event\TimetableActivitiesQueryEvent;
use OCA\Planninq\Service\TimetableGridService;
use OCA\Planninq\Service\TimetableInputBuilder;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * The builder asks the hour plan's owner through the real event, falls back to
 * the uploaded sheets, and says why an input is empty.
 */
class TimetableInputBuilderTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The app config values, in memory.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * A builder whose dispatcher runs $listener on the real event, as learniq's listener would.
	 *
	 * @param callable|null $listener Receives the TimetableActivitiesQueryEvent, or null for nobody listening.
	 *
	 * @return TimetableInputBuilder
	 */
	private function builder(?callable $listener): TimetableInputBuilder {
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(
			static function (object $event) use ($listener): void {
				if ($listener !== null && $event instanceof TimetableActivitiesQueryEvent) {
					$listener($event);
				}
			}
		);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = '', bool $lazy = false): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false): bool {
				$this->stored[$key] = $value;
				return true;
			}
		);

		return new TimetableInputBuilder(dispatcher: $dispatcher, appConfig: $config, grid: new TimetableGridService(appConfig: $config));
	}//end builder()

	/**
	 * Learniq's hour plan fixture: 3A has three English lessons and two double maths lessons.
	 *
	 * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
	 */
	private function hourPlan(): array {
		return [
			[
				['group' => '3A', 'subject' => 'English', 'teacher' => 'klaas', 'lessonsPerWeek' => 3, 'lessonLength' => 1, 'roomType' => 'classroom'],
				['group' => '3A', 'subject' => 'Maths', 'teacher' => 'noor', 'lessonsPerWeek' => 2, 'lessonLength' => 2, 'roomType' => 'classroom'],
			],
			[['reference' => 'r-101', 'capacity' => 30, 'type' => 'classroom']],
		];
	}//end hourPlan()

	/**
	 * An answered event gives one lesson per weekly lesson, the grid's forty periods and the wishes with their ids.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function testAnAnsweredEventBecomesLessons(): void {
		[$activities, $rooms] = $this->hourPlan();
		$asked = null;
		$input = $this->builder(
			static function (TimetableActivitiesQueryEvent $event) use ($activities, $rooms, &$asked): void {
				$asked = $event->getAcademicYear();
				$event->answer(app: 'learniq', activities: $activities, rooms: $rooms);
			}
		)->build(
			academicYear: '2026-2027',
			wishes: [['@self' => ['id' => 'w-1'], 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => ['wed-5'], 'strength' => 'hard', 'note' => 'not needed']]
		);

		self::assertSame(expected: '2026-2027', actual: $asked);
		self::assertSame(expected: 'learniq', actual: $input->source);
		self::assertNull(actual: $input->reason);
		self::assertCount(expectedCount: 40, haystack: $input->periods);
		self::assertSame(expected: ['mon-1', 'mon-2'], actual: array_slice($input->periods, 0, 2));
		self::assertSame(expected: 'fri-8', actual: $input->periods[39]);
		self::assertSame(expected: $rooms, actual: $input->rooms);
		self::assertSame(expected: ['3A:English:1', '3A:English:2', '3A:English:3', '3A:Maths:1', '3A:Maths:2'], actual: array_column($input->lessons, 'key'));
		self::assertSame(
			expected: ['key' => '3A:Maths:1', 'activity' => '3A:Maths', 'group' => '3A', 'subject' => 'Maths', 'teacher' => 'noor', 'roomType' => 'classroom', 'length' => 2],
			actual: $input->lessons[3]
		);
		self::assertSame(
			expected: [['id' => 'w-1', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => ['wed-5'], 'strength' => 'hard']],
			actual: $input->wishes
		);

		$scenario = ['title' => 'Autumn', 'source' => 'generated', 'weekOf' => '2026-10-05', 'windowFrom' => '2026-10-05', 'windowTo' => '2026-10-30', 'status' => 'queued', 'input' => $input->toArray()];
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: $scenario), message: 'the input fits the scenario schema');
	}//end testAnAnsweredEventBecomesLessons()

	/**
	 * Nobody answers and nothing was uploaded: an empty input and the reason.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function testAnUnansweredEventWithoutUploadGivesTheReason(): void {
		$input = $this->builder(listener: null)->build(academicYear: '2026-2027', wishes: []);

		self::assertTrue(condition: $input->isEmpty());
		self::assertSame(expected: 'none', actual: $input->source);
		self::assertSame(expected: 'No activities: learniq did not answer and no CSV was uploaded', actual: $input->reason);
		self::assertCount(expectedCount: 40, haystack: $input->periods);
	}//end testAnUnansweredEventWithoutUploadGivesTheReason()

	/**
	 * Nobody answers but an admin uploaded sheets: those are the input.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.2
	 */
	public function testUploadedSheetsAreTheFallback(): void {
		[$activities, $rooms] = $this->hourPlan();
		$builder = $this->builder(listener: null);
		$builder->storeUpload(rooms: $rooms, activities: $activities);

		$input = $builder->build(academicYear: '2026-2027', wishes: []);
		self::assertSame(expected: 'csv', actual: $input->source);
		self::assertCount(expectedCount: 5, haystack: $input->lessons);
		self::assertSame(expected: $rooms, actual: $input->rooms);
	}//end testUploadedSheetsAreTheFallback()

	/**
	 * A narrower grid gives fewer period keys.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function testThePeriodsFollowTheStoredGrid(): void {
		$this->stored['timetable_period_grid'] = '{"days":["mon","wed"],"periods":[{"start":"08:00","end":"08:45"},{"start":"08:45","end":"09:30"}]}';
		$input = $this->builder(listener: null)->build(academicYear: '2026-2027', wishes: []);

		self::assertSame(expected: ['mon-1', 'mon-2', 'wed-1', 'wed-2'], actual: $input->periods);
	}//end testThePeriodsFollowTheStoredGrid()
}//end class
