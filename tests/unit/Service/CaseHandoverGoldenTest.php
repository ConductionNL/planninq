<?php

/**
 * Golden test for CaseHandoverService::handOver(): per scenario (apps, the
 * project and its case link, the case, tasks and files, write and checksum
 * failures), every OpenRegister, file and log call in order and the record or
 * the refusal, compared with a recorded fixture. The handover time is
 * replaced by a placeholder. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the
 * fixture.
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

require_once __DIR__ . '/../Support/FindCallShape.php';

use OCA\Planninq\Exception\CaseHandoverException;
use OCA\Planninq\Service\CaseHandoverService;
use OCA\Planninq\Tests\Unit\Support\FindCallShape;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\CaseHandoverService
 * @uses   \OCA\Planninq\Exception\CaseHandoverException
 */
final class CaseHandoverGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/caseHandover.golden.json';

	private const DATE = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/';

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testHandOverMatchesTheRecording(): void {
		$base      = ['apps' => ['openregister', 'dossiq'], 'project' => 'linked', 'case' => 'found', 'user' => 'alice', 'tasks' => true, 'search' => 'paged', 'failWrite' => [], 'badSum' => [], 'taken' => ['Plan.pdf']];
		$scenarios = [
			'handed over'             => [],
			'tasks as a plain list'   => ['search' => 'list'],
			'no tasks'                => ['tasks' => false],
			'nobody signed in'        => ['user' => null],
			'a write fails'           => ['failWrite' => ['KEY-1 Draft - Draft.txt']],
			'a checksum differs'      => ['badSum' => ['Plan (1).pdf']],
			'metadata write fails'    => ['failWrite' => ['project-metadata.json']],
			'no case app'             => ['apps' => ['openregister']],
			'no OpenRegister'         => ['apps' => ['dossiq']],
			'project unreadable'      => ['project' => 'throws'],
			'project missing'         => ['project' => 'missing'],
			'project not linked'      => ['project' => 'unlinked'],
			'project without the link' => ['project' => 'no link field'],
			'case not found'          => ['case' => 'missing'],
			'case unreadable'         => ['case' => 'throws'],
			'earlier handovers'       => ['project' => 'handed over before'],
		];
		$actual    = [];
		foreach ($scenarios as $name => $change) {
			$actual[$name] = $this->record(setup: array_merge($base, $change));
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n";
		$json = (string)preg_replace(self::DATE, '<now>', $json);
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testHandOverMatchesTheRecording()

	/**
	 * Run handOver() once and record every call, in order.
	 *
	 * @param array<string,mixed> $setup The scenario.
	 *
	 * @return array<int,mixed>
	 */
	private function record(array $setup): array {
		$calls = [];
		$apps  = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturnCallback(static fn (string $app): bool => in_array($app, $setup['apps'], true));
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($setup['user'] === null ? null : $user);

		$objects   = $this->objectService(setup: $setup, calls: $calls);
		$files     = $this->fileService(setup: $setup, calls: $calls);
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => str_ends_with($id, 'FileService') === true ? $files : $objects
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $message, $context];
				}
			);
		}

		try {
			$record  = (new CaseHandoverService(container: $container, appManager: $apps, userSession: $session, logger: $logger))->handOver(projectId: 'p1');
			$calls[] = ['record', $record];
		} catch (CaseHandoverException $e) {
			$calls[] = ['refused', $e->getReason(), $e->getMessage()];
		}

		return $calls;
	}//end record()

	/**
	 * An object entity stand-in.
	 *
	 * @param string              $uuid The object id.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return object
	 */
	private static function entity(string $uuid, array $data): object {
		return new class($uuid, $data) {
			/**
			 * @param string              $uuid The object id.
			 * @param array<string,mixed> $data The object data.
			 */
			public function __construct(public string $uuid, private array $data) {
			}

			/**
			 * @return string
			 */
			public function getUuid(): string {
				return $this->uuid;
			}

			/**
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return $this->data;
			}
		};
	}//end entity()

	/**
	 * The ObjectService stand-in.
	 *
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return object
	 */
	private function objectService(array $setup, array &$calls): object {
		$projects = [
			'linked'             => ['@self' => ['id' => 'p1'], 'id' => 'p1', 'title' => 'Bridge', 'caseReference' => 'c1', 'portfolioReaders' => ['x']],
			'unlinked'           => ['@self' => ['id' => 'p1'], 'id' => 'p1', 'title' => 'Bridge', 'caseReference' => ''],
			'no link field'      => ['id' => 'p1', 'title' => 'Bridge'],
			'handed over before' => ['id' => 'p1', 'title' => 'Bridge', 'caseReference' => 'c1', 'caseHandovers' => ['old' => ['date' => '2026-01-01']]],
		];
		$tasks    = [
			self::entity(uuid: 't1', data: ['key' => 'KEY-1', 'title' => 'Draft', 'status' => 'done', 'dueDate' => '2026-10-01']),
			self::entity(uuid: 't2', data: ['key' => '', 'title' => 'a/b\\c']),
			self::entity(uuid: 't3', data: []),
			'not an object',
		];
		return new class($setup, $calls, $projects, $tasks) {
			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param array<string,mixed>                $setup    The scenario.
			 * @param array<int,mixed>                   $calls    Receives the calls.
			 * @param array<string,array<string,mixed>> $projects The project data per kind.
			 * @param array<int,mixed>                   $tasks    The task rows.
			 */
			public function __construct(private array $setup, array &$calls, private array $projects, private array $tasks) {
				$this->calls = &$calls;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object|null
			 */
			public function find(mixed ...$args): ?object {
				$args          = FindCallShape::flatten(args: $args);
				$this->calls[] = ['find', $args];
				$kind          = ($args['schema'] === 'project') ? $this->setup['project'] : $this->setup['case'];
				if ($kind === 'throws') {
					throw new \RuntimeException('read failed');
				}

				if ($kind === 'missing') {
					return null;
				}

				$data = ($args['schema'] === 'project') ? $this->projects[$kind] : ['title' => 'Case'];
				return CaseHandoverGoldenTest::stub(uuid: (string)$args['id'], data: $data);
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return array<mixed>
			 */
			public function searchObjectsBySlug(mixed ...$args): array {
				$this->calls[] = ['search', $args];
				$rows          = ($this->setup['tasks'] === true) ? $this->tasks : [];
				return ($this->setup['search'] === 'paged') ? ['results' => $rows, 'total' => count($rows)] : $rows;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object
			 */
			public function saveObject(mixed ...$args): object {
				$this->calls[] = ['saveObject', $args];
				return new \stdClass();
			}
		};
	}//end objectService()

	/**
	 * An object entity stand-in, for the ObjectService stand-in.
	 *
	 * @param string              $uuid The object id.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return object
	 */
	public static function stub(string $uuid, array $data): object {
		return self::entity(uuid: $uuid, data: $data);
	}//end stub()

	/**
	 * The FileService stand-in: the project holds a folder and two files, each
	 * task one file.
	 *
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return object
	 */
	private function fileService(array $setup, array &$calls): object {
		$files = [
			'p1' => [$this->createMock(originalClassName: Folder::class), $this->file(name: 'Plan.pdf', content: 'plan'), $this->file(name: 'notes.txt', content: 'notes')],
			't1' => [$this->file(name: 'Draft.txt', content: 'draft')],
			't2' => [$this->file(name: 'Spec', content: '')],
			't3' => [],
		];
		return new class($setup, $calls, $files) {
			/** @var array<int,mixed> */
			private array $calls;

			/** @var array<int,string> */
			private array $written = [];

			/**
			 * @param array<string,mixed>          $setup The scenario.
			 * @param array<int,mixed>             $calls Receives the calls.
			 * @param array<string,array<mixed>>   $files The files per object.
			 */
			public function __construct(private array $setup, array &$calls, private array $files) {
				$this->calls = &$calls;
			}

			/**
			 * @param object $entity The object.
			 *
			 * @return array<mixed>
			 */
			public function getFiles(object $entity): array {
				$this->calls[] = ['getFiles', $entity->uuid];
				return $this->files[$entity->uuid] ?? [];
			}

			/**
			 * @param object $entity The case.
			 * @param string $name   The file name.
			 *
			 * @return object|null
			 */
			public function getFile(object $entity, string $name): ?object {
				$this->calls[] = ['getFile', $entity->uuid, $name];
				$taken         = array_merge($this->setup['taken'], $this->written);
				return in_array($name, $taken, true) === true ? new \stdClass() : null;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object
			 */
			public function addFile(mixed ...$args): object {
				$this->calls[] = ['addFile', $args['objectEntity']->uuid, $args['fileName'], $args['content']];
				if (in_array($args['fileName'], $this->setup['failWrite'], true) === true) {
					throw new \RuntimeException('disk full');
				}

				$this->written[] = $args['fileName'];
				$content         = in_array($args['fileName'], $this->setup['badSum'], true) === true ? 'garbled' : $args['content'];
				return new class($content) {
					/**
					 * @param string $content The stored content.
					 */
					public function __construct(private string $content) {
					}

					/**
					 * @return string
					 */
					public function getContent(): string {
						return $this->content;
					}
				};
			}
		};
	}//end fileService()

	/**
	 * A file stand-in.
	 *
	 * @param string $name    The file name.
	 * @param string $content The content.
	 *
	 * @return File
	 */
	private function file(string $name, string $content): File {
		$file = $this->createMock(originalClassName: File::class);
		$file->method('getName')->willReturn($name);
		$file->method('getContent')->willReturn($content);
		return $file;
	}//end file()
}//end class
