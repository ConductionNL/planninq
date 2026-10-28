<?php

/**
 * Example data for PqKlant and PqOrganisatie: the two Zuiddrecht projects
 * delivered for pipelinq clients.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @link https://conduction.nl
 *
 * @spec exclude example-set data integrity; no requirement covers the demo fragment
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Each project finds its pipelinq client by the shared master entity.
 */
class ZuiddrechtClientProjectsTest extends TestCase {

	/**
	 * Straatfeest Parkstraat 2026 is Sanne de Vries's and Speeldagen Oosterhof 2026
	 * Buurtvereniging Oost's, found after pipelinq by masterEntityRef.
	 *
	 * @return void
	 */
	public function testTheProjectsNameTheirClients(): void {
		$set = json_decode((string)file_get_contents(__DIR__.'/../../../lib/Settings/example-sets/zuiddrecht.json'), true);
		$this->assertSame(expected: 'zuiddrecht', actual: $set['set']);
		$this->assertSame(expected: ['pipelinq'], actual: $set['after']);

		$projects = array_column($set['objects'], null, 'key');
		$expected = [
			'project-straatfeest-parkstraat' => ['Straatfeest Parkstraat 2026', 'requested', '0f5e0000-0000-4000-8000-000000000012'],
			'project-speeldagen-oosterhof'   => ['Speeldagen Oosterhof 2026', 'active', '0f5e0000-0000-4000-8000-000000000007'],
		];
		foreach ($expected as $key => [$title, $status, $master]) {
			$project = $projects[$key];
			$this->assertSame(expected: $title, actual: $project['data']['title']);
			$this->assertSame(expected: $status, actual: $project['data']['status']);
			$this->assertSame(expected: '{{lookup:client}}', actual: $project['data']['client']);
			$this->assertSame(
				expected: ['register' => 'pipelinq', 'schema' => 'client', 'where' => ['masterEntityRef' => $master]],
				actual: $project['lookups']['client']
			);
		}
	}//end testTheProjectsNameTheirClients()
}//end class
