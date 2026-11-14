<?php

/**
 * Shared fixture for the project export tests (project-data-export).
 *
 * OpenRegister's ObjectService, FileService, RegisterMapper, SchemaMapper and
 * ExportGate are in-memory doubles with OpenRegister's method and parameter
 * names; the ObjectService double honours `_limit` and `_offset` the way
 * OpenRegister pages a search. Files are OCP\Files\File mocks that stream
 * their content.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
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

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\Planninq\Service\ProjectExportService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\File;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds a ProjectExportService over in-memory OpenRegister doubles.
 */
trait ProjectExportFixture {

	/**
	 * Project A, the one exported.
	 */
	protected const PROJECT_A = 'aaaaaaaa-0000-4000-8000-000000000001';

	/**
	 * Project B, which must stay out of the export.
	 */
	protected const PROJECT_B = 'bbbbbbbb-0000-4000-8000-000000000001';

	/**
	 * Rows by schema slug and uuid.
	 *
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	public array $rows = [];

	/**
	 * Files by object uuid: name => [content, readable].
	 *
	 * @var array<string,array<string,array{0:string,1:bool}>>
	 */
	protected array $objectFiles = [];

	/**
	 * Comments by task uuid.
	 *
	 * @var array<string,list<array{id:string,actor:string,message:string}>>
	 */
	protected array $taskComments = [];

	/**
	 * Schema slugs the export gate refuses.
	 *
	 * @var array<int,string>
	 */
	public array $refused = [];

	/**
	 * Every call the gate received: [slug, profile, registerId].
	 *
	 * @var list<array{0:string|null,1:string,2:int|null}>
	 */
	public array $gateCalls = [];

	/**
	 * Every search the ObjectService double received.
	 *
	 * @var list<array{schema:string,filters:array<string,mixed>}>
	 */
	public array $searches = [];

	/**
	 * Seed project A with three tasks, a PDF and two comments on the first,
	 * a phase with a document, a column, a risk and a dependency; project B
	 * with five tasks.
	 *
	 * @return void
	 */
	protected function seedTwoProjects(): void {
		$this->rows = [
			'project'      => [
				self::PROJECT_A => ['title' => 'Herinrichting Marktplein', 'key' => 'HM', 'owner' => 'olga'],
				self::PROJECT_B => ['title' => 'Other', 'key' => 'OT', 'owner' => 'olga'],
			],
			'column'       => ['c0000000-0000-4000-8000-000000000001' => ['title' => 'Doing', 'project' => self::PROJECT_A]],
			'projectPhase' => ['f0000000-0000-4000-8000-000000000001' => ['title' => 'Ontwerp', 'project' => self::PROJECT_A]],
			'risk'         => ['r0000000-0000-4000-8000-000000000001' => ['title' => 'Rain', 'project' => self::PROJECT_A]],
			'boardView'    => [
				'v0000000-0000-4000-8000-000000000001' => ['title' => 'Mine', 'projects' => [self::PROJECT_A, self::PROJECT_B]],
				'v0000000-0000-4000-8000-000000000002' => ['title' => 'Theirs', 'projects' => [self::PROJECT_B]],
			],
			'task'         => [],
			'dependency'   => [],
		];

		for ($i = 1; $i <= 3; $i++) {
			$this->rows['task'][sprintf('a0000000-0000-4000-8000-%012d', $i)] = [
				'title'      => 'Task A' . $i,
				'key'        => 'HM-' . $i,
				'status'     => 'open',
				'project'    => self::PROJECT_A,
				'column'     => 'c0000000-0000-4000-8000-000000000001',
				'assignedTo' => 'olga',
				'startDate'  => '2026-11-0' . $i,
				'dueDate'    => '2026-12-0' . $i,
				'labels'     => ['urgent'],
			];
		}

		for ($i = 1; $i <= 5; $i++) {
			$this->rows['task'][sprintf('b0000000-0000-4000-8000-%012d', $i)] = ['title' => 'Task B' . $i, 'key' => 'OT-' . $i, 'project' => self::PROJECT_B];
		}

		$this->rows['dependency'] = [
			'd0000000-0000-4000-8000-000000000001' => ['blocker' => 'a0000000-0000-4000-8000-000000000001', 'blocked' => 'a0000000-0000-4000-8000-000000000002'],
			'd0000000-0000-4000-8000-000000000002' => ['blocker' => 'b0000000-0000-4000-8000-000000000001', 'blocked' => 'b0000000-0000-4000-8000-000000000002'],
		];

		$this->objectFiles = [
			'a0000000-0000-4000-8000-000000000001' => ['plan.pdf' => ['%PDF-1.4 plan', true]],
			'f0000000-0000-4000-8000-000000000001' => ['ontwerp.odt' => ['odt bytes', true]],
		];

		$this->taskComments = [
			'a0000000-0000-4000-8000-000000000001' => [
				['id' => '11', 'actor' => 'olga', 'message' => 'First'],
				['id' => '12', 'actor' => 'piet', 'message' => 'Second'],
			],
		];
	}//end seedTwoProjects()

