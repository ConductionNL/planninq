<?php

/**
 * Loading the example data fits it to the admin who loads it and to the load day.
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
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Planninq\Service\DemoDataService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * DemoDataService::install() and prepare() on the shipped descriptor.
 */
class DemoDataServiceTest extends TestCase {

	/**
	 * What the fake importer received.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $imported = null;

	/**
	 * A service over the real app directory, loaded by $uid on $today.
	 *
	 * @param string|null $uid   The signed-in admin, or null for nobody.
	 * @param string      $today The load day.
	 *
	 * @return DemoDataService
	 */
	private function service(?string $uid, string $today='2026-10-05'): DemoDataService {
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn(dirname(__DIR__, 3));
		$apps->method('getAppVersion')->willReturn('0.0.1');
		$apps->method('getInstalledApps')->willReturn(['openregister']);

		$test     = $this;
		$importer = new class($test) {
			/**
			 * Constructor.
			 *
			 * @param DemoDataServiceTest $test The test that reads the import.
			 */
			public function __construct(private DemoDataServiceTest $test) {
			}

			/**
			 * Record the import.
			 *
			 * @param string              $appId   Config identity.
			 * @param array<string,mixed> $data    The descriptor.
			 * @param string              $version App version.
			 * @param boolean             $force   Forced.
			 *
			 * @return array<string,mixed>
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->test->record(data: $data);
				return ['registers' => [1], 'schemas' => []];
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($importer);

		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable($today . 'T14:30:00+00:00'));

		return new DemoDataService($apps, $container, $this->createMock(LoggerInterface::class), $session, $time);
	}//end service()

	/**
	 * Called by the fake importer.
	 *
	 * @param array<string,mixed> $data The imported descriptor.
	 *
	 * @return void
	 */
	public function record(array $data): void {
		$this->imported = $data;
	}//end record()

	/**
	 * The imported objects of one schema, by slug.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function importedOf(string $schema): array {
		$out = [];
		foreach ($this->imported['components']['objects'] as $object) {
			if ($object['@self']['schema'] === $schema) {
				$out[$object['@self']['slug']] = $object;
			}
		}

		return $out;
	}//end importedOf()

	/**
	 * The loading admin owns and is a member of every example project, and has tasks.
	 *
	 * @return void
	 */
	public function testOperatorBecomesOwnerAndMember(): void {
		$this->service(uid: 'beheerder')->install();

		$projects = $this->importedOf(schema: 'project');
		self::assertCount(3, $projects);
		foreach ($projects as $project) {
			self::assertSame('beheerder', $project['owner']);
			self::assertSame(['beheerder'], $project['members']);
		}

		$assigned = array_filter($this->importedOf(schema: 'task'), static fn (array $task): bool => ($task['assignedTo'] ?? null) === 'beheerder');
		self::assertGreaterThanOrEqual(3, count($assigned));
		self::assertSame(['beheerder'], $this->importedOf(schema: 'projectPortfolio')['demo-digital-services']['managers']);
	}//end testOperatorBecomesOwnerAndMember()

	/**
	 * No placeholder reaches OpenRegister.
	 *
	 * @return void
	 */
	public function testNoPlaceholderSurvives(): void {
		$this->service(uid: 'admin')->install();

		self::assertStringNotContainsString(DemoDataService::OPERATOR_PLACEHOLDER, (string)json_encode($this->imported['components']['objects']));
	}//end testNoPlaceholderSurvives()

	/**
	 * Every date moves by the days between the anchor and the load day; ids and slugs stay.
	 *
	 * @return void
	 */
	public function testDatesShiftToLoadDay(): void {
		$raw = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/planninq_mock_register.json'), true);
		self::assertSame('2026-10-01', $raw['x-openregister']['anchorDate']);

		$this->service(uid: 'admin', today: '2026-10-05')->install();

		$tasks = $this->importedOf(schema: 'task');
		// Anchor plus three days, loaded four days after the anchor.
		self::assertSame('2026-10-08', $tasks['demo-k8s-namespaces']['dueDate']);
		// Overdue by two days on the load day.
		self::assertSame('2026-10-03', $tasks['demo-fix-login-redirect']['dueDate']);
		self::assertSame('2026-09-27T15:30:00+00:00', $tasks['demo-design-dashboard-widgets']['completedAt']);
		self::assertSame('0000de00-0000-4000-8000-000300000006', $tasks['demo-k8s-namespaces']['@self']['id']);
		self::assertSame('2026-10-05', $this->importedOf(schema: 'plannedTimeEntry')['demo-time-login-2']['date']);
	}//end testDatesShiftToLoadDay()

	/**
	 * A load before the anchor moves dates back, and a descriptor without an anchor keeps its dates.
	 *
	 * @return void
	 */
	public function testShiftWorksBackwardsAndNeedsAnAnchor(): void {
		$service = $this->service(uid: 'admin');
		$data    = [
			'x-openregister' => ['anchorDate' => '2026-10-01'],
			'components'     => ['objects' => [['@self' => ['slug' => 'demo-2026-10-01'], 'dueDate' => '2026-10-01', 'members' => ['@operator', 'admin']]]],
		];

		$fitted = $service->prepare(data: $data, operator: 'admin', today: new DateTimeImmutable('2026-09-21'));
		self::assertSame('2026-09-21', $fitted['components']['objects'][0]['dueDate']);
		self::assertSame('demo-2026-10-01', $fitted['components']['objects'][0]['@self']['slug']);
		self::assertSame(['admin'], $fitted['components']['objects'][0]['members']);

		unset($data['x-openregister']);
		$kept = $service->prepare(data: $data, operator: 'admin', today: new DateTimeImmutable('2026-09-21'));
		self::assertSame('2026-10-01', $kept['components']['objects'][0]['dueDate']);
	}//end testShiftWorksBackwardsAndNeedsAnAnchor()

	/**
	 * Without a signed-in admin nothing is imported.
	 *
	 * @return void
	 */
	public function testInstallRefusesWithoutASignedInUser(): void {
		$this->expectException(RuntimeException::class);
		try {
			$this->service(uid: null)->install();
		} finally {
			self::assertNull($this->imported);
		}
	}//end testInstallRefusesWithoutASignedInUser()
}//end class
