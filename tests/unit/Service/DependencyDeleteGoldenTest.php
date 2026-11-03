<?php

/**
 * Golden test for deleting a dependency edge.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Exception\DependencyValidationException;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\DependencyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Records who may delete which edge: the lookups made, the answer and the delete.
 *
 * @covers \OCA\Planninq\Service\DependencyService
 * @uses   \OCA\Planninq\Service\DependencyRepository
 * @uses   \OCA\Planninq\Service\ProjectRoles
 */
class DependencyDeleteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/dependency-delete.golden.json';

	private const PROJECT = 'p-1';

	private const EDGES = [
		'in-project'      => ['blocker' => 't-in', 'blocked' => 't-in2', 'type' => 'blocks'],
		'blocker-gone'    => ['blocker' => 't-gone', 'blocked' => 't-in', 'type' => 'blocks'],
		'blocker-no-proj' => ['blocker' => 't-loose', 'blocked' => 't-in', 'type' => 'blocks'],
		'both-gone'       => ['blocker' => 't-gone', 'blocked' => 't-gone2', 'type' => 'blocks'],
		'no-ends'         => ['type' => 'relates'],
		'blocked-no-proj' => ['blocker' => 't-gone', 'blocked' => 't-loose', 'type' => 'blocks'],
		'other-project'   => ['blocker' => 't-other', 'blocked' => 't-in', 'type' => 'blocks'],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testDeleteMatchesTheRecording(): void {
		$recorded = [];
		foreach (array_merge(array_keys(self::EDGES), ['missing-edge']) as $edge) {
			foreach (['carol', 'eve', 'grouped', ''] as $actor) {
				$objects = $this->objects();
				$answer  = 'deleted';
				try {
					$this->service(objects: $objects, actor: $actor)->delete(id: 'e-' . $edge);
				} catch (DependencyValidationException $e) {
					$answer = $e->getCode() . ' ' . $e->getMessage();
				}

				$recorded[$edge . ' / ' . ($actor === '' ? 'nobody' : $actor)] = [
					'answer' => $answer,
					'finds'  => array_map(static fn (array $find): string => $find['schema'] . ':' . $find['id'], $objects->finds),
					'left'   => array_keys($objects->rows['dependency']),
				];
			}
		}

		$fixture = [];
		if (is_file(self::FIXTURE) === true) {
			$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			$fixture = $recorded;
			file_put_contents(self::FIXTURE, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testDeleteMatchesTheRecording()

	/**
	 * An in-memory OpenRegister that finds by the schema set before the call, and answers null when nothing is stored.
	 *
	 * @return InMemoryObjectService
	 */
	private function objects(): InMemoryObjectService {
		$objects = new class () extends InMemoryObjectService {
			private mixed $current = null;

			public function setSchema(mixed $schema): static {
				$this->current = $schema;
				return $this;
			}

			public function find(
				int|string $id,
				?array $_extend = [],
				bool $files = false,
				mixed $register = null,
				mixed $schema = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $_render = true,
				bool $_audit = true,
			): ?object {
				$schema = ($schema ?? $this->current);
				if (isset($this->rows[(string)$schema][(string)$id]) === false) {
					$this->finds[] = ['id' => (string)$id, 'schema' => $schema];
					return null;
				}

				return parent::find($id, $_extend, $files, $register, $schema, $_rbac, $_multitenancy, $_render, $_audit);
			}
		};

		$objects->seed('project', self::PROJECT, ['title' => 'P', 'owner' => 'carol', 'members' => ['carol'], 'groups' => ['team']]);
		$objects->seed('project', 'p-2', ['title' => 'Other', 'owner' => 'eve', 'members' => ['eve']]);
		$objects->seed('task', 't-in', ['title' => 'In', 'project' => self::PROJECT]);
		$objects->seed('task', 't-in2', ['title' => 'In 2', 'project' => self::PROJECT]);
		$objects->seed('task', 't-loose', ['title' => 'Loose', 'project' => '']);
		$objects->seed('task', 't-other', ['title' => 'Other', 'project' => 'p-2']);
		foreach (self::EDGES as $name => $edge) {
			$objects->seed('dependency', 'e-' . $name, $edge);
		}

		return $objects;
	}//end objects()

	/**
	 * The dependency service acting for one user, or for nobody.
	 *
	 * @param InMemoryObjectService $objects The in-memory OpenRegister.
	 * @param string                $actor   The acting user id; empty for no session.
	 *
	 * @return DependencyService
	 */
	private function service(InMemoryObjectService $objects, string $actor): DependencyService {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturnCallback(
			static fn (IUser $who): array => ($who->getUID() === 'grouped' ? ['team'] : [])
		);

		return new DependencyService(
			repository: new DependencyRepository(container: $container, logger: new NullLogger(), appManager: $appManager),
			graph: new DependencyGraph(),
			userSession: $session,
			logger: new NullLogger(),
			groupManager: $groups
		);
	}//end service()
}//end class
