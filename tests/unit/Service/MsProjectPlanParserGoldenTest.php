<?php

/**
 * Golden test for reading a Microsoft Project XML plan.
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

use OCA\Planninq\Exception\MsProjectImportException;
use OCA\Planninq\Service\MsProjectPlanParser;
use PHPUnit\Framework\TestCase;

/**
 * Parses plans (outline levels, summaries, milestones, links, resources,
 * the unassigned placeholder, dates and durations), the refusals (binary
 * files, DOCTYPE and entities, too large, too many tasks, not a plan,
 * broken XML) and the limits around them, and compares every result or
 * refusal with the fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Service\MsProjectPlanParser
 * @uses   \OCA\Planninq\Exception\MsProjectImportException
 */
class MsProjectPlanParserGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/msproject-plan-parser.golden.json';

	/**
	 * One task element.
	 *
	 * @param int    $uid   The UID.
	 * @param string $inner The child elements.
	 *
	 * @return string
	 */
	private function task(int $uid, string $inner): string {
		return "<Task><UID>$uid</UID>$inner</Task>";
	}//end task()

	/**
	 * A plan document.
	 *
	 * @param string $tasks The Tasks content.
	 * @param string $extra Elements after Tasks.
	 *
	 * @return string
	 */
	private function plan(string $tasks, string $extra=''): string {
		$head = '<?xml version="1.0" encoding="UTF-8"?><Project xmlns="http://schemas.microsoft.com/project">';
		return $head . '<Name>Plan A</Name><SaveVersion>14</SaveVersion><Tasks>' . $tasks . '</Tasks>' . $extra . '</Project>';
	}//end plan()

	/**
	 * Every input, by name, as [content, file name].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private function inputs(): array {
		$rich = $this->task(uid: 0, inner: '<Name>Root</Name><OutlineLevel>0</OutlineLevel><Summary>1</Summary>')
			. $this->task(
				uid: 1,
				inner: '<Name>Design</Name><OutlineLevel>1</OutlineLevel><Summary>1</Summary><Start>2026-03-02T08:00:00</Start>'
					. '<Finish>2026-03-06T17:00:00</Finish><Duration>PT40H0M0S</Duration><PercentComplete>150</PercentComplete><Notes>n</Notes>'
			)
			. $this->task(
				uid: 2,
				inner: '<Name>Sketch</Name><OutlineLevel>2</OutlineLevel><Start>2026-03-02T08:00:00</Start><Duration>P2DT4H30M</Duration>'
					. '<PercentComplete>-5</PercentComplete><PredecessorLink><PredecessorUID>1</PredecessorUID><Type>3</Type><LinkLag>4800</LinkLag>'
					. '</PredecessorLink><PredecessorLink><PredecessorUID>9</PredecessorUID></PredecessorLink>'
			)
			. $this->task(uid: 3, inner: '<Name>Gate</Name><Milestone>1</Milestone><Duration>P</Duration><Start>not a date</Start>')
			. $this->task(uid: 4, inner: '<OutlineLevel>x</OutlineLevel><Duration>PT1.5S</Duration>')
			. $this->task(uid: 5, inner: '<Name>Late</Name><OutlineLevel>3</OutlineLevel><Finish>2026-04-01</Finish><Duration>garbage</Duration>');
		$assignments = '<Assignments><Assignment><TaskUID>2</TaskUID><ResourceUID>7</ResourceUID></Assignment>'
			. '<Assignment><TaskUID>3</TaskUID><ResourceUID>-65535</ResourceUID></Assignment>'
			. '<Assignment><TaskUID>5</TaskUID><ResourceUID>0</ResourceUID></Assignment></Assignments>';
		$many = static fn (int $count): string => implode(
			'',
			array_map(static fn (int $i): string => "<Task><UID>$i</UID></Task>", range(1, $count))
		);
		return [
			'rich plan'            => [$this->plan(tasks: $rich, extra: $assignments), 'plan.xml'],
			'no tasks element'     => ['<?xml version="1.0"?><Project><Name>Empty</Name></Project>', 'plan.xml'],
			'empty tasks'          => [$this->plan(tasks: ''), 'plan.XML'],
			'only level 0'         => [$this->plan(tasks: $this->task(uid: 0, inner: '<OutlineLevel>0</OutlineLevel>')), 'p.xml'],
			'2000 tasks'           => [$this->plan(tasks: $many(2000)), 'big.xml'],
			'2001 tasks'           => [$this->plan(tasks: $many(2001)), 'big.xml'],
			'mpp name'             => [$this->plan(tasks: ''), 'plan.MPP'],
			'mpp bytes'            => ["\xD0\xCF\x11\xE0rest", 'plan.xml'],
			'doctype'              => ['<?xml version="1.0"?><!DOCTYPE Project><Project/>', 'plan.xml'],
			'entity'               => ['<?xml version="1.0"?><!entity x "y"><Project/>', 'plan.xml'],
			'too large'            => [str_repeat(' ', MsProjectPlanParser::MAX_BYTES + 1), 'plan.xml'],
			'exactly max'          => [$this->plan(tasks: '') . str_repeat(' ', MsProjectPlanParser::MAX_BYTES - strlen($this->plan(tasks: ''))), 'plan.xml'],
			'other root'           => ['<?xml version="1.0"?><Plan><Tasks/></Plan>', 'plan.xml'],
			'namespaced other'     => ['<?xml version="1.0"?><x:Project xmlns:x="urn:x"><x:Name>N</x:Name></x:Project>', 'plan.xml'],
			'broken xml'           => ['<Project><Tasks>', 'plan.xml'],
			'empty'                => ['', 'plan.xml'],
			'text only'            => ['hello', 'plan.xml'],
		];
	}//end inputs()

	/**
	 * Every result or refusal matches the fixture.
	 *
	 * @return void
	 */
	public function testParseMatchesTheFixture(): void {
		$answers = [];
		$parser  = new MsProjectPlanParser();
		foreach ($this->inputs() as $name => [$content, $fileName]) {
			try {
				$plan = $parser->parse(content: $content, fileName: $fileName);
				$answers[$name] = [
					'name'        => $plan['name'],
					'saveVersion' => $plan['saveVersion'],
					'count'       => count($plan['tasks']),
					'tasks'       => array_slice($plan['tasks'], 0, 8),
				];
			} catch (MsProjectImportException $e) {
				$answers[$name] = ['refused' => $e->getReason(), 'message' => $e->getMessage()];
			} catch (\Throwable $e) {
				$answers[$name] = ['thrown' => get_class($e)];
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $answers);
	}//end testParseMatchesTheFixture()
}//end class
