<?php

/**
 * Planninq AppHost registration
 *
 * Wires OpenRegister's AppHost generic engine for the app's plumbing classes.
 *
 * @category AppInfo
 * @package  OCA\Planninq\AppInfo
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

namespace OCA\Planninq\AppInfo;

use OCA\OpenRegister\AppHost\Bootstrap;
use OCA\OpenRegister\AppHost\LeafAdoption;
use OCA\Planninq\Listener\DeepLinkRegistrationListener;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Container\ContainerInterface;

/**
 * Wires the AppHost generic engine for the mechanical plumbing classes.
 *
 * Aliases the leaf class NAMES (referenced by routes.php and info.xml) to
 * OpenRegister's `OCA\OpenRegister\AppHost\…` generics via factory closures.
 *
 * BOTH sides of every alias are plain strings. The OR generic FQCN is a
 * string so this class never autoloads an OR class at bootstrap. The leaf
 * service id is a string because — apart from AdminSettings, see below —
 * these leaf classes DO NOT EXIST as PHP classes at all: they are pure DI
 * service identifiers. Nextcloud's router turns `dashboard#page` into the
 * name `OCA\Planninq\Controller\DashboardController`, looks that string up in
 * the app container, and gets the generic instance back. Spelling them
 * `::class` also compiled (class-name resolution is a compile-time string
 * operation, it never autoloads) but it asserted to psalm/phpstan that a
 * class existed which never did — the LEAF_* constants say the same
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
 * The health and metrics controllers stay in Application: the gate that
 * allows container lookups of OpenRegister classes allows them only there.
 *
 * @spec openspec/specs/app-shell-and-data-store/spec.md
 */
class AppHostRegistration {
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
	 * Leaf DI service id for the AppHost admin settings section.
	 *
	 * @var string
	 */
	private const LEAF_SETTINGS_SECTION = 'OCA\\Planninq\\Sections\\SettingsSection';

	/**
	 * Alias the dashboard SPA and per-user preferences controllers.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-shell-and-data-store/spec.md
	 */
	public function registerUi(IRegistrationContext $context): void {
		$appId = Application::APP_ID;

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
	}//end registerUi()

	/**
	 * Alias the admin settings panel and its settings section.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-shell-and-data-store/spec.md
	 */
	public function registerSettings(IRegistrationContext $context): void {
		$appId = Application::APP_ID;

		// Admin settings panel (IDelegatedSettings, #299) — section id `planninq`,
		// priority 10, identical to the deleted bespoke AdminSettings. This is
		// the one leaf class that physically exists (see register()); it
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
	}//end registerSettings()

	/**
	 * Alias and subscribe the manifest-driven deep-link registration listener.
	 *
	 * Only fires when OpenRegister is installed and dispatches the event
	 * (src/manifest.json `deepLinks`).
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-shell-and-data-store/spec.md
	 */
	public function registerDeepLinks(IRegistrationContext $context): void {
		$appId = Application::APP_ID;

		// Like AdminSettings this leaf class physically exists, because
		// registerEventListener() is typed `class-string<IEventListener<Event>>`
		// — see register(). It is a bare subclass, so every behaviour
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
	}//end registerDeepLinks()

	/**
	 * Bind the store controller the adopted route table already declares.
	 *
	 * 🔴 THIS ROUTE ARRIVES WHETHER THE APP WANTS IT OR NOT.
	 *
	 * `Routes::standard()`, which appinfo/routes.php adopts, declares
	 * `/api/store/items`. The binding normally comes from
	 * `Bootstrap::register()`, and planninq does not call that: it aliases the
	 * plumbing classes it wants, one at a time, and keeps its own settings and
	 * kanban controllers. The store controller was never on that list.
	 *
	 * So the route matched a controller class that does not exist, and every
	 * request to it returned HTTP 500 rather than 404. Measured on a running
	 * instance 2026-09-03, alongside decidiq and filinq.
	 *
	 * The engine owns the controller's constructor argument list, which is why
	 * this calls the shared helper rather than adding a ninth hand-written
	 * factory beside the others: that argument list gained a parameter the
	 * same day this defect was found, and a hand-written copy would have
	 * broken instead of adapting.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-shell-and-data-store/spec.md
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) OCA\OpenRegister\AppHost\Bootstrap
	 * is a cross-app static entry point in a SIBLING app that may be absent or
	 * unloadable here — the call is guarded by class_exists() and wrapped in a
	 * catch(\Exception) for exactly that reason; signature drift (an \Error) is
	 * not caught. It cannot be injected: this runs at the composition root, so
	 * there is no container to resolve an adapter from.
	 */
	public function registerStore(IRegistrationContext $context): void {
		// The class_exists() guard MUST stay in this method: it is also the
		// assertion psalm relies on to accept the Bootstrap call below, and
		// psalm does not carry that narrowing across a call.
		// Application::register() has already run
		// OpenRegisterAutoloader::register().
		if (class_exists(Bootstrap::class) === true && class_exists(LeafAdoption::class) === true) {
			try {
				Bootstrap::aliasStoreController(
					context: $context,
					adoption: new LeafAdoption(
						appId: Application::APP_ID,
						options: ['controllerNamespace' => 'OCA\\Planninq\\Controller']
					)
				);
			} catch (\Exception) {
				// The engine failed at runtime. The store route is then no
				// worse off than without the binding, and every registration
				// around this one still runs.
				//
				// Only \Exception: an \Error (an unknown named parameter, a
				// TypeError, a missing method) means this call no longer matches
				// openregister's signature. Swallowing those is what hid the
				// HTTP 500 on /api/store/items, so they must fail loudly.
			}
		}

	}//end registerStore()
}//end class
