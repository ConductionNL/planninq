<?php

/**
 * Planninq OpenRegisterObjects
 *
 * Finds OpenRegister's ObjectService through the server container, and only
 * when the OpenRegister app is installed.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/projects/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;

/**
 * Locator for OpenRegister's ObjectService.
 *
 * @spec openspec/specs/projects/spec.md
 */
class OpenRegisterObjects {
	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager Tells whether OpenRegister is installed.
	 * @param ContainerInterface $container The server container.
	 */
	public function __construct(
		private IAppManager $appManager,
		private ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * OpenRegister's ObjectService, or null when OpenRegister is not installed.
	 *
	 * @return object|null
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function objectService(): ?object {
		if ($this->appManager->isInstalled('openregister') === false) {
			return null;
		}

		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()
}//end class
