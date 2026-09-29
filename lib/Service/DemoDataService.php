<?php
/**
 * Planninq DemoDataService.
 *
 * Imports `lib/Settings/planninq_mock_register.json`, a `type: mock` descriptor
 * of curated example objects with fixed ids (platform-demo-data). The ADR-111
 * generator keeps them (`--keep`) and validates them (`--check`), and
 * DemoDatasetTest resolves every reference. Before the import the descriptor is
 * fitted to the admin who loads it and to the load day, see prepare().
 *
 * 🔴 ON DEMAND ONLY, NEVER ON INSTALL. A mock register has no Repair step: demo
 * data is something an operator asks for from the setup wizard, and an install
 * that silently seeds example objects into a production instance is a defect,
 * not a convenience.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use DateTimeImmutable;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Imports the shipped demo dataset on request.
 *
 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
 */
class DemoDataService {
	/**
	 * App-relative path to the generated mock descriptor.
	 *
	 * @var string
	 */
	private const DESCRIPTOR = '/lib/Settings/planninq_mock_register.json';

	/**
	 * Configuration identity for the demo import.
	 *
	 * 🔴 ITS OWN NAMESPACE, not the app id. Sharing the app's identity would make
	 * the demo import and the real configuration import share one version gate, so
	 * installing demo data could mask a pending configuration update — or be
	 * masked by one.
	 *
	 * @var string
	 */
	private const CONFIG_APP_ID = Application::APP_ID . '.demo';

	/**
	 * The value the descriptor writes wherever a user id belongs.
	 *
	 * @var string
	 */
	public const OPERATOR_PLACEHOLDER = '@operator';

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager  Resolves this app's path and version.
	 * @param ContainerInterface $container   Resolves OpenRegister's importer.
	 * @param LoggerInterface    $logger      Records what was imported.
	 * @param IUserSession       $userSession The admin who loads the example data.
	 * @param ITimeFactory       $timeFactory The load day.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
		private readonly IUserSession $userSession,
		private readonly ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * Whether this app ships a demo dataset at all.
	 *
	 * @return boolean True when the descriptor is present on disk.
	 *
	 * @spec exclude Demo-data availability probe; ADR-111 rule 1 has no per-app behavioural spec.
	 */
	public function isAvailable(): bool {
		return is_file($this->descriptorPath()) === true;
	}//end isAvailable()

	/**
	 * The answer that means "plant nothing".
	 *
	 * 🔴 NOT THE ABSENCE OF AN ANSWER. An operator who declines has FINISHED the
	 * step; a step that can never be marked done reopens the wizard over every
	 * page (nextcloud-vue#806).
	 *
	 * @var string
	 */
	public const NONE_DATASET = 'none';

	/**
	 * The id of the dataset this app ships.
	 *
	 * @var string
	 */
	public const DEMO_DATASET = 'demo';

	/**
	 * Every answer the wizard's choice step may offer, declining included.
	 *
	 * 🔴 THE SERVER OWNS THIS LIST, AND THAT IS THE POINT. The step declares
	 * `optionsSource: datasets` and no options of its own, so the label, the
	 * description and the object count come from the descriptor that will
	 * actually be imported. A manifest that restated them could disagree with
	 * what lands, and nothing would notice.
	 *
	 * @return array<int, array{id: string, label: string, description: string, objectCount: integer, icon: string}> The answers.
	 *
	 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
	 */
	public function listChoices(): array {
		$choices = [
			[
				'id'          => self::NONE_DATASET,
				'label'       => 'None, I will set this up myself',
				'description' => 'Nothing is imported. You start with an empty app and add your own data.',
				'objectCount' => 0,
				'icon'        => 'CloseCircleOutline',
			],
		];

		$objects = $this->shippedObjectCount();
		if ($objects !== null) {
			$choices[] = [
				'id'    => self::DEMO_DATASET,
				'label' => 'Example data',
				// 🔴 NO NUMBER IN THIS SENTENCE. The wizard runs a card's
				// description through the app's translation function, which is a
				// literal lookup, so an interpolated count would make the string
				// untranslatable and leave a Dutch operator reading English. The
				// count travels as `objectCount` and the card renders it as a
				// stat, with a label the library translates.
				'description' => (
					'Three example projects with boards, phases, tasks, dependencies, logged time, '
					. 'risks, status reports and portfolios. You become their owner and the person '
					. 'the tasks are assigned to, and the dates fall around today. Safe to run more '
					. 'than once, and you can delete it afterwards.'
				),
				'objectCount' => $objects,
				'icon'        => 'DatabaseOutline',
			];
		}

		return $choices;

	}//end listChoices()

