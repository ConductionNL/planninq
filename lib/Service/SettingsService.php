<?php

/**
 * Planix Settings Service
 *
 * Service for managing Planix application configuration and settings.
 *
 * @category Service
 * @package  OCA\Planix\Service
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

namespace OCA\Planix\Service;

use OCA\Planix\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing Planix application configuration and settings.
 */
class SettingsService
{

    /**
     * Legacy configuration keys (register setup keys).
     *
     * @var array<string>
     */
    private const CONFIG_KEYS = [
        'register',
    ];

    /**
     * Admin configuration keys with their default values.
     *
     * @var array<string,string>
     */
    private const ADMIN_CONFIG_DEFAULTS = [
        'default_columns'        => '["To Do","In Progress","Review","Done"]',
        'allow_project_creation' => 'all',
    ];

    /**
     * Constructor for the SettingsService.
     *
     * @param IAppConfig         $appConfig    The app config interface
     * @param IAppManager        $appManager   The app manager
     * @param ContainerInterface $container    The container
     * @param IGroupManager      $groupManager The group manager
     * @param IUserSession       $userSession  The user session
     * @param LoggerInterface    $logger       The logger
     *
     * @return void
     */
    public function __construct(
        private IAppConfig $appConfig,
        private IAppManager $appManager,
        private ContainerInterface $container,
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Check whether OpenRegister is installed and available.
     *
     * @return bool
     */
    public function isOpenRegisterAvailable(): bool
    {
        return $this->appManager->isInstalled('openregister');
    }//end isOpenRegisterAvailable()

    /**
     * Check whether the current user has Nextcloud admin privileges.
     *
     * @return bool
     */
    public function isCurrentUserAdmin(): bool
    {
        $user = $this->userSession->getUser();
        return ($user !== null && $this->groupManager->isAdmin($user->getUID()));
    }//end isCurrentUserAdmin()

    /**
     * Retrieve all admin settings with defaults applied.
     *
     * Reads each key in ADMIN_CONFIG_DEFAULTS from IAppConfig, falling back to
     * the defined default when no value has been stored yet.
     *
     * @return array<string,string>
     */
    public function getAdminSettings(): array
    {
        $settings = [];
        foreach (self::ADMIN_CONFIG_DEFAULTS as $key => $default) {
            $settings[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, $default);
        }

        return $settings;
    }//end getAdminSettings()

    /**
     * Store admin settings. Unknown keys are silently ignored.
     *
     * @param array<string,mixed> $settings Settings to persist
     *
     * @return array<string,string> The full admin settings after update
     */
    public function setAdminSettings(array $settings): array
    {
        foreach (array_keys(self::ADMIN_CONFIG_DEFAULTS) as $key) {
            if (array_key_exists($key, $settings) === true) {
                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $settings[$key]);
            }
        }

        return $this->getAdminSettings();
    }//end setAdminSettings()

    /**
     * Retrieve all current settings (admin + metadata).
     *
     * Returns a flat array containing all app config values plus metadata
     * fields (openregisters, isAdmin) consumed by the frontend.
     *
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        $settings = [];
        foreach (self::CONFIG_KEYS as $key) {
            $settings[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, '');
        }

        return array_merge(
            $settings,
            $this->getAdminSettings(),
            [
                'openregisters' => $this->isOpenRegisterAvailable(),
                'isAdmin'       => $this->isCurrentUserAdmin(),
            ]
        );
    }//end getSettings()

    /**
     * Update settings with the provided data.
     *
     * @param array<string,mixed> $data The data to update
     *
     * @return array<string,mixed> The updated settings
     */
    public function updateSettings(array $data): array
    {
        foreach (self::CONFIG_KEYS as $key) {
            if (isset($data[$key]) === true) {
                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $data[$key]);
            }
        }

        $this->setAdminSettings(settings: $data);

        return $this->getSettings();
    }//end updateSettings()

    /**
     * Load configuration from planix_register.json via OpenRegister.
     *
     * @param bool $force Force re-import even if already configured.
     *
     * @return array<string,mixed> Result with success flag, message, and version.
     */
    public function loadConfiguration(bool $force=false): array
    {
        if ($this->isOpenRegisterAvailable() === false) {
            $this->logger->warning('Planix: OpenRegister not available, skipping register initialization');
            return [
                'success' => false,
                'message' => 'OpenRegister is not installed or enabled.',
            ];
        }

        try {
            $configPath = __DIR__.'/../Settings/planix_register.json';
            if (file_exists($configPath) === false) {
                $this->logger->error('Planix: planix_register.json not found at '.$configPath);
                return [
                    'success' => false,
                    'message' => 'Configuration file planix_register.json not found.',
                ];
            }

            $configContent = file_get_contents($configPath);
            if ($configContent === false) {
                $this->logger->error('Planix: failed to read planix_register.json');
                return [
                    'success' => false,
                    'message' => 'Failed to read configuration file.',
                ];
            }

            $configData = json_decode($configContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Planix: failed to parse planix_register.json: '.json_last_error_msg());
                return [
                    'success' => false,
                    'message' => 'Failed to parse configuration file: '.json_last_error_msg(),
                ];
            }

            $configVersion = ($configData['info']['version'] ?? '0.0.0');

            $configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
            $result = $configurationService->importFromApp(appId: Application::APP_ID, data: $configData, version: $configVersion, force: $force);

            if (empty($result) === false) {
                $this->logger->info('Planix: register configuration imported successfully');
                $this->ensureRegisterPublicAccess();
                return [
                    'success' => true,
                    'message' => 'Configuration imported successfully.',
                    'version' => ($result['version'] ?? 'unknown'),
                ];
            }

            // ImportFromApp may return empty even when the register already existed
            // and was updated. Apply the public-access patch unconditionally.
            $this->ensureRegisterPublicAccess();

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

    }//end loadConfiguration()

    /**
     * Directly update the planix register's public access flags in the DB.
     *
     * Called after importFromApp to ensure publicWrite/publicRead are set even
     * if OpenRegister's ConfigurationService did not update an existing record.
     * Fails silently — any exception is logged as a warning only.
     *
     * @return void
     */
    private function ensureRegisterPublicAccess(): void
    {
        try {
            $db = $this->container->get(\OCP\IDBConnection::class);
            $qb = $db->getQueryBuilder();
            $qb->update('openregister_registers')
                ->set('public_write', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
                ->set('public_read', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
                ->where($qb->expr()->eq('slug', $qb->createNamedParameter('planix')));
            $qb->executeStatement();
            $this->logger->info('Planix: register publicWrite/publicRead ensured in DB');
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Planix: could not directly update register public access in DB',
                ['error' => $e->getMessage()]
            );
        }//end try

    }//end ensureRegisterPublicAccess()
}//end class
