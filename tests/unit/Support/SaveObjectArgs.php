<?php

/**
 * Reads OpenRegister's ObjectSaveOptions back into the flat saveObject() arguments.
 *
 * OpenRegister PR #9123 folded saveObject()'s register, schema, uuid, extend,
 * silent, _validation, … parameters into one ObjectSaveOptions value object;
 * only `_rbac` and `_multitenancy` stay separate parameters. The test doubles
 * and golden fixtures were written against the flat parameters. This helper lets
 * a double record the same flat keys it always did, so the goldens keep proving
 * that each call still asks OpenRegister for exactly the same save.
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

use OCA\OpenRegister\Service\Object\ObjectSaveOptions;

/**
 * Flattens saveObject() arguments of the ObjectSaveOptions shape.
 */
final class SaveObjectArgs {

	/**
	 * Every flat field an ObjectSaveOptions carries, under the old parameter name.
	 *
	 * @param ObjectSaveOptions $options The options of one save.
	 *
	 * @return array<string,mixed>
	 */
	public static function fields(ObjectSaveOptions $options): array {
		return [
			'extend'         => $options->extend,
			'register'       => $options->target->register,
			'schema'         => $options->target->schema,
			'uuid'           => $options->uuid,
			'silent'         => $options->policy->silent,
			'_validation'    => $options->policy->validation,
			'uploadedFiles'  => $options->uploadedFiles,
			'currentUser'    => $options->actor->currentUser,
			'failIfExists'   => $options->policy->failIfExists,
			'_unowned'       => $options->actor->unowned,
			'_dedupOverride' => $options->policy->dedupOverride,
			'_owner'         => $options->actor->owner,
		];
	}//end fields()

	/**
	 * The arguments a variadic double received, with `options` spread back into the flat
	 * keys at the place each held in the old parameter list, fields equal to their default dropped.
	 *
	 * @param array<int|string,mixed> $args The arguments as `mixed ...$args` received them.
	 *
	 * @return array<string,mixed>
	 */
	public static function flatten(array $args): array {
		foreach ([0 => 'object', 1 => 'options', 2 => '_rbac', 3 => '_multitenancy'] as $position => $name) {
			if (array_key_exists($position, $args) === true) {
				$args[$name] = $args[$position];
				unset($args[$position]);
			}
		}

		$options = ($args['options'] ?? new ObjectSaveOptions());
		$fields  = self::fields(options: $options);
		$default = self::fields(options: new ObjectSaveOptions());
		$order   = ['object', 'extend', 'register', 'schema', 'uuid', '_rbac', '_multitenancy', 'silent', '_validation'];
		$order   = array_merge($order, ['uploadedFiles', 'currentUser', 'failIfExists', '_unowned', '_dedupOverride', '_owner']);

		$flat = [];
		foreach ($order as $name) {
			if (array_key_exists($name, $fields) === true) {
				if ($fields[$name] !== $default[$name]) {
					$flat[$name] = $fields[$name];
				}

				continue;
			}

			if (array_key_exists($name, $args) === true) {
				$flat[$name] = $args[$name];
			}
		}

		// A name OpenRegister does not declare stays visible, so a golden still catches it.
		foreach (array_diff_key($args, array_flip(array_merge($order, ['options']))) as $name => $value) {
			$flat[$name] = $value;
		}

		return $flat;
	}//end flatten()

	/**
	 * The same options with another uuid, for a double that hands out ids on create.
	 *
	 * @param ObjectSaveOptions $options The options of one save.
	 * @param string|null       $uuid    The uuid to save under.
	 *
	 * @return ObjectSaveOptions
	 */
	public static function withUuid(ObjectSaveOptions $options, ?string $uuid): ObjectSaveOptions {
		return new ObjectSaveOptions(
			target: $options->target,
			uuid: $uuid,
			extend: $options->extend,
			uploadedFiles: $options->uploadedFiles,
			policy: $options->policy,
			actor: $options->actor
		);
	}//end withUuid()
}//end class
