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
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-5
 */

declare(strict_types=1);

namespace OCA\Planix\Service;

use OCA\Planix\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
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
        'default_columns'         => '["To Do","In Progress","Review","Done"]',
        'allow_project_creation'  => 'all',
        'due_reminder_lead_hours' => '24',
    ];

    /**
     * Slug of the OpenRegister schema carrying the due-soon reminder rule.
     *
     * @var string
     */
    public const TASK_SCHEMA_SLUG = 'task';

    /**
     * Notification rule key for the due-date reminder (paired with the
     * `task_due_soon` subject key referenced by the user-settings spec).
     *
     * @var string
     */
    public const DUE_REMINDER_RULE_KEY = 'taskDueSoon';

    /**
     * Lower bound (inclusive) for the due-reminder lead time, in hours.
     *
     * @var int
     */
    public const LEAD_HOURS_MIN = 1;

    /**
     * Upper bound (inclusive) for the due-reminder lead time, in hours (2 weeks).
     *
     * @var int
     */
    public const LEAD_HOURS_MAX = 336;

    /**
     * Constructor for the SettingsService.
     *
     * @param IAppConfig         $appConfig    The app config interface
     * @param IConfig            $config       The user config interface
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
        private IConfig $config,
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
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-5
     */
    public function isOpenRegisterAvailable(): bool
    {
        return $this->appManager->isInstalled('openregister');
    }//end isOpenRegisterAvailable()

    /**
     * Check whether the current user has Nextcloud admin privileges.
     *
     * @return bool
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function isCurrentUserAdmin(): bool
    {
        $user = $this->userSession->getUser();
        return ($user !== null && $this->groupManager->isAdmin($user->getUID()));
    }//end isCurrentUserAdmin()

    /**
     * Check whether the current user is allowed to create a project.
     *
     * Enforces the `allow_project_creation` admin setting server-side.
     * 'all' (default) — any authenticated user may create a project.
     * 'admins' — only Nextcloud admins may create a project.
     *
     * This is the server-side enforcement for the client-side `canCreateProject`
     * computed property in ProjectList.vue (closes #H2).
     *
     * @return bool
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function canCurrentUserCreateProject(): bool
    {
        $policy = $this->appConfig->getValueString(
            Application::APP_ID,
            'allow_project_creation',
            'all'
        );

        if ($policy === 'admins') {
            return $this->isCurrentUserAdmin();
        }

        // Default ('all'): any authenticated user may create.
        return $this->userSession->getUser() !== null;

    }//end canCurrentUserCreateProject()

    /**
     * Retrieve all admin settings with defaults applied.
     *
     * Reads each key in ADMIN_CONFIG_DEFAULTS from IAppConfig, falling back to
     * the defined default when no value has been stored yet.
     *
     * @return array<string,string>
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
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
     * Validate the default_columns value before persisting.
     *
     * Must be a non-empty JSON array of non-empty strings. Returns a normalised
     * JSON string on success or null when validation fails (caller should reject).
     *
     * @param string $raw Raw value submitted by the client
     *
     * @return string|null Normalised JSON string, or null on validation failure
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function validateDefaultColumns(string $raw): ?string
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_array($decoded) === false || count($decoded) === 0) {
            return null;
        }

        $normalised = [];
        foreach ($decoded as $item) {
            if (is_string($item) === false || trim($item) === '') {
                return null;
            }

            $normalised[] = trim($item);
        }

        return json_encode($normalised);

    }//end validateDefaultColumns()

    /**
     * Store admin settings. Unknown keys are silently ignored.
     * Validates default_columns JSON shape before persisting; rejects malformed values.
     *
     * Internal use only — callers outside this class must go through updateSettings(),
     * which enforces the admin authorization check at the controller layer.
     *
     * @param array<string,mixed> $settings Settings to persist
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    private function setAdminSettings(array $settings): void
    {
        foreach (array_keys(self::ADMIN_CONFIG_DEFAULTS) as $key) {
            if (array_key_exists($key, $settings) === false) {
                continue;
            }

            $value = (string) $settings[$key];

            if ($key === 'default_columns') {
                $validated = $this->validateDefaultColumns(raw: $value);
                if ($validated === null) {
                    $this->logger->warning(
                        'Planix: invalid default_columns value rejected',
                        ['raw' => $value]
                    );
                    continue;
                }

                $value = $validated;
            }

            if ($key === 'due_reminder_lead_hours') {
                $validated = $this->validateLeadHours(raw: $value);
                if ($validated === null) {
                    $this->logger->warning(
                        'Planix: invalid due_reminder_lead_hours value rejected',
                        ['raw' => $value]
                    );
                    continue;
                }

                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $validated);
                // Reflect the new lead window on the live task schema rule.
                $this->patchDueReminderWindow(hours: $validated);
                continue;
            }

            $this->appConfig->setValueString(Application::APP_ID, $key, $value);
        }//end foreach

    }//end setAdminSettings()

    /**
     * Validate the due_reminder_lead_hours value before persisting.
     *
     * Must be a numeric integer string within the accepted range
     * (LEAD_HOURS_MIN..LEAD_HOURS_MAX). Returns the validated integer on
     * success or null when validation fails (caller should reject).
     *
     * @param string $raw Raw value submitted by the client
     *
     * @return int|null Validated lead hours, or null on validation failure
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
     */
    public function validateLeadHours(string $raw): ?int
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || preg_match('/^\d+$/', $trimmed) !== 1) {
            return null;
        }

        $hours = (int) $trimmed;
        if ($hours < self::LEAD_HOURS_MIN || $hours > self::LEAD_HOURS_MAX) {
            return null;
        }

        return $hours;

    }//end validateLeadHours()

    /**
     * Resolve the effective due-reminder lead time in hours.
     *
     * @return int The configured lead time, or the default (24) when unset.
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
     */
    public function getDueReminderLeadHours(): int
    {
        $stored = $this->appConfig->getValueString(
            Application::APP_ID,
            'due_reminder_lead_hours',
            self::ADMIN_CONFIG_DEFAULTS['due_reminder_lead_hours']
        );

        $validated = $this->validateLeadHours(raw: $stored);
        if ($validated === null) {
            return (int) self::ADMIN_CONFIG_DEFAULTS['due_reminder_lead_hours'];
        }

        return $validated;

    }//end getDueReminderLeadHours()

    /**
     * Build the ISO-8601 duration string for a `withinNext` lead window.
     *
     * @param int $hours The lead time in hours.
     *
     * @return string e.g. `PT24H`
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
     */
    public function leadHoursToDuration(int $hours): string
    {
        return 'PT'.$hours.'H';

    }//end leadHoursToDuration()

    /**
     * Patch the live `taskDueSoon` rule's `withinNext` window on the OpenRegister
     * task schema. No-op (logged) when OpenRegister is unavailable or the schema
     * cannot be resolved — the IAppConfig value remains the source of truth and a
     * later register import re-applies the rule.
     *
     * @param int $hours The lead time in hours to apply.
     *
     * @return void
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
     */
    private function patchDueReminderWindow(int $hours): void
    {
        if ($this->isOpenRegisterAvailable() === false) {
            $this->logger->info('Planix: OpenRegister unavailable, lead-time window not patched on live schema');
            return;
        }

        try {
            $schemaMapper = $this->container->get('OCA\OpenRegister\Db\SchemaMapper');
            $schemas      = $schemaMapper->findBySlug(self::TASK_SCHEMA_SLUG);
            if (is_array($schemas) === false || count($schemas) === 0) {
                $this->logger->warning('Planix: task schema not found, cannot patch due-reminder window');
                return;
            }

            $schema        = $schemas[0];
            $configuration = ($schema->getConfiguration() ?? []);
            if (isset($configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate']) === false) {
                $this->logger->warning('Planix: taskDueSoon rule not present on live schema, skipping window patch');
                return;
            }

            $configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate'] = [
                'operator' => 'withinNext',
                'value'    => $this->leadHoursToDuration(hours: $hours),
            ];

            $schema->setConfiguration($configuration);
            $schemaMapper->update($schema);
            $this->logger->info('Planix: patched taskDueSoon window to '.$this->leadHoursToDuration(hours: $hours));
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Planix: failed to patch due-reminder window on live schema',
                ['exception' => $e->getMessage()]
            );
        }//end try

    }//end patchDueReminderWindow()

    /**
     * Retrieve all current settings (admin + metadata).
     *
     * Returns a flat array containing all app config values plus metadata
     * fields (openregisters, isAdmin) consumed by the frontend.
     *
     * @return array<string,mixed>
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function getSettings(): array
    {
        $settings = [];
        foreach (self::CONFIG_KEYS as $key) {
            $settings[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, '');
        }

        $user         = $this->userSession->getUser();
        $userSettings = [];
        if ($user !== null) {
            $userSettings['notify_due_reminder'] = $this->getNotifyDueReminder(userId: $user->getUID());
        }

        return array_merge(
            $settings,
            $this->getAdminSettings(),
            $userSettings,
            [
                'openregisters' => $this->isOpenRegisterAvailable(),
                'isAdmin'       => $this->isCurrentUserAdmin(),
            ]
        );
    }//end getSettings()

    /**
     * Update the current user's personal settings (notification toggles).
     *
     * Distinct from updateSettings() (admin-only IAppConfig). This path is
     * available to any authenticated user for their own per-user preferences.
     *
     * @param string              $userId The user UID.
     * @param array<string,mixed> $data   The data to update.
     *
     * @return array<string,mixed> The updated settings.
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function updateUserSettings(string $userId, array $data): array
    {
        if (array_key_exists('notify_due_reminder', $data) === true) {
            $enabled = filter_var($data['notify_due_reminder'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $this->setNotifyDueReminder(userId: $userId, enabled: ($enabled !== false));
        }

        return $this->getSettings();

    }//end updateUserSettings()

    /**
     * Update settings with the provided data.
     *
     * @param array<string,mixed> $data The data to update
     *
     * @return array<string,mixed> The updated settings
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
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
     * Read the current user's notify_due_reminder preference.
     *
     * Backed by OCP\IConfig per-user value; defaults to enabled.
     *
     * @param string $userId The user UID.
     *
     * @return bool True when the user wants due-date reminders (default), false when opted out.
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function getNotifyDueReminder(string $userId): bool
    {
        $value = $this->config->getUserValue($userId, Application::APP_ID, 'notify_due_reminder', 'true');
        return ($value !== 'false');

    }//end getNotifyDueReminder()

    /**
     * Persist a user's notify_due_reminder preference and write it through to
     * the OpenRegister notification engine's override-only per-(schema, rule)
     * user preference for (task, taskDueSoon).
     *
     * Toggling OFF stores `false` AND writes the OR override `{"enabled": false}`.
     * Toggling ON stores `true` AND clears the OR override (null) so the user
     * falls through to the schema default rather than pinning a stale value.
     *
     * The OR override write is best-effort: when OpenRegister is unavailable the
     * IConfig value is still stored, the failure is logged, and the override is
     * skipped (reconciled later by the repair step / next toggle).
     *
     * @param string $userId  The user UID.
     * @param bool   $enabled Whether due-date reminders are enabled for this user.
     *
     * @return void
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function setNotifyDueReminder(string $userId, bool $enabled): void
    {
        $storedValue = 'false';
        if ($enabled === true) {
            $storedValue = 'true';
        }

        $this->config->setUserValue(
            $userId,
            Application::APP_ID,
            'notify_due_reminder',
            $storedValue
        );

        // ON clears the override (null → schema default); OFF writes {"enabled": false}.
        $override = ['enabled' => false];
        if ($enabled === true) {
            $override = null;
        }

        $this->writeDueReminderOverride(userId: $userId, override: $override);

    }//end setNotifyDueReminder()

    /**
     * Resolve the OpenRegister NotificationPreferenceService from the container,
     * or null when OpenRegister is unavailable / the class cannot be resolved.
     *
     * @return object|null The OR preference service, or null.
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function getNotificationPreferenceService(): ?object
    {
        if ($this->isOpenRegisterAvailable() === false) {
            return null;
        }

        try {
            return $this->container->get('OCA\OpenRegister\Service\Notification\NotificationPreferenceService');
        } catch (\Throwable $e) {
            $this->logger->info('Planix: NotificationPreferenceService unavailable', ['exception' => $e->getMessage()]);
            return null;
        }

    }//end getNotificationPreferenceService()

    /**
     * Write (or clear) the OpenRegister per-user notification override for
     * (task, taskDueSoon). Guarded gracefully when OpenRegister is unavailable.
     *
     * @param string                    $userId   The user UID.
     * @param array<string, mixed>|null $override Override body, or null to clear.
     *
     * @return bool True when the override was written/cleared, false when skipped.
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function writeDueReminderOverride(string $userId, ?array $override): bool
    {
        $preferenceService = $this->getNotificationPreferenceService();
        if ($preferenceService === null) {
            $this->logger->info(
                'Planix: OpenRegister unavailable, notify_due_reminder override skipped',
                ['user' => $userId]
            );
            return false;
        }

        try {
            $preferenceService->setOverride(
                userId: $userId,
                schemaSlug: self::TASK_SCHEMA_SLUG,
                notificationKey: self::DUE_REMINDER_RULE_KEY,
                override: $override
            );
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Planix: failed to write notify_due_reminder OR override',
                ['user' => $userId, 'exception' => $e->getMessage()]
            );
            return false;
        }//end try

    }//end writeDueReminderOverride()

    /**
     * Load configuration from planix_register.json via OpenRegister.
     *
     * @param bool $force Force re-import even if already configured.
     *
     * @return array<string,mixed> Result with success flag, message, and version.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
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

            // Validate that the imported JSON belongs to this app to prevent
            // accidentally loading a register file from a different application.
            $declaredApp = ($configData['x-openregister']['app'] ?? '');
            if ($declaredApp !== Application::APP_ID) {
                $this->logger->error(
                    'Planix: register JSON x-openregister.app mismatch',
                    ['expected' => Application::APP_ID, 'got' => $declaredApp]
                );
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Register JSON is for app "%s", expected "%s".',
                        $declaredApp,
                        Application::APP_ID
                    ),
                ];
            }

            $configVersion = ($configData['info']['version'] ?? '0.0.0');

            $configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
            $result = $configurationService->importFromApp(appId: Application::APP_ID, data: $configData, version: $configVersion, force: $force);

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

    }//end loadConfiguration()
}//end class
