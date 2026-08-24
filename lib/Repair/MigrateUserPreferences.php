<?php

/**
 * Planninq Migrate User Preferences Repair Step
 *
 * Repair step that carries this app's per-user preferences across the
 * `planix` -> `planninq` app-id rename.
 *
 * WHY THIS EXISTS SEPARATELY FROM MigrateAppConfigKeys. `IAppConfig` and
 * `IConfig`'s user values are different stores: the former is `oc_appconfig`,
 * the latter `oc_preferences`. Both are namespaced by app id, so both are cut
 * off by the rename, but copying one does nothing for the other.
 *
 * WHY IT MATTERS MORE THAN IT LOOKS. The only per-user key this app stores is
 * `notify_due_reminder`, and `SettingsService::isDueReminderEnabled()` reads it
 * with a default of `'true'`. So a user who explicitly turned due reminders OFF
 * has `'false'` stored under the OLD app id; after the rename the lookup finds
 * nothing, falls back to the default, and that user silently starts receiving
 * the reminders they opted out of. The failure is invisible — no error, no log,
 * just a preference quietly reverting — which is exactly why it needs a
 * migration rather than a release note.
 *
 * WHY IT ENUMERATES BY VALUE RATHER THAN BY USER. `IConfig` offers no "list
 * every key this app stored for every user" call. It does offer
 * `getUsersForUserValue(app, key, value)`, so the step asks for both concrete
 * values of a boolean flag. That is exhaustive for this key by construction:
 * anything not `'true'` or `'false'` was never written by this app, and a user
 * with nothing stored needs no migration because the default already applies.
 * If a future release adds another per-user key, it must be added to
 * `MIGRATED_KEYS` — hence the assertion-by-comment there.
 *
 * SAFETY. Idempotent and non-destructive, matching MigrateAppConfigKeys:
 *   - a value is copied only when the user has nothing stored under the new
 *     app id, so a preference changed after the rename is never clobbered and
 *     a second run is a no-op;
 *   - the old `planix` rows are never deleted, so a rollback still finds them;
 *   - every failure is logged and the loop continues, because one unreadable
 *     preference is not worth aborting an install over.
 *
 * Registered under BOTH `<install>` and `<post-migration>` in
 * `appinfo/info.xml` alongside MigrateAppConfigKeys — see the ordering comment
 * there. Unlike the app-config step this one has no ordering relationship with
 * InitializeSettings, which never writes user values.
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

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Copy per-user preferences from the planix app id to planninq.
 *
 * @spec openspec/specs/app-metadata/spec.md
 */
class MigrateUserPreferences implements IRepairStep {

	private const OLD_APP_ID = 'planix';

	private const NEW_APP_ID = 'planninq';

	/**
	 * Every per-user key this app has ever written, with the values it can
	 * hold. Add to this list when a new per-user preference is introduced;
	 * a key missing here is a key that silently resets on the next rename.
	 */
	private const MIGRATED_KEYS = ['notify_due_reminder' => ['true', 'false']];

	/**
	 * Constructor.
	 *
	 * @param IConfig         $config The user-value store to read and write.
	 * @param LoggerInterface $logger Logger for preferences that fail to copy.
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function __construct(
		private IConfig $config,
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
		return 'Copy planninq per-user preferences from the planix app id';
	}//end getName()

	/**
	 * Copy every known per-user preference from the old app id to the new one.
	 *
	 * @param IOutput $output Repair output channel.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function run(IOutput $output): void {
		$migrated = 0;
		$alreadyPresent = 0;

		foreach (self::MIGRATED_KEYS as $key => $values) {
			foreach ($values as $value) {
				$userIds = $this->config->getUsersForUserValue(self::OLD_APP_ID, $key, $value);
				foreach ($userIds as $userId) {
					try {
						$existing = $this->config->getUserValue($userId, self::NEW_APP_ID, $key, '');
						if ($existing !== '') {
							$alreadyPresent++;
							continue;
						}

						$this->config->setUserValue($userId, self::NEW_APP_ID, $key, $value);
						$migrated++;
					} catch (\Throwable $e) {
						$this->logger->warning(
							'MigrateUserPreferences: could not migrate one preference; leaving it under the old app id.',
							['exception' => $e, 'key' => $key, 'app' => self::NEW_APP_ID]
						);
					}
				}
			}
		}

		if ($migrated === 0 && $alreadyPresent === 0) {
			$output->info('MigrateUserPreferences: no stored planix user preferences on this install; nothing to do.');
			return;
		}

		$output->info(
			sprintf(
				'MigrateUserPreferences: migrated %d preference(s); %d already set under planninq.',
				$migrated,
				$alreadyPresent
			)
		);
	}//end run()

}//end class
