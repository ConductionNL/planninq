/**
 * Helpers for the timetable wish editor (timetabling-generator, section 3).
 *
 * A period key names one period of the school week grid, as in `wed-5`: the
 * day, a dash and the period number counted from 1. The grid itself is the
 * admin setting `timetable_period_grid` (days and periods with start and end).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

/**
 * The days a grid may use, in week order (TimetableGridService::WEEK_DAYS).
 */
export const WEEK_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

/**
 * The wish kinds that name periods, and the one that needs a limit.
 */
export const PERIOD_KINDS = ['unavailable', 'avoid']
export const LIMIT_KIND = 'maxPerDay'

/**
 * Monday to Friday, eight periods of 50 minutes from 08:30 (TimetableGridService::DEFAULT_GRID).
 */
export const DEFAULT_GRID = {
	days: ['mon', 'tue', 'wed', 'thu', 'fri'],
	periods: [
		{ start: '08:30', end: '09:20' },
		{ start: '09:20', end: '10:10' },
		{ start: '10:10', end: '11:00' },
		{ start: '11:00', end: '11:50' },
		{ start: '11:50', end: '12:40' },
		{ start: '12:40', end: '13:30' },
		{ start: '13:30', end: '14:20' },
		{ start: '14:20', end: '15:10' },
	],
}

/**
 * The key of one period.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {string} day A week day, as in `wed`.
 * @param {number} number The period number, from 1.
 * @return {string} The period key, as in `wed-5`.
 */
export function periodKey(day, number) {
	return `${day}-${number}`
}

/**
 * The day and number of a period key, or null for a key that is not one.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {string} key A period key.
 * @return {{day: string, number: number}|null} The parts.
 */
export function parsePeriodKey(key) {
	const match = /^(mon|tue|wed|thu|fri|sat|sun)-([1-9][0-9]?)$/.exec(String(key ?? ''))
	if (match === null) {
		return null
	}
	return { day: match[1], number: Number(match[2]) }
}

/**
 * The stored grid setting as an object; the default grid when it is missing or unreadable.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {string|object|null} raw The `timetable_period_grid` setting.
 * @return {{days: string[], periods: Array<{start: string, end: string}>}} The grid.
 */
export function readGrid(raw) {
	let grid = raw
	if (typeof raw === 'string') {
		try {
			grid = JSON.parse(raw)
		} catch {
			grid = null
		}
	}
	const days = Array.isArray(grid?.days) ? grid.days.filter((day) => WEEK_DAYS.includes(day)) : []
	const periods = Array.isArray(grid?.periods) ? grid.periods : []
	if (days.length === 0 || periods.length === 0) {
		return DEFAULT_GRID
	}
	return { days, periods }
}

/**
 * The rows of the period picker: one row per period number, one cell per day.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {{days: string[], periods: Array<{start: string, end: string}>}} grid The grid.
 * @param {string[]} selected The chosen period keys.
 * @return {Array<{number: number, start: string, end: string, cells: Array<{key: string, day: string, selected: boolean}>}>} The rows.
 */
export function gridRows(grid, selected = []) {
	const chosen = new Set(selected)
	return grid.periods.map((period, index) => ({
		number: index + 1,
		start: period.start,
		end: period.end,
		cells: grid.days.map((day) => {
			const key = periodKey(day, index + 1)
			return { key, day, selected: chosen.has(key) }
		}),
	}))
}

/**
 * The chosen periods with one key switched on or off, in week order.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {string[]} selected The chosen period keys.
 * @param {string} key The key to switch.
 * @return {string[]} The new selection.
 */
export function togglePeriod(selected, key) {
	const next = selected.includes(key) ? selected.filter((item) => item !== key) : [...selected, key]
	return sortPeriodKeys(next)
}

/**
 * Period keys in week order, then period order; keys that are not period keys are dropped.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {string[]} keys The keys.
 * @return {string[]} The sorted keys.
 */
export function sortPeriodKeys(keys) {
	return keys
		.map((key) => ({ key, parts: parsePeriodKey(key) }))
		.filter((item) => item.parts !== null)
		.sort((a, b) => (WEEK_DAYS.indexOf(a.parts.day) - WEEK_DAYS.indexOf(b.parts.day)) || (a.parts.number - b.parts.number))
		.map((item) => item.key)
}

/**
 * The form a wish is edited in, from a stored wish or empty for a new one.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {object|null} wish A stored wish.
 * @return {object} The form values.
 */
export function wishForm(wish) {
	return {
		appliesTo: wish?.appliesTo ?? 'teacher',
		reference: wish?.reference ?? '',
		kind: wish?.kind ?? 'unavailable',
		periods: sortPeriodKeys(Array.isArray(wish?.periods) ? wish.periods : []),
		limit: wish?.limit ?? null,
		strength: wish?.strength ?? 'soft',
		weight: wish?.weight ?? 1,
		note: wish?.note ?? '',
	}
}

/**
 * What is wrong with the form, as the field and a message key; empty when it can be saved.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {object} form The form values.
 * @return {Array<{field: string, message: string}>} The problems.
 */
export function wishProblems(form) {
	const problems = []
	if (String(form.reference ?? '').trim() === '') {
		problems.push({ field: 'reference', message: 'Say who or what the wish is about.' })
	}
	if (PERIOD_KINDS.includes(form.kind) && form.periods.length === 0) {
		problems.push({ field: 'periods', message: 'Choose at least one period.' })
	}
	if (form.kind === LIMIT_KIND && !(Number.isInteger(Number(form.limit)) && Number(form.limit) >= 1)) {
		problems.push({ field: 'limit', message: 'The limit is a whole number of 1 or more.' })
	}
	return problems
}

/**
 * The object saved for a form: periods only for a kind that names periods, a limit only
 * for "at most a number of lessons a day", and a weight only for a soft wish.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 * @param {object} form The form values.
 * @return {object} The wish to save.
 */
export function wishPayload(form) {
	const payload = {
		appliesTo: form.appliesTo,
		reference: String(form.reference).trim(),
		kind: form.kind,
		periods: PERIOD_KINDS.includes(form.kind) ? sortPeriodKeys(form.periods) : [],
		limit: form.kind === LIMIT_KIND ? Number(form.limit) : null,
		strength: form.strength,
		weight: form.strength === 'soft' ? Math.min(3, Math.max(1, Number(form.weight) || 1)) : null,
	}
	const note = String(form.note ?? '').trim()
	if (note !== '') {
		payload.note = note
	}
	return payload
}
