<?php

/**
 * Planninq case handover service
 *
 * Hands a project's record over to the case it is linked to: every file on the
 * project and on its tasks is copied byte for byte to the case object in
 * Dossiq's register, followed by a metadata file describing the project and
 * its tasks. Each file's SHA-256 is taken before the copy and read back after
 * it; a mismatch fails that file. The handover is recorded on the project with
 * the names and checksums, so "unchanged" can be proven later.
 *
 * Everything runs as the acting user: the project and tasks are read with
 * OpenRegister's access rules on, and the case must be readable by that user,
 * which is the same rule OpenRegister's own files API applies before it adds
 * a file to an object.
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
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\CaseHandoverException;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Copies a project's files and metadata to its case.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
 */
class CaseHandoverService {

	/**
	 * The case app's id, as Dossiq declares it in appinfo/info.xml.
	 *
	 * @var string
	 */
	public const CASE_APP = 'dossiq';

	/**
	 * Dossiq's register slug.
	 *
	 * @var string
	 */
	private const CASE_REGISTER = 'dossiq';

	/**
	 * Dossiq's case schema slug.
	 *
	 * @var string
	 */
	private const CASE_SCHEMA = 'case';

	/**
	 * Planninq's register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * The metadata file's name.
	 *
	 * @var string
	 */
	private const METADATA_FILE = 'project-metadata.json';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container   Resolves OpenRegister's ObjectService and FileService.
	 * @param IAppManager        $appManager  Tells whether the case app is installed.
	 * @param IUserSession       $userSession The acting user.
	 * @param LoggerInterface    $logger      The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private IAppManager $appManager,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether a handover can run on this instance: OpenRegister and the case app are installed.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
	 */
	public function isAvailable(): bool {
		return $this->appManager->isInstalled('openregister') === true && $this->appManager->isInstalled(self::CASE_APP) === true;
	}//end isAvailable()

	/**
	 * The handovers recorded on a project, read with the acting user's rights.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return list<array<string,mixed>>
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
	 */
	public function handovers(string $projectId): array {
		if ($this->appManager->isInstalled('openregister') === false) {
			return [];
		}

		$project = $this->find(schema: 'project', id: $projectId, register: self::REGISTER);

		return array_values((array)($project['data']['caseHandovers'] ?? []));
	}//end handovers()

