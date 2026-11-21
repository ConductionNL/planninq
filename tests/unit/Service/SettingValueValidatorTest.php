<?php

/**
 * Unit tests for SettingValueValidator: the shape rules of submitted settings.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\SettingValueValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\SettingValueValidator
 */
class SettingValueValidatorTest extends TestCase {

	/**
	 * A non-empty list of names is trimmed; anything else is refused.
	 *
	 * @return void
	 */
	public function testNonEmptyNamesAreTrimmedAndEmptyOrMalformedListsRefused(): void {
		$values = new SettingValueValidator();

		$this->assertSame(expected: '["To do","Done"]', actual: $values->nonEmptyNames(raw: '[" To do ","Done"]'));
		$this->assertNull(actual: $values->nonEmptyNames(raw: '[]'));
		$this->assertNull(actual: $values->nonEmptyNames(raw: 'not json'));
		$this->assertNull(actual: $values->nonEmptyNames(raw: '"Done"'));
		$this->assertNull(actual: $values->nonEmptyNames(raw: '["Done","  "]'));
		$this->assertNull(actual: $values->nonEmptyNames(raw: '["Done",3]'));
	}//end testNonEmptyNamesAreTrimmedAndEmptyOrMalformedListsRefused()

	/**
	 * A list of distinct names refuses a name repeated in another case or with spaces.
	 *
	 * @return void
	 */
	public function testDistinctNamesRefuseARepeatIgnoringCaseAndSpaces(): void {
		$values = new SettingValueValidator();

		$this->assertSame(expected: '["Personnel","Other"]', actual: $values->distinctNames(raw: '["Personnel","Other"]'));
		$this->assertNull(actual: $values->distinctNames(raw: '["Personnel"," personnel "]'));
		$this->assertNull(actual: $values->distinctNames(raw: '[]'));
	}//end testDistinctNamesRefuseARepeatIgnoringCaseAndSpaces()

	/**
	 * An optional list may be empty, must be a list, and caps the name length.
	 *
	 * @return void
	 */
	public function testOptionalNamesMayBeEmptyAndCapTheNameLength(): void {
		$values = new SettingValueValidator();

		$this->assertSame(expected: '[]', actual: $values->optionalNames(raw: '[]', maxLength: 5));
		$this->assertSame(expected: '["Build","Test"]', actual: $values->optionalNames(raw: '["Build","Test"]', maxLength: 5));
		$this->assertNull(actual: $values->optionalNames(raw: '["Building"]', maxLength: 5));
		$this->assertNull(actual: $values->optionalNames(raw: '{"a":"Build"}', maxLength: 5));
		$this->assertNull(actual: $values->optionalNames(raw: '["Build","build"]', maxLength: 5));
		$this->assertNull(actual: $values->optionalNames(raw: '[1]', maxLength: 5));
		$this->assertNull(actual: $values->optionalNames(raw: 'nope', maxLength: 5));
	}//end testOptionalNamesMayBeEmptyAndCapTheNameLength()

	/**
	 * A whole number is accepted within its bounds only.
	 *
	 * @return void
	 */
	public function testWholeNumberWithinBounds(): void {
		$values = new SettingValueValidator();

		$this->assertSame(expected: 1, actual: $values->wholeNumber(raw: '1', min: 1, max: 365));
		$this->assertSame(expected: 14, actual: $values->wholeNumber(raw: ' 14 ', min: 1, max: 365));
		$this->assertSame(expected: 365, actual: $values->wholeNumber(raw: '365', min: 1, max: 365));
		$this->assertNull(actual: $values->wholeNumber(raw: '0', min: 1, max: 365));
		$this->assertNull(actual: $values->wholeNumber(raw: '366', min: 1, max: 365));
		$this->assertNull(actual: $values->wholeNumber(raw: '-3', min: 1, max: 365));
		$this->assertNull(actual: $values->wholeNumber(raw: '2.5', min: 1, max: 365));
		$this->assertNull(actual: $values->wholeNumber(raw: '', min: 1, max: 365));
	}//end testWholeNumberWithinBounds()
}//end class
