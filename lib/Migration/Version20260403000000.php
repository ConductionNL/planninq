<?php

/**
 * Planix Migration — Ensure register public access settings
 *
 * Triggers the post-migration repair step so that the Planix register's
 * publicWrite and publicRead settings are applied in the live OpenRegister DB.
 * This migration introduces no schema changes; its sole purpose is to run
 * InitializeSettings as a post-migration repair step, which calls
 * SettingsService::loadConfiguration(force: true) with the updated
 * planix_register.json (version 0.2.1) that contains publicWrite: true.
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
 * The repair step (InitializeSettings) re-runs loadConfiguration(force: true)
 * with the updated register spec (version 0.2.1) which includes
 * publicWrite: true and publicRead: true on the planix register.
 */
class Version20260403000000 extends SimpleMigrationStep
{
    /**
     * No schema changes — only the post-migration repair step is needed.
     *
     * @param IOutput $output        The migration output handler
     * @param Closure $schemaClosure Closure to get the current DB schema
     * @param array   $options       Migration options
     *
     * @return ISchemaWrapper|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        return null;

    }//end changeSchema()
}//end class
