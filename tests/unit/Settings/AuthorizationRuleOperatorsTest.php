<?php

/**
 * Every authorization `match` in the shipped register uses only operators
 * OpenRegister evaluates.
 *
 * WHY THIS EXISTS (planninq#681)
 * ------------------------------
 * `task`, `column` and `projectPhase` scoped every verb, and `plannedTimeEntry`
 * its second read rule, with `{"project": {"$in": {"$lookup": {...}}}}`.
 * OpenRegister has no `$lookup`. `Schema::validateAuthorizationRule()` only
 * checks that `match` is an array, so the import accepted the rule, and at
 * runtime the `$lookup` map became the literal operand of `$in`: the list path
 * bound it as the string `Array`, the find path compared against the map, and
 * the rule matched no row. Every project member saw an empty board and a task
 * create answered 403. Nothing warned (openregister#4089).
 *
 * The operator set below is the one `OperatorEvaluator::valueMatchesOperator()`
 * switches on and `docs/Features/access-control.md` documents, plus `$contains`
 * from the code. An operand that is not a list of scalars, or a dynamic variable
 * OpenRegister resolves (`$userId`, `$user.groups`, `$now`, ...), is not a list
 * OpenRegister can compare against.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
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

namespace OCA\Planninq\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the authorization grammar of every schema the app ships.
 */
class AuthorizationRuleOperatorsTest extends TestCase {

	/**
	 * Operators OpenRegister evaluates in a `match` clause.
	 *
	 * @var array<int,string>
	 */
	private const EVALUATED_OPERATORS = [
		'$eq',
		'$ne',
		'$gt',
		'$gte',
		'$lt',
		'$lte',
		'$in',
		'$nin',
		'$exists',
		'$contains',
	];

	/**
	 * Operators whose operand must be a list.
	 *
	 * @var array<int,string>
	 */
	private const LIST_OPERATORS = ['$in', '$nin'];

	/**
	 * Dynamic variables `ConditionMatcher::resolveDynamicValue()` resolves.
	 *
	 * The dotted forms (`$user.groups`, `$organisation.uuid`) are matched by
	 * prefix in {@see isDynamicVariable()}.
	 *
	 * @var array<int,string>
	 */
	private const DYNAMIC_VARIABLES = ['$userId', '$user', '$organisation', '$activeOrganisation', '$now'];

	/**
	 * Every schema declared in a register file under lib/Settings.
	 *
	 * Keyed `<file>:<slug>` so a red run names the schema that broke.
	 *
	 * @return array<string, array{0: string, 1: string}> Pairs of register file and schema slug.
	 */
	public static function schemaProvider(): array {
		$cases = [];
		foreach (self::registerFiles() as $file) {
			foreach (array_keys(self::schemasIn(file: $file)) as $slug) {
				$cases[basename($file) . ':' . $slug] = [$file, (string)$slug];
			}
		}

		return $cases;
	}//end schemaProvider()

	/**
	 * Every JSON file under lib/Settings.
	 *
	 * @return array<int,string> Absolute paths.
	 */
	private static function registerFiles(): array {
		return array_map('strval', (array)glob(__DIR__ . '/../../../lib/Settings/*.json'));
	}//end registerFiles()

	/**
	 * The schemas a register file declares.
	 *
	 * @param string $file The register file.
	 *
	 * @return array<string, mixed> Schemas keyed by slug, empty when the file declares none.
	 */
	private static function schemasIn(string $file): array {
		$decoded = json_decode((string)file_get_contents($file), true);
		$schemas = ($decoded['components']['schemas'] ?? null);
		if (is_array($schemas) === false) {
			return [];
		}

		return $schemas;
	}//end schemasIn()

	/**
	 * The provider found the shipped register, so the per-schema cases cannot pass vacuously.
	 *
	 * @return void
	 */
	public function testProviderReadsTheShippedSchemas(): void {
		$slugs = array_column(self::schemaProvider(), 1);

		foreach (['task', 'column', 'projectPhase', 'plannedTimeEntry', 'project'] as $expected) {
			self::assertContains(needle: $expected, haystack: $slugs, message: "schema '{$expected}' was not read");
		}

	}//end testProviderReadsTheShippedSchemas()

