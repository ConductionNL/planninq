<?php

/**
 * Static-analysis stub for OpenRegister's AppHost bootstrap entry point.
 *
 * ANALYSIS-ONLY. Nothing autoloads or executes this file: it sits outside the
 * `tests/stubs/openregister/` PSR-4 root the unit bootstrap maps, so
 * `class_exists(Bootstrap::class)` stays false under PHPUnit. psalm reads it
 * from `psalm.xml` `<stubs>` and phpstan from `phpstan.neon` `scanFiles`.
 *
 * WHY A LOCAL COPY. The shared stub in hydra-gates (v1.19.0) still mirrors the
 * old `aliasStoreController(context, appId, controllerNs)` contract. planninq
 * calls the current one, which takes a `LeafAdoption`, so both analysers
 * reported a call that runs fine on OpenRegister main as wrong. This file
 * mirrors the public surface of `openregister/lib/AppHost/Bootstrap.php` and
 * `LeafAdoption.php` on main. When the shared stub catches up, drop this
 * file and point both configs back at it.
 *
 * DRIFT IS THE COST. When the engine changes a public signature, change this
 * file with it.
 *
 * @category Test
 * @package  OCA\OpenRegister\AppHost
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
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\OpenRegister\AppHost;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * A leaf app's id and its namespace overrides.
 *
 * Analysis-only stub. Mirrored from openregister/lib/AppHost/LeafAdoption.php.
 */
final class LeafAdoption {

	/**
	 * Constructor.
	 *
	 * @param string               $appId   The leaf app id.
	 * @param array<string, mixed> $options The namespace and section overrides.
	 */
	public function __construct(
		public readonly string $appId,
		private readonly array $options = [],
	) {
	}//end __construct()

	/**
	 * The app id in StudlyCase.
	 *
	 * @return string
	 */
	public function studly(): string {
		return ucfirst($this->appId);
	}//end studly()

	/**
	 * One namespace of the leaf: the override, else the base plus the suffix.
	 *
	 * @param string $option The override key.
	 * @param string $suffix The suffix after the base namespace.
	 *
	 * @return string
	 */
	public function namespace(string $option, string $suffix): string {
		return (string) ($this->options[$option] ?? ('OCA\\' . $this->studly() . '\\' . $suffix));
	}//end namespace()

	/**
	 * Whether a feature flag is on (default on).
	 *
	 * @param string $flag The flag.
	 *
	 * @return bool
	 */
	public function enabled(string $flag): bool {
		return ($this->options[$flag] ?? true) !== false;
	}//end enabled()
}//end class

/**
 * Declarative one-call bootstrap for AppHost leaf apps (ADR-040).
 *
 * Analysis-only stub. Mirrored from openregister/lib/AppHost/Bootstrap.php.
 */
class Bootstrap {

	/**
	 * Register all standard AppHost plumbing for a leaf app.
	 *
	 * @param IRegistrationContext     $context  The leaf app's registration context.
	 * @param string|LeafAdoption|null $appId    The leaf app id, or the adoption.
	 * @param array<string, mixed>     $options  Optional overrides, documented on the engine.
	 * @param LeafAdoption|null        $adoption The leaf app id and overrides.
	 *
	 * @return void
	 */
	public static function register(
		IRegistrationContext $context,
		string|LeafAdoption|null $appId = null,
		array $options = [],
		?LeafAdoption $adoption = null,
	): void {
	}//end register()

	/**
	 * Bind the store controller the shared route table already declares.
	 *
	 * @param IRegistrationContext     $context      The leaf app's registration context.
	 * @param string|LeafAdoption|null $appId        The leaf app id, or the adoption.
	 * @param string|null              $controllerNs The leaf's controller namespace; derived when null.
	 * @param LeafAdoption|null        $adoption     The leaf app id and namespace overrides.
	 *
	 * @return void
	 */
	public static function aliasStoreController(
		IRegistrationContext $context,
		string|LeafAdoption|null $appId = null,
		?string $controllerNs = null,
		?LeafAdoption $adoption = null,
	): void {
	}//end aliasStoreController()
}//end class
