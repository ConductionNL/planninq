<?php

/**
 * Unit tests for MailIntakeController: only an admin may test the intake mailbox.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\MailIntakeController;
use OCA\Planninq\Service\MailIntake\MailboxConnectionTest;
use OCA\Planninq\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The connection test is the admin's alone.
 *
 * @covers \OCA\Planninq\Controller\MailIntakeController
 */
class MailIntakeControllerTest extends TestCase {

	/**
	 * A non-admin is refused and the mailbox is not opened.
	 *
	 * @return void
	 */
	public function testANonAdminIsRefused(): void {
		$settings = $this->createMock(SettingsService::class);
		$settings->method('isCurrentUserAdmin')->willReturn(false);
		$test = $this->createMock(MailboxConnectionTest::class);
		$test->expects($this->never())->method('run');

		$response = (new MailIntakeController($this->createMock(IRequest::class), $test, $settings))->test();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'Admin privileges required to test the mailbox.'], $response->getData());
	}//end testANonAdminIsRefused()

	/**
	 * An admin gets the test's answer as it is.
	 *
	 * @return void
	 */
	public function testAnAdminGetsTheTestsAnswer(): void {
		$settings = $this->createMock(SettingsService::class);
		$settings->method('isCurrentUserAdmin')->willReturn(true);
		$test = $this->createMock(MailboxConnectionTest::class);
		$test->expects($this->once())->method('run')->willReturn(['ok' => false, 'reason' => MailboxConnectionTest::NO_CLIENT, 'messages' => null]);

		$response = (new MailIntakeController($this->createMock(IRequest::class), $test, $settings))->test();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['ok' => false, 'reason' => 'no-client', 'messages' => null], $response->getData());
	}//end testAnAdminGetsTheTestsAnswer()
}//end class
