<?php

/**
 * Shared wiring for the project-membership tests (planninq#681).
 *
 * Builds the real {@see TaskScopeResolver} and {@see ProjectMembershipService}
 * over an {@see InMemoryObjectService} and two slug-resolving mapper fakes, so
 * the listener, service and repair tests exercise the production classes and
 * only OpenRegister itself is replaced.
 *
 * Ids used throughout: register `1` is `planninq`, register `9` belongs to
 * another app; schema `10` task, `11` column, `12` projectPhase,
 * `13` plannedTimeEntry, `14` project, `15` label.
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

use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Service\ForgeLinkService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the membership collaborators over in-memory OpenRegister fakes.
 */
trait MembershipFixture {

	/**
	 * The in-memory ObjectService the fixture wires in.
	 *
	 * @var InMemoryObjectService
	 */
	protected InMemoryObjectService $objects;

	/**
	 * Register id => slug.
	 *
	 * @var array<string,string>
	 */
	protected array $registerSlugs = ['1' => 'planninq', '9' => 'elsewhere'];

	/**
	 * Schema id => slug.
	 *
	 * @var array<string,string>
	 */
	protected array $schemaSlugs = [
		'10' => 'task',
		'11' => 'column',
		'12' => 'projectPhase',
		'13' => 'plannedTimeEntry',
		'14' => 'project',
		'15' => 'label',
		'16' => 'projectLogEntry',
		'17' => 'risk',
		'18' => 'projectStatusReport',
		'19' => 'projectPortfolio',
		'20' => 'financeLine',
		'21' => 'projectField',
		'22' => 'projectRelease',
		'23' => 'boardFilter',
		'24' => 'boardView',
		'25' => 'forgeLink',
		'26' => 'wikiPage',
	];

	/**
	 * Schema slug => id, the reverse of $schemaSlugs.
	 *
	 * @param string $slug The schema slug.
	 *
	 * @return string The schema id.
	 */
	protected function schemaId(string $slug): string {
		return (string)array_search($slug, $this->schemaSlugs, true);
	}//end schemaId()

	/**
	 * A container serving the in-memory ObjectService and the mapper fakes.
	 *
	 * @return ContainerInterface
	 */
	protected function container(): ContainerInterface {
		$this->objects = ($this->objects ?? new InMemoryObjectService());

		$registerMapper = $this->slugMapper(slugs: $this->registerSlugs);
		$schemaMapper = $this->slugMapper(slugs: $this->schemaSlugs);
		$objects = $this->objects;

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			fn (string $id): object => match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registerMapper,
				'OCA\\OpenRegister\\Db\\SchemaMapper' => $schemaMapper,
				'OCA\\OpenRegister\\Service\\ObjectService' => $objects,
				'OCA\\Planninq\\Service\\ProjectMembershipService' => $this->membershipService(),
				'OCA\\Planninq\\Service\\ForgeLinkService' => $this->forgeLinkService(),
				default => throw new \RuntimeException('unexpected service: ' . $id),
			}
		);

		return $container;
	}//end container()

	/**
	 * The real scope resolver over the fixture container.
	 *
	 * @return TaskScopeResolver
	 */
	protected function scopeResolver(): TaskScopeResolver {
		return new TaskScopeResolver(
			container: $this->container(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end scopeResolver()

	/**
	 * The real membership service over the fixture container.
	 *
	 * @param bool $openRegisterInstalled What IAppManager answers for `openregister`.
	 *
	 * @return ProjectMembershipService
	 */
	protected function membershipService(bool $openRegisterInstalled = true): ProjectMembershipService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturnCallback(
			static fn (string $app): bool => ($app === 'openregister' && $openRegisterInstalled === true)
		);

		return new ProjectMembershipService(
			container: $this->container(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end membershipService()

	/**
	 * The real code link service over the fixture container.
	 *
	 * @return ForgeLinkService
	 */
	protected function forgeLinkService(): ForgeLinkService {
		return new ForgeLinkService(
			container: $this->container(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end forgeLinkService()

	/**
	 * A mapper fake whose find() returns an entity with the mapped slug.
	 *
	 * @param array<string,string> $slugs Id => slug.
	 *
	 * @return object
	 */
	private function slugMapper(array $slugs): object {
		return new class($slugs) {
			// phpcs:disable
			public function __construct(
				private array $slugs,
			) {
			}
			public function find($id): object {
				if (isset($this->slugs[(string)$id]) === false) {
					throw new \RuntimeException('no row ' . $id);
				}
				$slug = $this->slugs[(string)$id];
				return new class($slug) {
					public function __construct(
						private string $slug,
					) {
					}
					public function getSlug(): string {
						return $this->slug;
					}
				};
			}
			// phpcs:enable
		};
	}//end slugMapper()
}//end trait
