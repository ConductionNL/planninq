<?php

/**
 * The store controller binding reaches openregister's Bootstrap at its current signature.
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

namespace OCA\Planninq\Tests\Unit\AppInfo;

use OCA\OpenRegister\AppHost\Bootstrap;
use OCA\Planninq\AppInfo\AppHostRegistration;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * registerStore() calls Bootstrap::aliasStoreController() with a LeafAdoption.
 *
 * The old call passed appId: and controllerNs: by name. Against the
 * LeafAdoption signature that is an unknown named parameter, which the old
 * catch(\Throwable) swallowed, leaving /api/store/items on HTTP 500 with every
 * suite green. This test records the call the fixture receives, so the old
 * shape reads as "no binding" and fails here.
 */
class AppHostRegistrationStoreTest extends TestCase {
	/**
	 * The store binding arrives with planninq's app id and controller namespace.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRegisterStoreBindsThroughALeafAdoption(): void {
		require_once __DIR__ . '/fixtures/openregister-leaf-adoption-bootstrap.php';

		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		(new AppHostRegistration())->registerStore(context: $context);

		self::assertSame(
			expected: [['appId' => 'planninq', 'controllerNs' => 'OCA\\Planninq\\Controller']],
			actual: Bootstrap::$calls
		);
	}//end testRegisterStoreBindsThroughALeafAdoption()
}//end class
