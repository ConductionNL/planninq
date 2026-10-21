<?php

/**
 * Applies the timetableSession read rule to pupils, teachers and planners.
 *
 * The rule is read from lib/Settings/planninq_register.json, not copied, and
 * evaluated the way OpenRegister evaluates a read rule on a row: a rule applies
 * when the caller qualifies for its group (`authenticated` for any signed-in
 * user, otherwise membership of the named group), and then admits the row when
 * every `match` property equals its value, with `$userId` resolved to the
 * caller (openregister MagicRbacHandler::processConditionalRule()). Any rule
 * shape this evaluator does not know fails the test, so a later rule can never
 * pass here by being skipped.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-a-timetable-session-schema-carries-the-schools-own-ids-req-001
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Who reads which lesson under the timetableSession read rule (planninq#711).
 */
class TimetableSessionReadRuleTest extends TestCase {

	/**
	 * The read rule as the register declares it.
	 *
	 * @var array<int,mixed>
	 */
	private array $readRule;

	/**
	 * Lessons of two groups, each with its own teacher, keyed by externalRef.
	 *
	 * @var array<string,array<string,string>>
	 */
	private array $lessons = [
		'4a-bio' => ['groupReference' => '4A', 'cohortId' => 'c-4a', 'teacherUserId' => 'klaas', 'status' => 'scheduled'],
		'4a-wis' => ['groupReference' => '4A', 'cohortId' => 'c-4a', 'teacherUserId' => 'marieke', 'status' => 'scheduled'],
		'4b-bio' => ['groupReference' => '4B', 'cohortId' => 'c-4b', 'teacherUserId' => 'klaas', 'status' => 'cancelled'],
		'4b-ned' => ['groupReference' => '4B', 'cohortId' => 'c-4b', 'teacherUserId' => 'joost', 'status' => 'scheduled'],
	];

	/**
	 * Load the read rule from the register JSON.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$contents = (string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json');
		$register = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

		$this->readRule = $register['components']['schemas']['timetableSession']['authorization']['read'];

	}//end setUp()

	/**
	 * A pupil of 4A reads no lesson of 4B, and none of 4A either, directly.
	 *
	 * The pupil's own timetable reaches them through learniq, which resolves
	 * their cohorts itself and reads through planninq's query event.
	 *
	 * @return void
	 */
	public function testPupilCannotReadAnotherGroupsLessons(): void {
		$pupil = ['userId' => 'pupil-4a', 'groups' => ['leerlingen', '4A']];

		self::assertSame(expected: [], actual: $this->readableBy(user: $pupil));
		self::assertNotContains(needle: '4b-bio', haystack: $this->readableBy(user: $pupil));
		self::assertNotContains(needle: '4b-ned', haystack: $this->readableBy(user: $pupil));

	}//end testPupilCannotReadAnotherGroupsLessons()

	/**
	 * A teacher reads the lessons that name them, in any group, and no other.
	 *
	 * @return void
	 */
	public function testTeacherReadsOnlyTheLessonsThatNameThem(): void {
		$teacher = ['userId' => 'klaas', 'groups' => ['docenten']];

		self::assertSame(expected: ['4a-bio', '4b-bio'], actual: $this->readableBy(user: $teacher));

	}//end testTeacherReadsOnlyTheLessonsThatNameThem()

	/**
	 * The timetable group and admins read every lesson.
	 *
	 * @return void
	 */
	public function testTimetableGroupAndAdminsReadEveryLesson(): void {
		$everyLesson = array_keys($this->lessons);

		self::assertSame(expected: $everyLesson, actual: $this->readableBy(user: ['userId' => 'roostermaker', 'groups' => ['planninq-timetable']]));
		self::assertSame(expected: $everyLesson, actual: $this->readableBy(user: ['userId' => 'beheer', 'groups' => ['admin']]));

	}//end testTimetableGroupAndAdminsReadEveryLesson()

