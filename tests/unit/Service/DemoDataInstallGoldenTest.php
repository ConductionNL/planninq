<?php

/**
 * Golden test for DemoDataService: install() and listChoices() over a set of
 * descriptors and sessions, recording the result or the exception, what the
 * importer received and what was logged. Set PLANNINQ_GOLDEN_RECORD=1 to
 * rewrite the fixture.
 *
 * @category Tests
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
 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\DemoDataService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\DemoDataService
 */
final class DemoDataInstallGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/demoDataInstall.golden.json';

	private const DESCRIPTORS = [
		'missing'              => null,
		'not json'             => 'not json at all',
		'json scalar'          => '42',
		'no components'        => '{"x-openregister":{"anchorDate":"2026-01-01"}}',
		'components scalar'    => '{"components":"none"}',
		'objects scalar'       => '{"components":{"objects":"none"}}',
		'empty objects'        => '{"components":{"objects":[]}}',
		'objects with dates'   => '{"x-openregister":{"anchorDate":"2026-10-01"},"components":{"objects":[{"owner":"@operator","members":["@operator","bob"],"dueDate":"2026-10-04","at":"2026-10-02T09:00:00+00:00"},{"title":"plain"}]}}',
		'objects, no anchor'   => '{"components":{"objects":[{"owner":"@operator","dueDate":"2026-10-04"}]}}',
	];

	private string $appPath;

	/**
	 * Make a temporary app directory for the descriptor.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appPath = sys_get_temp_dir() . '/pl-demo-golden-' . uniqid();
		mkdir($this->appPath . '/lib/Settings', 0777, true);
	}//end setUp()

	/**
	 * Remove the temporary app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		@unlink($this->descriptor());
		@rmdir($this->appPath . '/lib/Settings');
		@rmdir($this->appPath . '/lib');
		@rmdir($this->appPath);
	}//end tearDown()

	/**
	 * Every descriptor, with and without a signed-in admin and OpenRegister.
	 *
	 * @return void
	 */
	public function testInstallMatchesTheRecording(): void {
		$actual = [];
		foreach (self::DESCRIPTORS as $name => $content) {
			@unlink($this->descriptor());
			if ($content !== null) {
				file_put_contents($this->descriptor(), $content);
			}

			foreach (['admin, OpenRegister' => [true, true], 'nobody' => [false, true], 'no OpenRegister' => [true, false], 'nobody, no OpenRegister' => [false, false]] as $setup => [$signedIn, $orThere]) {
				$actual[$name][$setup] = $this->record(signedIn: $signedIn, orThere: $orThere);
			}
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testInstallMatchesTheRecording()

	/**
	 * Run listChoices() and install() once and record what happened.
	 *
	 * @param bool $signedIn Whether an admin is signed in.
	 * @param bool $orThere  Whether OpenRegister is installed.
	 *
	 * @return array<string,mixed>
	 */
	private function record(bool $signedIn, bool $orThere): array {
		$log      = [];
		$importer = new class {
			/** @var array<int,array<string,mixed>> */
			public array $calls = [];

			/**
			 * Record an import.
			 *
			 * @param string              $appId   The app id.
			 * @param array<string,mixed> $data    The descriptor.
			 * @param string              $version The app version.
			 * @param bool                $force   Whether to force.
			 *
			 * @return array<string,mixed>
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->calls[] = ['appId' => $appId, 'data' => $data, 'version' => $version, 'force' => $force];
				return ['registers' => [1], 'schemas' => [1, 2, 3]];
			}
		};

		$service = $this->service(signedIn: $signedIn, orThere: $orThere, importer: $importer, log: $log);
		$out     = ['choices' => $service->listChoices()];
		try {
			$out['install'] = $service->install();
		} catch (\Throwable $e) {
			$out['install'] = [get_class($e) => str_replace($this->appPath, '<app>', $e->getMessage())];
		}

		$out['imported'] = $importer->calls;
		$out['log']      = $log;
		return $out;
	}//end record()

	/**
	 * The service over the temporary app, with the given session and apps.
	 *
	 * @param bool                     $signedIn Whether an admin is signed in.
	 * @param bool                     $orThere  Whether OpenRegister is installed.
	 * @param object                   $importer The importer the container serves.
	 * @param array<int,array<mixed>> $log      Receives every log call.
	 *
	 * @return DemoDataService
	 */
	private function service(bool $signedIn, bool $orThere, object $importer, array &$log): DemoDataService {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppPath')->willReturn($this->appPath);
		$apps->method('getAppVersion')->willReturn('1.2.3');
		$apps->method('getInstalledApps')->willReturn($orThere === true ? ['openregister'] : []);
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($importer);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error', 'debug'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$log, $level): void {
					$log[] = [$level, $message, $context];
				}
			);
		}

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($signedIn === true ? $user : null);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new \DateTimeImmutable('2026-10-05T15:30:00+00:00'));

		return new DemoDataService(appManager: $apps, container: $container, logger: $logger, userSession: $session, timeFactory: $time);
	}//end service()

	/**
	 * The descriptor path inside the temporary app.
	 *
	 * @return string
	 */
	private function descriptor(): string {
		return $this->appPath . '/lib/Settings/planninq_mock_register.json';
	}//end descriptor()
}//end class
