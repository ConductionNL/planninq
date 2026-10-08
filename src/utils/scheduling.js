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
 * The tasks a changed task pushes, in the order to write them.
 *
 * @param {Array<object>} tasks The project's tasks (id, title, startDate, dueDate)
 * @param {Array<object>} edges The dependency edges (blocker, blocked, type)
 * @param {string} changedId The task whose dates changed
 * @param {{startDate: string, dueDate: string}} changed Its new dates
 * @param {object} calendar From normaliseCalendar()
 * @return {Array<{id: string, title: string, from: object, to: object}>}
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
export function cascade(tasks, edges, changedId, changed, calendar) {
	const byId = new Map((tasks || []).map((task) => [task.id, { ...task }]))
	if (!byId.has(changedId)) {
		return []
	}
	byId.set(changedId, { ...byId.get(changedId), ...changed })
	const blocking = (edges || []).filter((edge) => (edge.type || 'blocks') === 'blocks')
	const moves = new Map()
	const queue = [changedId]
	for (let guard = 0; queue.length > 0 && guard < 10000; guard++) {
		const blocker = byId.get(queue.shift())
		const due = day(blocker.dueDate)
		for (const edge of blocking.filter((link) => link.blocker === blocker.id)) {
			const next = byId.get(edge.blocked)
			if (!next || !day(next.startDate) || !day(next.dueDate) || !due) {
				continue
			}
			if (day(next.startDate) > due) {
				continue
			}
			const length = Math.max(1, workingDaysBetween(day(next.startDate), day(next.dueDate), calendar))
			const startDate = nextWorkingDay(addWorkingDays(due, 1, calendar), calendar)
			const to = { startDate, dueDate: addWorkingDays(startDate, length - 1, calendar) }
			if (!moves.has(next.id)) {
				moves.set(next.id, { id: next.id, title: next.title, from: { startDate: day(next.startDate), dueDate: day(next.dueDate) }, to })
			} else {
				moves.get(next.id).to = to
			}
			byId.set(next.id, { ...next, ...to })
			queue.push(next.id)
		}
	}
	return [...moves.values()]
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
