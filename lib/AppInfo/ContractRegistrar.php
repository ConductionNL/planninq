<?php

/**
 * Planninq ContractRegistrar
 *
 * Bindings of the Kernel contracts to their implementations.
 *
 * @category AppInfo
 * @package  OCA\Planninq\AppInfo
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract wiring, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\AppInfo;

use OCA\Planninq\Kernel\Contracts\ProjectMembershipServiceInterface;
use OCA\Planninq\Kernel\Contracts\TaskScopeResolverInterface;
use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Nextcloud autowires concrete classes but not interfaces, so every contract
 * in OCA\Planninq\Kernel\Contracts that a caller type-hints needs an alias to
 * the class that implements it. An alias resolves to the same shared instance
 * as the implementation itself.
 *
 * @category AppInfo
 * @package  OCA\Planninq\AppInfo
 * @author   Conduction Development Team <dev@conductio.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract wiring, no functional requirement.
 */
final class ContractRegistrar {

	/**
	 * Alias every contract to its implementation.
	 *
	 * @param IRegistrationContext $context Registration context
	 *
	 * @return void
	 *
	 * @spec exclude Structural contract wiring, no functional requirement.
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(ProjectMembershipServiceInterface::class, ProjectMembershipService::class);
		$context->registerServiceAlias(TaskScopeResolverInterface::class, TaskScopeResolver::class);
	}//end register()
}//end class
