<?php

/**
 * Planninq RetentionCategoryService
 *
 * The selectielijst category the admin picks for planninq's `project`, `task`
 * and `plannedTimeEntry` schemas (retention-on-planninq-records task 2.1).
 * The categories come from the selection list OpenRegister is configured
 * with; the pick is kept in planninq's app config and written into each live
 * schema's `archive.classification`, which OpenRegister's retention reads.
 * The register import rewrites the live schemas, so the
 * ApplyRetentionCategories repair step writes the picks again after it, the
 * way ApplyProjectPolicy does for the project reviewers.
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
 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Kernel\AppIdentity;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Lists the selection-list categories and keeps the live schemas on the admin's pick.
 *
 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
class RetentionCategoryService {

	/**
	 * The app config key holding the pick, a JSON object of schema slug to category.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'retention_categories';

	/**
	 * The schemas whose records carry a retention category.
	 *
	 * @var array<int,string>
	 */
	public const SCHEMAS = ['project', 'task', 'plannedTimeEntry'];

	/**
	 * Planninq's register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * The most selection-list rows read for the picker.
	 *
	 * @var integer
	 */
	private const ROW_LIMIT = 1000;

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager Tells whether OpenRegister is installed.
	 * @param ContainerInterface $container  Resolves OpenRegister's services and mappers.
	 * @param IAppConfig         $appConfig  Keeps the pick.
	 * @param LoggerInterface    $logger     The logger.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function __construct(
		private IAppManager $appManager,
		private ContainerInterface $container,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The category picked per schema; a schema without a pick maps to ''.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function picks(): array {
		$stored = json_decode($this->appConfig->getValueString(AppIdentity::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (is_array($stored) === false) {
			$stored = [];
		}

		$picks = [];
		foreach (self::SCHEMAS as $schema) {
			$value = ($stored[$schema] ?? '');
			$picks[$schema] = '';
			if (is_string($value) === true) {
				$picks[$schema] = trim($value);
			}
		}

		return $picks;
	}//end picks()

	/**
	 * The categories of the selection list OpenRegister is configured with.
	 *
	 * One entry per category, sorted by category. Empty when OpenRegister is
	 * missing, names no selection list, or the list cannot be read.
	 *
	 * @return array<int,array{category: string, description: string, period: string, action: string}>
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function categories(): array {
		if ($this->appManager->isInstalled('openregister') === false) {
			return [];
		}

		try {
			$settings = $this->container->get('OCA\\OpenRegister\\Kernel\\Contracts\\AppSettingsInterface')->getArchivalSettingsOnly();
			$register = ($settings['selectielijstRegister'] ?? null);
			$schema   = ($settings['selectielijstSchema'] ?? null);
			if (empty($register) === true || empty($schema) === true) {
				return [];
			}

			$rows = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService')->findAll(
				config: ['filters' => ['register' => $register, 'schema' => $schema], 'limit' => self::ROW_LIMIT],
				_rbac: false,
				_multitenancy: false
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: the selection list could not be read', ['exception' => $e->getMessage()]);
			return [];
		}//end try

		return self::categoryRows(rows: (array)$rows);
	}//end categories()

	/**
	 * Store a pick and write it into the live schemas.
	 *
	 * Every schema in SCHEMAS gets a value; one missing from the input keeps
	 * its earlier pick, '' clears it. A category the selection list does not
	 * hold is refused, so a typo cannot leave records without a period.
	 *
	 * @param array<string,mixed> $input Schema slug to category.
	 *
	 * @return array{picks: array<string,string>, refused: array<int,string>, applied: bool}
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function save(array $input): array {
		$known   = array_column($this->categories(), 'category');
		$picks   = $this->picks();
		$refused = [];
		foreach (self::SCHEMAS as $schema) {
			if (array_key_exists($schema, $input) === false) {
				continue;
			}

			$value = $input[$schema];
			if (is_string($value) === false || (trim($value) !== '' && in_array(trim($value), $known, true) === false)) {
				$refused[] = $schema;
				continue;
			}

			$picks[$schema] = trim($value);
		}

		if ($refused !== []) {
			return ['picks' => $this->picks(), 'refused' => $refused, 'applied' => false];
		}

		$this->appConfig->setValueString(AppIdentity::APP_ID, self::CONFIG_KEY, (string)json_encode($picks));

		return ['picks' => $picks, 'refused' => [], 'applied' => $this->apply()];
	}//end save()

	/**
	 * Write the stored pick into the `archive` block of each live schema.
	 *
	 * Only planninq's own schemas are written: another app may have a
	 * `project` or `task` schema too. A schema without an `archive` block is
	 * left alone; a cleared pick removes `classification`.
	 *
	 * @return bool Whether every schema was found and written.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function apply(): bool {
		if ($this->appManager->isInstalled('openregister') === false) {
			$this->logger->info('Planninq: OpenRegister unavailable, retention categories not written');
			return false;
		}

		try {
			$schemaMapper = $this->container->get('OCA\\OpenRegister\\Db\\SchemaMapper');
			$register     = $this->container->get('OCA\\OpenRegister\\Db\\RegisterMapper')->find(self::REGISTER);
			$ids          = array_map('intval', (array)($register->getSchemas() ?? []));
			$written      = 0;
			foreach ($this->picks() as $slug => $category) {
				$schema = self::ownSchema(schemas: (array)$schemaMapper->findBySlug($slug), ids: $ids);
				if ($schema === null) {
					continue;
				}

				$schema->setArchive(self::withCategory(archive: ($schema->getArchive() ?? []), category: $category));
				$schemaMapper->update($schema);
				$written++;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: failed to write the retention categories', ['exception' => $e->getMessage()]);
			return false;
		}//end try

		return $written === count(self::SCHEMAS);
	}//end apply()

	/**
	 * The first schema of planninq's register that carries an archive block.
	 *
	 * @param array<int,object> $schemas The schemas with the slug.
	 * @param array<int,int>    $ids     The schema ids of planninq's register.
	 *
	 * @return object|null
	 */
	private static function ownSchema(array $schemas, array $ids): ?object {
		foreach ($schemas as $schema) {
			if (in_array((int)$schema->getId(), $ids, true) === true && ($schema->getArchive() ?? []) !== []) {
				return $schema;
			}
		}

		return null;
	}//end ownSchema()

	/**
	 * The archive block with the category set, or without one for ''.
	 *
	 * @param array<string,mixed> $archive  The live archive block.
	 * @param string              $category The picked category.
	 *
	 * @return array<string,mixed>
	 */
	private static function withCategory(array $archive, string $category): array {
		unset($archive['classification']);
		if ($category !== '') {
			$archive['classification'] = $category;
		}

		return $archive;
	}//end withCategory()

	/**
	 * One picker row per category from the selection-list objects.
	 *
	 * @param array<int,mixed> $rows The objects, as entities or arrays.
	 *
	 * @return array<int,array{category: string, description: string, period: string, action: string}>
	 */
	private static function categoryRows(array $rows): array {
		$byCategory = [];
		foreach ($rows as $row) {
			$data = self::rowData(row: $row);
			$category = trim((string)($data['categorie'] ?? ''));
			if ($category === '' || isset($byCategory[$category]) === true) {
				continue;
			}

			$byCategory[$category] = [
				'category' => $category,
				'description' => trim((string)($data['omschrijving'] ?? '')),
				'period' => trim((string)($data['bewaartermijn'] ?? '')),
				'action' => trim((string)($data['archiefnominatie'] ?? '')),
			];
		}

		ksort($byCategory, SORT_NATURAL);

		return array_values($byCategory);
	}//end categoryRows()

	/**
	 * The data of one selection-list object.
	 *
	 * @param mixed $row An ObjectEntity, its serialised array, or its data.
	 *
	 * @return array<string,mixed>
	 */
	private static function rowData(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			$row = $row->getObject();
		}

		if (is_array($row) === false) {
			return [];
		}

		if (is_array($row['object'] ?? null) === true) {
			return $row['object'];
		}

		return $row;
	}//end rowData()
}//end class
