<?php

/**
 * Planninq RenameTimeEntrySchemaSlug repair step.
 *
 * Moves the time-entry schema's slug from `timeEntry` to `plannedTimeEntry`
 * IN PLACE, before InitializeSettings imports the register.
 *
 * WHY IT MOVES AT ALL. A schema slug is global per organisation, and three apps
 * declared a `timeEntry`: humaniq's HR booking, planninq's project booking, and
 * this app's project booking. `SchemaMapper::find()` matches `LOWER(slug)`,
 * so whichever row it reached first answered for all three. humaniq is the
 * agreed owner of the hours; this app's record is the PLANNING side of one —
 * the task it was booked against, the contractor and the hourly rate — and
 * keeps a `timeEntry` reference to the humaniq booking.
 *
 * OpenRegister's import matches an existing schema by (application, slug):
 * ImportHandler calls `findByApplicationAndSlug()` and creates a NEW schema when that
 * misses. A slug rename in the shipped register fragment therefore does not rename
 * anything — it CREATES a second schema and silently orphans the first, together with
 * every object already written against it. The old schema keeps its shard table and its
 * rows; the app resolves the new id and reads an empty collection. Nothing errors.
 *
 * That is why this step exists and why it must run FIRST. Renaming the row before the
 * import means the import finds the schema it was always going to find, keeps its id,
 * and updates it in place — so the shard table, and the objects in it, stay attached.
 *
 * Idempotent: a no-op once the slug is already namespaced, and a no-op on an install that
 * never had the schema. Refuses when both slugs exist, because picking one would decide
 * which set of objects to abandon — a choice this step must not make silently.
 *
 * @category Repair
 * @package  OCA\Planninq\Repair
 *
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git_id>
 *
 * @link https://planninq.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Renames the time-entry schema slug in place, ahead of the register import.
 *
 * @spec exclude No canonical spec covers the fleet-wide slug collision
 *  migration. Pointing this at an existing spec would report conformance to a
 *  requirement that says nothing about it.
 */
class RenameTimeEntrySchemaSlug implements IRepairStep {
	/**
	 * The slug this step migrates away from.
	 *
	 * @var string
	 */
	private const OLD_SLUG = 'timeEntry';

	/**
	 * The namespaced slug the register fragment now declares.
	 *
	 * @var string
	 */
	private const NEW_SLUG = 'plannedTimeEntry';

	/**
	 * The owning application, as stored on the schema row.
	 *
	 * @var string
	 */
	private const APPLICATION = 'planninq';

	/**
	 * Renames one schema row's slug in place, keeping its id.
	 *
	 * @var string
	 */
	private const RENAME_SQL = 'UPDATE `*PREFIX*openregister_schemas` SET slug = ? WHERE id = ?';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db Database connection.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Human-readable step name.
	 *
	 * @return string
	 *
	 * @spec exclude No canonical spec covers the fleet-wide slug collision
	 *  migration. Pointing this at an existing spec would report conformance to a
	 *  requirement that says nothing about it.
	 */
	public function getName(): string {
		return 'Namespace the planninq time-entry schema slug';
	}//end getName()

	/**
	 * Rename the slug, unless doing so would be ambiguous.
	 *
	 * @param IOutput $output Repair output.
	 *
	 * @return void
	 *
	 * @spec exclude No canonical spec covers the fleet-wide slug collision
	 *  migration. Pointing this at an existing spec would report conformance to a
	 *  requirement that says nothing about it.
	 */
	public function run(IOutput $output): void {
		$old = $this->schemaIds(slug: self::OLD_SLUG);
		$new = $this->schemaIds(slug: self::NEW_SLUG);

		$skip = self::nothingToRename(old: $old, new: $new);
		if ($skip !== null) {
			$output->info($skip);
			return;
		}

		$conflict = ($this->bothSlugsConflict(old: $old, new: $new) ?? $this->duplicateConflict(old: $old));
		if ($conflict !== null) {
			$output->warning($conflict);
			return;
		}

		$this->renameRow(id: $old[0], output: $output);
	}//end run()

