<?php

/**
 * Golden test for ProjectController::leaveProject(): per scenario (caller,
 * OpenRegister, the stored project and its role lists, the save), every
 * ObjectService and log call in order and the response, compared with a
 * recorded fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\ProjectController;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\ProjectController
 * @uses   \OCA\Planninq\Service\ProjectRoles
 */
final class ProjectLeaveGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/projectLeave.golden.json';

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testLeaveMatchesTheRecording(): void {
		$team      = ['owner' => 'alice', 'managers' => ['alice', 'zed'], 'members' => ['alice', 'bob', 'carol'], 'viewers' => ['alice', 'vera'], 'title' => 'Bridge'];
		$scenarios = [
			'owner leaves, manager takes over' => ['alice', $team, 'ok'],
			'member leaves'                    => ['bob', $team, 'ok'],
			'viewer leaves'                    => ['vera', $team, 'ok'],
			'owner leaves, a member takes over' => ['alice', ['owner' => 'alice', 'members' => ['alice', 'bob']], 'ok'],
			'last member'                      => ['alice', ['owner' => 'alice', 'members' => ['alice']], 'ok'],
			'not a member'                     => ['mallory', $team, 'ok'],
			'group-owned project, owner leaves' => ['alice', ['owner' => 'alice', 'ownerGroup' => 'pmo', 'members' => ['alice']], 'ok'],
			'project not found'                => ['alice', null, 'ok'],
			'save throws'                      => ['bob', $team, 'throw'],
			'no OpenRegister'                  => ['bob', $team, 'no or'],
			'nobody signed in'                 => [null, $team, 'ok'],
		];
		$actual    = [];
		foreach ($scenarios as $name => [$uid, $project, $mode]) {
			$actual[$name] = $this->record(uid: $uid, setup: [$project, $mode]);
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testLeaveMatchesTheRecording()

	/**
	 * Run leaveProject() once and record every call, in order.
	 *
	 * @param string|null      $uid   The caller, null when nobody is signed in.
	 * @param array<int,mixed> $setup The stored project (null: not found) and the mode.
	 *
	 * @return array<int,mixed>
	 */
	private function record(?string $uid, array $setup): array {
		[$project, $mode] = $setup;
		$calls            = [];
		$user             = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn((string)$uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === null ? null : $user);

		$objects   = new class($project, $mode, $calls) {
			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param array<string,mixed>|null $project The stored project.
			 * @param string                   $mode    'ok' or 'throw'.
			 * @param array<int,mixed>         $calls   Receives the calls.
			 */
			public function __construct(private ?array $project, private string $mode, array &$calls) {
				$this->calls = &$calls;
			}

			/**
			 * @param string $register The register.
			 *
			 * @return void
			 */
			public function setRegister(string $register): void {
				$this->calls[] = ['setRegister', $register];
			}

			/**
			 * @param string $schema The schema.
			 *
			 * @return void
			 */
			public function setSchema(string $schema): void {
				$this->calls[] = ['setSchema', $schema];
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object|null
			 */
			public function find(mixed ...$args): ?object {
				$this->calls[] = ['find', $args];
				return $this->project === null ? null : ProjectLeaveGoldenTest::entity(data: $this->project);
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object
			 */
			public function saveObject(mixed ...$args): object {
				$this->calls[] = ['saveObject', $args];
				if ($this->mode === 'throw') {
					throw new \RuntimeException('database down');
				}

				return ProjectLeaveGoldenTest::entity(data: $args['object']);
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use (&$calls, $mode, $objects): object {
				$calls[] = ['container.get', $id];
				if ($mode === 'no or') {
					throw new \RuntimeException('OpenRegister missing');
				}

				if ($id === 'OCA\\Planninq\\Listener\\TaskScopeResolver') {
					return new class($calls) {
						/** @var array<int,mixed> */
						private array $calls;

						/**
						 * @param array<int,mixed> $calls Receives the calls.
						 */
						public function __construct(array &$calls) {
							$this->calls = &$calls;
						}

						/**
						 * @param string $registerId The register id.
						 * @param string $schemaId   The schema id.
						 *
						 * @return string
						 */
						public function planninqSchemaSlug(string $registerId, string $schemaId): string {
							$this->calls[] = ['planninqSchemaSlug', $registerId, $schemaId];
							return ($registerId === '1' && $schemaId === '14') ? 'project' : 'task';
						}
					};
				}

				return $objects;
			}
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $message, $context];
				}
			);
		}

		$controller = new ProjectController(
			request: $this->createMock(originalClassName: IRequest::class),
			settingsService: $this->createMock(originalClassName: SettingsService::class),
			userSession: $session,
			container: $container,
			logger: $logger,
			boardColumns: $this->createMock(originalClassName: BoardColumnService::class),
			keys: $this->createMock(originalClassName: WorkItemKeyService::class),
		);
		$response   = $controller->leaveProject('project-uuid-1');
		$calls[]    = ['response', $response->getStatus(), $response->getData()];
		return $calls;
	}//end record()

	/**
	 * A project entity stand-in.
	 *
	 * @param array<string,mixed> $data The project data.
	 *
	 * @return object
	 */
	public static function entity(array $data): object {
		return new class($data) {
			/**
			 * @param array<string,mixed> $data The project data.
			 */
			public function __construct(private array $data) {
			}

			/**
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return $this->data;
			}

			/**
			 * @return string
			 */
			public function getUuid(): string {
				return 'project-uuid-1';
			}

			/**
			 * @return string
			 */
			public function getRegister(): string {
				return '1';
			}

			/**
			 * @return string
			 */
			public function getSchema(): string {
				return '14';
			}

			/**
			 * @return array<string,mixed>
			 */
			public function jsonSerialize(): array {
				return $this->data;
			}
		};
	}//end entity()
}//end class
