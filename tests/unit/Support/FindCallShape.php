<?php

/**
 * Flattens a find() call's ObjectFindOptions back into named arguments.
 *
 * Openregister #9116 moved register, schema, extend, files, render and audit
 * into one ObjectFindOptions argument. The golden recordings were taken with
 * those as separate named arguments; flattening the options in their place
 * keeps every recording comparable, so a golden still proves that the
 * migrated call reads the same object as the old one.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;

/**
 * The flattened shape of a find() call.
 */
final class FindCallShape {
	/**
	 * Replace `options` by the option fields that differ from their defaults.
	 *
	 * The fields keep the old argument names (`_extend`, `files`, `register`,
	 * `schema`, `_render`, `_audit`) and the place `options` held.
	 *
	 * @param array<int|string, mixed> $args The named arguments of the call.
	 *
	 * @return array<int|string, mixed> The arguments with the options inlined.
	 */
	public static function flatten(array $args): array {
		$out = [];
		foreach ($args as $name => $value) {
			if ($name !== 'options' || ($value instanceof ObjectFindOptions) === false) {
				$out[$name] = $value;
				continue;
			}

			$fields = [
				'_extend'  => [$value->extend, []],
				'files'    => [$value->files, false],
				'register' => [$value->register, null],
				'schema'   => [$value->schema, null],
				'_render'  => [$value->render, true],
				'_audit'   => [$value->audit, true],
			];
			foreach ($fields as $field => [$actual, $default]) {
				if ($actual !== $default) {
					$out[$field] = $actual;
				}
			}
		}//end foreach

		return $out;
	}//end flatten()
}//end class
