// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure date sums for editing a task on the timeline (planning-timeline-editing
 * section 2): moving a bar, resizing its ends and the keyboard steps, all in
 * the working calendar of src/utils/workingCalendar.js.
 *
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
import { addWorkingDays, isWorkingDay, nextWorkingDay, workingDaysBetween } from './workingCalendar.js'

const DAY_MS = 86400000

/**
 * A key moved by whole calendar days.
 *
 * @param {string} date The `YYYY-MM-DD` key
 * @param {number} days Days, negative for earlier
 * @return {string}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function shiftDays(date, days) {
	return new Date(Date.parse(`${String(date).slice(0, 10)}T00:00:00Z`) + days * DAY_MS).toISOString().slice(0, 10)
}

/**
 * The working day `count` working days before or after a date; negative
 * counts go back. Count 0 is the date's next working day.
 *
 * @param {string} date The `YYYY-MM-DD` key
 * @param {number} count Working days
 * @param {object} calendar From normaliseCalendar()
 * @return {string}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function stepWorkingDays(date, count, calendar) {
	if (count >= 0) {
		return addWorkingDays(date, count, calendar)
	}
	let day = String(date).slice(0, 10)
	for (let i = 0; i < -count; i++) {
		day = shiftDays(day, -1)
		for (let guard = 0; guard < 3660 && !isWorkingDay(day, calendar); guard++) {
			day = shiftDays(day, -1)
		}
	}
	return day
}

/**
 * A task's length in working days (at least 1).
 *
 * @param {object} task With startDate and dueDate
 * @param {object} calendar From normaliseCalendar()
 * @return {number}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function workingLength(task, calendar) {
	return Math.max(1, workingDaysBetween(String(task.startDate).slice(0, 10), String(task.dueDate).slice(0, 10), calendar))
}

/**
 * The dates of a task whose whole bar starts on a new day: the start lands on
 * a working day and the task keeps its length in working days.
 *
 * @param {object} task With startDate and dueDate
 * @param {string} start The day the bar was dropped on
 * @param {object} calendar From normaliseCalendar()
 * @return {{startDate: string, dueDate: string}}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function moveTo(task, start, calendar) {
	const length = workingLength(task, calendar)
	const startDate = nextWorkingDay(start, calendar)
	return { startDate, dueDate: addWorkingDays(startDate, length - 1, calendar) }
}

/**
 * The dates after dragging one end of the bar. The end never crosses the
 * other one: a start past the due date, or a due date before the start,
 * stops on it.
 *
 * @param {object} task With startDate and dueDate
 * @param {'start'|'due'} edge Which end moved
 * @param {string} date The day it was dropped on
 * @return {{startDate: string, dueDate: string}}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function resizeTo(task, edge, date) {
	const startDate = String(task.startDate).slice(0, 10)
	const dueDate = String(task.dueDate).slice(0, 10)
	if (edge === 'start') {
		return { startDate: date > dueDate ? dueDate : date, dueDate }
	}
	return { startDate, dueDate: date < startDate ? startDate : date }
}

/**
 * The keyboard step: Left or Right moves the task one working day, and with
 * Shift only its due date.
 *
 * @param {object} task With startDate and dueDate
 * @param {number} step 1 for Right, -1 for Left
 * @param {boolean} dueOnly Whether Shift was held
 * @param {object} calendar From normaliseCalendar()
 * @return {{startDate: string, dueDate: string}}
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export function keyStep(task, step, dueOnly, calendar) {
	if (dueOnly) {
		return resizeTo(task, 'due', stepWorkingDays(String(task.dueDate).slice(0, 10), step, calendar))
	}
	return moveTo(task, stepWorkingDays(nextWorkingDay(task.startDate, calendar), step, calendar), calendar)
}
