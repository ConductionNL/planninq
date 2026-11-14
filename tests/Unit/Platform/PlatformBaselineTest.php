<?php

/**
 * Checks that keep the platform baseline true.
 *
 * planninq runs on the customer's own server with OpenRegister as its only
 * app dependency, signs in through Nextcloud alone, holds no HTTP client of
 * its own, has no paid tier, and declares on every routed method who may
 * call it. None of that is visible at runtime until it breaks, so this test
 * reads the source.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Platform
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#requirement-the-app-runs-on-the-customers-own-server-req-plb-004
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;

/**
 * Source-level checks for REQ-PLB-004, 005, 006, 008 and 009.
 *
 * @coversNothing
 */
class PlatformBaselineTest extends TestCase {

	/**
	 * The app root.
	 */
	private const ROOT = __DIR__ . '/../../..';

	/**
	 * Routed methods that are public on purpose, with the check each relies on.
	 *
	 * @var array<string, string>
	 */
	private const PUBLIC_ALLOW_LIST = [
		'OpenApiController::show' => 'Serves the OpenAPI description of the API, which holds no data; no token is needed.',
	];

	/**
	 * The attributes and docblock tags that say who may call a method.
	 *
	 * @var list<string>
	 */
	private const ACCESS_MARKERS = ['NoAdminRequired', 'PublicPage', 'AuthorizedAdminSetting', 'SubAdminRequired'];

	/**
	 * OpenRegister is the only app planninq depends on (REQ-PLB-004).
	 *
	 * @return void
	 */
	public function testOpenRegisterIsTheOnlyAppDependency(): void {
		$info = new SimpleXMLElement((string) file_get_contents(self::ROOT . '/appinfo/info.xml'));
		$children = [];
		foreach ($info->dependencies->children() as $name => $node) {
			$children[] = $name;
			if ($name === 'app' || $name === 'lib') {
				$this->assertSame('openregister', (string) $node, 'planninq depends on no app but OpenRegister');
			}
		}

		$this->assertContains('nextcloud', $children);
		$this->assertSame([], array_diff($children, ['php', 'nextcloud', 'app', 'database', 'lib', 'command']), 'unexpected dependency kinds');
		// REQ-PLB-009: every feature ships under the open source licence the app declares.
		$this->assertSame('EUPL-1.2', (string) $info->licence);
		$this->assertStringStartsWith('EUROPEAN UNION PUBLIC LICENCE v. 1.2', (string) file_get_contents(self::ROOT . '/LICENSE'));
	}//end testOpenRegisterIsTheOnlyAppDependency()

	/**
	 * No file under lib/ makes HTTP calls of its own (REQ-PLB-008) or signs anyone in (REQ-PLB-005).
	 *
	 * @return void
	 */
	public function testLibHoldsNoHttpClientAndNoSignInCode(): void {
		$patterns = [
			'an HTTP client (REQ-PLB-008)'         => '/\bIClientService\b|\bcurl_[a-z_]+\s*\(|\bGuzzleHttp\\\\/',
			'a sign-in of its own (REQ-PLB-005)'   => '/->login\s*\(|\bpassword_verify\s*\(|\bIUserSession\b[^;\n]*->setUser\s*\(|implements\s+[^{]*\\\\?OCP\\\\Authentication\\\\/',
			'a session handler (REQ-PLB-005)'      => '/\bsession_start\s*\(|\bsession_set_save_handler\s*\(/',
		];
		$found = [];
		foreach ($this->files(dir: 'lib', extensions: ['php']) as $file => $source) {
			foreach ($patterns as $what => $pattern) {
				if (preg_match($pattern, $source) === 1) {
					$found[] = $file . ': ' . $what;
				}
			}
		}

		$this->assertSame([], $found);
	}//end testLibHoldsNoHttpClientAndNoSignInCode()