	/**
	 * How many objects the shipped descriptor carries, or null when it ships none.
	 *
	 * Counted from the FILE, so the card promises the number that will actually
	 * be imported. A missing or malformed descriptor returns null and the app
	 * then offers only "None" — honest, rather than an import that cannot run.
	 *
	 * @return integer|null The object count, or null when there is no usable descriptor.
	 */
	private function shippedObjectCount(): ?int {
		$path = $this->descriptorPath();
		if (is_file($path) === false) {
			return null;
		}

		$raw = file_get_contents($path);
		if ($raw === false) {
			return null;
		}

		$data = json_decode($raw, true);
		if (is_array($data) === false) {
			return null;
		}

		$components = ($data['components'] ?? []);
		if (is_array($components) === false || is_array(($components['objects'] ?? null)) === false) {
			return 0;
		}

		return count($components['objects']);

	}//end shippedObjectCount()

	/**
	 * Import the demo dataset.
	 *
	 * 🔴 THROWS RATHER THAN RETURNING A QUIET FAILURE. The caller reports the
	 * outcome to an operator who just asked for this, so "nothing happened" must
	 * not be presentable as success.
	 *
	 * @return array{objects: integer, registers: integer, schemas: integer} What was imported.
	 *
	 * @throws RuntimeException When the descriptor is missing, unreadable, OpenRegister is absent, or nobody is signed in.
	 *
	 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
	 */
	public function install(): array {
		$path = $this->descriptorPath();
		if (is_file($path) === false) {
			throw new RuntimeException('No demo dataset ships with this app (' . self::DESCRIPTOR . ' not found).');
		}

		$raw = file_get_contents($path);
		if ($raw === false) {
			throw new RuntimeException('The demo dataset could not be read: ' . $path);
		}

		$data = json_decode($raw, true);
		if (is_array($data) === false) {
			throw new RuntimeException('The demo dataset is not valid JSON: ' . $path);
		}

		// Counted from the FILE, not the importer's reply, so the number reported
		// is the number ASKED FOR. An object whose schema does not resolve is
		// SKIPPED rather than errored, so a discrepancy here is a real condition
		// an operator should be able to see.
		$objects = 0;
		$components = ($data['components'] ?? []);
		if (is_array($components) === true && is_array(($components['objects'] ?? null)) === true) {
			$objects = count($components['objects']);
		}

		$operator = $this->userSession->getUser();
		if ($operator === null) {
			throw new RuntimeException('Example data is loaded by a signed-in admin, and nobody is signed in.');
		}

		$today = $this->timeFactory->now();
		$data  = $this->prepare(data: $data, operator: $operator->getUID(), today: new DateTimeImmutable($today->format('Y-m-d')));

		$result = $this->configurationService()->importFromApp(
			appId: self::CONFIG_APP_ID,
			data: $data,
			version: $this->appManager->getAppVersion(Application::APP_ID),
			force: true
		);

		$imported = [
			'objects'   => $objects,
			'registers' => count((array)($result['registers'] ?? [])),
			'schemas'   => count((array)($result['schemas'] ?? [])),
		];

		$this->logger->info(
			'[DemoDataService] imported demo data: '
			. $imported['objects'] . ' object(s), '
			. $imported['registers'] . ' register(s), '
			. $imported['schemas'] . ' schema(s).',
			['app' => Application::APP_ID]
		);

		return $imported;
	}//end install()

