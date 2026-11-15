<?php

/**
 * Planninq ProjectPolicySchemaService
 *
 * Writes the reviewers of project requests into the live `project` schema.
 * The reviewers are the people who may create projects: admins, plus the
 * creation groups under the `groups` policy. They go into two places the
 * register file cannot know: the `authorization` of the `approve` and
 * `reject` transitions, and a read and an update rule per group for projects
 * in the status `requested`. Follows DueReminderWindowService; the register
 * import rewrites the live schema, so the ApplyProjectPolicy repair step runs
 * this again after it.
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
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the live project schema in step with the creation policy.
 */
class ProjectPolicySchemaService {

	/**
	 * The transitions reviewers run.
	 *
	 * @var array<int,string>
	 */
	private const REVIEW_TRANSITIONS = ['approve', 'reject'];

	/**
	 * The object rights reviewers get on a requested project.
	 *
	 * @var array<int,string>
	 */
	private const REVIEW_OPERATIONS = ['read', 'update'];

	/**
	 * The match that marks a reviewer rule.
	 *
	 * @var array<string,string>
	 */
	private const REQUESTED = ['status' => 'requested'];

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager Tells whether OpenRegister is installed.
	 * @param ContainerInterface $container  Resolves OpenRegister's mappers.
	 * @param LoggerInterface    $logger     The logger.
	 */
	public function __construct(
		private IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Make these groups (and admins) the reviewers of project requests.
	 *
	 * @param array<int,string> $groups The reviewer group ids; empty for admins only.
	 *
	 * @return bool Whether the live schema was written.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function apply(array $groups): bool {
		if ($this->appManager->isInstalled('openregister') === false) {
			$this->logger->info('Planninq: OpenRegister unavailable, project reviewers not written');
			return false;
		}

		try {
			$schemaMapper = $this->container->get('OCA\\OpenRegister\\Db\\SchemaMapper');
			$schema       = $this->liveSchema(schemaMapper: $schemaMapper);
			if ($schema === null) {
				$this->logger->warning('Planninq: project schema or its lifecycle not found, project reviewers not written');
				return false;
			}

			$groups = array_values(array_unique(array_filter($groups, static fn ($group): bool => is_string($group) === true && $group !== '')));
			$schema->setConfiguration($this->withReviewTransitions(configuration: $schema->getConfiguration(), groups: $groups));
			$schema->setAuthorization($this->withReviewRules(authorization: ($schema->getAuthorization() ?? []), groups: $groups));
			$schemaMapper->update($schema);
			$this->logger->info('Planninq: project reviewers written to the live schema', ['groups' => $groups]);

			return true;
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: failed to write the project reviewers', ['exception' => $e->getMessage()]);
			return false;
		}//end try
	}//end apply()

	/**
	 * Planninq's own live project schema, carrying the lifecycle block; null when absent.
	 *
	 * Another app may have a `project` schema too, so the slug alone is not
	 * enough: the schema must belong to the planninq register.
	 *
	 * @param object $schemaMapper OpenRegister's SchemaMapper.
	 *
	 * @return object|null
	 */
	private function liveSchema(object $schemaMapper): ?object {
		$register = $this->container->get('OCA\\OpenRegister\\Db\\RegisterMapper')->find(ProjectMembershipService::REGISTER);
		$ids      = array_map('intval', (array)($register->getSchemas() ?? []));
		foreach ((array)$schemaMapper->findBySlug(ProjectMembershipService::PROJECT_SCHEMA) as $schema) {
			$lifecycle = (($schema->getConfiguration() ?? [])['x-openregister-lifecycle'] ?? null);
			if (in_array((int)$schema->getId(), $ids, true) === true && is_array($lifecycle) === true) {
				return $schema;
			}
		}

		return null;
	}//end liveSchema()

	/**
	 * The configuration with approve and reject open to admins and the groups.
	 *
	 * @param array<string,mixed> $configuration The live configuration.
	 * @param array<int,string>   $groups        The reviewer groups.
	 *
	 * @return array<string,mixed>
	 */
	private function withReviewTransitions(array $configuration, array $groups): array {
		foreach (self::REVIEW_TRANSITIONS as $action) {
			if (isset($configuration['x-openregister-lifecycle']['transitions'][$action]) === true) {
				$configuration['x-openregister-lifecycle']['transitions'][$action]['authorization'] = array_values(array_unique(array_merge(['admin'], $groups)));
			}
		}

		return $configuration;
	}//end withReviewTransitions()

	/**
	 * The authorization with this app's reviewer rules replaced by one read and update rule per group.
	 *
	 * @param array<string,mixed> $authorization The live authorization.
	 * @param array<int,string>   $groups        The reviewer groups.
	 *
	 * @return array<string,mixed>
	 */
	private function withReviewRules(array $authorization, array $groups): array {
		foreach (self::REVIEW_OPERATIONS as $operation) {
			$rules = array_values(
				array_filter(
					(array)($authorization[$operation] ?? []),
					static fn ($rule): bool => is_array($rule) === false || ($rule['match'] ?? null) !== self::REQUESTED
				)
			);
			foreach ($groups as $group) {
				$rules[] = ['group' => $group, 'match' => self::REQUESTED];
			}

			$authorization[$operation] = $rules;
		}

		return $authorization;
	}//end withReviewRules()
}//end class
