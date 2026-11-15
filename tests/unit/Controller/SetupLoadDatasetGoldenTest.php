<?php

/**
 * Golden test for SetupController::runAction() on the dataset actions: per
 * scenario (action, stored pick, posted dataset, import outcome), the response
 * and every config write and log call, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\SetupController;
use OCA\Planninq\Service\DemoDataService;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\SetupController
 */
final class SetupLoadDatasetGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/setupLoadDataset.golden.json';

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testDatasetActionsMatchTheRecording(): void {
		$actions  = ['load-demo-data', 'install-demo-data', 'skip-demo-data', 'other'];
		$stored   = ['', 'demo', 'none'];
		$posted   = [null, 'demo', 'none', 'nope', 7, ['demo'], ''];
		$installs = ['ok', 'throws'];
		$actual   = [];
		foreach ($actions as $action) {
			foreach ($stored as $pick) {
				foreach ($posted as $post) {
					foreach ($installs as $install) {
						$name          = $action . ' | stored ' . json_encode($pick) . ' | posted ' . json_encode($post) . ' | install ' . $install;
						$actual[$name] = $this->record(action: $action, setup: [$pick, $post, $install]);
					}
				}
			}
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testDatasetActionsMatchTheRecording()

	/**
	 * Run one action and record what it did.
	 *
	 * @param string           $action The action id.
	 * @param array<int,mixed> $setup  The stored pick, the posted dataset, the import outcome.
	 *
	 * @return array<int,mixed>
	 */
	private function record(string $action, array $setup): array {
		[$pick, $post, $install] = $setup;
		$calls  = [];
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$calls, $pick): string {
				$calls[] = ['get', $app, $key, $default];
				return $pick;
			}
		);
		$config->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$calls): bool {
				$calls[] = ['set', $app, $key, $value];
				return true;
			}
		);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => $key === 'dataset' ? $post : $default
		);
		$demo = $this->createMock(originalClassName: DemoDataService::class);
		$demo->method('listChoices')->willReturn([['id' => 'none'], ['id' => 'demo']]);
		if ($install === 'ok') {
			$demo->method('install')->willReturnCallback(
				static function () use (&$calls): array {
					$calls[] = ['install'];
					return ['objects' => 12, 'registers' => 1, 'schemas' => 3];
				}
			);
		} else {
			$demo->method('install')->willThrowException(new \RuntimeException('nobody is signed in'));
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			static function (string $message, array $context = []) use (&$calls): void {
				$calls[] = ['log.error', $message, array_keys($context)];
			}
		);

		$response = (new SetupController($request, $config, $logger, $demo))->runAction($action);
		$calls[]  = ['response', $response->getStatus(), $response->getData()];
		return $calls;
	}//end record()
}//end class