	/**
	 * Why there is nothing to rename, or null when the old slug is there to rename.
	 *
	 * @param array<int, mixed>|null $old Ids of the old slug, null when unreadable.
	 * @param array<int, mixed>|null $new Ids of the new slug, null when unreadable.
	 *
	 * @return string|null The message for the output, or null.
	 */
	private static function nothingToRename(array|null $old, array|null $new): string|null {
		if ($old === null || $new === null) {
			return 'RenameTimeEntrySchemaSlug: schema table unreadable; leaving the slug alone.';
		}

		if ($old === []) {
			return 'RenameTimeEntrySchemaSlug: no time-entry schema on this install; nothing to do.';
		}

		return null;
	}//end nothingToRename()

	/**
	 * Refuse when both slugs exist, logging why.
	 *
	 * Each may own objects, and renaming would collide with the English row.
	 * Abandoning either set of objects is not a call a repair step gets to make
	 * without being asked.
	 *
	 * @param array<int, mixed> $old Ids of the old slug.
	 * @param array<int, mixed> $new Ids of the new slug.
	 *
	 * @return string|null The warning for the output, or null when the new slug is absent.
	 */
	private function bothSlugsConflict(array $old, array $new): ?string {
		if ($new === []) {
			return null;
		}

		$this->logger->warning(
			'RenameTimeEntrySchemaSlug: both slugs exist; refusing to merge them.',
			['old' => $old, 'new' => $new]
		);
		return 'RenameTimeEntrySchemaSlug: both `' . self::OLD_SLUG . '` and `' . self::NEW_SLUG
			. '` exist; refusing to merge them. Resolve by hand.';
	}//end bothSlugsConflict()

	/**
	 * Refuse when the old slug is on more than one schema, logging why.
	 *
	 * @param array<int, mixed> $old Ids of the old slug.
	 *
	 * @return string|null The warning for the output, or null when there is one.
	 */
	private function duplicateConflict(array $old): ?string {
		if (count($old) <= 1) {
			return null;
		}

		$this->logger->warning(
			'RenameTimeEntrySchemaSlug: duplicate time-entry slugs; refusing to guess.',
			['ids' => $old]
		);
		return 'RenameTimeEntrySchemaSlug: duplicate `' . self::OLD_SLUG . '` schemas; refusing to guess.';
	}//end duplicateConflict()

	/**
	 * Rename the old slug's schema row in place, and report it.
	 *
	 * A failure is safe: the import then creates a new schema rather than
	 * updating this one, which is the pre-existing behaviour. It is loud,
	 * because the objects on the old schema stop being reachable.
	 *
	 * @param mixed   $id     The schema id.
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 */
	private function renameRow(mixed $id, IOutput $output): void {
		try {
			$this->db->executeStatement(self::RENAME_SQL, [self::NEW_SLUG, $id]);
		} catch (Exception $e) {
			$this->logger->error(
				'RenameTimeEntrySchemaSlug: slug rename failed; the import will create a second schema.',
				['id' => $id, 'exception' => $e->getMessage()]
			);
			$output->warning('RenameTimeEntrySchemaSlug: slug rename failed; see the log.');
			return;
		}

		$output->info(self::renamedMessage(id: $id));
	}//end renameRow()

	/**
	 * The output line for a renamed schema row.
	 *
	 * @param mixed $id The schema id.
	 *
	 * @return string
	 */
	private static function renamedMessage(mixed $id): string {
		return 'RenameTimeEntrySchemaSlug: schema ' . $id . ' renamed `'
			. self::OLD_SLUG . '` -> `' . self::NEW_SLUG . '`; its objects stay attached.';
	}//end renamedMessage()

	/**
	 * Ids of this application's schemas carrying the given slug.
	 *
	 * @param string $slug The schema slug to look for.
	 *
	 * @return array<int, mixed>|null The ids, or null when the table cannot be read.
	 */
	private function schemaIds(string $slug): ?array {
		try {
			$rows = $this->db->executeQuery(
				'SELECT id FROM `*PREFIX*openregister_schemas` WHERE slug = ? AND application = ?',
				[$slug, self::APPLICATION]
			)->fetchAll(\PDO::FETCH_COLUMN);

			return array_values((array)$rows);
		} catch (Exception $e) {
			$this->logger->warning(
				'RenameTimeEntrySchemaSlug: could not read the schema table; skipping.',
				['exception' => $e->getMessage()]
			);
			return null;
		}
	}//end schemaIds()
}//end class
