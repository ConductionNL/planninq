<?php

/**
 * The integriq configuration planninq ships for code forges parses as an
 * integriq configuration document and writes only the forgeLink schema and
 * fields planninq declares.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

/**
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-3.1
 */
class IntegriqConfigurationTest extends TestCase {
	use RegisterSchemaValidation;

	private const FILE = __DIR__ . '/../../../lib/Settings/integriq/planninq-code-forge.json';

	private const REGISTER = __DIR__ . '/../../../lib/Settings/planninq_register.json';

	/**
	 * @return array<string,mixed>
	 */
	private function configuration(): array {
		self::assertFileExists(self::FILE);
		$decoded = json_decode((string)file_get_contents(self::FILE), true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($decoded);

		return $decoded;
	}//end configuration()

	public function testTargetsOnlyForgeLinkFields(): void {
		$config     = $this->configuration();
		$components = ($config['components'] ?? null);
		self::assertIsArray($components, 'integriq imports only a document with components');

		$register = json_decode((string)file_get_contents(self::REGISTER), true, 512, JSON_THROW_ON_ERROR);
		$fields   = array_keys($register['components']['schemas']['forgeLink']['properties']);

		self::assertNotEmpty($components['endpoints'] ?? []);
		foreach ($components['endpoints'] as $slug => $endpoint) {
			self::assertSame($slug, $endpoint['slug'], 'keyed by slug');
			self::assertSame('POST', $endpoint['method'], $slug);
			self::assertSame('register/schema', $endpoint['targetType'], $slug);
			self::assertSame('planninq/forgeLink', $endpoint['targetId'], $slug);
			self::assertArrayHasKey($endpoint['inputMapping'], $components['mappings'], $slug . ' names a mapping in the file');

			$ruleTypes = [];
			foreach ($endpoint['rules'] as $rule) {
				self::assertArrayHasKey($rule, $components['rules'], $slug . ' names a rule in the file');
				$ruleTypes[] = $components['rules'][$rule]['type'];
			}

			self::assertContains('webhook_signature', $ruleTypes, $slug . ' checks the forge signature');
		}

		foreach ($components['mappings'] as $slug => $mapping) {
			self::assertFalse($mapping['passThrough'], $slug . ' sends nothing but the mapped fields');
			self::assertSame([], array_diff(array_keys($mapping['mapping']), $fields), $slug . ' writes only forgeLink fields');
			self::assertSame('integriq', $mapping['mapping']['source'], $slug);
			foreach (['taskKey', 'url', 'externalId', 'kind'] as $needed) {
				self::assertArrayHasKey($needed, $mapping['mapping'], $slug);
			}

			foreach (['task', 'project', 'members', 'portfolioReaders'] as $planninqOwned) {
				self::assertArrayNotHasKey($planninqOwned, $mapping['mapping'], $slug . ': planninq fills ' . $planninqOwned);
			}
		}

		foreach ($components['rules'] as $slug => $rule) {
			if ($rule['type'] === 'webhook_signature') {
				self::assertSame('before', $rule['timing'], $slug . ' runs before the write');
				self::assertSame('', $rule['configuration']['secret'], $slug . ' ships no secret');
			}
		}
	}//end testTargetsOnlyForgeLinkFields()
	/**
	 * Render the mapping the way integriq does (a Twig template per field)
	 * over a recorded GitHub delivery.
	 *
	 * @param array<string,mixed> $event The GitHub payload.
	 *
	 * @return array<string,string>
	 */
	private function render(array $event): array {
		$mapping = $this->configuration()['components']['mappings']['planninq-github-forge-link']['mapping'];
		$twig    = new Environment(new ArrayLoader($mapping), ['autoescape' => false, 'strict_variables' => false]);
		$out     = [];
		foreach (array_keys($mapping) as $field) {
			$out[$field] = trim($twig->render($field, $event));
		}

		return $out;
	}//end render()

	public function testAMergedPullRequestMapsToAValidLink(): void {
		$link = $this->render(
			[
				'action'       => 'closed',
				'repository'   => ['full_name' => 'acme/portal'],
				'pull_request' => [
					'number' => 42, 'title' => 'VC-12 handle paper jams', 'html_url' => 'https://github.com/acme/portal/pull/42',
					'state' => 'closed', 'merged' => true, 'user' => ['login' => 'octocat'], 'updated_at' => '2026-09-30T10:00:00Z',
					'head' => ['ref' => 'feature/vc-12-paper-jams'],
				],
			]
		);

		self::assertSame('mergeRequest', $link['kind']);
		self::assertSame('merged', $link['state']);
		self::assertSame('github:acme/portal#42', $link['externalId']);
		self::assertStringContainsString('VC-12', $link['taskKey']);
		$stored = ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-000000000001', 'taskKey' => 'VC-12'] + $link;
		self::assertSame([], $this->registerSchemaErrors(slug: 'forgeLink', payload: $stored));
	}//end testAMergedPullRequestMapsToAValidLink()

	public function testAPushMapsItsHeadCommit(): void {
		$link = $this->render(
			[
				'ref'         => 'refs/heads/main',
				'repository'  => ['full_name' => 'acme/portal'],
				'head_commit' => [
					'id' => '3f2a9c1d8e7b6a5f4c3d2e1f0a9b8c7d6e5f4a3b', 'message' => "VC-12 handle paper jams\n\nLonger body.",
					'url' => 'https://github.com/acme/portal/commit/3f2a9c1d8e7b6a5f4c3d2e1f0a9b8c7d6e5f4a3b',
					'timestamp' => '2026-09-30T10:00:00+02:00', 'author' => ['name' => 'Octo Cat', 'username' => 'octocat'],
				],
			]
		);

		self::assertSame('commit', $link['kind']);
		self::assertSame('', $link['state']);
		self::assertSame('VC-12 handle paper jams', $link['title']);
		self::assertSame('octocat', $link['author']);
		$stored = ['task' => '00000000-0000-4000-8000-000000000012', 'project' => '00000000-0000-4000-8000-000000000001', 'taskKey' => 'VC-12'] + $link;
		self::assertSame([], $this->registerSchemaErrors(slug: 'forgeLink', payload: $stored));
	}//end testAPushMapsItsHeadCommit()
}//end class
