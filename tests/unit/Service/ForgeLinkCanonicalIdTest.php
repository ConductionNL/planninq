<?php

/**
 * Tests for the canonical form of a forge id.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ForgeLinkService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * One stored form per forge id format.
 *
 * @covers \OCA\Planninq\Service\ForgeLinkService
 */
class ForgeLinkCanonicalIdTest extends TestCase {

	/**
	 * Each sent id and the form it is stored in.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function ids(): array {
		return [
			'integration pull request'     => ['github:acme/portal#42', 'github:acme/portal#42'],
			'pull request, owner in case'  => ['GitHub:Acme/Portal#42', 'github:acme/portal#42'],
			'pull request, padded'         => ["  github:acme/portal#42\t", 'github:acme/portal#42'],
			'pushed commit'                => ['3F2A9C1D8E7B6A5F4C3D2E1F0A9B8C7D6E5F4A3B', '3f2a9c1d8e7b6a5f4c3d2e1f0a9b8c7d6e5f4a3b'],
			'short hash'                   => [' ABCDEF1 ', 'abcdef1'],
			'pasted merge request'         => ['GitLab.Example.org:Group/Sub/Portal:mergeRequest:!7', 'gitlab.example.org:group/sub/portal:mergeRequest:!7'],
			'pasted commit'                => ['github.com:Acme/Portal:commit:ABCDEF1', 'github.com:acme/portal:commit:abcdef1'],
			'pasted issue'                 => ['Codeberg.org:Acme/Portal:issue:#3', 'codeberg.org:acme/portal:issue:#3'],
			'pasted branch keeps its name' => ['GitHub.com:Acme/Portal:branch:Feature/VC-12-Widgets', 'github.com:acme/portal:branch:Feature/VC-12-Widgets'],
			'host with a port'             => ['Git.Example.org:8443:Acme/Portal:issue:#3', 'git.example.org:8443:acme/portal:issue:#3'],
			'unknown kind is only trimmed' => [' Example:Acme/Portal:Tag:V1 ', 'Example:Acme/Portal:Tag:V1'],
			'not a hash, only trimmed'     => [' Deploy-Ready ', 'Deploy-Ready'],
			'too long for a hash'          => [str_repeat('A', 41), str_repeat('A', 41)],
			'pull without a number'        => ['github:Acme/Portal#next', 'github:Acme/Portal#next'],
			'empty'                        => ['  ', ''],
		];
	}//end ids()

	/**
	 * Every format is stored in its one form, and that form is stable.
	 *
	 * @dataProvider ids
	 *
	 * @param string $sent     The id as sent.
	 * @param string $expected The stored form.
	 *
	 * @return void
	 */
	public function testEachFormatHasOneStoredForm(string $sent, string $expected): void {
		$service = new ForgeLinkService(container: $this->createMock(originalClassName: ContainerInterface::class), logger: new NullLogger());

		self::assertSame(expected: $expected, actual: $service->canonicalId(id: $sent));
		self::assertSame(expected: $expected, actual: $service->canonicalId(id: $expected));
	}//end testEachFormatHasOneStoredForm()
}//end class
