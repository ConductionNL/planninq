<?php

/**
 * Golden test for LabelService::deleteWithCascade(): per scenario, the result
 * or the exception, every save and delete the ObjectService received and every
 * log call, in order, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\OpenRegister\Kernel\Contracts\RegisterSchemaRef;
use OCA\Planninq\Service\LabelService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\LabelService
 */
final class LabelCascadeGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/labelCascade.golden.json';

	private const TASKS = [
		['@self' => ['id' => 'T1'], 'title' => 'a', 'labels' => ['L1', 'L2']],
		['@self' => ['id' => 'T2'], 'title' => 'b', 'labels' => ['L2']],
		['@self' => ['id' => 'T3'], 'title' => 'c', 'labels' => ['L1']],
		['@self' => ['id' => 'T4'], 'title' => 'd', 'labels' => 'L1'],
		['@self' => ['id' => ''], 'title' => 'e', 'labels' => ['L1']],
		['title' => 'f', 'labels' => ['L1']],
		['@self' => ['id' => 'T7'], 'title' => 'g', 'labels' => ['L1', 'L1', 'L3']],
		['@self' => ['id' => 'T8'], 'title' => 'h', 'labels' => [7, 'L1']],
		['@self' => ['id' => 'T9'], 'title' => 'i'],
		['@self' => ['id' => 'T10'], 'title' => 'j', 'labels' => []],
	];

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testCascadeMatchesTheRecording(): void {
		$scenarios = [
			'label L1'                 => ['L1', self::TASKS, false, true],
			'label 7'                  => ['7', self::TASKS, false, true],
			'unused label'             => ['L9', self::TASKS, false, true],
			'label delete fails'       => ['L1', self::TASKS, true, true],
			'save fails mid-sweep'     => ['L1', self::TASKS, 'T3', true],
			'no tasks'                 => ['L1', [], false, true],
			'empty id'                 => ['', self::TASKS, false, true],
			'OpenRegister unavailable' => ['L1', self::TASKS, false, false],
		];
		$actual    = [];
		foreach ($scenarios as $name => [$labelId, $tasks, $deleteFails, $orThere]) {
			$actual[$name] = $this->record(labelId: $labelId, setup: [$tasks, $deleteFails, $orThere]);
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testCascadeMatchesTheRecording()

	/**
	 * Run one cascade and record every call, in order.
	 *
	 * @param string           $labelId The label to delete.
	 * @param array<int,mixed> $setup   The tasks, true when the label delete fails or the task id whose save fails, whether OpenRegister answers.
	 *
	 * @return array<int,mixed>
	 */
	private function record(string $labelId, array $setup): array {
		[$tasks, $failure, $orThere] = $setup;
		$calls   = [];
		$objects = new class($tasks, $failure, $calls) {
			/** @var array<int,array<string,mixed>> */
			private array $tasks;

			private bool|string $failure;

			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param array<int,array<string,mixed>> $tasks   The task rows.
			 * @param bool|string                    $failure True when deleteObject throws, or the task id whose save throws.
			 * @param array<int,mixed>               $calls   Receives the calls.
			 */
			public function __construct(array $tasks, bool|string $failure, array &$calls) {
				$this->tasks   = $tasks;
				$this->failure = $failure;
				$this->calls   = &$calls;
			}

			/**
			 * @param RegisterSchemaRef $target The register and schema.
			 *
			 * @return array<int,array<string,mixed>>
			 */
			public function searchObjectsBySlug(RegisterSchemaRef $target): array {
				$registerSlug = (string)$target->register;
				$schemaSlug   = (string)$target->schema;
				$this->calls[] = ['search', $registerSlug, $schemaSlug];
				return $schemaSlug === 'task' ? $this->tasks : [];
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return array<string,mixed>
			 */
			public function saveObject(mixed ...$args): array {
				$this->calls[] = ['save', $args];
				if ($args['uuid'] === $this->failure) {
					throw new \RuntimeException('save failed');
				}

				return [];
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return bool
			 */
			public function deleteObject(mixed ...$args): bool {
				$this->calls[] = ['delete', $args];
				if ($this->failure === true) {
					throw new \RuntimeException('already gone');
				}

				return true;
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		if ($orThere === true) {
			$container->method('get')->willReturn($objects);
		} else {
			$container->method('get')->willThrowException(new \RuntimeException('no OpenRegister'));
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $message, $context];
				}
			);
		}

		try {
			$calls[] = ['result', (new LabelService(container: $container, logger: $logger))->deleteWithCascade(labelId: $labelId)];
		} catch (\Throwable $e) {
			$calls[] = ['exception', get_class($e), $e->getMessage()];
		}

		return $calls;
	}//end record()
}//end class
