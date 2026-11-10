<?php

/**
 * Golden test for ProjectController::create(): per scenario (signed-in user,
 * creation policy, OpenRegister, posted body, project key, readable links,
 * the save), every collaborator call in order and the response, compared with
 * a recorded fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/SaveObjectArgs.php';

use OCA\Planninq\Controller\ProjectController;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\SaveObjectArgs;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\ProjectController
 */
final class ProjectCreateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/projectCreate.golden.json';

	private const BODY = [
		'title'         => 'Bridge',
		'key'           => 'br1',
		'owner'         => 'mallory',
		'members'       => ['mallory'],
		'status'        => 'closed',
		'reviewedBy'    => 'mallory',
		'caseReference' => 'case-1',
		'client'        => 'client-1',
		'@self'         => ['id' => 'forged'],
	];

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testCreateMatchesTheRecording(): void {
		$base      = ['user' => 'alice', 'create' => true, 'request' => false, 'or' => true, 'body' => self::BODY, 'key' => 'ok', 'links' => 'readable', 'save' => 'ok'];
		$scenarios = [
			'created'                       => [],
			'created, saved id in @self'    => ['save' => 'self id'],
			'no user'                       => ['user' => null],
			'policy denies, no requests'    => ['create' => false],
			'policy denies, request stored' => ['create' => false, 'request' => true],
			'no OpenRegister'               => ['or' => false],
			'no OpenRegister, bad key'      => ['or' => false, 'key' => 'format'],
			'key format'                    => ['key' => 'format'],
			'key taken'                     => ['key' => 'taken'],
			'no key'                        => ['body' => ['title' => 'Bridge']],
			'links unreadable'              => ['links' => 'unreadable'],
			'links throw'                   => ['links' => 'throw'],
			'save throws'                   => ['save' => 'throw'],
			'columns throw'                 => ['save' => 'columns throw'],
			'request, save throws'          => ['create' => false, 'request' => true, 'save' => 'throw'],
		];
		$actual    = [];
		foreach ($scenarios as $name => $change) {
			$actual[$name] = $this->record(setup: array_merge($base, $change));
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testCreateMatchesTheRecording()

	/**
	 * Run create() once and record every call, in order.
	 *
	 * @param array<string,mixed> $setup The scenario.
	 *
	 * @return array<int,mixed>
	 */
	private function record(array $setup): array {
		$calls      = [];
		$controller = new ProjectController(
			request: $this->request(setup: $setup, calls: $calls),
			settingsService: $this->settings(setup: $setup, calls: $calls),
			userSession: $this->session(setup: $setup, calls: $calls),
			container: $this->container(setup: $setup, calls: $calls),
			logger: $this->logger(calls: $calls),
			boardColumns: $this->columns(setup: $setup, calls: $calls),
			keys: $this->keys(setup: $setup, calls: $calls),
		);
		$response   = $controller->create();
		$calls[]    = ['response', $response->getStatus(), $response->getData()];
		return $calls;
	}//end record()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return IRequest
	 */
	private function request(array $setup, array &$calls): IRequest {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturnCallback(
			static function () use (&$calls, $setup): array {
				$calls[] = ['getParams'];
				return $setup['body'];
			}
		);
		return $request;
	}//end request()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return SettingsService
	 */
	private function settings(array $setup, array &$calls): SettingsService {
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('canCurrentUserCreateProject')->willReturnCallback(
			static function () use (&$calls, $setup): bool {
				$calls[] = ['canCreate'];
				return $setup['create'];
			}
		);
		$settings->method('canCurrentUserRequestProject')->willReturnCallback(
			static function () use (&$calls, $setup): bool {
				$calls[] = ['canRequest'];
				return $setup['request'];
			}
		);
		return $settings;
	}//end settings()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return IUserSession
	 */
	private function session(array $setup, array &$calls): IUserSession {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn((string)$setup['user']);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			static function () use (&$calls, $setup, $user): ?IUser {
				$calls[] = ['getUser'];
				return $setup['user'] === null ? null : $user;
			}
		);
		return $session;
	}//end session()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return ContainerInterface
	 */
	private function container(array $setup, array &$calls): ContainerInterface {
		$objects   = new class($setup, $calls) {
			/** @var array<string,mixed> */
			private array $setup;

			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param array<string,mixed> $setup The scenario.
			 * @param array<int,mixed>    $calls Receives the calls.
			 */
			public function __construct(array $setup, array &$calls) {
				$this->setup = $setup;
				$this->calls = &$calls;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object|null
			 */
			public function find(mixed ...$args): ?object {
				$this->calls[] = ['find', $args];
				if ($this->setup['links'] === 'throw') {
					throw new \RuntimeException('no such app');
				}

				return $this->setup['links'] === 'readable' ? new \stdClass() : null;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object
			 */
			public function saveObject(mixed ...$args): object {
				$args          = SaveObjectArgs::flatten(args: $args);
				$this->calls[] = ['saveObject', $args];
				if ($this->setup['save'] === 'throw') {
					throw new \RuntimeException('database down');
				}

				$data = $this->setup['save'] === 'self id' ? ['@self' => ['id' => 'self-uuid'], 'title' => 'Bridge'] : ['id' => 'new-uuid', 'title' => 'Bridge'];
				return new class($data) {
					/**
					 * @param array<string,mixed> $data The saved project.
					 */
					public function __construct(private array $data) {
					}

					/**
					 * @return array<string,mixed>
					 */
					public function jsonSerialize(): array {
						return $this->data;
					}
				};
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use (&$calls, $setup, $objects): object {
				$calls[] = ['container.get', $id];
				if ($setup['or'] === false) {
					throw new \RuntimeException('OpenRegister missing');
				}

				return $objects;
			}
		);
		return $container;
	}//end container()

	/**
	 * @param array<int,mixed> $calls Receives the calls.
	 *
	 * @return LoggerInterface
	 */
	private function logger(array &$calls): LoggerInterface {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $message, $context];
				}
			);
		}

		return $logger;
	}//end logger()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return BoardColumnService
	 */
	private function columns(array $setup, array &$calls): BoardColumnService {
		$columns = $this->createMock(originalClassName: BoardColumnService::class);
		$columns->method('createDefaultColumns')->willReturnCallback(
			static function (string $projectId) use (&$calls, $setup): array {
				$calls[] = ['createDefaultColumns', $projectId];
				if ($setup['save'] === 'columns throw') {
					throw new \RuntimeException('columns failed');
				}

				return [];
			}
		);
		return $columns;
	}//end columns()

	/**
	 * @param array<string,mixed> $setup The scenario.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return WorkItemKeyService
	 */
	private function keys(array $setup, array &$calls): WorkItemKeyService {
		$keys = $this->createMock(originalClassName: WorkItemKeyService::class);
		$keys->method('normalise')->willReturnCallback(
			static function (mixed $key) use (&$calls): string {
				$calls[] = ['normalise', $key];
				return strtoupper((string)$key);
			}
		);
		$keys->method('isValidFormat')->willReturnCallback(
			static function (string $key) use (&$calls, $setup): bool {
				$calls[] = ['isValidFormat', $key];
				return $setup['key'] !== 'format';
			}
		);
		$keys->method('isTaken')->willReturnCallback(
			static function (string $key) use (&$calls, $setup): bool {
				$calls[] = ['isTaken', $key];
				return $setup['key'] === 'taken';
			}
		);
		return $keys;
	}//end keys()
}//end class
