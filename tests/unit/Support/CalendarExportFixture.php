<?php

/**
 * Shared doubles for the task calendar export tests (planning-calendar): an
 * in-memory CalDAV backend with the CalDavBackend calls the export makes, a
 * recording OpenRegister ObjectService, and the real TaskCalendarExportService
 * wired to them.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Support
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

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Service\TaskCalendarTaskStore;
use OCA\Planninq\Service\TaskVtodoBuilder;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

trait CalendarExportFixture {

	/**
	 * The in-memory backend: lists[principal] = id, objects[id][uri] = ics.
	 *
	 * @var object
	 */
	protected object $backend;

	/**
	 * The recording ObjectService.
	 *
	 * @var object
	 */
	protected object $objects;

	/**
	 * User values: [user][key] = value.
	 *
	 * @var array<string,array<string,string>>
	 */
	protected array $userValues = [];

	/**
	 * Jobs queued: [class, argument].
	 *
	 * @var array<int,array{0:string,1:mixed}>
	 */
	protected array $queued = [];

	/**
	 * Build the real export service over the doubles.
	 *
	 * @param array<int,string>                  $on       Users who switched the export on.
	 * @param bool                               $withDav  Whether the DAV backend resolves.
	 * @param array<string,array<string,mixed>>  $assigned Tasks the backfill search returns (every task), by uuid.
	 *
	 * @return TaskCalendarExportService
	 */
	protected function makeExport(array $on = [], bool $withDav = true, array $assigned = []): TaskCalendarExportService {
		$this->userValues = [];
		foreach ($on as $user) {
			$this->userValues[$user][TaskCalendarExportService::SWITCH_KEY] = 'true';
		}

		$this->queued  = [];
		$this->backend = $this->newBackend();
		$this->objects = $this->newObjectService(assigned: $assigned);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(fn ($user, $app, $key, $default = '') => $this->userValues[$user][$key] ?? $default);
		$config->method('setUserValue')->willReturnCallback(function ($user, $app, $key, $value): void {
			$this->userValues[$user][$key] = (string)$value;
		});
		$config->method('getSystemValueString')->willReturnCallback(fn ($key, $default = '') => $key === 'instanceid' ? 'inst' : $default);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(fn (string $path) => 'https://nc.example' . $path);

		$jobs = $this->createMock(IJobList::class);
		$jobs->method('add')->willReturnCallback(function ($job, $argument = null): void {
			$this->queued[] = [is_string($job) ? $job : get_class($job), $argument];
		});

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text) => $text);

		$objects   = $this->objects;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(fn (string $id) => $this->containerGet(id: $id, objects: $objects));

		$backend = $withDav ? $this->backend : null;

		$logger = $this->createMock(LoggerInterface::class);
		$store  = new TaskCalendarTaskStore($container, $logger);

		return new class($config, $container, $urls, $jobs, $l10n, new TaskVtodoBuilder(), $store, $logger, $backend) extends TaskCalendarExportService {
			// phpcs:disable
			public function __construct($config, $container, $urls, $jobs, $l10n, $builder, $store, $logger, private ?object $fake) {
				parent::__construct($config, $container, $urls, $jobs, $l10n, $builder, $store, $logger);
			}
			public function davBackend(): ?object {
				return $this->fake;
			}
			// phpcs:enable
		};
	}//end makeExport()

	/**
	 * The scope resolver over register 1 = planninq and schema 2 = task.
	 *
	 * @return TaskScopeResolver
	 */
	protected function makeScope(): TaskScopeResolver {
		$objects   = $this->objects;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(fn (string $id) => $this->containerGet(id: $id, objects: $objects));

		return new TaskScopeResolver($container, $this->createMock(LoggerInterface::class));
	}//end makeScope()

	/**
	 * The VTODO text in a user's Planninq list for a task, or null.
	 *
	 * @param string $user   The user.
	 * @param string $taskId The task uuid.
	 *
	 * @return string|null
	 */
	protected function vtodo(string $user, string $taskId): ?string {
		$list = $this->backend->lists['principals/users/' . $user] ?? null;
		if ($list === null) {
			return null;
		}

		return $this->backend->objects[$list]['planninq-task-' . $taskId . '.ics'] ?? null;
	}//end vtodo()

	/**
	 * Resolve a container id to a double.
	 *
	 * @param string $id      The id.
	 * @param object $objects The ObjectService double.
	 *
	 * @return object
	 */
	private function containerGet(string $id, object $objects): object {
		$slugged = static fn (string $slug) => new class($slug) {
			// phpcs:disable
			public function __construct(private string $slug) {}
			public function getSlug(): string { return $this->slug; }
			// phpcs:enable
		};
		$mapper = static fn (array $slugs) => new class($slugs, $slugged) {
			// phpcs:disable
			public function __construct(private array $slugs, private $make) {}
			public function find($id): object { return ($this->make)($this->slugs[(string)$id] ?? 'other'); }
			// phpcs:enable
		};

		return match ($id) {
			'OCA\\OpenRegister\\Db\\RegisterMapper' => $mapper(['1' => 'planninq']),
			'OCA\\OpenRegister\\Db\\SchemaMapper' => $mapper(['2' => 'task']),
			'OCA\\OpenRegister\\Service\\ObjectService' => $objects,
			default => throw new \RuntimeException('unexpected: ' . $id),
		};
	}//end containerGet()

	/**
	 * An in-memory CalDavBackend with the calls the export makes.
	 *
	 * @return object
	 */
	private function newBackend(): object {
		return new class {
			// phpcs:disable
			public array $lists = [];
			public array $objects = [];
			public array $created = [];
			public bool $fail = false;
			private int $next = 1;
			public function getCalendarByUri($principal, $uri) { return isset($this->lists[$principal]) && $uri === 'planninq' ? ['id' => $this->lists[$principal], 'uri' => $uri] : null; }
			public function createCalendar($principal, $uri, array $properties) { $this->created[] = [$principal, $uri, $properties]; $this->lists[$principal] = $this->next; $this->objects[$this->next] = []; return $this->next++; }
			public function deleteCalendar($id, bool $force = false) { unset($this->objects[$id]); $this->lists = array_filter($this->lists, fn ($l) => $l !== $id); }
			public function getCalendarObject($id, $uri) { return isset($this->objects[$id][$uri]) ? ['calendardata' => $this->objects[$id][$uri]] : null; }
			public function createCalendarObject($id, $uri, $data) { if ($this->fail) { throw new \RuntimeException('dav down'); } if (isset($this->objects[$id][$uri])) { throw new \RuntimeException('exists'); } $this->objects[$id][$uri] = $data; }
			public function updateCalendarObject($id, $uri, $data) { if ($this->fail) { throw new \RuntimeException('dav down'); } if (!isset($this->objects[$id][$uri])) { throw new \RuntimeException('missing'); } $this->objects[$id][$uri] = $data; }
			public function deleteCalendarObject($id, $uri) { unset($this->objects[$id][$uri]); }
			// phpcs:enable
		};
	}//end newBackend()

	/**
	 * A recording ObjectService: project titles, the backfill search, saved objects.
	 *
	 * @param array<string,array<string,mixed>> $assigned Tasks the search returns.
	 *
	 * @return object
	 */
	private function newObjectService(array $assigned): object {
		return new class($assigned) {
			// phpcs:disable
			public array $saved = [];
			public array $searches = [];
			public function __construct(private array $assigned) {}
			public function find($id, $register = null, $schema = null, $_rbac = true, $_multitenancy = true) { return ['id' => $id, 'title' => 'Project ' . $id]; }
			public function searchObjectsBySlug($registerSlug, $schemaSlug, $filters = [], $_rbac = true, $_multitenancy = true) { $this->searches[] = [$registerSlug, $schemaSlug, $filters]; $rows = []; foreach ($this->assigned as $id => $data) { $rows[] = ['id' => $id] + $data; } return ['results' => $rows]; }
			public function saveObject($object, $register = null, $schema = null, $uuid = null, $_rbac = true, $_multitenancy = true, $silent = false) { $this->saved[] = ['object' => $object, 'register' => $register, 'schema' => $schema, 'uuid' => $uuid, 'silent' => $silent]; return $object; }
			// phpcs:enable
		};
	}//end newObjectService()
}//end trait
