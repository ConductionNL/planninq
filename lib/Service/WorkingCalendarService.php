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
		foreach ([self::WEEKDAYS_KEY, self::DAYS_KEY] as $key) {
			if (array_key_exists($key, $data) === true) {
				$this->store(key: $key, raw: $data[$key]);
			}
		}
	}//end save()

	/**
	 * Store one submitted key when its value is valid; log and keep the stored value otherwise.
	 *
	 * @param string $key The config key, WEEKDAYS_KEY or DAYS_KEY.
	 * @param mixed  $raw The submitted value.
	 *
	 * @return void
	 */
	private function store(string $key, mixed $raw): void {
		$value = match ($key) {
			self::WEEKDAYS_KEY => $this->normaliseWeekdays(raw: $raw),
			default => $this->normaliseDays(raw: $raw),
		};

		if ($value === null) {
			$this->logger->warning('Planninq: invalid ' . $key . ' value rejected', ['raw' => $raw]);
			return;
		}

		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}//end store()

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
		if ($this->isWeekdayList(days: $days) === false) {
			return null;
		}

		$days = array_values(array_unique($days));
		sort($days);

		return (string)json_encode($days);
	}//end normaliseWeekdays()

	/**
	 * Whether a decoded value is a non-empty list of ISO weekday numbers.
	 *
	 * @param array<mixed>|null $days The decoded value.
	 *
	 * @return bool
	 *
	 * @psalm-assert-if-true list<int<1,7>> $days
	 */
	private function isWeekdayList(?array $days): bool {
		if ($days === null || $days === [] || array_is_list($days) === false) {
			return false;
		}

		return count(array_filter($days, $this->isWeekday(...))) === count($days);
	}//end isWeekdayList()

	/**
	 * Whether a value is an ISO weekday number, 1 (Monday) to 7 (Sunday).
	 *
	 * @param mixed $day The value.
	 *
	 * @return bool
	 */
	private function isWeekday(mixed $day): bool {
		return is_int($day) === true && $day >= 1 && $day <= 7;
	}//end isWeekday()

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

		$entries = array_map($this->entry(...), $days);
		if (in_array(null, $entries, true) === true) {
			return null;
		}

		$clean = array_column($entries, null, 'date');
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
		if (is_array($day) === false || $this->isDate(value: ($day['date'] ?? null)) === false) {
			return null;
		}

		$name = ($day['name'] ?? '');
		if ($this->isName(value: $name) === false) {
			return null;
		}

		return ['date' => $day['date'], 'name' => trim($name)];
	}//end entry()

	/**
	 * Whether a value is a real calendar date written as YYYY-MM-DD.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 *
	 * @psalm-assert-if-true string $value
	 */
	private function isDate(mixed $value): bool {
		if (is_string($value) === false || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
			return false;
		}

		return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
	}//end isDate()

	/**
	 * Whether a value is a day name of at most NAME_MAX characters.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 *
	 * @psalm-assert-if-true string $value
	 */
	private function isName(mixed $value): bool {
		return is_string($value) === true && mb_strlen($value) <= self::NAME_MAX;
	}//end isName()

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
