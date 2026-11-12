<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * Planninq Migrate App Config Keys Repair Step
 *
 * Repair step that carries this app's stored `IAppConfig` values across the
 * `planix` -> `planninq` app-id rename.
 *
 * Nextcloud namespaces `IAppConfig` by app id at the storage layer
 * (`oc_appconfig.appid`), so renaming `<id>` does not rename the rows — it
 * makes every previously stored value unreachable, because the app now asks
 * for them under a different app id. There is no in-place app-id upgrade in
 * Nextcloud: the new id is simply a different app. This step therefore copies
 * each value from the old namespace to the new one.
 *
 * WHY EVERY KEY RATHER THAN A FIXED LIST. `SettingsService` writes its admin
 * keys from `ADMIN_CONFIG_DEFAULTS`, but that is not the whole stored set —
 * `RegisterImportService` records the imported register/schema ids, and past
 * releases have written keys this app no longer reads. Enumerating
 * `IAppConfig::getKeys()` is exhaustive by construction and cannot drift out
 * of date the way a hardcoded list does.
 *
 * SAFETY. Idempotent and non-destructive:
 *   - a key is copied only when the old value is non-empty AND the new
 *     namespace does not already hold a value, so an admin edit made after the
 *     rename is never clobbered and a second run is a no-op;
 *   - the old `planix` rows are never deleted, so a rollback to the previous
 *     app id still finds its configuration intact;
 *   - values round-trip as raw strings. `IAppConfig` stores every value as a
 *     string and the typed accessors only coerce on read, so a string
 *     round-trip cannot lose or corrupt a value written by a typed setter;
 *   - every failure is logged and the loop continues. A repair step that
 *     throws aborts the install, and a config value that could not be copied
 *     is not worth failing an install over — the app falls back to its
 *     defaults and the admin can re-enter the setting.
 *
 * Registered under BOTH `<install>` and `<post-migration>` in
 * `appinfo/info.xml`, and before `InitializeSettings` in both — see the
 * ordering comment there.
 *
 * @category Repair
 * @package  OCA\Planninq\Repair
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/app-metadata/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Copy every stored IAppConfig value from the planix namespace to planninq.
 *
 * @spec openspec/specs/app-metadata/spec.md
 */
class MigrateAppConfigKeys implements IRepairStep {
	/**
	 * The app-config namespace this app used before the rename.
	 *
	 * Deliberately the OLD app id. This constant is the one place in the app
	 * that is supposed to still say `planix`.
	 *
	 * @var string
	 */
	private const OLD_APP_ID = 'planix';

	/**
	 * The app-config namespace this app uses after the rename.
	 *
	 * @var string
	 */
	private const NEW_APP_ID = 'planninq';

	/**
	 * Config keys Nextcloud owns for every app. These MUST NOT be copied.
	 *
	 * `AppManager::enableApp()` writes `enabled` through the deprecated
	 * `IAppConfig::setValue()`, which stores type MIXED. Copying it here with
	 * `setValueString()` stores type STRING, and the next `app:enable` then
	 * fails with an `AppConfigTypeConflictException` — permanently, because the
	 * conflict is hit before the app can run anything that would repair it.
	 * `installed_version` and `types` are Nextcloud's own bookkeeping for the
	 * app and copying the old app's values would misreport the new one.
	 *
	 * @var string[]
	 */
	private const RESERVED_KEYS = [
		'enabled',
		'installed_version',
		'types',
	];

	/**
	 * Constructor for MigrateAppConfigKeys.
	 *
	 * @param IAppConfig $appConfig The app config interface
	 * @param LoggerInterface $logger The logger interface
	 *
	 * @return void
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Get the name of this repair step.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function getName(): string {
		return 'Copy Planninq app configuration from the planix namespace to planninq';
	}//end getName()

	/**
	 * Run the repair step to migrate the stored app configuration.
	 *
	 * @param IOutput $output The output interface for progress reporting
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function run(IOutput $output): void {
		$keys = $this->oldKeys();
		if ($keys === []) {
			$output->info('MigrateAppConfigKeys: no stored planix configuration on this install; nothing to do.');
			return;
		}

		$counts = ['migrated' => 0, 'present' => 0, 'empty' => 0, 'reserved' => 0, 'failed' => 0];
		foreach ($keys as $key) {
			$counts[$this->migrateKey(key: $key)]++;
		}

		$output->info(self::summary(counts: $counts));
	}//end run()

	/**
	 * The output line that sums up a run.
	 *
	 * @param array<string,int> $counts Keys per outcome.
	 *
	 * @return string
	 */
	private static function summary(array $counts): string {
		return 'MigrateAppConfigKeys: ' . $counts['migrated'] . ' key(s) migrated, ' . $counts['present']
			. ' already present, ' . $counts['empty'] . ' had no value to migrate, '
			. $counts['reserved'] . ' skipped as Nextcloud-reserved.';
	}//end summary()

	/**
	 * Copy one key from the old app id to the new one, unless it is reserved,
	 * empty under the old id, or already set under the new one.
	 *
	 * @param string $key The app config key.
	 *
	 * @return string The outcome: migrated, present, empty, reserved or failed.
	 */
	private function migrateKey(string $key): string {
		if (in_array($key, self::RESERVED_KEYS, strict: true) === true) {
			return 'reserved';
		}

		$old = $this->appConfig->getValueString(self::OLD_APP_ID, $key, '');
		if ($old === '') {
			return 'empty';
		}

		if ($this->appConfig->getValueString(self::NEW_APP_ID, $key, '') !== '') {
			return 'present';
		}

		return $this->copyKey(key: $key, value: $old);
	}//end migrateKey()

	/**
	 * Write one value under the new app id; a failed write is logged and the
	 * key stays under the old namespace.
	 *
	 * @param string $key   The app config key.
	 * @param string $value The value stored under the old app id.
	 *
	 * @return string `migrated`, or `failed`.
	 */
	private function copyKey(string $key, string $value): string {
		try {
			$this->appConfig->setValueString(self::NEW_APP_ID, $key, $value);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Planninq: could not migrate one app config key; leaving it under the old namespace',
				['key' => $key, 'exception' => $e->getMessage()]
			);
			return 'failed';
		}

		return 'migrated';
	}//end copyKey()

	/**
	 * Every key currently stored under the old app-config namespace.
	 *
	 * @return array<int, string> The stored key names, empty when unreadable
	 */
	private function oldKeys(): array {
		try {
			return $this->appConfig->getKeys(self::OLD_APP_ID);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Planninq: could not enumerate planix app config keys; skipping the migration',
				['exception' => $e->getMessage()]
			);
			return [];
		}//end try
	}//end oldKeys()
}//end class