	/**
	 * The service under test, over the doubles.
	 *
	 * @return ProjectExportService
	 */
	protected function exportService(): ProjectExportService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		$users = $this->createMock(IUserManager::class);
		$users->method('getDisplayName')->willReturnCallback(
			static fn (string $uid): ?string => ['olga' => 'Olga Owner', 'piet' => 'Piet Pieters'][$uid] ?? null
		);

		return new ProjectExportService(
			container: $this->openRegister(),
			appManager: $appManager,
			comments: $this->commentsManager(),
			userManager: $users,
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end exportService()

	/**
	 * A container answering OpenRegister's service names with the doubles.
	 *
	 * @return ContainerInterface
	 */
	protected function openRegister(): ContainerInterface {
		$services = [
			'OCA\\OpenRegister\\Service\\ObjectService'        => $this->objectService(),
			'OCA\\OpenRegister\\Service\\FileService'          => $this->fileService(),
			'OCA\\OpenRegister\\Service\\Export\\ExportGate'   => $this->exportGate(),
			'OCA\\OpenRegister\\Db\\RegisterMapper'            => $this->registerMapper(),
			'OCA\\OpenRegister\\Db\\SchemaMapper'              => $this->schemaMapper(),
		];

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(static fn (string $id): object => $services[$id]);

		return $container;
	}//end openRegister()

	/**
	 * The schema slugs in register order; a schema's id is its index + 1.
	 *
	 * @return array<int,string>
	 */
	protected function slugs(): array {
		return array_merge(['project'], ProjectExportService::PROJECT_SCHEMAS, ProjectExportService::LIST_SCHEMAS, ['dependency']);
	}//end slugs()

	/**
	 * The RegisterMapper double: register 7 holds every slug.
	 *
	 * @return object
	 */
	private function registerMapper(): object {
		$ids = range(1, count($this->slugs()));
		return new class($ids) {
			// phpcs:disable
			public function __construct(private array $ids) {
			}
			public function find(string|int $id, bool $_rbac = true, bool $_multitenancy = true): object {
				$ids = $this->ids;
				return new class($ids) {
					public function __construct(private array $ids) {
					}
					public function getId(): int {
						return 7;
					}
					public function getSchemas(): array {
						return $this->ids;
					}
				};
			}
			// phpcs:enable
		};
	}//end registerMapper()

	/**
	 * The SchemaMapper double.
	 *
	 * @return object
	 */
	private function schemaMapper(): object {
		$slugs = $this->slugs();
		return new class($slugs) {
			// phpcs:disable
			public function __construct(private array $slugs) {
			}
			public function find(string|int $id, ?array $_extend = [], bool $_rbac = true, bool $_multitenancy = true): object {
				$slug = $this->slugs[(int)$id - 1];
				return new class($slug, (int)$id) {
					public function __construct(private string $slug, private int $id) {
					}
					public function getSlug(): string {
						return $this->slug;
					}
					public function getId(): int {
						return $this->id;
					}
				};
			}
			// phpcs:enable
		};
	}//end schemaMapper()

	/**
	 * The ExportGate double: refuses the slugs in `$this->refused`.
	 *
	 * @return object
	 */
	private function exportGate(): object {
		$test = $this;
		return new class($test) {
			// phpcs:disable
			public function __construct(private object $test) {
			}
			public function refusalFor(?object $schema, string $profile, ?int $registerId = null): ?JSONResponse {
				$slug = $schema?->getSlug();
				$this->test->gateCalls[] = [$slug, $profile, $registerId];
				if (in_array($slug, $this->test->refused, true) === true) {
					return new JSONResponse(['message' => 'You do not have the export permission.', 'verb' => 'export'], 403);
				}
				return null;
			}
			// phpcs:enable
		};
	}//end exportGate()

	/**
	 * The ObjectService double with OpenRegister's paging.
	 *
	 * @return object
	 */
	private function objectService(): object {
		$test = $this;
		return new class($test) {
			// phpcs:disable
			public function __construct(private object $test) {
			}
			public function find(int|string $id, ?array $_extend = [], bool $files = false, mixed $register = null, mixed $schema = null, bool $_rbac = true, bool $_multitenancy = true): ?object {
				$data = ($this->test->rows[(string)$schema][(string)$id] ?? null);
				if ($data === null) {
					throw new \RuntimeException('Object not found');
				}
				return ProjectExportEntity::of((string)$id, $data);
			}
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array|int {
				$this->test->searches[] = ['schema' => $schemaSlug, 'filters' => $filters, 'rbac' => $_rbac];
				$limit  = (int)($filters['_limit'] ?? 20);
				$offset = (int)($filters['_offset'] ?? 0);
				$found  = [];
				foreach (($this->test->rows[$schemaSlug] ?? []) as $uuid => $data) {
					$match = true;
					foreach ($filters as $key => $value) {
						if (str_starts_with((string)$key, '_') === false && ($data[$key] ?? null) !== $value) {
							$match = false;
						}
					}
					if ($match === true) {
						$found[] = ProjectExportEntity::of((string)$uuid, $data);
					}
				}
				return ['results' => array_slice($found, $offset, $limit)];
			}
			// phpcs:enable
		};
	}//end objectService()

	/**
	 * The FileService double: getFiles() answers File mocks.
	 *
	 * @return object
	 */
	private function fileService(): object {
		$files = [];
		foreach ($this->objectFiles as $uuid => $named) {
			foreach ($named as $name => [$content, $readable]) {
				$files[$uuid][] = $this->fileNode(name: $name, content: $content, readable: $readable);
			}
		}

		return new class($files) {
			// phpcs:disable
			public function __construct(private array $files) {
			}
			public function getFiles(object|string $object, ?bool $sharedFilesOnly = false): array {
				return $this->files[(string)$object->getUuid()] ?? [];
			}
			// phpcs:enable
		};
	}//end fileService()

	/**
	 * One file mock that streams its content.
	 *
	 * @param string $name     The name.
	 * @param string $content  The bytes.
	 * @param bool   $readable Whether the requester may read it.
	 *
	 * @return File
	 */
	protected function fileNode(string $name, string $content, bool $readable=true): File {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->method('isReadable')->willReturn($readable);
		$file->method('fopen')->willReturnCallback(
			static function () use ($content) {
				$stream = fopen('php://memory', 'w+');
				fwrite($stream, $content);
				rewind($stream);
				return $stream;
			}
		);

		return $file;
	}//end fileNode()

	/**
	 * The comments manager double over `$this->taskComments`.
	 *
	 * @return ICommentsManager
	 */
	private function commentsManager(): ICommentsManager {
		$manager = $this->createMock(ICommentsManager::class);
		$manager->method('getForObject')->willReturnCallback(
			function (string $type, string $id): array {
				$this->assertSame('openregister', $type);
				return array_map(
					function (array $row): IComment {
						$comment = $this->createMock(IComment::class);
						$comment->method('getId')->willReturn($row['id']);
						$comment->method('getActorId')->willReturn($row['actor']);
						$comment->method('getMessage')->willReturn($row['message']);
						$comment->method('getCreationDateTime')->willReturn(new \DateTime('2026-11-10T09:00:00+00:00'));
						return $comment;
					},
					($this->taskComments[$id] ?? [])
				);
			}
		);

		return $manager;
	}//end commentsManager()
}//end trait

/**
 * An object as OpenRegister returns it: data, uuid and the `@self` block.
 */
final class ProjectExportEntity implements \JsonSerializable {

	/**
	 * Constructor
	 *
	 * @param string              $uuid The uuid.
	 * @param array<string,mixed> $data The stored data.
	 */
	public function __construct(private string $uuid, private array $data) {
	}//end __construct()

	/**
	 * Build one.
	 *
	 * @param string              $uuid The uuid.
	 * @param array<string,mixed> $data The stored data.
	 *
	 * @return self
	 */
	public static function of(string $uuid, array $data): self {
		return new self(uuid: $uuid, data: $data);
	}//end of()

	/**
	 * The stored data.
	 *
	 * @return array<string,mixed>
	 */
	public function getObject(): array {
		return $this->data;
	}//end getObject()

	/**
	 * The uuid.
	 *
	 * @return string
	 */
	public function getUuid(): string {
		return $this->uuid;
	}//end getUuid()

	/**
	 * The stored data with its `@self` block.
	 *
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		return array_merge($this->data, ['@self' => ['id' => $this->uuid]]);
	}//end jsonSerialize()
}//end class
