<?php

/**
 * Golden test for the risk scale checks.
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

use OCA\Planninq\Service\RiskScaleService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Feeds a fixed set of scale documents and stored risk answers through
 * normalise() and conflict() and compares every answer with a recording.
 *
 * @covers \OCA\Planninq\Service\RiskScaleService
 */
class RiskScaleServiceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/risk-scale-service.golden.json';

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testNormaliseAndConflictMatchTheGoldenRecording(): void {
		$recorded = ['normalise' => [], 'conflict' => []];
		$service  = $this->service(answer: [], installed: true);
		foreach ($this->scales() as $name => $raw) {
			$recorded['normalise'][$name] = $service->normalise(raw: $raw);
		}

		foreach ($this->stores() as $name => $store) {
			foreach ([3, 4, 5, 6] as $levels) {
				$service = $this->service(answer: $store['answer'], installed: ($store['installed'] ?? true));
				$recorded['conflict'][$name.'@'.$levels] = $service->conflict(levels: $levels);
			}
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach (['normalise', 'conflict'] as $part) {
			foreach ($recorded[$part] as $name => $answer) {
				self::assertSame(expected: $expected[$part][$name], actual: json_decode((string)json_encode($answer), true), message: $part.' '.$name);
			}

			self::assertSame(expected: array_keys($expected[$part]), actual: array_keys($recorded[$part]));
		}
	}//end testNormaliseAndConflictMatchTheGoldenRecording()

	/**
	 * Scale documents, valid and broken in every way the rules know.
	 *
	 * @return array<string,string>
	 */
	private function scales(): array {
		$labels = static fn (int $count): array => array_map(static fn (int $n): string => 'L'.$n, range(1, $count));
		$scale  = static fn (array $over): string => (string)json_encode(array_merge(['levels' => 3, 'likelihood' => $labels(3), 'impact' => $labels(3), 'thresholds' => ['medium' => 3, 'high' => 6]], $over));

		return [
			'default' => RiskScaleService::DEFAULT_SCALE,
			'three' => $scale([]),
			'five' => $scale(['levels' => 5, 'likelihood' => $labels(5), 'impact' => $labels(5), 'thresholds' => ['medium' => 5, 'high' => 25]]),
			'four-keyed-labels' => $scale(['levels' => 4, 'likelihood' => ['a' => ' Low ', 'b' => 'Mid', 'c' => 'High', 'd' => 'Top'], 'impact' => $labels(4), 'thresholds' => ['medium' => 2, 'high' => 16]]),
			'not-json' => '{levels:3',
			'json-string' => '"three"',
			'json-list' => '[3,4]',
			'no-levels' => $scale(['levels' => null]),
			'levels-string' => $scale(['levels' => '3']),
			'levels-float' => $scale(['levels' => 3.0]),
			'levels-two' => $scale(['levels' => 2, 'likelihood' => $labels(2), 'impact' => $labels(2)]),
			'levels-six' => $scale(['levels' => 6, 'likelihood' => $labels(6), 'impact' => $labels(6)]),
			'likelihood-short' => $scale(['likelihood' => $labels(2)]),
			'impact-long' => $scale(['impact' => $labels(4)]),
			'likelihood-string' => $scale(['likelihood' => 'L1,L2,L3']),
			'impact-missing' => (string)json_encode(['levels' => 3, 'likelihood' => $labels(3), 'thresholds' => ['medium' => 3, 'high' => 6]]),
			'label-blank' => $scale(['impact' => ['L1', '  ', 'L3']]),
			'label-int' => $scale(['likelihood' => ['L1', 2, 'L3']]),
			'thresholds-missing' => (string)json_encode(['levels' => 3, 'likelihood' => $labels(3), 'impact' => $labels(3)]),
			'thresholds-string' => $scale(['thresholds' => 'medium']),
			'medium-string' => $scale(['thresholds' => ['medium' => '3', 'high' => 6]]),
			'high-missing' => $scale(['thresholds' => ['medium' => 3]]),
			'high-string' => $scale(['thresholds' => ['medium' => 3, 'high' => '6']]),
			'medium-one' => $scale(['thresholds' => ['medium' => 1, 'high' => 6]]),
			'medium-two' => $scale(['thresholds' => ['medium' => 2, 'high' => 3]]),
			'high-equals-medium' => $scale(['thresholds' => ['medium' => 4, 'high' => 4]]),
			'high-below-medium' => $scale(['thresholds' => ['medium' => 5, 'high' => 4]]),
			'high-at-max' => $scale(['thresholds' => ['medium' => 3, 'high' => 9]]),
			'high-over-max' => $scale(['thresholds' => ['medium' => 3, 'high' => 10]]),
			'extra-keys' => $scale(['colour' => 'red']),
		];
	}//end scales()

	/**
	 * Stored risk answers in the shapes OpenRegister can give.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function stores(): array {
		$risk = static fn (string $id, int $likelihood, int $impact): array => ['@self' => ['id' => $id], 'likelihood' => $likelihood, 'impact' => $impact];
		$object = static fn (string $id, array $data): object => new class($id, $data) {
			/**
			 * An entity.
			 *
			 * @param string              $id   The id.
			 * @param array<string,mixed> $data The data.
			 */
			public function __construct(private string $id, private array $data) {
			}

			/**
			 * The id.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				return $this->id;
			}

			/**
			 * The data.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return $this->data;
			}
		};

		return [
			'none' => ['answer' => []],
			'list' => ['answer' => [$risk('r1', 5, 2), $risk('r2', 5, 1), $risk('r3', 2, 4), $risk('r4', 1, 1), $risk('r5', 4, 5)]],
			'wrapped' => ['answer' => ['results' => [$risk('r1', 4, 4), $risk('r3', 2, 5)], 'total' => 2]],
			'entities' => ['answer' => [$object('e1', ['likelihood' => 5, 'impact' => 3]), $object('e2', ['likelihood' => 1, 'impact' => 4])]],
			'plain-ids' => ['answer' => [['id' => 'p1', 'likelihood' => 4, 'impact' => 2], ['likelihood' => 5, 'impact' => 5], ['likelihood' => '4', 'impact' => null], 'junk', 7]],
			'missing-fields' => ['answer' => [['@self' => ['id' => 'm1'], 'likelihood' => 5], ['@self' => ['id' => 'm2'], 'impact' => 4], ['@self' => ['id' => 'm3']]]],
			'throws' => ['answer' => new RuntimeException('down')],
			'not-installed' => ['answer' => [$risk('r1', 5, 5)], 'installed' => false],
			'scalar' => ['answer' => 3],
		];
	}//end stores()

	/**
	 * The service over one stored answer.
	 *
	 * @param mixed $answer    What the search answers, or a throwable it throws.
	 * @param bool  $installed Whether OpenRegister is installed.
	 *
	 * @return RiskScaleService
	 */
	private function service(mixed $answer, bool $installed): RiskScaleService {
		$objects = new class($answer) {
			/**
			 * A search double.
			 *
			 * @param mixed $answer The answer.
			 */
			public function __construct(private mixed $answer) {
			}

			/**
			 * Answer the rows whose likelihood or impact is in the filter's list.
			 *
			 * @param string              $registerSlug  The register.
			 * @param string              $schemaSlug    The schema.
			 * @param array<string,mixed> $filters       The filters.
			 * @param bool                $_rbac         RBAC.
			 * @param bool                $_multitenancy Multitenancy.
			 *
			 * @return mixed
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], bool $_rbac=true, bool $_multitenancy=true): mixed {
				if ($this->answer instanceof \Throwable) {
					throw $this->answer;
				}

				if (is_array($this->answer) === false) {
					return $this->answer;
				}

				$axis = (string)array_key_first($filters);
				$keep = static function (mixed $row) use ($axis, $filters): bool {
					$data = is_object($row) === true ? $row->getObject() : $row;
					return is_array($data) === false || in_array((int)($data[$axis] ?? 0), $filters[$axis], true);
				};
				if (isset($this->answer['results']) === true) {
					return array_merge($this->answer, ['results' => array_values(array_filter($this->answer['results'], $keep))]);
				}

				return array_values(array_filter($this->answer, $keep));
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn($installed);

		return new RiskScaleService(container: $container, appManager: $appManager, logger: new NullLogger());
	}//end service()
}//end class
