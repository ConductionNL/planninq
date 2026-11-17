<?php

/**
 * Unit tests for AppIdentity.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Kernel
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

namespace OCA\Planninq\Tests\Unit\Kernel;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Kernel\AppIdentity;
use PHPUnit\Framework\TestCase;

/**
 * The app id is the one the application registers under.
 *
 * @covers \OCA\Planninq\Kernel\AppIdentity
 */
final class AppIdentityTest extends TestCase {

	/**
	 * The id is planninq, and the application class reads the same one.
	 *
	 * @return void
	 */
	public function testTheAppIdIsTheApplicationsId(): void {
		$this->assertSame('planninq', AppIdentity::APP_ID);
		$this->assertSame(AppIdentity::APP_ID, Application::APP_ID);
	}//end testTheAppIdIsTheApplicationsId()
}//end class
