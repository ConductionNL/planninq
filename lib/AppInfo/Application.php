<?php

/**
 * Planninq Application
 *
 * Main application class for the Planninq Nextcloud app.
 *
 * @category AppInfo
 * @package  OCA\Planninq\AppInfo
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

namespace OCA\Planninq\AppInfo;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\DeepLinkRegistrationListener;
use OCA\Planninq\Listener\TaskActivityListener;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Container\ContainerInterface;

/**
 * Main application class for the Planninq Nextcloud app.
 *
 * @spec openspec/specs/app-metadata/spec.md
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'planninq';

	/**
	 * Constructor for the Application class.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct(appName: self::APP_ID);
	}//end __construct()

	/**
	 * Register event listeners and services.
	 *
	 * @param IRegistrationContext $context The registration context
	 *
	 * @return void
	 */
	public function register(IRegistrationContext $context): void {
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

		// NOTE: the task-lifecycle Activity listener is subscribed from boot(),
		// not here — see registerFilteredObjectListener().
		//
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
	 * OpenRegister is absent — planninq carries no hard dependency on it — this
	 * degrades to the plain global registration it replaced, which is exactly
	 * the behaviour every listener had before.
	 *
	 * MUST be called from boot(), never from register(). Nextcloud enables each
	 * app's own autoloader immediately before calling that app's register(), so
	 * during register() OpenRegister's classes are only autoloadable to apps
	 * that happen to be registered after it — the class_exists() guard below
	 * would then resolve differently purely by app load order and silently fall
	 * back to an unfiltered registration. boot() runs only after every app's
	 * register() has completed, so the guard is order-independent there.
	 *
	 * @param IEventDispatcher $dispatcher The live event dispatcher.
	 * @param string $event OpenRegister event class name.
	 * @param string $listener Listener class name.
	 * @param array<int,string> $registers Register slugs the listener reacts to.
	 * @param array<int,string> $schemas Schema slugs the listener reacts to.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	private function registerFilteredObjectListener(
		IEventDispatcher $dispatcher,
		string $event,
		string $listener,
		array $registers,
		array $schemas,
	): void {
		$subscription = '\\OCA\\OpenRegister\\Event\\ObjectEventSubscription';
		if (class_exists($subscription) === true) {
			$subscription::subscribe(
				dispatcher: $dispatcher,
				event: $event,
				listener: $listener,
				registers: $registers,
				schemas: $schemas
			);
			return;
		}

		// Loud on purpose. This fallback is correct but UNFILTERED, and while it
		// was silent it was indistinguishable from a working narrowing.
		\OCP\Server::get(\Psr\Log\LoggerInterface::class)->warning(
			'OpenRegister ObjectEventSubscription unavailable: ' . $listener
			. ' fell back to an UNFILTERED registration for ' . $event
			. ' and will be invoked on every object write instance-wide.',
			['app' => self::APP_ID]
		);

		$dispatcher->addServiceListener($event, $listener);
	}//end registerFilteredObjectListener()

	/**
	 * OpenRegister's deep-link registration event name.
	 *
	 * @var string
	 */
	private const OR_DEEPLINK_REGISTRATION_EVENT = 'OCA\\OpenRegister\\Event\\DeepLinkRegistrationEvent';

	/**
	 * Leaf DI service id for the AppHost dashboard SPA controller.
	 *
	 * @var string
	 */
	private const LEAF_DASHBOARD_CONTROLLER = 'OCA\\Planninq\\Controller\\DashboardController';

	/**
	 * Leaf DI service id for the AppHost per-user preferences controller.
	 *
	 * @var string
	 */
	private const LEAF_PREFERENCES_CONTROLLER = 'OCA\\Planninq\\Controller\\PreferencesController';

	/**
	 * Leaf DI service id for the AppHost health controller.
	 *
	 * @var string
	 */
	private const LEAF_HEALTH_CONTROLLER = 'OCA\\Planninq\\Controller\\HealthController';

	/**
	 * Leaf DI service id for the AppHost metrics controller.
	 *
	 * @var string
	 */
	private const LEAF_METRICS_CONTROLLER = 'OCA\\Planninq\\Controller\\MetricsController';

	/**
	 * Leaf DI service id for the AppHost admin settings section.
	 *
	 * @var string
	 */
	private const LEAF_SETTINGS_SECTION = 'OCA\\Planninq\\Sections\\SettingsSection';

	/**
	 * Wire the AppHost generic engine for the mechanical plumbing classes.
	 *
	 * Aliases the leaf class NAMES (referenced by routes.php and info.xml) to
	 * OpenRegister's `OCA\OpenRegister\AppHost\…` generics via factory closures.
	 *
	 * BOTH sides of every alias are plain strings. The OR generic FQCN is a
	 * string so this method never autoloads an OR class at bootstrap. The leaf
	 * service id is a string because — apart from AdminSettings, see below —
	 * these leaf classes DO NOT EXIST as PHP classes at all: they are pure DI
	 * service identifiers. Nextcloud's router turns `dashboard#page` into the
	 * name `OCA\Planninq\Controller\DashboardController`, looks that string up in
	 * the app container, and gets the generic instance back. Spelling them
	 * `::class` also compiled (class-name resolution is a compile-time string
	 * operation, it never autoloads) but it asserted to psalm/phpstan that a
	 * class existed which never did — the LEAF_* constants above say the same
	 * thing truthfully. `registerService()` takes a string either way, so this
	 * is byte-identical at runtime.
	 *
	 * Two leaf names ARE real classes, both because a Nextcloud API demands a
	 * verifiable `class-string` of them — a contract no bare service id can
	 * satisfy:
	 *   - `Settings\AdminSettings` — `#[AuthorizedAdminSetting(settings: …)]` on
	 *     LabelController is typed `class-string<IDelegatedSettings>`.
	 *   - `Listener\DeepLinkRegistrationListener` — `registerEventListener()` is
	 *     typed `class-string<IEventListener<Event>>`.
	 * Both are one-line subclasses of the corresponding OR generic that add no
	 * behaviour, and both are still only autoloaded from inside the factory
	 * closures below, never at bootstrap.
	 *
	 * With OpenRegister disabled the closures simply never run (or surface as a
	 * degraded 5xx), so Nextcloud boots cleanly — the lazy-by-construction
	 * invariant of ADR-040.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerAppHost(IRegistrationContext $context): void {
		$this->registerAppHostUi(context: $context);
		$this->registerAppHostObservability(context: $context);
		$this->registerAppHostSettings(context: $context);
		$this->registerAppHostDeepLinks(context: $context);
	}//end registerAppHost()

	/**
	 * Alias the dashboard SPA and per-user preferences controllers.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerAppHostUi(IRegistrationContext $context): void {
		$appId = self::APP_ID;

		// Dashboard SPA + history-mode catch-all.
		$context->registerService(
			self::LEAF_DASHBOARD_CONTROLLER,
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
			self::LEAF_PREFERENCES_CONTROLLER,
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
	}//end registerAppHostUi()

	/**
	 * Alias the observability health + metrics controllers.
	 *
	 * Driven by the `observability` block in src/manifest.json. URLs unchanged.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerAppHostObservability(IRegistrationContext $context): void {
		$appId = self::APP_ID;

		// Health (public).
		$context->registerService(
			self::LEAF_HEALTH_CONTROLLER,
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

		// Metrics (admin).
		$context->registerService(
			self::LEAF_METRICS_CONTROLLER,
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
	}//end registerAppHostObservability()

	/**
	 * Alias the admin settings panel and its settings section.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerAppHostSettings(IRegistrationContext $context): void {
		$appId = self::APP_ID;

		// Admin settings panel (IDelegatedSettings, #299) — section id `planninq`,
		// priority 10, identical to the deleted bespoke AdminSettings. This is
		// the one leaf class that physically exists (see registerAppHost()); it
		// is a bare subclass, so every behaviour still comes from the engine.
		$context->registerService(
			AdminSettings::class,
			static fn (ContainerInterface $c): AdminSettings => new AdminSettings(
				appId: $appId,
				sectionId: $appId,
				priority: 10,
				appManager: $c->get('OCP\\App\\IAppManager'),
				initialState: $c->get('OCP\\AppFramework\\Services\\IInitialState'),
				appConfig: $c->get('OCP\\IAppConfig')
			)
		);

		// Admin settings section (IIconSection) — name `Planninq`, priority 75.
		$context->registerService(
			self::LEAF_SETTINGS_SECTION,
			static function (ContainerInterface $c) use ($appId) {
				$class = 'OCA\\OpenRegister\\AppHost\\Settings\\GenericSettingsSection';
				return new $class(
					sectionId: $appId,
					name: 'Planninq',
					appId: $appId,
					iconFile: 'app-dark.svg',
					priority: 75,
					urlGenerator: $c->get('OCP\\IURLGenerator')
				);
			}
		);
	}//end registerAppHostSettings()

	/**
	 * Alias and subscribe the manifest-driven deep-link registration listener.
	 *
	 * Only fires when OpenRegister is installed and dispatches the event
	 * (src/manifest.json `deepLinks`).
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerAppHostDeepLinks(IRegistrationContext $context): void {
		$appId = self::APP_ID;

		// Like AdminSettings this leaf class physically exists, because
		// registerEventListener() is typed `class-string<IEventListener<Event>>`
		// — see registerAppHost(). It is a bare subclass, so every behaviour
		// still comes from the engine.
		$context->registerService(
			DeepLinkRegistrationListener::class,
			static fn (ContainerInterface $c): DeepLinkRegistrationListener => new DeepLinkRegistrationListener(
				appId: $appId,
				appManager: $c->get('OCP\\App\\IAppManager'),
				logger: $c->get('Psr\\Log\\LoggerInterface')
			)
		);
		// The event NAME is a plain string for the same reason every OR generic
		// FQCN in this class is: IEventDispatcher keys on the string, so nothing
		// here needs OpenRegister to be autoloadable at bootstrap.
		$context->registerEventListener(
			event: self::OR_DEEPLINK_REGISTRATION_EVENT,
			listener: DeepLinkRegistrationListener::class
		);
	}//end registerAppHostDeepLinks()

	/**
	 * Boot the application.
	 *
	 * @param IBootContext $context The boot context
	 *
	 * @return void
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function boot(IBootContext $context): void {
		$dispatcher = $context->getServerContainer()->get(IEventDispatcher::class);

		// Publish task lifecycle events to the Nextcloud Activity stream.
		//
		// The listener's own scope check (TaskScopeResolver::isPlanninqTask,
		// REGISTER_SLUG `planix` + TASK_SCHEMA_SLUG `task` — spelled out as
		// literals here so Application does not import the resolver and push
		// its already-over-threshold PHPMD coupling count up by one) is now
		// also declared at SUBSCRIPTION time, so an unrelated app's write no longer
		// constructs the listener — nor performs the two mapper lookups the
		// scope resolver needs to reject it. The in-listener guard stays in
		// place as defence in depth.
		//
		// The register slug below is still the PRE-RENAME `planix`, deliberately.
		// The app id became `planninq`, but the OpenRegister register that holds
		// the live data is still slugged `planix` on every existing install, and
		// this release ships no register-slug migration. Changing the literal
		// here without one would point the app at a register that does not
		// exist yet and silently orphan every stored task.
		foreach ([ObjectCreatedEvent::class, ObjectUpdatedEvent::class, ObjectDeletedEvent::class] as $event) {
			$this->registerFilteredObjectListener(
				dispatcher: $dispatcher,
				event: $event,
				listener: TaskActivityListener::class,
				registers: ['planix'],
				schemas: ['task']
			);
		}

		// Dependency-edge cascade, on the PRE-delete event.
		//
		// ADR-078 / gate-61 forbid a synchronous write in a POST-event listener
		// and require deferral; a `*ing` listener is the sanctioned place for
		// work that must happen INSIDE the delete, which this is — the edges
		// have to go with the task, not eventually. Before this registration
		// `DependencyService::removeEdgesForTask()` had no caller at all, so
		// deleting a task left its edges pointing at nothing (gate-57).
		//
		// Both class names are LITERAL STRINGS, not imports, for the same reason
		// the scope resolver's slugs are literals above: `Application` sits at
		// PHPMD's CouplingBetweenObjects threshold, and two more `use` lines
		// measured 14 against a limit of 13. The helper takes `string`, so
		// nothing is lost but the coupling count.
		$this->registerFilteredObjectListener(
			dispatcher: $dispatcher,
			event: 'OCA\\OpenRegister\\Event\\ObjectDeletingEvent',
			listener: 'OCA\\Planninq\\Listener\\TaskDependencyCleanupListener',
			registers: ['planix'],
			schemas: ['task']
		);
	}//end boot()
}//end class
