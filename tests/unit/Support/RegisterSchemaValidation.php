<?php

/**
 * Validates a payload against a schema of the real planninq register, with the
 * validator OpenRegister itself uses (Opis JSON Schema, see
 * openregister lib/Service/Object/ValidateObject.php getValidator()).
 *
 * OpenRegister prepares a schema before it validates: an object reference
 * (`"$ref": "column"`) names a schema slug, not a JSON pointer, and
 * `nullable: true` admits null. This helper applies the same two rewrites and
 * drops the keys only OpenRegister reads (`visible`, `x-*`), so a payload this
 * accepts is one OpenRegister's validator accepts too.
 *
 * @category Tests
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

use Opis\JsonSchema\Validator;

trait RegisterSchemaValidation {

	/**
	 * The validation errors for a payload, empty when it is valid.
	 *
	 * @param string              $slug    The schema slug in lib/Settings/planninq_register.json.
	 * @param array<string,mixed> $payload The payload that would be written.
	 *
	 * @return array<int,string>
	 */
	protected function registerSchemaErrors(string $slug, array $payload): array {
		$register = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
		$schema   = $register['components']['schemas'][$slug];

		$properties = [];
		foreach ($schema['properties'] as $name => $property) {
			$properties[$name] = self::prepareProperty(property: $property);
		}

		$jsonSchema = [
			'type'       => 'object',
			'required'   => ($schema['required'] ?? []),
			'properties' => $properties,
		];

		$validator = new Validator();
		$result    = $validator->validate(
			json_decode((string)json_encode($payload)),
			json_decode((string)json_encode($jsonSchema))
		);
		if ($result->isValid() === true) {
			return [];
		}

		$formatter = new \Opis\JsonSchema\Errors\ErrorFormatter();
		$errors    = [];
		foreach ($formatter->format($result->error(), true) as $path => $messages) {
			$errors[] = $path . ': ' . implode('; ', (array)$messages);
		}

		return $errors;
	}//end registerSchemaErrors()

	/**
	 * Rewrite one register property the way OpenRegister does before validating.
	 *
	 * @param array<string,mixed> $property The property as declared.
	 *
	 * @return array<string,mixed>
	 */
	private static function prepareProperty(array $property): array {
		$out = [];
		foreach ($property as $key => $value) {
			if ($key === 'visible' || $key === 'nullable' || str_starts_with((string)$key, 'x-') === true) {
				continue;
			}

			if ($key === '$ref' && is_string($value) === true && str_starts_with($value, '#') === false) {
				continue;
			}

			if ($key === 'items' && is_array($value) === true) {
				$value = self::prepareProperty(property: $value);
			}

			$out[$key] = $value;
		}

		if (($property['nullable'] ?? false) === true && isset($out['type']) === true) {
			$out['type'] = array_values(array_unique(array_merge((array)$out['type'], ['null'])));
			if (isset($out['enum']) === true) {
				$out['enum'][] = null;
			}
		}

		return $out;
	}//end prepareProperty()
}//end trait
