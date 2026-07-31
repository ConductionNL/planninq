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
        $this->registerAppHost(context: $context);

        // Publish task lifecycle events to the Nextcloud Activity stream.
        //
        // The listener's own scope check (TaskScopeResolver::isPlanixTask,
        // REGISTER_SLUG `planix` + TASK_SCHEMA_SLUG `task` — spelled out as
        // literals here so Application does not import the resolver and push
        // its already-over-threshold PHPMD coupling count up by one) is now
        // also declared at REGISTRATION time, so an unrelated app's write no longer
        // constructs the listener — nor performs the two mapper lookups the
        // scope resolver needs to reject it. The in-listener guard stays in
        // place as defence in depth.
        foreach ([ObjectCreatedEvent::class, ObjectUpdatedEvent::class, ObjectDeletedEvent::class] as $event) {
            $this->registerFilteredObjectListener(
                context: $context,
                event: $event,
                listener: TaskActivityListener::class,
                registers: ['planix'],
                schemas: ['task']
            );
        }

        // NOTE: the Activity Provider + Filter are registered declaratively via
        // the <activity> block in appinfo/info.xml — IRegistrationContext has no
        // activity-registration methods in this Nextcloud version.
    }//end register()

    /**
     * Register an object-lifecycle listener that declares its interest up front.
     *
     * OpenRegister's `ObjectEventSubscription` records the register/schema slugs
     * a listener reacts to and routes dispatches through a single shared proxy,
     * so an uninterested listener is neither constructed nor invoked. When
     * OpenRegister is absent — planix carries no hard dependency on it — this
     * degrades to the plain global registration it replaced, which is exactly
     * the behaviour every listener had before.
     *
     * @param IRegistrationContext $context   Registration context.
     * @param string               $event     OpenRegister event class name.
     * @param string               $listener  Listener class name.
     * @param array<int,string>    $registers Register slugs the listener reacts to.
     * @param array<int,string>    $schemas   Schema slugs the listener reacts to.
     *
     * @return void
     *
     * @spec openspec/specs/task-collaboration.md
     */
    private function registerFilteredObjectListener(
        IRegistrationContext $context,
        string $event,
        string $listener,
        array $registers,
        array $schemas
    ): void {
        $subscription = '\\OCA\\OpenRegister\\Event\\ObjectEventSubscription';
        if (class_exists($subscription) === true) {
            $subscription::register(
                context: $context,
                event: $event,
                listener: $listener,
                registers: $registers,
                schemas: $schemas
            );
            return;
        }

        $context->registerEventListener(event: $event, listener: $listener);
    }//end registerFilteredObjectListener()

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