	/**
	 * Hand the project over to its case and record it on the project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return array<string,mixed> The handover record.
	 *
	 * @throws CaseHandoverException When there is no case app, no case link or no readable case.
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.1
	 */
	public function handOver(string $projectId): array {
		if ($this->isAvailable() === false) {
			throw new CaseHandoverException(reason: CaseHandoverException::NO_CASE_APP, message: 'The case app is not installed.');
		}

		$project = $this->find(schema: 'project', id: $projectId, register: self::REGISTER);
		if ($project === null) {
			throw new CaseHandoverException(reason: CaseHandoverException::NO_CASE, message: 'The project cannot be read.');
		}

		$caseId = (string)($project['data']['caseReference'] ?? '');
		if ($caseId === '') {
			throw new CaseHandoverException(reason: CaseHandoverException::NO_CASE, message: 'The project is not linked to a case.');
		}

		$case = $this->find(schema: self::CASE_SCHEMA, id: $caseId, register: self::CASE_REGISTER);
		if ($case === null) {
			throw new CaseHandoverException(reason: CaseHandoverException::CASE_NOT_FOUND, message: 'The linked case was not found or cannot be read.');
		}

		$tasks  = $this->tasks(projectId: $projectId);
		$record = [
			'date'     => (new \DateTimeImmutable())->format(DATE_ATOM),
			'by'       => (string)($this->userSession->getUser()?->getUID() ?? ''),
			'case'     => $caseId,
			'files'    => [],
			'failures' => [],
		];

		$sources = [['entity' => $project['entity'], 'prefix' => '']];
		foreach ($tasks as $task) {
			$sources[] = ['entity' => $task['entity'], 'prefix' => $this->taskPrefix(task: $task['data'])];
		}

		foreach ($sources as $source) {
			foreach ($this->fileService()->getFiles($source['entity']) as $file) {
				if ($file instanceof File === false) {
					// A folder in the object's folder is not a document of the record.
					continue;
				}

				$this->copy(case: $case['entity'], name: $source['prefix'] . $file->getName(), content: (string)$file->getContent(), record: $record);
			}
		}

		$metadata = (string)json_encode($this->metadata(project: $project, tasks: $tasks), (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$this->copy(case: $case['entity'], name: self::METADATA_FILE, content: $metadata, record: $record);

		$data = $project['data'];
		unset($data['@self'], $data['id']);
		$data['caseHandovers']   = array_values((array)($data['caseHandovers'] ?? []));
		$data['caseHandovers'][] = $record;
		$this->objectService()->saveObject(object: $data, register: self::REGISTER, schema: 'project', uuid: $projectId);

		$this->logger->info('Planninq: project handed over to its case', ['project' => $projectId, 'case' => $caseId, 'files' => count($record['files']), 'failures' => count($record['failures'])]);

		return $record;
	}//end handOver()

	/**
	 * Copy one file to the case and check it reads back unchanged.
	 *
	 * @param object              $case    The case entity.
	 * @param string              $name    The file name wanted.
	 * @param string              $content The bytes.
	 * @param array<string,mixed> $record  The handover record, updated.
	 *
	 * @return void
	 */
	private function copy(object $case, string $name, string $content, array &$record): void {
		$before = hash('sha256', $content);
		$target = $this->freeName(case: $case, name: $name);

		try {
			$written = $this->fileService()->addFile(objectEntity: $case, fileName: $target, content: $content);
			$after   = hash('sha256', (string)$written->getContent());
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: a file was not handed over', ['name' => $name, 'exception' => $e->getMessage()]);
			$record['failures'][] = ['name' => $name, 'reason' => 'write'];
			return;
		}

		if ($after !== $before) {
			$record['failures'][] = ['name' => $name, 'reason' => 'checksum'];
			return;
		}

		$record['files'][] = ['name' => $target, 'sha256' => $before, 'size' => strlen($content)];
	}//end copy()

	/**
	 * A name not yet taken on the case: "plan.pdf", else "plan (1).pdf", and so on.
	 *
	 * @param object $case The case entity.
	 * @param string $name The name wanted.
	 *
	 * @return string
	 */
	private function freeName(object $case, string $name): string {
		$dot  = strrpos($name, '.');
		$stem = $name;
		$ext  = '';
		if ($dot !== false && $dot > 0) {
			$stem = substr($name, 0, $dot);
			$ext  = substr($name, $dot);
		}

		$candidate = $name;
		for ($i = 1; $i < 1000 && $this->fileService()->getFile($case, $candidate) !== null; $i++) {
			$candidate = $stem . ' (' . $i . ')' . $ext;
		}

		return $candidate;
	}//end freeName()

	/**
	 * The prefix a task's files get on the case: its key and title.
	 *
	 * @param array<string,mixed> $task The task data.
	 *
	 * @return string
	 */
	private function taskPrefix(array $task): string {
		$label = trim(trim((string)($task['key'] ?? '')) . ' ' . trim((string)($task['title'] ?? '')));
		$label = str_replace(['/', '\\'], '-', $label);
		if ($label === '') {
			return '';
		}

		return $label . ' - ';
	}//end taskPrefix()

	/**
	 * What the metadata file says about the project and its tasks.
	 *
	 * @param array{data:array<string,mixed>}       $project The project.
	 * @param list<array{id:string,data:array<string,mixed>}> $tasks   Its tasks.
	 *
	 * @return array<string,mixed>
	 */
	private function metadata(array $project, array $tasks): array {
		$data = $project['data'];
		unset($data['@self'], $data['caseHandovers'], $data['portfolioReaders']);

		$rows = [];
		foreach ($tasks as $task) {
			$rows[] = [
				'id'         => $task['id'],
				'key'        => ($task['data']['key'] ?? null),
				'title'      => ($task['data']['title'] ?? null),
				'status'     => ($task['data']['status'] ?? null),
				'startDate'  => ($task['data']['startDate'] ?? null),
				'dueDate'    => ($task['data']['dueDate'] ?? null),
				'assignedTo' => ($task['data']['assignedTo'] ?? null),
			];
		}

		return ['source' => 'planninq', 'project' => $data, 'tasks' => $rows];
	}//end metadata()

	/**
	 * The project's tasks, read with the acting user's rights.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return list<array{id:string,data:array<string,mixed>,entity:object}>
	 */
	private function tasks(string $projectId): array {
		$rows = $this->objectService()->searchObjectsBySlug(registerSlug: self::REGISTER, schemaSlug: 'task', filters: ['project' => $projectId]);
		if (is_array($rows) === true && array_key_exists('results', $rows) === true) {
			$rows = (array)$rows['results'];
		}

		$tasks = [];
		foreach ((array)$rows as $row) {
			if (is_object($row) === false) {
				continue;
			}

			$tasks[] = ['id' => (string)$row->getUuid(), 'data' => (array)$row->getObject(), 'entity' => $row];
		}

		return $tasks;
	}//end tasks()

	/**
	 * One object read with the acting user's rights, or null when it is missing or unreadable.
	 *
	 * @param string $schema   The schema slug.
	 * @param string $id       The UUID.
	 * @param string $register The register slug.
	 *
	 * @return array{entity:object,data:array<string,mixed>}|null
	 */
	private function find(string $schema, string $id, string $register): ?array {
		try {
			$entity = $this->objectService()->find(id: $id, register: $register, schema: $schema);
		} catch (\Throwable $e) {
			$this->logger->info('Planninq: handover could not read an object', ['schema' => $schema, 'id' => $id, 'exception' => $e->getMessage()]);
			return null;
		}

		if ($entity === null) {
			return null;
		}

		return ['entity' => $entity, 'data' => (array)$entity->getObject()];
	}//end find()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object
	 */
	private function objectService(): object {
		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()

	/**
	 * OpenRegister's FileService.
	 *
	 * @return object
	 */
	private function fileService(): object {
		return $this->container->get('OCA\\OpenRegister\\Service\\FileService');
	}//end fileService()
}//end class
