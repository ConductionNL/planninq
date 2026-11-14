/**
 * The running timer: elapsed time, the minutes a stop books, and when to warn.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */

/** Hours after which a stopped timer is flagged before it is booked. */
export const LONG_TIMER_HOURS = 12

const MINUTE = 60 * 1000
const HOUR = 60 * MINUTE

/**
 * Milliseconds a timer has run, never negative; null when the start is not a date.
 *
 * @param {string} startedAt ISO date-time the timer started.
 * @param {number|Date} [now] The moment to measure at, now when omitted.
 * @return {number|null}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function elapsedMs(startedAt, now = Date.now()) {
	const start = Date.parse(startedAt)
	if (typeof startedAt !== 'string' || Number.isNaN(start)) {
		return null
	}
	return Math.max(0, Number(now) - start)
}

/**
 * The minutes a stop books: the elapsed time rounded up to whole minutes, at least one.
 *
 * @param {string} startedAt ISO date-time the timer started.
 * @param {number|Date} [now] The moment it stopped, now when omitted.
 * @return {number}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function bookedMinutes(startedAt, now = Date.now()) {
	const ms = elapsedMs(startedAt, now) ?? 0
	return Math.max(1, Math.ceil(ms / MINUTE))
}

/**
 * Whether a timer ran longer than LONG_TIMER_HOURS.
 *
 * @param {string} startedAt ISO date-time the timer started.
 * @param {number|Date} [now] The moment it stopped, now when omitted.
 * @return {boolean}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function isLongTimer(startedAt, now = Date.now()) {
	return (elapsedMs(startedAt, now) ?? 0) > LONG_TIMER_HOURS * HOUR
}

/**
 * Whole hours a timer ran, rounded to the nearest hour, for the long-timer warning.
 *
 * @param {string} startedAt ISO date-time the timer started.
 * @param {number|Date} [now] The moment it stopped, now when omitted.
 * @return {number}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function elapsedHours(startedAt, now = Date.now()) {
	return Math.round((elapsedMs(startedAt, now) ?? 0) / HOUR)
}

/**
 * The elapsed time as a clock: H:MM:SS.
 *
 * @param {string} startedAt ISO date-time the timer started.
 * @param {number|Date} [now] The moment to show, now when omitted.
 * @return {string}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 */
export function elapsedClock(startedAt, now = Date.now()) {
	const seconds = Math.floor((elapsedMs(startedAt, now) ?? 0) / 1000)
	const h = Math.floor(seconds / 3600)
	const m = Math.floor((seconds % 3600) / 60)
	const s = seconds % 60
	return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

/**
 * A new running timer on a task, started at `now`.
 *
 * @param {string} task Task UUID.
 * @param {number|Date} [now] The start, now when omitted.
 * @return {{task: string, startedAt: string}}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function newTimer(task, now = Date.now()) {
	return { task, startedAt: new Date(Number(now)).toISOString() }
}

/**
 * What a stop hands the time entry form: the task, the measured minutes, the
 * start and stop, and whether to warn about a long timer.
 *
 * @param {{task: string, startedAt: string}} timer The running timer.
 * @param {number|Date} [now] The moment it stopped, now when omitted.
 * @return {{task: string, minutes: number, startedAt: string, endedAt: string, long: boolean, hours: number}}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function stoppedTimer(timer, now = Date.now()) {
	return {
		task: timer.task,
		minutes: bookedMinutes(timer.startedAt, now),
		startedAt: timer.startedAt,
		endedAt: new Date(Number(now)).toISOString(),
		long: isLongTimer(timer.startedAt, now),
		hours: elapsedHours(timer.startedAt, now),
	}
}
