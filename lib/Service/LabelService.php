<?php

/**
 * Planix Label Service
 *
 * Server-side logic for app-wide label management: the two operations that need
 * real logic beyond OpenRegister object CRUD (ADR-022 / gate-17):
 *   1. usage-count aggregation — count, per label UUID, how many tasks reference
 *      it in their `labels` array;
 *   2. cascade delete — remove a label's UUID from every referencing task's
 *      `labels` array (server-authoritative, sweep first) and then delete the
 *      label object; idempotent on re-run.
 *
 * List/create/edit are plain OR object CRUD done directly from the frontend, so
 * they are deliberately NOT mirrored here (a wrapper would be gate-17 redundant).
 *
 * @category Service
 * @package  OCA\Planix\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Domain service for app-wide labels.
 *
 * The aggregation ({@see countUsageByLabel()}) is a pure function over the task
 * label-arrays so it is unit-testable without OpenRegister; {@see listWithUsage()}
 * and {@see deleteWithCascade()} wrap it with the ObjectService persistence.
 */
class LabelService
{

    /**
     * OpenRegister register slug owning the planix schemas.
     *
     * @var string
     */
    private const REGISTER = 'planix';

    /**
     * OpenRegister schema slug for labels.
     *
     * @var string
     */
    private const LABEL_SCHEMA = 'label';

    /**
     * OpenRegister schema slug for tasks.
     *
     * @var string
     */
    private const TASK_SCHEMA = 'task';

    /**
     * Constructor for the LabelService.
     *
     * @param ContainerInterface $container The DI container (resolves the OR ObjectService at runtime).
     * @param LoggerInterface    $logger    The logger.
     *
     * @return void
     */
    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {

    }//end __construct()

    /**
     * Resolve the OpenRegister ObjectService from the container by FQCN string,
     * so planix carries no compile-time dependency on the openregister package.
     *
     * @return object|null The OR ObjectService, or null when OpenRegister is unavailable.
     */
    private function objectService(): ?object
    {
        try {
            return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
            return null;
        }

    }//end objectService()

    /**
     * Count, per label UUID, how many tasks reference it in their `labels` array.
     *
     * Pure aggregation over the supplied task list — no I/O — so it can be unit
     * tested directly. A task contributes at most 1 to each distinct label UUID
     * it lists (duplicate UUIDs within one task's array do not inflate the count).
     *
     * @param array<int,array<string,mixed>> $tasks Task data arrays (each may carry a `labels` array of UUIDs).
     *
     * @return array<string,int> Map of label UUID → number of referencing tasks.
     *
     * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
     */
    public static function countUsageByLabel(array $tasks): array
    {
        $counts = [];
        foreach ($tasks as $task) {
            $labels = ($task['labels'] ?? []);
            if (is_array($labels) === false) {
                continue;
            }

            $seen = [];
            foreach ($labels as $labelId) {
                $uuid = (string) $labelId;
                if ($uuid === '' || isset($seen[$uuid]) === true) {
                    continue;
                }

                $seen[$uuid]   = true;
                $counts[$uuid] = (($counts[$uuid] ?? 0) + 1);
            }
        }

        return $counts;

    }//end countUsageByLabel()

    /**
     * List every label with its usage count attached.
     *
     * Reads the labels and tasks from OpenRegister, aggregates the per-label
     * usage with {@see countUsageByLabel()}, and returns labels sorted by title
     * (case-insensitive) so the admin list ordering is stable.
     *
     * @return array<int,array<string,mixed>> Each label's data plus `id` and `usageCount`.
     *
     * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
     */
    public function listWithUsage(): array
    {
        $objectService = $this->objectService();
        if ($objectService === null) {
            return [];
        }

        $tasks  = $this->fetchAll(objectService: $objectService, schema: self::TASK_SCHEMA);
        $counts = self::countUsageByLabel(tasks: $tasks);

        $labels = [];
        foreach ($this->fetchAll(objectService: $objectService, schema: self::LABEL_SCHEMA) as $label) {
            $id          = (string) ($label['id'] ?? '');
            $label['id'] = $id;
            $label['usageCount'] = ($counts[$id] ?? 0);
            $labels[]            = $label;
        }

        usort(
            $labels,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
        );

        return $labels;

    }//end listWithUsage()