	/**
	 * Fit the shipped descriptor to the admin who loads it and to the load day.
	 *
	 * Every `@operator` value, alone or inside a list, becomes the admin's uid, so
	 * the example projects are theirs and show up on their pages; no invented
	 * account is written. Every date and date-time moves by the days between the
	 * descriptor's `x-openregister.anchorDate` and today, so a task due three
	 * days after the anchor is due three days after loading. Without an anchor
	 * the dates stay as written.
	 *
	 * @param array<string,mixed> $data     The decoded descriptor.
	 * @param string              $operator The uid of the admin who loads it.
	 * @param DateTimeImmutable   $today    The load day (time of day ignored).
	 *
	 * @return array<string,mixed> The descriptor to import.
	 *
	 * @spec openspec/changes/platform-demo-data/tasks.md#task-2.1
	 */
	public function prepare(array $data, string $operator, DateTimeImmutable $today): array {
		$days   = 0;
		$anchor = ($data['x-openregister']['anchorDate'] ?? null);
		if (is_string($anchor) === true && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor) === 1) {
			$from = new DateTimeImmutable($anchor);
			$to   = new DateTimeImmutable($today->format('Y-m-d'));
			$days = (int)$from->diff($to)->format('%r%a');
		}

		$objects = ($data['components']['objects'] ?? null);
		if (is_array($objects) === true) {
			$data['components']['objects'] = $this->fitValue(value: $objects, operator: $operator, days: $days);
		}

		return $data;
	}//end prepare()

	/**
	 * Replace the placeholder and shift dates in one value, recursing into arrays.
	 *
	 * The `@self` block is left alone: its slug and id are the object's identity.
	 *
	 * @param mixed   $value    The value.
	 * @param string  $operator The admin's uid.
	 * @param integer $days     Days to add to every date.
	 *
	 * @return mixed The fitted value.
	 */
	private function fitValue(mixed $value, string $operator, int $days): mixed {
		if (is_array($value) === true) {
			$out = [];
			foreach ($value as $key => $item) {
				if ($key === '@self') {
					$out[$key] = $item;
					continue;
				}

				$out[$key] = $this->fitValue(value: $item, operator: $operator, days: $days);
			}

			if (array_is_list($out) === true && in_array($operator, $out, true) === true) {
				$out = array_values(array_unique($out, SORT_REGULAR));
			}

			return $out;
		}

		if (is_string($value) === false) {
			return $value;
		}

		if ($value === self::OPERATOR_PLACEHOLDER) {
			return $operator;
		}

		if ($days !== 0 && preg_match('/^(\d{4}-\d{2}-\d{2})(T.*)?$/', $value, $match) === 1) {
			$shifted = (new DateTimeImmutable($match[1]))->modify(sprintf('%+d days', $days))->format('Y-m-d');
			return $shifted . ($match[2] ?? '');
		}

		return $value;
	}//end fitValue()

	/**
	 * Absolute path to the shipped descriptor.
	 *
	 * @return string The path.
	 */
	private function descriptorPath(): string {
		return $this->appManager->getAppPath(Application::APP_ID) . self::DESCRIPTOR;
	}//end descriptorPath()

	/**
	 * OpenRegister's configuration importer.
	 *
	 * 🔴 A CROSS-APP CLASS IS A RUNTIME LOOKUP. OpenRegister may not be installed,
	 * and asking the container for a class from a missing app raises something the
	 * caller cannot act on. Check first and say which app is missing.
	 *
	 * 🔴 THE RETURN TYPE IS `object`, NOT THE CLASS, AND THAT IS THE POINT. Naming
	 * a class from an OPTIONAL app in a native return type makes PHP resolve it
	 * whenever this method returns, so on an instance without OpenRegister the
	 * failure is a TypeError about a class nobody mentioned instead of the
	 * RuntimeException above that names the missing app.
	 *
	 * @return object The importer — an OCA\OpenRegister\Service\ConfigurationService.
	 *
	 * @psalm-return \OCA\OpenRegister\Service\ConfigurationService
	 *
	 * @throws RuntimeException When OpenRegister is not installed.
	 */
	private function configurationService(): object {
		if (in_array('openregister', $this->appManager->getInstalledApps(), true) === false) {
			throw new RuntimeException('Demo data needs OpenRegister, which is not installed.');
		}

		return $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
	}//end configurationService()
}//end class
