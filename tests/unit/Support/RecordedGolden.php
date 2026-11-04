<?php

/**
 * Compare a recorded structure with its fixture, case by case.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
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

namespace OCA\Planninq\Tests\Unit\Support;

/**
 * RECORD_GOLDEN=1 writes the fixture; any other run compares against it.
 */
trait RecordedGolden {

	/**
	 * Assert each case equals its recording.
	 *
	 * @param string               $name     The fixture name.
	 * @param array<string,mixed> $recorded The cases.
	 *
	 * @return void
	 */
	private function assertMatchesRecording(string $name, array $recorded): void {
		$file    = __DIR__ . '/../fixtures/golden/' . $name . '.json';
		$encoded = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents($file, $encoded . "\n");
		}

		$fixture = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		$actual  = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($actual));
		foreach ($actual as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end assertMatchesRecording()
}//end trait
