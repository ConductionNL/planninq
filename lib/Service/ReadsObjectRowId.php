<?php

/**
 * Planninq ReadsObjectRowId
 *
 * Reads the object UUID from an ObjectService row, which is an OpenRegister
 * entity or a plain array.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * The object UUID of an ObjectService row.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
trait ReadsObjectRowId {

	/**
	 * Extract the object UUID from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return string The UUID, or '' when the row has none.
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	private function extractId(mixed $row): string {
		if (is_object($row) === true) {
			return $this->entityRowId(entity: $row);
		}

		if (is_array($row) === true) {
			return $this->arrayRowId(row: $row);
		}

		return '';
	}//end extractId()

	/**
	 * The UUID of an entity row: getUuid(), else getId(), skipping a getter that is missing, fails or answers nothing.
	 *
	 * @param object $entity The entity.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	private function entityRowId(object $entity): string {
		foreach (['getUuid', 'getId'] as $getter) {
			$value = $this->callRowGetter(entity: $entity, getter: $getter);
			if ($value !== null && (string)$value !== '') {
				return (string)$value;
			}
		}

		return '';
	}//end entityRowId()

	/**
	 * Call one getter of an entity, or answer null when it cannot be called or fails.
	 *
	 * `is_callable()`, NOT `method_exists()`: OpenRegister's ObjectEntity
	 * extends \OCP\AppFramework\Db\Entity, which implements every property
	 * accessor through `__call()` and declares neither `getUuid()` nor
	 * `getId()`. `method_exists()` does not see magic methods, so every
	 * entity would read as having no id: labels listed with `"id": ""`, an
	 * Edit that opened a create dialog, a cascade delete on an empty id and
	 * usage counts all keyed on ''.
	 *
	 * @param object $entity The entity.
	 * @param string $getter The getter name.
	 *
	 * @return mixed
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	private function callRowGetter(object $entity, string $getter): mixed {
		if (is_callable([$entity, $getter]) === false) {
			return null;
		}

		try {
			return $entity->$getter();
		} catch (\Throwable $e) {
			return null;
		}
	}//end callRowGetter()

	/**
	 * The UUID of an array row: `@self.id` when set, else `id`.
	 *
	 * @param array<string,mixed> $row The row.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
	 */
	private function arrayRowId(array $row): string {
		if (isset($row['@self']['id']) === true) {
			return (string)$row['@self']['id'];
		}

		return (string)($row['id'] ?? '');
	}//end arrayRowId()
}//end trait