	/**
	 * No code reads a licence key, plan or tier to switch a feature (REQ-PLB-009).
	 *
	 * @return void
	 */
	public function testNoFeatureSitsBehindALicenceOrTier(): void {
		$pattern = '/licen[cs]e[_-]?key|\bisPremium\b|\bisEnterprise\b|\bpaidTier\b|subscription[_-]?tier|\bplanTier\b|\bgetTier\s*\(/i';
		$found = [];
		foreach (['lib' => ['php'], 'src' => ['js', 'vue', 'ts']] as $dir => $extensions) {
			foreach ($this->files(dir: $dir, extensions: $extensions) as $file => $source) {
				if (preg_match($pattern, $source) === 1) {
					$found[] = $file;
				}
			}
		}

		$this->assertSame([], $found);
	}//end testNoFeatureSitsBehindALicenceOrTier()

	/**
	 * Every routed method says who may call it, and every public one is on the allow-list (REQ-PLB-006).
	 *
	 * @return void
	 */
	public function testEveryRoutedMethodDeclaresItsAccess(): void {
		preg_match_all("/'name'\s*=>\s*'([A-Za-z\\\\]+)#([A-Za-z]+)'/", (string) file_get_contents(self::ROOT . '/appinfo/routes.php'), $routes, PREG_SET_ORDER);
		$this->assertGreaterThan(10, count($routes), 'the routes were read');

		$unmarked = [];
		$public = [];
		foreach ($routes as [, $controller, $method]) {
			$class = str_replace('\\', '/', ucfirst($controller)) . 'Controller';
			$path = self::ROOT . '/lib/Controller/' . $class . '.php';
			$this->assertFileExists($path, $controller . '#' . $method . ' routes to a controller');
			$markers = $this->markersOf(source: (string) file_get_contents($path), method: $method);
			$this->assertNotNull($markers, $class . '::' . $method . ' exists');
			if ($markers === []) {
				$unmarked[] = $class . '::' . $method;
			}

			if (in_array('PublicPage', $markers, true) === true) {
				$public[] = basename($class) . '::' . $method;
			}
		}

		$this->assertSame([], $unmarked, 'every routed method declares its access');
		$this->assertSame([], array_values(array_diff(array_unique($public), array_keys(self::PUBLIC_ALLOW_LIST))), 'every public method is on the allow-list');
	}//end testEveryRoutedMethodDeclaresItsAccess()

	/**
	 * The access markers on one method: its attributes and docblock tags.
	 *
	 * @param string $source The controller source
	 * @param string $method The method name
	 *
	 * @return list<string>|null The markers, or null when the method is not there.
	 */
	private function markersOf(string $source, string $method): ?array {
		if (preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
			return null;
		}

		$before = substr($source, 0, $match[0][1]);
		// From the end of the previous member (a closing brace at method depth,
		// or the class's opening line) up to this method: its docblock and attributes.
		$previous = max((int) strrpos($before, "\n\t}"), (int) strrpos($before, "\n\tprivate "), (int) strrpos($before, "\n\tpublic const"), (int) strpos($before, "\nclass "));
		$head = substr($before, $previous);

		$markers = [];
		foreach (self::ACCESS_MARKERS as $marker) {
			if (preg_match('/#\[[^\]]*\b' . $marker . '\b|@' . $marker . '\b/', $head) === 1) {
				$markers[] = $marker;
			}
		}

		return $markers;
	}//end markersOf()

	/**
	 * Every file under a directory with one of these extensions, by relative path.
	 *
	 * @param string       $dir        The directory under the app root
	 * @param list<string> $extensions The extensions
	 *
	 * @return array<string, string> Path to source.
	 */
	private function files(string $dir, array $extensions): array {
		$out = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/' . $dir));
		foreach ($iterator as $file) {
			if ($file->isFile() === true && in_array($file->getExtension(), $extensions, true) === true) {
				$out[$dir . substr($file->getPathname(), strlen(self::ROOT . '/' . $dir))] = (string) file_get_contents($file->getPathname());
			}
		}

		return $out;
	}//end files()
}//end class
