<?php

/**
 * Golden test for how a submitted creation policy setting is stored.
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

use OCA\Planninq\Service\CreationPolicyService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Records normalise() for every policy key against a set of submitted values.
 *
 * @covers \OCA\Planninq\Service\CreationPolicyService
 */
class CreationPolicyNormaliseGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/creation-policy-normalise.golden.json';

	private const KEYS = ['allow_project_creation', 'project_requests', 'project_creation_groups', 'default_columns', ''];

	private const VALUES = [
		'all', 'admins', 'groups', 'ALL', ' all', 'on', 'off', 'On', '', '0', 'null', '{}', '[]',
		'["staff"]', '["staff","staff","gone"]', '["staff",3,null,"board"]', '{"a":"staff","b":"board"}',
		'"staff"', '[["staff"]]', '[""]', 'not json',
	];

	/**
	 * Every key and value pair matches the recording.
	 *
	 * @return void
	 */
	public function testNormaliseMatchesTheRecording(): void {
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('groupExists')->willReturnCallback(static fn (string $gid): bool => in_array($gid, ['staff', 'board'], true));
		$service = new CreationPolicyService(
			appConfig: $this->createMock(originalClassName: IAppConfig::class),
			groupManager: $groups,
			userSession: $this->createMock(originalClassName: IUserSession::class)
		);

		$recorded = [];
		foreach (self::KEYS as $key) {
			foreach (self::VALUES as $value) {
				$recorded['(' . $key . ') = (' . $value . ')'] = $service->normalise(key: $key, value: $value);
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testNormaliseMatchesTheRecording()
}//end class
