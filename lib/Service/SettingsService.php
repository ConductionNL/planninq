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
     * Configuration keys managed by this service.
     *
     * @var array<string>
     */
    private const CONFIG_KEYS = [
        'register',
        'default_columns',
        'allow_project_creation',
    ];

    /**
     * Default values for admin settings.
     *
     * @var array<string,string>
     */
    private const DEFAULTS = [
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
     * Determine whether the current user is an administrator.
     *
     * @return bool
     */
    public function isCurrentUserAdmin(): bool
    {
        $user = $this->userSession->getUser();
        return ($user !== null && $this->groupManager->isAdmin($user->getUID()));
    }//end isCurrentUserAdmin()

    /**
     * Retrieve all current settings.
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
            $default = (self::DEFAULTS[$key] ?? '');
            $raw     = $this->appConfig->getValueString(Application::APP_ID, $key, $default);
            if ($key === 'default_columns') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) === true) {
                    $settings[$key] = $decoded;
                } else {
                    $settings[$key] = json_decode($default, true);
                }
            } else {
                if ($raw !== '') {
                    $settings[$key] = $raw;
                } else {
                    $settings[$key] = $default;
                }
            }
        }

        return array_merge(
            $settings,
            [
                'openregisters' => $this->isOpenRegisterAvailable(),
                'isAdmin'       => $this->isCurrentUserAdmin(),
            ]
        );
    }//end getSettings()

    /**
     * Retrieve admin settings with defaults applied.
     *
     * @return array<string,mixed>
     */
    public function getAdminSettings(): array
    {
        return $this->getSettings();
    }//end getAdminSettings()

    /**
     * Update settings with the provided data.
     *
     * @param array<string,mixed> $data The data to update
     *
     * @return array<string,mixed> The updated settings
     */
    public function updateSettings(array $data): array
    {
        return $this->setAdminSettings(data: $data);
    }//end updateSettings()

    /**
     * Validate and persist admin settings. Unknown keys are silently ignored.
     *
     * @param array<string,mixed> $data Key-value pairs to store.
     *
     * @return array<string,mixed> The updated settings after save.
     */
    public function setAdminSettings(array $data): array
    {
        foreach (self::CONFIG_KEYS as $key) {
            if (isset($data[$key]) === false) {
                continue;
            }

            if ($key === 'default_columns') {
                if (is_array($data[$key]) === true) {
                    $value = json_encode($data[$key]);
                } else {
                    $value = (string) $data[$key];
                }

                $this->appConfig->setValueString(Application::APP_ID, $key, $value);
            } else {
                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $data[$key]);
            }
        }

        return $this->getSettings();
    }//end setAdminSettings()

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
            $configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
            $result = $configurationService->importFromApp(appId: Application::APP_ID, force: $force);

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
                'message' => 'Configuration import failed. Check the server log for details.',
            ];
        }//end try
    }//end loadConfiguration()
}//end class
