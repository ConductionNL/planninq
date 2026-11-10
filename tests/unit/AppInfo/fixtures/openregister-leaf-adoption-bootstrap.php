<?php

/**
 * Test fixture: openregister's Bootstrap and LeafAdoption at the shape of
 * openregister #9078, where aliasStoreController() takes a LeafAdoption.
 *
 * Loaded only by AppHostRegistrationStoreTest, in a separate process, so the
 * declarations never reach another test.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\AppInfo
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

namespace OCA\OpenRegister\AppHost;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Mirror of openregister's LeafAdoption (the parts aliasStoreController reads).
 */
final class LeafAdoption {
	/**
	 * Constructor.
	 *
	 * @param string               $appId   The leaf app id.
	 * @param array<string, mixed> $options The adoption overrides.
	 */
	public function __construct(
		public readonly string $appId,
		private readonly array $options = [],
	) {
	}//end __construct()

	/**
	 * Resolve a namespace override, else the app namespace plus a suffix.
	 *
	 * @param string $option The override key.
	 * @param string $suffix The suffix after the base namespace.
	 *
	 * @return string The namespace.
	 */
	public function namespace(string $option, string $suffix): string {
		$base = rtrim((string)($this->options['namespace'] ?? ('OCA\\' . ucfirst($this->appId))), '\\');
		return (string)($this->options[$option] ?? ($base . '\\' . $suffix));
	}//end namespace()
}//end class

/**
 * Mirror of openregister's Bootstrap::aliasStoreController() after #9078; records each call.
 */
class Bootstrap {
	/**
	 * The recorded calls: app id and controller namespace.
	 *
	 * @var list<array{appId: string, controllerNs: string}>
	 */
	public static array $calls = [];

	/**
	 * Bind the store controller.
	 *
	 * @param IRegistrationContext $context  Leaf registration context.
	 * @param LeafAdoption         $adoption Leaf app id and namespace.
	 *
	 * @return void
	 */
	public static function aliasStoreController(IRegistrationContext $context, LeafAdoption $adoption): void {
		self::$calls[] = [
			'appId'        => $adoption->appId,
			'controllerNs' => $adoption->namespace(option: 'controllerNamespace', suffix: 'Controller'),
		];
	}//end aliasStoreController()
}//end class
