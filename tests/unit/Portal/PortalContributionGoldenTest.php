<?php

/**
 * Golden test for the portal manifest: what getAudiences(), getAudience()
 * and getContribution() return for a set of subjects, compared with a
 * recorded fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Portal
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Portal;

use OCA\Planninq\Portal\PortalContributionProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Portal\PortalContributionProvider
 */
final class PortalContributionGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/portalContribution.golden.json';

	/**
	 * The audiences and the manifest per subject match the recording.
	 *
	 * @return void
	 */
	public function testManifestMatchesTheRecording(): void {
		$provider = new PortalContributionProvider();
		$subjects = [
			'external employee' => ['audience' => 'external-employee', 'subjectRef' => '00000000-0000-0000-0000-000000000001'],
			'external employee, high' => ['audience' => 'external-employee', 'trustLevel' => 'high'],
			'citizen' => ['audience' => 'citizen'],
			'no audience' => [],
			'audience in other case' => ['audience' => 'External-Employee'],
		];

		$actual = ['audiences' => $provider->getAudiences(), 'audience' => $provider->getAudience()];
		foreach ($subjects as $name => $subject) {
			$actual['contribution'][$name] = $provider->getContribution($subject);
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testManifestMatchesTheRecording()
}//end class
