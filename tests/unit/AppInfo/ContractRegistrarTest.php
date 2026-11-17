<?php

/**
 * Unit tests for ContractRegistrar.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\AppInfo;

use OCA\Planninq\AppInfo\ContractRegistrar;
use OCA\Planninq\Kernel\Contracts\ProjectMembershipServiceInterface;
use OCA\Planninq\Kernel\Contracts\TaskScopeResolverInterface;
use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

/**
 * Each Kernel contract is aliased to the class that implements it.
 *
 * @covers \OCA\Planninq\AppInfo\ContractRegistrar
 */
final class ContractRegistrarTest extends TestCase {

	/**
	 * The registrar binds the two contracts, each to its implementation.
	 *
	 * @return void
	 */
	public function testEachContractIsBoundToItsImplementation(): void {
		$aliases = [];
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->exactly(2))->method('registerServiceAlias')->willReturnCallback(
			static function (string $alias, string $target) use (&$aliases): void {
				$aliases[$alias] = $target;
			}
		);

		(new ContractRegistrar())->register(context: $context);

		$this->assertSame(
			[
				ProjectMembershipServiceInterface::class => ProjectMembershipService::class,
				TaskScopeResolverInterface::class => TaskScopeResolver::class,
			],
			$aliases
		);
		foreach ($aliases as $alias => $target) {
			$this->assertTrue(is_subclass_of($target, $alias), $target.' implements '.$alias);
		}
	}//end testEachContractIsBoundToItsImplementation()
}//end class
