<?php

/**
 * Planninq Risk Scale Service
 *
 * The admin's risk scale: how many likelihood and impact levels there are
 * (3 to 5), what each level is called, and the two score thresholds that
 * split low, medium and high. A smaller scale is refused while a risk still
 * uses a level above it, so the heat map and the list never disagree.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Validates the risk scale and finds the risks a smaller scale would strand.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
class RiskScaleService {

	/**
	 * App config key of the scale.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'risk_scale';

	/**
	 * The scale an install starts with: five levels.
	 *
	 * @var string
	 */
	public const DEFAULT_SCALE = '{"levels":5,'
		. '"likelihood":["Very low","Low","Medium","High","Very high"],'
		. '"impact":["Very low","Low","Medium","High","Very high"],'
		. '"thresholds":{"medium":5,"high":12}}';

	/**
	 * Fewest levels a scale may have.
	 *
	 * @var integer
	 */
	public const MIN_LEVELS = 3;

	/**
	 * Most levels a scale may have; the risk schema caps likelihood and impact at it.
	 *
	 * @var integer
	 */
	public const MAX_LEVELS = 5;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService at runtime.
	 * @param IAppManager $appManager Tells whether OpenRegister is installed.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Parse and check a submitted scale.
	 *
	 * @param string $raw The JSON the admin page sends.
	 *
	 * @return array{levels: int, likelihood: array<int,string>, impact: array<int,string>, thresholds: array{medium: int, high: int}}|null
	 *   The scale, or null when it is not valid.
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public static function normalise(string $raw): ?array {
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return null;
		}

		$levels = ($decoded['levels'] ?? null);
		if (is_int($levels) === false || $levels < self::MIN_LEVELS || $levels > self::MAX_LEVELS) {
			return null;
		}

		$likelihood = self::labels(value: ($decoded['likelihood'] ?? null), levels: $levels);
		$impact = self::labels(value: ($decoded['impact'] ?? null), levels: $levels);
		$medium = ($decoded['thresholds']['medium'] ?? null);
		$high = ($decoded['thresholds']['high'] ?? null);
		if ($likelihood === null || $impact === null || is_int($medium) === false || is_int($high) === false) {
			return null;
		}

		if ($medium < 2 || $high <= $medium || $high > ($levels * $levels)) {
			return null;
		}

		return [
			'levels' => $levels,
			'likelihood' => $likelihood,
			'impact' => $impact,
			'thresholds' => ['medium' => $medium, 'high' => $high],
		];
	}//end normalise()

	/**
	 * The risks a scale of this many levels would strand, and the highest level they use.
	 *
	 * @param int $levels The number of levels the admin wants.
	 *
	 * @return array{count: int, level: int}|null Null when no risk is in the way.
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public function conflict(int $levels): ?array {
		if ($levels >= self::MAX_LEVELS || $this->appManager->isInstalled('openregister') === false) {
			return null;
		}

		$above = range($levels + 1, self::MAX_LEVELS);
		$stranded = [];
		foreach (['likelihood', 'impact'] as $axis) {
			foreach ($this->search(filters: [$axis => $above]) as $id => $data) {
				$stranded[$id] = max((int)($data['likelihood'] ?? 0), (int)($data['impact'] ?? 0));
			}
		}

		if ($stranded === []) {
			return null;
		}

		return ['count' => count($stranded), 'level' => max($stranded)];
	}//end conflict()

	/**
	 * The refusal an admin reads.
	 *
	 * @param int $count How many risks are in the way.
	 * @param int $level The highest level they use.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
	 */
	public static function refusal(int $count, int $level): string {
		if ($count === 1) {
			return sprintf('1 risk uses level %d. Change it first.', $level);
		}

		return sprintf('%d risks use level %d. Change them first.', $count, $level);
	}//end refusal()

	/**
	 * A trimmed list of exactly `$levels` non-empty labels.
	 *
	 * @param mixed $value The submitted labels.
	 * @param int $levels The number of levels.
	 *
	 * @return array<int,string>|null
	 */
	private static function labels(mixed $value, int $levels): ?array {
		if (is_array($value) === false || count($value) !== $levels) {
			return null;
		}

		$labels = [];
		foreach (array_values($value) as $label) {
			if (is_string($label) === false || trim($label) === '') {
				return null;
			}

			$labels[] = trim($label);
		}

		return $labels;
	}//end labels()

	/**
	 * Risks matching the filters, read as the system: the check must see every
	 * risk, not only the ones the saving admin is a member of.
	 *
	 * @param array<string,mixed> $filters Property filters.
	 *
	 * @return array<string, array<string,mixed>> Data keyed by uuid.
	 */
	private function search(array $filters): array {
		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$results = $objectService->searchObjectsBySlug(
				registerSlug: ProjectMembershipService::REGISTER,
				schemaSlug: 'risk',
				filters: $filters,
				_rbac: false,
				_multitenancy: false
			);
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: could not read risks for the risk scale check', ['exception' => $e->getMessage()]);
			return [];
		}

		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$rows = [];
		foreach ((array)$results as $row) {
			if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
				$rows[(string)$row->getUuid()] = (array)$row->getObject();
				continue;
			}

			if (is_array($row) === true) {
				$rows[(string)($row['@self']['id'] ?? ($row['id'] ?? ''))] = $row;
			}
		}

		return $rows;
	}//end search()
}//end class
