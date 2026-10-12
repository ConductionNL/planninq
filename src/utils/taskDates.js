/**
 * Task date helpers: reading a stored `YYYY-MM-DD` as a local calendar day,
 * and the start-before-due check shared by the task page and the task dialog.
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-1.1
 */

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/

/**
 * Parse a stored date as the viewer's local date.
 *
 * A date-only string keeps its calendar day in every time zone; anything
 * else (a full timestamp, a Date) is read as given.
 *
 * @param {string|Date|null|undefined} raw The stored value.
 * @return {Date|null} The date, or null when empty or unreadable.
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-1.1
 */
export function parseLocalDate(raw) {
	if (raw === null || raw === undefined || raw === '') {
		return null
	}
	if (raw instanceof Date) {
		return Number.isNaN(raw.getTime()) ? null : raw
	}
	const match = DATE_ONLY.exec(String(raw))
	const date = match
		? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
		: new Date(raw)
	return Number.isNaN(date.getTime()) ? null : date
}

/**
 * Check that the start date is not after the due date.
 *
 * @param {string|null} startDate The start date (`YYYY-MM-DD`) or empty.
 * @param {string|null} dueDate   The due date (`YYYY-MM-DD`) or empty.
 * @return {string|null} `'startAfterDue'` when the order is wrong, otherwise null.
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-1.2
 */
export function validateTaskDates(startDate, dueDate) {
	if (!startDate || !dueDate) {
		return null
	}
	return startDate > dueDate ? 'startAfterDue' : null
}

/**
 * Turn a stored `YYYY-MM-DD` into the Date a date picker takes.
 *
 * @param {string|null} stored The stored date.
 * @return {Date|null}
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-2.1
 */
export function toPickerDate(stored) {
	return parseLocalDate(stored)
}

/**
 * Turn a picked Date into the stored `YYYY-MM-DD` (no time), or '' when cleared.
 *
 * @param {Date|null} picked The picked date.
 * @return {string}
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-2.1
 */
export function fromPickerDate(picked) {
	if (!(picked instanceof Date) || Number.isNaN(picked.getTime())) {
		return ''
	}
	const pad = (n) => String(n).padStart(2, '0')
	return `${picked.getFullYear()}-${pad(picked.getMonth() + 1)}-${pad(picked.getDate())}`
}
