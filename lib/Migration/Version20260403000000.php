<?php

/**
 * Planix Migration — Re-import register with explicit access controls
 *
 * Triggers the post-migration repair step so that the Planix register's
 * publicRead: false and publicWrite: false settings are applied in the
 * live OpenRegister DB.
 * This migration introduces no schema changes; its sole purpose is to run
 * InitializeSettings as a post-migration repair step, which calls
 * SettingsService::loadConfiguration() with the updated planix_register.json
 * (version 0.2.1) that contains explicit publicWrite: false and
 * publicRead: false together with authorization blocks on all data schemas.
 *
 * @category Migration
 * @package  OCA\Planix\Migration
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Empty schema migration that triggers the post-migration repair step.
 *
 * The repair step (InitializeSettings) re-runs loadConfiguration()
 * with the updated register spec (version 0.2.1) which sets
 * publicWrite: false and publicRead: false on the planix register and
 * adds authorization blocks to project, task, column, and timeEntry schemas
 * to enforce member-based row-level access control.
 *
 * @spec openspec/specs/register-schemas/spec.md
 */
class Version20260403000000 extends SimpleMigrationStep {
	/**
	 * No schema changes — only the post-migration repair step is needed.
	 *
	 * @param IOutput $output The migration output handler
	 * @param Closure $schemaClosure Closure to get the current DB schema
	 * @param array $options Migration options
	 *
	 * @return ISchemaWrapper|null
	 *
	 * @spec openspec/specs/register-schemas/spec.md
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}//end changeSchema()
}//end class
