/**
 * My tasks and the personal project order (portfolio-my-work-dashboard).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { filterMyTasks, groupMyTasks, isMine, movePinned, orderProjects, togglePin } from '../../src/utils/myWork.js'

// Wednesday 2026-10-07: the week runs to Sunday 2026-10-11.
const TODAY = new Date('2026-10-07T09:00:00')

describe('isMine', () => {
	it('is true for the person responsible and for people it is shared with', () => {
		expect(isMine({ assignedTo: 'anna' }, 'anna')).toBe(true)
		expect(isMine({ assignedTo: 'bram', sharedWith: ['anna'] }, 'anna')).toBe(true)
		expect(isMine({ assignedTo: 'bram' }, 'anna')).toBe(false)
		expect(isMine({}, '')).toBe(false)
	})
})

describe('groupMyTasks', () => {
	const tasks = [
		{ id: 'a', title: 'Overdue normal', status: 'open', dueDate: '2026-10-01', priority: 'normal' },
		{ id: 'b', title: 'Overdue urgent', status: 'in_progress', dueDate: '2026-10-06', priority: 'urgent' },
		{ id: 'c', title: 'Friday', status: 'open', dueDate: '2026-10-09', priority: 'low' },
		{ id: 'd', title: 'Today high', status: 'blocked', dueDate: '2026-10-07', priority: 'high' },
		{ id: 'e', title: 'Next week', status: 'open', dueDate: '2026-10-12' },
		{ id: 'f', title: 'No date', status: 'open', priority: 'urgent' },
		{ id: 'g', title: 'Done', status: 'done', dueDate: '2026-10-01' },
		{ id: 'h', title: 'Cancelled', status: 'cancelled' },
	]

	it('puts open tasks under Overdue, Due this week and Later, and leaves closed ones out', () => {
		const groups = groupMyTasks(tasks, TODAY)
		expect(groups.map((g) => [g.id, g.tasks.map((t) => t.id)])).toEqual([
			['overdue', ['b', 'a']],
			['week', ['d', 'c']],
			['later', ['f', 'e']],
		])
	})

	it('sorts each group from urgent to low, then by due date', () => {
		const groups = groupMyTasks([
			{ id: '1', status: 'open', dueDate: '2026-10-10', priority: 'high' },
			{ id: '2', status: 'open', dueDate: '2026-10-08', priority: 'high' },
			{ id: '3', status: 'open', dueDate: '2026-10-08' },
		], TODAY)
		expect(groups[1].tasks.map((t) => t.id)).toEqual(['2', '1', '3'])
	})
})

describe('filterMyTasks', () => {
	const tasks = [
		{ id: 'mine-open', assignedTo: 'anna', status: 'open', dueDate: '2026-10-01' },
		{ id: 'mine-progress', assignedTo: 'anna', status: 'in_progress' },
		{ id: 'shared-open', assignedTo: 'bram', sharedWith: ['anna'], status: 'open', dueDate: '2026-10-01' },
		{ id: 'done-today', assignedTo: 'anna', status: 'done', completedAt: '2026-10-07T08:15:00+02:00' },
		{ id: 'done-before', assignedTo: 'anna', status: 'done', completedAt: '2026-10-06T16:00:00+02:00' },
		{ id: 'other', assignedTo: 'carl', status: 'open' },
	]

	it('lists open tasks assigned to me or shared with me when no figure is chosen', () => {
		expect(filterMyTasks(tasks, 'anna', '', TODAY).map((t) => t.id)).toEqual(['mine-open', 'mine-progress', 'shared-open'])
	})

	it('narrows to the tasks a dashboard figure counts: the ones I am responsible for', () => {
		expect(filterMyTasks(tasks, 'anna', 'open', TODAY).map((t) => t.id)).toEqual(['mine-open', 'mine-progress'])
		expect(filterMyTasks(tasks, 'anna', 'overdue', TODAY).map((t) => t.id)).toEqual(['mine-open'])
		expect(filterMyTasks(tasks, 'anna', 'in_progress', TODAY).map((t) => t.id)).toEqual(['mine-progress'])
		expect(filterMyTasks(tasks, 'anna', 'completed_today', TODAY).map((t) => t.id)).toEqual(['done-today'])
	})
})

describe('the personal project order', () => {
	const projects = [{ id: 'p1', title: 'Beta' }, { id: 'p2', title: 'Alpha' }, { id: 'p3', title: 'Gamma' }]

	it('lists pinned projects first in the stored order, then the rest as given', () => {
		expect(orderProjects(projects, ['p3', 'gone', 'p2']).map((p) => p.id)).toEqual(['p3', 'p2', 'p1'])
		expect(orderProjects(projects, []).map((p) => p.id)).toEqual(['p1', 'p2', 'p3'])
	})

	it('pins at the end of the pinned list and unpins', () => {
		expect(togglePin(['p3'], 'p1')).toEqual(['p3', 'p1'])
		expect(togglePin(['p3', 'p1'], 'p3')).toEqual(['p1'])
	})

	it('moves a pinned project up or down and stops at the ends', () => {
		expect(movePinned(['p1', 'p2', 'p3'], 'p3', -1)).toEqual(['p1', 'p3', 'p2'])
		expect(movePinned(['p1', 'p2', 'p3'], 'p1', 1)).toEqual(['p2', 'p1', 'p3'])
		expect(movePinned(['p1', 'p2'], 'p1', -1)).toEqual(['p1', 'p2'])
		expect(movePinned(['p1', 'p2'], 'p9', 1)).toEqual(['p1', 'p2'])
	})
})
