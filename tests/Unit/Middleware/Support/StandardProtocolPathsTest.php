<?php

/**
 * Unit tests for StandardProtocolPaths.
 *
 * Covers the app-root stripping that turns a full request path into an
 * app-relative one, and the protocol match that the API contract middleware
 * and the OpenAPI document both read through.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Middleware\Support
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/api-contract/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Middleware\Support;

use OCA\Planninq\Middleware\Support\StandardProtocolPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StandardProtocolPaths.
 *
 * @covers \OCA\Planninq\Middleware\Support\StandardProtocolPaths
 */
class StandardProtocolPathsTest extends TestCase {

	/**
	 * Request paths and the app-relative path they reduce to.
	 *
	 * @return array<string,array{string,string}>
	 */
	public static function appRelativeProvider(): array {
		return [
			'already relative'            => ['/api/health', '/api/health'],
			'relative without slash'      => ['api/health', '/api/health'],
			'full request path'           => ['/apps/planninq/api/health', '/api/health'],
			'index.php request path'      => ['/index.php/apps/planninq/api/metrics', '/api/metrics'],
			'nested below the app root'   => ['/apps/planninq/api/projects/p-1/flow', '/api/projects/p-1/flow'],
			'another app is left whole'   => ['/apps/openregister/api/health', '/apps/openregister/api/health'],
			'app root only, no trailing'  => ['/apps/planninq', '/apps/planninq'],
			'root of the app'             => ['/apps/planninq/', '/'],
		];

	}//end appRelativeProvider()

	/**
	 * appRelative strips the app root wherever it appears, and leaves other paths whole.
	 *
	 * @param string $path     The request or app-relative path.
	 * @param string $expected The app-relative path.
	 *
	 * @return void
	 */
	#[DataProvider('appRelativeProvider')]
	public function testAppRelative(string $path, string $expected): void {
		$this->assertSame($expected, StandardProtocolPaths::appRelative(path: $path));

	}//end testAppRelative()

	/**
	 * Paths and whether they speak a standard protocol.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function matchesProvider(): array {
		return [
			'health, relative'               => ['/api/health', true],
			'metrics, relative'              => ['/api/metrics', true],
			'health under the app root'      => ['/apps/planninq/api/health', true],
			'metrics via index.php'          => ['/index.php/apps/planninq/api/metrics', true],
			'a domain route is not protocol' => ['/api/projects', false],
			'health with a trailing segment' => ['/api/health/live', false],
			'health of another app'          => ['/apps/openregister/api/health', false],
			'the bare root'                  => ['/', false],
		];

	}//end matchesProvider()

	/**
	 * matches() is true only for a protocol path, after the app root is stripped.
	 *
	 * @param string $path     The request or app-relative path.
	 * @param bool   $expected Whether it is a protocol path.
	 *
	 * @return void
	 */
	#[DataProvider('matchesProvider')]
	public function testMatches(string $path, bool $expected): void {
		$this->assertSame($expected, StandardProtocolPaths::matches(path: $path));

	}//end testMatches()
}//end class
