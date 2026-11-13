<?php

/**
 * Unit tests for the exceptions that carry a machine-readable reason.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Exception
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Exception;

use OCA\Planninq\Exception\CaseHandoverException;
use OCA\Planninq\Exception\MsProjectImportException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The reason, message and runtime-exception type survive the throw.
 *
 * @covers \OCA\Planninq\Exception\CaseHandoverException
 * @covers \OCA\Planninq\Exception\MsProjectImportException
 */
class ReasonExceptionsTest extends TestCase {

	/**
	 * A case handover failure keeps its reason and message.
	 *
	 * @return void
	 */
	public function testCaseHandoverKeepsReasonAndMessage(): void {
		$exception = new CaseHandoverException(reason: CaseHandoverException::CASE_NOT_FOUND, message: 'Case c-1 not found');

		$this->assertInstanceOf(expected: RuntimeException::class, actual: $exception);
		$this->assertSame(expected: 'caseNotFound', actual: $exception->getReason());
		$this->assertSame(expected: 'Case c-1 not found', actual: $exception->getMessage());
		$this->assertSame(expected: 0, actual: $exception->getCode());
	}//end testCaseHandoverKeepsReasonAndMessage()

	/**
	 * The handover reasons are distinct strings the frontend switches on.
	 *
	 * @return void
	 */
	public function testCaseHandoverReasonsAreDistinct(): void {
		$this->assertSame(
			expected: ['noCaseApp', 'noCase', 'caseNotFound'],
			actual: [CaseHandoverException::NO_CASE_APP, CaseHandoverException::NO_CASE, CaseHandoverException::CASE_NOT_FOUND]
		);
	}//end testCaseHandoverReasonsAreDistinct()

	/**
	 * An import failure keeps its reason and message, and is catchable as a runtime exception.
	 *
	 * @return void
	 */
	public function testImportFailureKeepsReasonAndMessage(): void {
		try {
			throw new MsProjectImportException(reason: MsProjectImportException::TOO_MANY_TASKS, message: 'More than 5000 tasks');
		} catch (RuntimeException $caught) {
			$this->assertInstanceOf(expected: MsProjectImportException::class, actual: $caught);
			$this->assertSame(expected: 'tooManyTasks', actual: $caught->getReason());
			$this->assertSame(expected: 'More than 5000 tasks', actual: $caught->getMessage());
		}
	}//end testImportFailureKeepsReasonAndMessage()
}//end class
