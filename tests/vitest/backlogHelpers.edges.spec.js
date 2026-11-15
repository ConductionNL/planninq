/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the backlog helpers: sorting with missing dates and
 * priorities, empty inputs and the default priority.
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { backlogTasks, filterBacklog, newBacklogTask, sortBacklog } from '../../src/utils/backlogHelpers.js'

const ids = (list) => list.map((task) => task.id)

describe('backlogTasks edges', () => {
	it('treats a null list as empty and skips holes', () => {
		expect(backlogTasks(null)).toEqual([])
		expect(ids(backlogTasks([null, { id: 'a', status: 'open' }]))).toEqual(['a'])
	})

	it('ranks a task without columnOrder as 0', () => {
		const list = backlogTasks([{ id: 'late', columnOrder: 5 }, { id: 'unranked' }, { id: 'nan', columnOrder: 'x' }])
		expect(ids(list)).toEqual(['unranked', 'nan', 'late'])
	})

	it('lists only the cancelled tasks when asked', () => {
		const list = backlogTasks([{ id: 'a', status: 'open' }, { id: 'b', status: 'cancelled' }], { cancelled: true })
		expect(ids(list)).toEqual(['b'])
	})
})

describe('sortBacklog edges', () => {
	it('counts a missing priority as normal and an unknown one as normal', () => {
		const list = sortBacklog([
			{ id: 'low', priority: 'low', columnOrder: 0 },
			{ id: 'none', columnOrder: 2 },
			{ id: 'odd', priority: 'whatever', columnOrder: 1 },
			{ id: 'urgent', priority: 'urgent', columnOrder: 3 },
		], 'priority')
		expect(ids(list)).toEqual(['urgent', 'odd', 'none', 'low'])
	})

	it('puts tasks with a due date before those without, rank breaking ties', () => {
		const list = sortBacklog([
			{ id: 'noDue2', columnOrder: 2 },
			{ id: 'later', dueDate: '2026-12-01', columnOrder: 0 },
			{ id: 'noDue1', columnOrder: 1 },
			{ id: 'sooner', dueDate: '2026-11-01', columnOrder: 9 },
		], 'due')
		expect(ids(list)).toEqual(['sooner', 'later', 'noDue1', 'noDue2'])
	})

	it('breaks an equal due date by rank', () => {
		const list = sortBacklog([{ id: 'b', dueDate: '2026-11-01', columnOrder: 2 }, { id: 'a', dueDate: '2026-11-01', columnOrder: 1 }], 'due')
		expect(ids(list)).toEqual(['a', 'b'])
	})

	it('puts tasks with a creation time first, oldest first, rank breaking ties', () => {
		const list = sortBacklog([
			{ id: 'unknown2', columnOrder: 2 },
			{ id: 'new', '@self': { created: '2026-11-10T10:00:00Z' }, columnOrder: 0 },
			{ id: 'unknown1', columnOrder: 1 },
			{ id: 'old', '@self': { created: '2026-11-01T10:00:00Z' }, columnOrder: 5 },
			{ id: 'oldTwin', '@self': { created: '2026-11-01T10:00:00Z' }, columnOrder: 4 },
		], 'created')
		expect(ids(list)).toEqual(['oldTwin', 'old', 'new', 'unknown1', 'unknown2'])
	})

	it('keeps rank order for an unknown sort and does not change its input', () => {
		const input = [{ id: 'b', columnOrder: 2 }, { id: 'a', columnOrder: 1 }]
		expect(ids(sortBacklog(input, 'nonsense'))).toEqual(['a', 'b'])
		expect(ids(input)).toEqual(['b', 'a'])
		expect(sortBacklog(null)).toEqual([])
	})
})

describe('filterBacklog and newBacklogTask edges', () => {
	it('treats a task without priority as normal and a null list as empty', () => {
		const list = filterBacklog([{ id: 'a' }, { id: 'b', priority: 'high' }], { priority: 'normal' })
		expect(ids(list)).toEqual(['a'])
		expect(filterBacklog(null, { priority: 'high' })).toEqual([])
		expect(filterBacklog(null)).toEqual([])
	})

	it('makes an empty title from a missing one', () => {
		expect(newBacklogTask(undefined, 'p1').title).toBe('')
		expect(newBacklogTask('  Iets  ', 'p1')).toMatchObject({ title: 'Iets', project: 'p1', column: null, status: 'open' })
	})
})
