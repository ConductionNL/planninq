<?php

/**
 * Planninq ForgeLinkService
 *
 * Finds the task a code link (`forgeLink`) belongs to, and the links that
 * already point at the same forge item on that task. A person adding a link
 * on the task page sends the task id; the integration sends the forge text it
 * read (a commit message, branch name or pull request title) in `taskKey`,
 * because integriq's Twig mapping has no function to cut a key out of text.
 * The first task key in that text that belongs to a task wins.
 *
 * Kept apart from ProjectMembershipService, which reads the project of a link
 * through linkedTask() for its membership gate, so both agree on the task.
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
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Task lookup and repeat handling for code links.
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
 */
class ForgeLinkService {

	/**
	 * The code link schema slug.
	 *
	 * @var string
	 */
	public const SCHEMA = 'forgeLink';

	/**
	 * A task key in forge text: a project key (a letter, then one to nine
	 * letters or digits), a hyphen and a number (tasks-readable-keys).
	 *
	 * @var string
	 */
	private const TASK_KEY_PATTERN = '/(?<![A-Z0-9])[A-Z][A-Z0-9]{1,9}-[0-9]+(?![0-9])/';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves ProjectMembershipService and OpenRegister's ObjectService at runtime.
	 * @param LoggerInterface    $logger    The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The task a code link belongs to, with its project.
	 *
	 * @param array<string,mixed> $data The link data.
	 *
	 * @return array{id:string,data:array<string,mixed>,project:string}|null The task, or null when none resolves.
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function linkedTask(array $data): ?array {
		$membership = $this->membership();
		$task       = null;

		$taskId = '';
		if (is_string($data['task'] ?? null) === true) {
			$taskId = trim($data['task']);
		}
		if ($taskId !== '') {
			$found = $membership->objectData(schema: 'task', id: $taskId);
			if ($found !== null) {
				$task = ['id' => $taskId, 'data' => $found];
			}
		}

		if ($taskId === '') {
			preg_match_all(self::TASK_KEY_PATTERN, strtoupper((string)($data['taskKey'] ?? '')), $matches);
			foreach (array_unique($matches[0]) as $key) {
				$task = ($membership->rows(schema: 'task', filters: ['key' => $key])[0] ?? null);
				if ($task !== null) {
					break;
				}
			}
		}

		if ($task === null) {
			return null;
		}

		$task['project'] = $membership->projectIdFor(schemaSlug: 'task', data: $task['data']);

		return $task;
	}//end linkedTask()

	/**
	 * The links on a task that already point at a forge item.
	 *
	 * @param string $taskId     The task UUID.
	 * @param string $externalId The forge's id for the item.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function repeats(string $taskId, string $externalId): array {
		if ($externalId === '') {
			return [];
		}

		return $this->membership()->rows(schema: self::SCHEMA, filters: ['task' => $taskId, 'externalId' => $externalId]);
	}//end repeats()

	/**
	 * Delete a code link as the system; false when OpenRegister refuses.
	 *
	 * @param string $id The link UUID.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function remove(string $id): bool {
		try {
			return (bool)$this->container->get('OCA\\OpenRegister\\Service\\ObjectService')->deleteObject(
				uuid: $id,
				register: ProjectMembershipService::REGISTER,
				schema: self::SCHEMA,
				_rbac: false,
				_multitenancy: false
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: could not replace a code link', ['object' => $id, 'exception' => $e->getMessage()]);
			return false;
		}
	}//end remove()

	/**
	 * The membership service, resolved when needed: it reads links through this class.
	 *
	 * @return ProjectMembershipService
	 */
	private function membership(): ProjectMembershipService {
		$service = $this->container->get(ProjectMembershipService::class);
		if ($service instanceof ProjectMembershipService === false) {
			throw new RuntimeException('ProjectMembershipService is not available.');
		}

		return $service;
	}//end membership()
}//end class