	/**
	 * Every `match` operator on a schema is one OpenRegister evaluates, with a usable operand.
	 *
	 * @param string $file The register file.
	 * @param string $slug The schema slug.
	 *
	 * @return void
	 */
	#[DataProvider('schemaProvider')]
	public function testAuthorizationMatchUsesOnlyOperatorsOpenRegisterEvaluates(string $file, string $slug): void {
		$schema = (array)self::schemasIn(file: $file)[$slug];
		$problems = [];

		foreach ($this->matchClauses(authorization: ($schema['authorization'] ?? []), path: $slug) as $where => $match) {
			$problems = array_merge($problems, $this->checkMatch(match: $match, where: $where));
		}

		foreach ((array)($schema['properties'] ?? []) as $name => $property) {
			if (is_array($property) === false || isset($property['authorization']) === false) {
				continue;
			}

			$clauses = $this->matchClauses(authorization: $property['authorization'], path: "{$slug}.properties.{$name}");
			foreach ($clauses as $where => $match) {
				$problems = array_merge($problems, $this->checkMatch(match: $match, where: $where));
			}
		}

		self::assertSame(
			expected: [],
			actual: $problems,
			message: "Schema '{$slug}' has authorization rules OpenRegister does not evaluate. It stores them and"
				. ' matches no row, so the rule fails closed without a warning (planninq#681).'
		);

	}//end testAuthorizationMatchUsesOnlyOperatorsOpenRegisterEvaluates()

	/**
	 * Collect every `match` clause in an authorization block, keyed by where it sits.
	 *
	 * @param mixed $authorization The authorization block.
	 * @param string $path Location prefix for messages.
	 *
	 * @return array<string, mixed> Map of location to match clause.
	 */
	private function matchClauses(mixed $authorization, string $path): array {
		$found = [];
		if (is_array($authorization) === false) {
			return $found;
		}

		foreach ($authorization as $action => $rules) {
			if (is_array($rules) === false) {
				continue;
			}

			foreach ($rules as $index => $rule) {
				if (is_array($rule) === true && array_key_exists('match', $rule) === true) {
					$found["{$path}.authorization.{$action}[{$index}]"] = $rule['match'];
				}
			}
		}

		return $found;
	}//end matchClauses()

	/**
	 * Check one match clause.
	 *
	 * @param mixed $match The match clause.
	 * @param string $where Its location, for messages.
	 *
	 * @return array<int,string> Problems found, empty when the clause is sound.
	 */
	private function checkMatch(mixed $match, string $where): array {
		if (is_array($match) === false) {
			return ["{$where}: match is not an object"];
		}

		$problems = [];
		foreach ($match as $property => $condition) {
			if (is_array($condition) === false) {
				// A plain value is an equality test (`{"user": "$userId"}`).
				continue;
			}

			foreach ($condition as $operator => $operand) {
				$problems = array_merge(
					$problems,
					$this->checkOperator(operator: (string)$operator, operand: $operand, where: "{$where}.{$property}")
				);
			}
		}

		return $problems;
	}//end checkMatch()

	/**
	 * Check one operator and its operand.
	 *
	 * @param string $operator The operator key.
	 * @param mixed $operand The operand.
	 * @param string $where Its location, for messages.
	 *
	 * @return array<int,string> Problems found.
	 */
	private function checkOperator(string $operator, mixed $operand, string $where): array {
		if (in_array($operator, self::EVALUATED_OPERATORS, true) === false) {
			return ["{$where}: operator '{$operator}' is not one OpenRegister evaluates"];
		}

		if (in_array($operator, self::LIST_OPERATORS, true) === true) {
			if (is_string($operand) === true && $this->isDynamicVariable(value: $operand) === true) {
				return [];
			}

			if (is_array($operand) === false || array_is_list($operand) === false) {
				return ["{$where}.{$operator}: operand must be a list or a dynamic variable, got " . json_encode($operand)];
			}

			foreach ($operand as $item) {
				if (is_scalar($item) === false && $item !== null) {
					return ["{$where}.{$operator}: operand list holds a non-scalar " . json_encode($item)];
				}
			}

			return [];
		}

		if (is_array($operand) === true) {
			return ["{$where}.{$operator}: operand must be a scalar or a dynamic variable, got " . json_encode($operand)];
		}

		return [];
	}//end checkOperator()

	/**
	 * Whether a string is a dynamic variable OpenRegister resolves.
	 *
	 * @param string $value The candidate.
	 *
	 * @return bool
	 */
	private function isDynamicVariable(string $value): bool {
		if (in_array($value, self::DYNAMIC_VARIABLES, true) === true) {
			return true;
		}

		foreach (['$user.', '$organisation.', '$activeOrganisation.'] as $prefix) {
			if (str_starts_with($value, $prefix) === true) {
				return true;
			}
		}

		return false;
	}//end isDynamicVariable()
}//end class
