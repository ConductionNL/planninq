<?php

/**
 * Golden test for the activity provider's subject texts.
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
use OCP\Activity\IEvent;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Parses every handled subject with subject parameters that are present,
 * missing, empty or not strings, and compares what the provider asked the
 * translator for and set on the event with the fixture. Set
 * PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Activity\Provider
 * @covers \OCA\Planninq\Activity\ProviderSubjectHandler
 */
class ProviderGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/provider-subject.golden.json';

	private const SUBJECTS = ['task_created', 'task_status_changed', 'task_assigned_activity', 'task_due_date_changed', 'task_deleted'];

	/**
	 * Values a subject parameter can hold; the key is absent for "(absent)".
	 */
	private const VALUES = ['(absent)', '', 'Ada %1$s {task}', 42, null, true];

	/**
	 * What the provider asked the translator for and set on the event, in order per kind.
	 *
	 * @var array{translated:list<array<string,mixed>>,event:list<array<string,mixed>>}
	 */
	private array $log = ['translated' => [], 'event' => []];

	/**
	 * The recorded texts equal the fixture.
	 *
	 * @return void
	 */
	public function testParseMatchesTheGoldenRecording(): void {
		$cases = [];
		foreach (self::SUBJECTS as $subject) {
			foreach (array_keys(self::VALUES) as $index) {
				$params = [];
				foreach (['actor', 'title', 'objectId', 'status', 'assignee', 'dueDate'] as $offset => $key) {
					$pick = self::VALUES[(($index + $offset) % count(self::VALUES))];
					if ($pick !== '(absent)') {
						$params[$key] = $pick;
					}
				}

				$cases[] = ['subject' => $subject, 'params' => $params, 'log' => $this->parse(subject: $subject, params: $params)];
			}
		}

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			if (is_dir(dirname(self::FIXTURE)) === false) {
				mkdir(dirname(self::FIXTURE));
			}

			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testParseMatchesTheGoldenRecording()

	/**
	 * Parse one event and return the translator calls and the event calls.
	 *
	 * @param string              $subject The event subject.
	 * @param array<string,mixed> $params  The subject parameters.
	 *
	 * @return array{translated:list<array<string,mixed>>,event:list<array<string,mixed>>}
	 */
	private function parse(string $subject, array $params): array {
		$this->log = ['translated' => [], 'event' => []];

		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(
			function (string $text, $args = []): string {
				$this->log['translated'][] = ['t' => $text, 'args' => $args];
				return '[' . $text . '|' . json_encode($args) . ']';
			}
		);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $file): string => '/' . $app . '/img/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://nc.example' . $url);

		$event = $this->createMock(originalClassName: IEvent::class);
		$event->method('getApp')->willReturn('planninq');
		$event->method('getSubject')->willReturn($subject);
		$event->method('getSubjectParameters')->willReturn($params);
		$event->method('getObjectId')->willReturn(7);
		$event->method('setParsedSubject')->willReturnCallback(
			function (string $text) use ($event): IEvent {
				$this->log['event'][] = ['parsed' => $text];
				return $event;
			}
		);
		$event->method('setRichSubject')->willReturnCallback(
			function (string $text, array $parameters = []) use ($event): IEvent {
				$this->log['event'][] = ['rich' => $text, 'parameters' => $parameters];
				return $event;
			}
		);
		$event->method('setIcon')->willReturnCallback(
			function (string $icon) use ($event): IEvent {
				$this->log['event'][] = ['icon' => $icon];
				return $event;
			}
		);

		$provider = new Provider(l10nFactory: $factory, urlGenerator: $urls, subjectHandler: new ProviderSubjectHandler());
		$provider->parse('nl', $event);

		return $this->log;
	}//end parse()
}//end class
