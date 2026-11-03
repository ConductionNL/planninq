// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Auto-scheduling on the timeline (planning-timeline-editing section 3): when
 * a task's due date slips, the tasks it blocks move later, transitively, in
 * working days. Forward only, `blocks` links only (a link without a type is
 * `blocks`, the schema default).
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
import { addWorkingDays, nextWorkingDay, workingDaysBetween } from './workingCalendar.js'

const day = (value) => String(value ?? '').slice(0, 10)

/**
 * The state of one cascade run: tasks by id with their dates so far, the
 * `blocks` edges, the moves found, and the tasks still to look past.
 *
 * @param {{tasks: Array<object>, edges: Array<object>, calendar: object}} plan The project's tasks, edges and calendar
 * @param {{id: string, dates: {startDate: string, dueDate: string}}} change The changed task and its new dates
 * @return {object|null} The run, or null when the task is unknown
 */
function startRun(plan, change) {
	const byId = new Map((plan.tasks || []).map((task) => [task.id, { ...task }]))
	if (!byId.has(change.id)) {
		return null
	}
	byId.set(change.id, { ...byId.get(change.id), ...change.dates })
	const blocking = (plan.edges || []).filter((edge) => (edge.type || 'blocks') === 'blocks')
	return { byId, blocking, calendar: plan.calendar, moves: new Map(), queue: [change.id] }
}

/**
 * The dates a task moves to when it has to start after a due date, keeping
 * its length in working days.
 *
 * @param {object} task The task to move
 * @param {{due: string, calendar: object}} after The due date it must follow, and the calendar
 * @return {{startDate: string, dueDate: string}}
 */
function shiftedDates(task, after) {
	const length = Math.max(1, workingDaysBetween(day(task.startDate), day(task.dueDate), after.calendar))
	const startDate = nextWorkingDay(addWorkingDays(after.due, 1, after.calendar), after.calendar)
	return { startDate, dueDate: addWorkingDays(startDate, length - 1, after.calendar) }
}

/**
 * Record that a task moves, keeping its first dates as `from`, and queue it
 * so the tasks it blocks are checked in turn.
 *
 * @param {object} run From startRun()
 * @param {{task: object, to: {startDate: string, dueDate: string}}} move The task and its new dates
 */
function recordMove(run, move) {
	const { task, to } = move
	if (!run.moves.has(task.id)) {
		run.moves.set(task.id, { id: task.id, title: task.title, from: { startDate: day(task.startDate), dueDate: day(task.dueDate) }, to })
	} else {
		run.moves.get(task.id).to = to
	}
	run.byId.set(task.id, { ...task, ...to })
	run.queue.push(task.id)
}

/**
 * Move every task a blocker blocks that does not yet start after its due date.
 *
 * @param {object} run From startRun()
 * @param {object} blocker The blocking task, with its current dates
 */
function pushBlocked(run, blocker) {
	const due = day(blocker.dueDate)
	for (const edge of run.blocking.filter((link) => link.blocker === blocker.id)) {
		const next = run.byId.get(edge.blocked)
		if (!next || !day(next.startDate) || !day(next.dueDate) || !due) {
			continue
		}
		if (day(next.startDate) > due) {
			continue
		}
		recordMove(run, { task: next, to: shiftedDates(next, { due, calendar: run.calendar }) })
	}
}

/**
 * The tasks a changed task pushes, in the order to write them.
 *
 * @param {{tasks: Array<object>, edges: Array<object>, calendar: object}} plan The project's tasks (id, title, startDate, dueDate), dependency edges (blocker, blocked, type) and calendar from normaliseCalendar()
 * @param {{id: string, dates: {startDate: string, dueDate: string}}} change The task whose dates changed, and its new dates
 * @return {Array<{id: string, title: string, from: object, to: object}>}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
export function cascade(plan, change) {
	const run = startRun(plan, change)
	if (run === null) {
		return []
	}
	for (let guard = 0; run.queue.length > 0 && guard < 10000; guard++) {
		pushBlocked(run, run.byId.get(run.queue.shift()))
	}
	return [...run.moves.values()]
}

/**
 * Write a run of moves one at a time, stopping at the first failure.
 *
 * @param {Array<{id: string, to: object}>} moves The moves, in order
 * @param {function(string, object): Promise<object|null>} write Writes one task's dates; a truthy result is success
 * @return {Promise<{written: number, failed: object|null}>}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
 */
export async function writeRun(moves, write) {
	let written = 0
	for (const move of moves) {
		const result = await write(move.id, move.to)
		if (!result) {
			return { written, failed: move }
		}
		written++
	}
	return { written, failed: null }
}
