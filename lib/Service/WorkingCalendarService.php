<?php

/**
 * Planninq WorkingCalendarService
 *
 * The app-wide working calendar (planning-timeline-editing): the working
 * weekdays and the listed non-working days, two admin keys that every signed-in
 * user reads through GET /api/settings, so the timeline computes dates in
 * working days. Kept out of SettingsService, which is at phpmd's complexity
 * and coupling limits, the way TimetableGridService is.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Reads, validates and stores the working weekdays and non-working days.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
 */
class WorkingCalendarService {

	/**
	 * The working weekdays, a JSON list of ISO weekday numbers (1 Monday to 7 Sunday).
	 */
	public const WEEKDAYS_KEY = 'working_weekdays';

	/**
	 * The non-working days, a JSON list of {date: YYYY-MM-DD, name}.
	 */
	public const DAYS_KEY = 'non_working_days';

	/**
	 * Monday to Friday.
	 */
	public const DEFAULT_WEEKDAYS = '[1,2,3,4,5]';

	/**
	 * The longest name a non-working day may carry.
	 */
	private const NAME_MAX = 100;

	/**
	 * Constructor.
	 *
	 * @param IAppConfig      $appConfig The app config.
	 * @param LoggerInterface $logger    Logs a rejected value.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Both keys, as stored or their defaults.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
	 */
	public function settings(): array {
		return [
			self::WEEKDAYS_KEY => $this->appConfig->getValueString(Application::APP_ID, self::WEEKDAYS_KEY, self::DEFAULT_WEEKDAYS),
			self::DAYS_KEY     => $this->appConfig->getValueString(Application::APP_ID, self::DAYS_KEY, '[]'),
		];
	}//end settings()

	/**
	 * Store the keys present in an admin settings write; a malformed value is logged and not stored.
	 *
	 * @param array<string,mixed> $data The posted settings.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
	 */
	public function save(array $data): void {
		$checks = [
			self::WEEKDAYS_KEY => fn (mixed $raw): ?string => $this->normaliseWeekdays(raw: $raw),
			self::DAYS_KEY     => fn (mixed $raw): ?string => $this->normaliseDays(raw: $raw),
		];
		foreach ($checks as $key => $check) {
			if (array_key_exists($key, $data) === false) {
				continue;
			}

			$value = $check($data[$key]);
			if ($value === null) {
				$this->logger->warning('Planninq: invalid ' . $key . ' value rejected', ['raw' => $data[$key]]);
				continue;
			}

			$this->appConfig->setValueString(Application::APP_ID, $key, $value);
		}
	}//end save()

	/**
	 * The weekdays as a sorted JSON list, or null when malformed.
	 *
	 * @param mixed $raw JSON text or a list.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
	 */
	public function normaliseWeekdays(mixed $raw): ?string {
		$days = $this->decode(raw: $raw);
		if ($days === null || $days === [] || array_is_list($days) === false) {
			return null;
		}

		foreach ($days as $day) {
			if (is_int($day) === false || $day < 1 || $day > 7) {
				return null;
			}
		}

		$days = array_values(array_unique($days));
		sort($days);

		return (string)json_encode($days);
	}//end normaliseWeekdays()

	/**
	 * The non-working days as a JSON list sorted by date, or null when malformed.
	 *
	 * @param mixed $raw JSON text or a list.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
	 */
	public function normaliseDays(mixed $raw): ?string {
		$days = $this->decode(raw: $raw);
		if ($days === null || array_is_list($days) === false) {
			return null;
		}

		$clean = [];
		foreach ($days as $day) {
			$entry = $this->entry(day: $day);
			if ($entry === null) {
				return null;
			}

			$clean[$entry['date']] = $entry;
		}

		ksort($clean);

		return (string)json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
	}//end normaliseDays()

	/**
	 * One non-working day, or null when it is not a real date with a short name.
	 *
	 * @param mixed $day The entry.
	 *
	 * @return array{date: string, name: string}|null
	 */
	private function entry(mixed $day): ?array {
		if (is_array($day) === false || is_string($day['date'] ?? null) === false) {
			return null;
		}

		$date = $day['date'];
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1 || checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) === false) {
			return null;
		}

		$name = $day['name'] ?? '';
		if (is_string($name) === false || mb_strlen($name) > self::NAME_MAX) {
			return null;
		}

		return ['date' => $date, 'name' => trim($name)];
	}//end entry()

	/**
	 * JSON text or an array as an array, or null.
	 *
	 * @param mixed $raw The value.
	 *
	 * @return array<mixed>|null
	 */
	private function decode(mixed $raw): ?array {
		if (is_string($raw) === true) {
			$raw = json_decode($raw, true);
		}

		if (is_array($raw) === false) {
			return null;
		}

		return $raw;
	}//end decode()
}//end class
