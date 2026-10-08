/**
 * Timer helpers: how long a running timer has been going, and when to warn.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */

/** A timer running longer than this many hours is probably forgotten. */
export const TIMER_WARNING_HOURS = 12

/**
 * Elapsed minutes of a timer, rounded up to whole minutes and never below one.
 *
 * @param {string}      startedAt ISO 8601 start time.
 * @param {Date|number} [now]     The current time.
 * @return {number}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function elapsedMinutes(startedAt, now = Date.now()) {
	const ms = new Date(now).getTime() - new Date(startedAt).getTime()
	if (!Number.isFinite(ms)) {
		return 1
	}
	return Math.max(1, Math.ceil(ms / 60000))
}

/**
 * Whether the timer has run longer than the warning threshold.
 *
 * @param {string}      startedAt ISO 8601 start time.
 * @param {Date|number} [now]     The current time.
 * @return {boolean}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
export function timerNeedsWarning(startedAt, now = Date.now()) {
	return elapsedMinutes(startedAt, now) > TIMER_WARNING_HOURS * 60
}

/**
 * The admin's work types from the settings value (a JSON list), or an empty list.
 *
 * @param {string|Array|undefined} raw The stored value.
 * @return {Array<string>}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.3
 */
export function workTypesOf(raw) {
	let list = raw
	if (typeof raw === 'string') {
		try {
			list = JSON.parse(raw)
		} catch {
			return []
		}
	}
	return Array.isArray(list) ? list.filter((name) => typeof name === 'string' && name !== '') : []
}