    /**
     * Delete a label with a server-authoritative cascade over `task.labels`.
     *
     * Order matters: sweep first, delete last. Every task whose `labels` array
     * contains the UUID is saved without that element BEFORE the label object is
     * removed. If the sweep fails partway the label still exists, so the admin
     * can retry; the cascade is idempotent — a re-run sweeps only the tasks that
     * still carry the UUID and then deletes the (already-or-still present) label.
     *
     * @param string $labelId UUID of the label to delete.
     *
     * @return array{tasksUpdated:int} Number of tasks swept on this invocation.
     *
     * @throws RuntimeException When OpenRegister is unavailable.
     *
     * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
     */
    public function deleteWithCascade(string $labelId): array
    {
        if ($labelId === '') {
            throw new RuntimeException('A label id is required.');
        }

        $objectService = $this->objectService();
        if ($objectService === null) {
            throw new RuntimeException('OpenRegister is not available.');
        }

        // 1. Sweep first: remove the UUID from every referencing task.
        $tasksUpdated = 0;
        foreach ($this->fetchAll(objectService: $objectService, schema: self::TASK_SCHEMA) as $task) {
            $taskId = (string) ($task['id'] ?? '');
            $labels = ($task['labels'] ?? []);
            if ($taskId === '' || is_array($labels) === false) {
                continue;
            }

            if (in_array($labelId, array_map('strval', $labels), true) === false) {
                // Idempotent: tasks already swept (or never referencing) are skipped.
                continue;
            }

            $task['labels'] = array_values(
                array_filter($labels, static fn($l): bool => (string) $l !== $labelId)
            );
            unset($task['id']);

            $objectService->saveObject(
                object: $task,
                register: self::REGISTER,
                schema: self::TASK_SCHEMA,
                uuid: $taskId,
                _rbac: false
            );
            $tasksUpdated++;
        }//end foreach

        // 2. Delete the label object last (no dangling-reference window).
        try {
            $objectService->deleteObject(register: self::REGISTER, schema: self::LABEL_SCHEMA, uuid: $labelId);
        } catch (\Throwable $e) {
            // Idempotent: a re-run after the label is already gone still completes
            // any remaining sweep above and then no-ops the delete.
            $this->logger->info(
                'Planix: label already deleted or delete failed (cascade idempotent)',
                ['label' => $labelId, 'exception' => $e->getMessage()]
            );
        }

        $this->logger->info(
            'Planix: label deleted with cascade',
            ['label' => $labelId, 'tasksUpdated' => $tasksUpdated]
        );

        return ['tasksUpdated' => $tasksUpdated];

    }//end deleteWithCascade()

    /**
     * Fetch every object of a schema, normalised to plain arrays carrying at
     * least an `id` key (extracted from the OR `@self` envelope or entity).
     *
     * @param object $objectService The OR ObjectService.
     * @param string $schema        The schema slug to read.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchAll(object $objectService, string $schema): array
    {
        // `searchObjectsBySlug()`, not `setRegister()/setSchema()` +
        // `searchObjects()`.
        //
        // `ObjectService::searchObjects()` does not read the register/schema
        // left on the service by the setters — it logs
        // `[MagicMapper] searchObjects() called without register/schema context`
        // and matches nothing. This method is the ONLY read path behind
        // `GET /apps/planix/api/labels`, so the admin "Label management" section
        // rendered "No labels yet." on every instance, however many labels
        // existed, with no error anywhere in the UI. The e2e suite caught it as
        // four failing label-management specs on a fresh CI instance that had a
        // seeded label.
        //
        // `searchObjectsBySlug()` resolves both slugs to numeric IDs and
        // delegates to `searchObjects()` on the documented fast path.
        $results = $objectService->searchObjectsBySlug(
            registerSlug: self::REGISTER,
            schemaSlug: $schema
        );

        $rows = [];
        foreach ($this->normaliseResults(results: $results) as $row) {
            $data       = $this->extractData(row: $row);
            $data['id'] = $this->extractId(row: $row);
            $rows[]     = $data;
        }

        return $rows;

    }//end fetchAll()

    /**
     * Normalise an ObjectService result set (paginated array with a `results`
     * key, plain list, or list of entity objects) to a plain list of rows.
     *
     * @param mixed $results The raw ObjectService return value.
     *
     * @return array<int,mixed>
     */
    private function normaliseResults(mixed $results): array
    {
        if (is_array($results) === true && array_key_exists('results', $results) === true) {
            return (array) $results['results'];
        }

        if (is_array($results) === true) {
            return $results;
        }

        return [];

    }//end normaliseResults()

    /**
     * Extract the object UUID from an ObjectService row (entity or array).
     *
     * @param mixed $row An entity object or a plain array row.
     *
     * @return string
     */
    private function extractId(mixed $row): string
    {
        if (is_object($row) === true) {
            // `is_callable()`, NOT `method_exists()`.
            //
            // OpenRegister's ObjectEntity extends \OCP\AppFramework\Db\Entity,
            // which implements every property accessor through `__call()` and
            // declares neither `getUuid()` nor `getId()`. `method_exists()` does
            // not see magic methods, so both branches were skipped and this
            // helper returned '' for every entity.
            //
            // Consequences, all silent: every label in
            // `GET /apps/planix/api/labels` came back with `"id": ""`, so the
            // admin dialog's `isEdit` (`!!label.id`) was false and the Edit
            // button opened a CREATE dialog — editing a label would have
            // duplicated it — while Delete cascaded on an empty id, and
            // `countUsageByLabel()` keyed every count on '' so every label read
            // "used by 0 tasks".
            foreach (['getUuid', 'getId'] as $getter) {
                if (is_callable([$row, $getter]) === false) {
                    continue;
                }

                try {
                    $value = $row->$getter();
                } catch (\Throwable $e) {
                    continue;
                }

                if ($value !== null && (string) $value !== '') {
                    return (string) $value;
                }
            }//end foreach
        }//end if

        if (is_array($row) === true) {
            if (isset($row['@self']['id']) === true) {
                return (string) $row['@self']['id'];
            }

            return (string) ($row['id'] ?? '');
        }

        return '';

    }//end extractId()

    /**
     * Extract the object data array from an ObjectService row (entity or array).
     *
     * @param mixed $row An entity object or a plain array row.
     *
     * @return array<string,mixed>
     */
    private function extractData(mixed $row): array
    {
        if (is_object($row) === true && method_exists($row, 'getObject') === true) {
            return (array) $row->getObject();
        }

        if (is_array($row) === true) {
            return $row;
        }

        return [];

    }//end extractData()
}//end class
