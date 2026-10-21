<?php

/**
 * The OpenRegister event stubs carry exactly the public methods of the real classes.
 *
 * WHY THIS EXISTS
 * ---------------
 * Listener tests construct the event classes from tests/stubs/openregister when
 * no Nextcloud tree is loaded. A stub with a method the real class lacks makes a
 * wrong accessor pass: learniq#984 called `getObject()` on `ObjectUpdatingEvent`,
 * which only has `getNewObject()` and `getOldObject()`, and every object update
 * on the instance answered 500 while the suite was green.
 *
 * This compares each stub with the real source when the openregister app sits
 * beside this one (the Nextcloud apps directory, or a lane clone), or when
 * OPENREGISTER_SOURCE points at it. Without that source the check is skipped
 * and says so; it is never reported as a pass.
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Stub-versus-source comparison for the OpenRegister event stubs.
 */
class OpenRegisterEventStubDriftTest extends TestCase {

	/**
	 * One case per event stub.
	 *
	 * @return array<string, array{0: string}> Stub file paths keyed by class name.
	 */
	public static function stubProvider(): array {
		$cases = [];
		foreach ((array)glob(__DIR__ . '/../../stubs/openregister/Event/*.php') as $file) {
			$cases[basename((string)$file, '.php')] = [(string)$file];
		}

		return $cases;
	}//end stubProvider()

	/**
	 * A stub declares the same public methods as the class it mirrors.
	 *
	 * @param string $stubFile The stub file.
	 *
	 * @return void
	 */
	#[DataProvider('stubProvider')]
	public function testStubDeclaresTheRealPublicMethods(string $stubFile): void {
		$root = $this->openRegisterRoot();
		if ($root === null) {
			$this->markTestSkipped(
				'openregister source not found beside planninq and OPENREGISTER_SOURCE is unset:'
				. ' stub drift is UNVERIFIED by this run.'
			);
		}

		$realFile = $root . '/lib/Event/' . basename($stubFile);
		self::assertFileExists(
			filename: $realFile,
			message: 'The stub mirrors a class OpenRegister no longer ships: ' . basename($stubFile)
		);

		self::assertSame(
			expected: $this->publicMethods(file: $realFile),
			actual: $this->publicMethods(file: $stubFile),
			message: basename($stubFile) . ' drifted from ' . $realFile
		);

	}//end testStubDeclaresTheRealPublicMethods()

	/**
	 * The openregister source root, when one is available.
	 *
	 * @return string|null The root, or null when absent.
	 */
	private function openRegisterRoot(): ?string {
		$candidates = [
			(string)getenv('OPENREGISTER_SOURCE'),
			dirname(__DIR__, 4) . '/openregister',
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== '' && is_dir($candidate . '/lib/Event') === true) {
				return $candidate;
			}
		}

		return null;
	}//end openRegisterRoot()

	/**
	 * Sorted public method names declared in a PHP file.
	 *
	 * @param string $file The file.
	 *
	 * @return array<int,string> Method names.
	 */
	private function publicMethods(string $file): array {
		preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', (string)file_get_contents($file), $matches);
		$names = array_values(array_unique($matches[1]));
		sort($names);

		return $names;
	}//end publicMethods()
}//end class
