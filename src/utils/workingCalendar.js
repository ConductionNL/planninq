// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * The app-wide working calendar (planning-timeline-editing): the working
 * weekdays and the listed non-working days from the admin settings, and the
 * date sums the timeline makes with them. Dates are `YYYY-MM-DD` keys read in
 * UTC, as the timeline axis reads them, so a date never shifts with the
 * browser's time zone.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */

const DEFAULT_WEEKDAYS = [1, 2, 3, 4, 5]
const DAY_MS = 86400000

/**
 * A value that may arrive as JSON text or already parsed.
 *
 * @param {string|Array|object|undefined} value The setting
 * @return {Array|object|undefined} The parsed value, or undefined when it does not parse
 */
function parse(value) {
	if (typeof value !== 'string') {
		return value
	}
	try {
		return JSON.parse(value)
	} catch {
		return undefined
	}
}

/**
 * The calendar from the settings, falling back to Monday to Friday and no
 * listed days when a value is malformed.
 *
 * @param {object} settings The settings (working_weekdays, non_working_days)
 * @return {{weekdays: Array<number>, holidays: Map<string, string>}}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function normaliseCalendar(settings = {}) {
	const weekdays = parse(settings?.working_weekdays)
	const days = parse(settings?.non_working_days)
	const valid = Array.isArray(weekdays) && weekdays.length > 0 && weekdays.every((day) => Number.isInteger(day) && day >= 1 && day <= 7)
	const holidays = new Map()
	for (const day of Array.isArray(days) ? days : []) {
		if (day && /^\d{4}-\d{2}-\d{2}$/.test(String(day.date))) {
			holidays.set(day.date, String(day.name ?? ''))
		}
	}
	return { weekdays: valid ? weekdays : DEFAULT_WEEKDAYS, holidays }
}

/**
 * A key as a UTC timestamp.
 *
 * @param {string} key The `YYYY-MM-DD` key
 * @return {number}
 */
function time(key) {
	return Date.parse(`${String(key).slice(0, 10)}T00:00:00Z`)
}

/**
 * A UTC timestamp as a key.
 *
 * @param {number} ms The timestamp
 * @return {string}
 */
function key(ms) {
	return new Date(ms).toISOString().slice(0, 10)
}

/**
 * Whether a day is a working day: a working weekday and not listed.
 *
 * @param {string} date The `YYYY-MM-DD` key
 * @param {object} calendar From normaliseCalendar()
 * @return {boolean}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function isWorkingDay(date, calendar) {
	const day = String(date).slice(0, 10)
	const iso = ((new Date(time(day)).getUTCDay() + 6) % 7) + 1
	return calendar.weekdays.includes(iso) && !calendar.holidays.has(day)
}

/**
 * The day itself when it is a working day, else the next one.
 *
 * @param {string} date The `YYYY-MM-DD` key
 * @param {object} calendar From normaliseCalendar()
 * @return {string}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function nextWorkingDay(date, calendar) {
	let ms = time(date)
	for (let i = 0; i < 3660 && !isWorkingDay(key(ms), calendar); i++) {
		ms += DAY_MS
	}
	return key(ms)
}

/**
 * The working day `count` working days after a date (the date's next working
 * day when count is 0).
 *
 * @param {string} date The `YYYY-MM-DD` key
 * @param {number} count Working days to add, 0 or more
 * @param {object} calendar From normaliseCalendar()
 * @return {string}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function addWorkingDays(date, count, calendar) {
	let day = nextWorkingDay(date, calendar)
	for (let i = 0; i < count; i++) {
		day = nextWorkingDay(key(time(day) + DAY_MS), calendar)
	}
	return day
}

/**
 * The working days from one date to another, both included; 0 when the end
 * is before the start.
 *
 * @param {string} from The first `YYYY-MM-DD` key
 * @param {string} to The last `YYYY-MM-DD` key
 * @param {object} calendar From normaliseCalendar()
 * @return {number}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function workingDaysBetween(from, to, calendar) {
	let count = 0
	for (let ms = time(from); ms <= time(to); ms += DAY_MS) {
		if (isWorkingDay(key(ms), calendar)) {
			count++
		}
	}
	return count
}

/**
 * Easter Sunday of a year (anonymous Gregorian algorithm), as a UTC timestamp.
 *
 * @param {number} year The year
 * @return {number}
 */
function easter(year) {
	const a = year % 19
	const b = Math.floor(year / 100)
	const c = year % 100
	const d = Math.floor(b / 4)
	const e = b % 4
	const f = Math.floor((b + 8) / 25)
	const g = Math.floor((b - f + 1) / 3)
	const h = (19 * a + b - d - g + 15) % 30
	const i = Math.floor(c / 4)
	const k = c % 4
	const l = (32 + 2 * e + 2 * i - h - k) % 7
	const m = Math.floor((a + 11 * h + 22 * l) / 451)
	const month = Math.floor((h + l - 7 * m + 114) / 31)
	const day = ((h + l - 7 * m + 114) % 31) + 1
	return Date.UTC(year, month - 1, day)
}

/**
 * The Dutch national holidays of a year, in date order, each named through
 * `translate` (the caller passes t() so the names land in the admin's language).
 *
 * @param {number} year The year
 * @param {function(string): string} translate English name to the name to store
 * @return {Array<{date: string, name: string}>}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
export function dutchHolidays(year, translate = (name) => name) {
	const sunday = easter(year)
	const kingsDay = new Date(Date.UTC(year, 3, 27)).getUTCDay() === 0 ? Date.UTC(year, 3, 26) : Date.UTC(year, 3, 27)
	const days = [
		[Date.UTC(year, 0, 1), 'New Year\'s Day'],
		[sunday, 'Easter Sunday'],
		[sunday + DAY_MS, 'Easter Monday'],
		[kingsDay, 'King\'s Day'],
		[Date.UTC(year, 4, 5), 'Liberation Day'],
		[sunday + 39 * DAY_MS, 'Ascension Day'],
		[sunday + 49 * DAY_MS, 'Whit Sunday'],
		[sunday + 50 * DAY_MS, 'Whit Monday'],
		[Date.UTC(year, 11, 25), 'Christmas Day'],
		[Date.UTC(year, 11, 26), 'Boxing Day'],
	]
	return days
		.sort((x, y) => x[0] - y[0])
		.map(([ms, name]) => ({ date: key(ms), name: translate(name) }))
}
