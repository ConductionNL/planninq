// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure helpers for the task calendar (planning-calendar): where a task sits,
 * the days of a month grid or a week, and the same tasks as a dated list.
 * Dates are handled as local `YYYY-MM-DD` keys, so a due date never moves a
 * day because of the viewer's time zone.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */

const pad = (n) => String(n).padStart(2, '0')

/**
 * A date as its `YYYY-MM-DD` key: the leading date of an ISO string as
 * written, or the local date of a Date.
 *
 * @param {string|Date|null|undefined} value The date
 * @return {string} The key, or '' when there is no date
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function dateKey(value) {
	if (value instanceof Date) {
		return Number.isNaN(value.getTime()) ? '' : `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
	}
	const match = String(value ?? '').match(/^(\d{4}-\d{2}-\d{2})/)
	return match ? match[1] : ''
}

/**
 * A key as a local Date at midnight.
 *
 * @param {string} key The `YYYY-MM-DD` key
 * @return {Date}
 */
function fromKey(key) {
	const [y, m, d] = key.split('-').map(Number)
	return new Date(y, m - 1, d)
}

/**
 * Where a task sits: on its due date, or on its start date marked as
 * starting when it has no due date; null when it has neither.
 *
 * @param {object} task The task
 * @return {{date: string, starts: boolean}|null}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function placement(task) {
	const due = dateKey(task?.dueDate)
	if (due) {
		return { date: due, starts: false }
	}
	const start = dateKey(task?.startDate)
	return start ? { date: start, starts: true } : null
}

/**
 * The tasks per day key, each day sorted by title.
 *
 * @param {Array<object>} tasks The tasks
 * @return {Map<string, Array<{task: object, starts: boolean}>>}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function tasksByDay(tasks) {
	const days = new Map()
	for (const task of tasks || []) {
		const where = placement(task)
		if (!where) {
			continue
		}
		if (!days.has(where.date)) {
			days.set(where.date, [])
		}
		days.get(where.date).push({ task, starts: where.starts })
	}
	for (const items of days.values()) {
		items.sort((a, b) => String(a.task?.title ?? '').localeCompare(String(b.task?.title ?? '')))
	}
	return days
}

/**
 * The seven day keys of the week (Monday to Sunday) holding a date.
 *
 * @param {string} key A `YYYY-MM-DD` key
 * @return {Array<string>}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function weekDays(key) {
	const day = fromKey(key)
	const monday = new Date(day.getFullYear(), day.getMonth(), day.getDate() - ((day.getDay() + 6) % 7))
	return Array.from({ length: 7 }, (_, i) => dateKey(new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i)))
}

/**
 * The weeks of a month grid, Monday first, padded with the days of the
 * neighbouring months.
 *
 * @param {number} year The year
 * @param {number} month The month, 0 for January
 * @return {Array<Array<{date: string, inMonth: boolean}>>}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function monthWeeks(year, month) {
	const first = dateKey(new Date(year, month, 1))
	const last = dateKey(new Date(year, month + 1, 0))
	const weeks = []
	let week = weekDays(first)
	for (;;) {
		weeks.push(week.map((date) => ({ date, inMonth: date >= first && date <= last })))
		if (week[6] >= last) {
			break
		}
		const next = fromKey(week[6])
		week = weekDays(dateKey(new Date(next.getFullYear(), next.getMonth(), next.getDate() + 1)))
	}
	return weeks
}

/**
 * Move a month forward or back.
 *
 * @param {number} year The year
 * @param {number} month The month, 0 for January
 * @param {number} step Months to move, negative for back
 * @return {{year: number, month: number}}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function shiftMonth(year, month, step) {
	const date = new Date(year, month + step, 1)
	return { year: date.getFullYear(), month: date.getMonth() }
}

/**
 * The tasks between two day keys (inclusive) as a list grouped by date, in
 * date order: the list view of the calendar.
 *
 * @param {Array<object>} tasks The tasks
 * @param {string} from The first day key
 * @param {string} to The last day key
 * @return {Array<{date: string, items: Array<{task: object, starts: boolean}>}>}
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
export function listByDate(tasks, from, to) {
	return [...tasksByDay(tasks).entries()]
		.filter(([date]) => date >= from && date <= to)
		.sort(([a], [b]) => a.localeCompare(b))
		.map(([date, items]) => ({ date, items }))
}
