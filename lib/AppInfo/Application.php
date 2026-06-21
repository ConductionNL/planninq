<?php

/**
 * Planix Application
 *
 * Main application class for the Planix Nextcloud app.
 *
 * @category AppInfo
 * @package  OCA\Planix\AppInfo
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

namespace OCA\Planix\AppInfo;

use OCA\Planix\Listener\DeepLinkRegistrationListener;
use OCA\Planix\Listener\TaskActivityListener;
use OCA\OpenRegister\Event\DeepLinkRegistrationEvent;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Container\ContainerInterface;

/**
 * Main application class for the Planix Nextcloud app.
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'planix';

    /**
     * Constructor for the Application class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(appName: self::APP_ID);
    }//end __construct()

    /**
     * Register event listeners and services.
     *
     * @param IRegistrationContext $context The registration context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function register(IRegistrationContext $context): void
    {
        // AppHost adoption (ADR-040): alias the mechanical plumbing classes
        // (dashboard SPA serving, observability controllers, admin settings
        // panel, settings section, deep-link listener) to OpenRegister's
        // shared generic engine. The factory closures reference OR classes by
        // string, so a disabled/absent OpenRegister never fatals NC bootstrap —
        // the closure only runs when the leaf DI container resolves the service
        // (i.e. when a route is dispatched), surfacing as a degraded 5xx.
        //
        // The domain controllers/services (SettingsController + SettingsService
        // with per-user due-reminder logic, Repair\InitializeSettings register
        // import, the kanban Project/Dependency/Label controllers) are kept.
        $this->registerAppHost($context);

        // Publish task lifecycle events to the Nextcloud Activity stream.
        // Scoped inside the listener to the planix register's `task` schema.
        $context->registerEventListener(
            event: ObjectCreatedEvent::class,
            listener: TaskActivityListener::class
        );
        $context->registerEventListener(
            event: ObjectUpdatedEvent::class,
            listener: TaskActivityListener::class
        );
        $context->registerEventListener(
            event: ObjectDeletedEvent::class,
            listener: TaskActivityListener::class
        );

        // NOTE: the Activity Provider + Filter are registered declaratively via
        // the <activity> block in appinfo/info.xml — IRegistrationContext has no
        // activity-registration methods in this Nextcloud version.

    }//end register()

    /**
     * Wire the AppHost generic engine for the mechanical plumbing classes.
     *
     * Aliases the leaf class names (referenced by routes.php and info.xml) to
     * OpenRegister's `OCA\OpenRegister\AppHost\…` generics via factory closures.
     * Every generic FQCN is a plain string so this method never autoloads an OR
     * class at bootstrap; the closure body runs only when the leaf DI container
     * resolves the service (route dispatch). With OpenRegister disabled the
     * closures simply never run (or surface as a degraded 5xx), so Nextcloud
     * boots cleanly — the lazy-by-construction invariant of ADR-040.
     *
     * @param IRegistrationContext $context The registration context.
     *
     * @return void
     */
    private function registerAppHost(IRegistrationContext $context): void
    {
        $appId = self::APP_ID;

        // Dashboard SPA + history-mode catch-all.
        $context->registerService(
            \OCA\Planix\Controller\DashboardController::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Controller\\GenericDashboardController';
                return new $class(
                    appName: $appId,
                    request: $c->get('OCP\\IRequest')
                );
            }
        );

        // Per-user UI preferences (shared nextcloud-vue widgets). Pure engine
        // capability — backs the canonical preferences#* routes.
        $context->registerService(
            \OCA\Planix\Controller\PreferencesController::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Controller\\GenericPreferencesController';
                return new $class(
                    appName: $appId,
                    request: $c->get('OCP\\IRequest'),
                    config: $c->get('OCP\\IConfig'),
                    userSession: $c->get('OCP\\IUserSession')
                );
            }
        );

        // Observability — health (public) + metrics (admin), driven by the
        // `observability` block in src/manifest.json. URLs unchanged.
        $context->registerService(
            \OCA\Planix\Controller\HealthController::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Controller\\GenericHealthController';
                return new $class(
                    appName: $appId,
                    request: $c->get('OCP\\IRequest'),
                    manifestLoader: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader'),
                    executor: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\HealthCheckExecutor')
                );
            }
        );
        $context->registerService(
            \OCA\Planix\Controller\MetricsController::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Controller\\GenericMetricsController';
                return new $class(
                    appName: $appId,
                    request: $c->get('OCP\\IRequest'),
                    manifestLoader: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader'),
                    engine: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\MetricsEngine')
                );
            }
        );

        // Admin settings panel (IDelegatedSettings, #299) — section id `planix`,
        // priority 10, identical to the deleted bespoke AdminSettings.
        $context->registerService(
            \OCA\Planix\Settings\AdminSettings::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Settings\\GenericAdminSettings';
                return new $class(
                    appId: $appId,
                    sectionId: $appId,
                    priority: 10,
                    appManager: $c->get('OCP\\App\\IAppManager'),
                    initialState: $c->get('OCP\\AppFramework\\Services\\IInitialState'),
                    appConfig: $c->get('OCP\\IAppConfig')
                );
            }
        );

        // Admin settings section (IIconSection) — name `Planix`, priority 75.
        $context->registerService(
            \OCA\Planix\Sections\SettingsSection::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Settings\\GenericSettingsSection';
                return new $class(
                    sectionId: $appId,
                    name: 'Planix',
                    appId: $appId,
                    iconFile: 'app-dark.svg',
                    priority: 75,
                    urlGenerator: $c->get('OCP\\IURLGenerator')
                );
            }
        );

        // Deep-link registration — manifest-driven (src/manifest.json deepLinks).
        // Only fires when OpenRegister is installed and dispatches the event.
        $context->registerService(
            DeepLinkRegistrationListener::class,
            static function (ContainerInterface $c) use ($appId) {
                $class = 'OCA\\OpenRegister\\AppHost\\Listener\\GenericDeepLinkRegistrationListener';
                return new $class(
                    appId: $appId,
                    appManager: $c->get('OCP\\App\\IAppManager'),
                    logger: $c->get('Psr\\Log\\LoggerInterface')
                );
            }
        );
        $context->registerEventListener(
            event: DeepLinkRegistrationEvent::class,
            listener: DeepLinkRegistrationListener::class
        );
    }//end registerAppHost()

    /**
     * Boot the application.
     *
     * @param IBootContext $context The boot context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function boot(IBootContext $context): void
    {
    }//end boot()
}//end class