	/**
	 * A draft reaches the teacher it names and admins, not the timetable group, another teacher or a pupil.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-a-draft-lesson-is-readable-only-by-the-teacher-it-names-and-by-admins
	 */
	public function testDraftIsReadOnlyByItsTeacherAndAdmins(): void {
		$this->lessons['4a-wis']['status'] = 'draft';

		self::assertContains(needle: '4a-wis', haystack: $this->readableBy(user: ['userId' => 'marieke', 'groups' => ['docenten']]));
		self::assertContains(needle: '4a-wis', haystack: $this->readableBy(user: ['userId' => 'beheer', 'groups' => ['admin']]));
		self::assertNotContains(needle: '4a-wis', haystack: $this->readableBy(user: ['userId' => 'roostermaker', 'groups' => ['planninq-timetable']]));
		self::assertNotContains(needle: '4a-wis', haystack: $this->readableBy(user: ['userId' => 'klaas', 'groups' => ['docenten']]));
		self::assertNotContains(needle: '4a-wis', haystack: $this->readableBy(user: ['userId' => 'pupil-4a', 'groups' => ['leerlingen', '4A']]));
		self::assertSame(expected: ['4a-bio', '4b-bio', '4b-ned'], actual: $this->readableBy(user: ['userId' => 'roostermaker', 'groups' => ['planninq-timetable']]));

	}//end testDraftIsReadOnlyByItsTeacherAndAdmins()

	/**
	 * The lessons a caller may read under the register's rule.
	 *
	 * @param array{userId:string,groups:array<int,string>} $user The signed-in caller.
	 *
	 * @return array<int,string> The readable lessons' externalRefs, in fixture order.
	 */
	private function readableBy(array $user): array {
		$readable = [];
		foreach ($this->lessons as $ref => $lesson) {
			foreach ($this->readRule as $rule) {
				if ($this->admits(rule: $rule, user: $user, lesson: $lesson) === true) {
					$readable[] = $ref;
					break;
				}
			}
		}

		return $readable;

	}//end readableBy()

	/**
	 * Whether one rule admits the caller to one lesson.
	 *
	 * @param mixed                                         $rule   One read rule.
	 * @param array{userId:string,groups:array<int,string>} $user   The signed-in caller.
	 * @param array<string,string>                          $lesson The lesson.
	 *
	 * @return bool
	 */
	private function admits(mixed $rule, array $user, array $lesson): bool {
		if (is_string($rule) === true) {
			$rule = ['group' => $rule];
		}

		self::assertIsArray(actual: $rule, message: 'a read rule is a group name or an object');
		self::assertSame(expected: [], actual: array_diff(array_keys($rule), ['group', 'match']), message: 'this evaluator knows only group and match');
		self::assertIsString(actual: ($rule['group'] ?? null), message: 'every rule names a group');

		$qualifies = ($rule['group'] === 'authenticated') || (in_array($rule['group'], $user['groups'], true) === true);
		if ($qualifies === false) {
			return false;
		}

		foreach (($rule['match'] ?? []) as $property => $expected) {
			if (is_array($expected) === true) {
				self::assertSame(expected: ['$in'], actual: array_keys($expected), message: "match on {$property}: this evaluator knows only \$in");
				// OpenRegister's \$in denies a missing value (OperatorEvaluator::operatorIn).
				if (in_array(($lesson[$property] ?? null), $expected['$in'], true) === false) {
					return false;
				}

				continue;
			}

			self::assertIsString(actual: $expected, message: "match on {$property} must be a plain value this evaluator knows");
			if ($expected === '$userId') {
				$expected = $user['userId'];
			}

			self::assertStringStartsNotWith(prefix: '$', string: $expected, message: "unknown token in match on {$property}");
			if (($lesson[$property] ?? null) !== $expected) {
				return false;
			}
		}

		return true;

	}//end admits()
}//end class
