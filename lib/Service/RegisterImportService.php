<?php

/**
 * Planix Register Import Service
 *
 * Owns one concern: importing `lib/Settings/planix_register.json` into
 * OpenRegister. Split out of SettingsService, which had accumulated both the
 * app/user settings plane AND the register bootstrap plane and tripped PHPMD's
 * ExcessiveClassComplexity threshold — the rule was correctly naming a real
 * Single Responsibility violation.
 *
 * Behaviour is unchanged: load() is the non-forced import the repair step runs,
 * reload() is the forced import the admin "Load configuration" button runs.
 *
 * @category Service
 * @package  OCA\Planix\Service
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

namespace OCA\Planix\Service;

use OCA\Planix\AppInfo\Application;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports the Planix register definition into OpenRegister.
 *
 * @spec openspec/specs/register-schemas/spec.md
 */
class RegisterImportService
{
    /**
     * Constructor for the RegisterImportService.
     *
     * @param SettingsService    $settingsService The settings service (OpenRegister availability probe)
     * @param ContainerInterface $container       The container
     * @param LoggerInterface    $logger          The logger
     *
     * @return void
     */
    public function __construct(
        private SettingsService $settingsService,
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Import planix_register.json via OpenRegister if it is not already applied.
     *
     * OpenRegister skips the import when the register is already at the declared
     * version. Use reload() to force a re-import over an existing register.
     *
     * @return array<string,mixed> Result with success flag, message, and version.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
     */
    public function load(): array
    {
        return $this->import(force: false);

    }//end load()

    /**
     * Force a re-import of planix_register.json via OpenRegister.
     *
     * Same as load() but re-applies the register even when it is already at the
     * declared version — the behaviour the admin "Load configuration" button
     * needs, because a non-forced import advances the recorded version WITHOUT
     * applying changed schemas.
     *
     * @return array<string,mixed> Result with success flag, message, and version.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
     */
    public function reload(): array
    {
        return $this->import(force: true);

    }//end reload()

    /**
     * Read, validate and parse planix_register.json.
     *
     * @return array{ok:true,data:array<string,mixed>}|array{ok:false,message:string}
     *         The decoded register definition, or the reason it is unusable.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     */
    private function readRegisterDefinition(): array
    {
        $configPath = __DIR__.'/../Settings/planix_register.json';
        if (file_exists($configPath) === false) {
            $this->logger->error('Planix: planix_register.json not found at '.$configPath);
            return ['ok' => false, 'message' => 'Configuration file planix_register.json not found.'];
        }

        $configContent = file_get_contents($configPath);
        if ($configContent === false) {
            $this->logger->error('Planix: failed to read planix_register.json');
            return ['ok' => false, 'message' => 'Failed to read configuration file.'];
        }

        $configData = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Planix: failed to parse planix_register.json: '.json_last_error_msg());
            return ['ok' => false, 'message' => 'Failed to parse configuration file: '.json_last_error_msg()];
        }

        // Validate that the imported JSON belongs to this app to prevent
        // accidentally loading a register file from a different application.
        $declaredApp = ($configData['x-openregister']['app'] ?? '');
        if ($declaredApp !== Application::APP_ID) {
            $this->logger->error(
                'Planix: register JSON x-openregister.app mismatch',
                ['expected' => Application::APP_ID, 'got' => $declaredApp]
            );
            return [
                'ok'      => false,
                'message' => sprintf(
                    'Register JSON is for app "%s", expected "%s".',
                    $declaredApp,
                    Application::APP_ID
                ),
            ];
        }

        return ['ok' => true, 'data' => $configData];

    }//end readRegisterDefinition()

    /**
     * Shared implementation behind load() / reload().
     *
     * @param bool $force Force re-import even if already configured.
     *
     * @return array<string,mixed> Result with success flag, message, and version.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
     */
    private function import(bool $force): array
    {
        if ($this->settingsService->isOpenRegisterAvailable() === false) {
            $this->logger->warning('Planix: OpenRegister not available, skipping register initialization');
            return [
                'success' => false,
                'message' => 'OpenRegister is not installed or enabled.',
            ];
        }

        try {
            $definition = $this->readRegisterDefinition();
            if ($definition['ok'] === false) {
                return ['success' => false, 'message' => $definition['message']];
            }

            $configData    = $definition['data'];
            $configVersion = ($configData['info']['version'] ?? '0.0.0');

            $configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
            $result = $configurationService->importFromApp(
                appId: Application::APP_ID,
                data: $configData,
                version: $configVersion,
                force: $force
            );

            if (empty($result) === false) {
                $this->logger->info('Planix: register configuration imported successfully');
                return [
                    'success' => true,
                    'message' => 'Configuration imported successfully.',
                    'version' => ($result['version'] ?? 'unknown'),
                ];
            }

            return [
                'success' => false,
                'message' => 'Import returned an empty result.',
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                'Planix: configuration import failed',
                ['exception' => $e->getMessage()]
            );
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }//end try

    }//end import()
}//end class
