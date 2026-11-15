/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the project overview helpers: people, log order and types,
 * entry payloads and actions placed on the first column.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import {
	actionTasks,
	entryAuthor,
	entryCreated,
	filterLog,
	latestLogEntries,
	logEntryPayload,
	newActionTask,
	projectPeople,
	projectProgress,
} from '../../src/utils/projectOverview.js'

describe('progress and people edges', () => {
	it('skips holes and cancelled tasks', () => {
		expect(projectProgress([null, { status: 'done' }, { status: 'cancelled' }, { status: 'open' }])).toEqual({ done: 1, total: 2 })
		expect(projectProgress(null)).toEqual({ done: 0, total: 0 })
	})

	it('lists the owner first, once, and skips non-strings and empty ids', () => {
		expect(projectPeople({ owner: 'ann', members: ['bob', 'ann', '', 7, null, 'bob'] })).toEqual(['ann', 'bob'])
		expect(projectPeople(null)).toEqual([])
	})
})

describe('log entry metadata edges', () => {
	it('reads created from @self first, then the entry, else empty', () => {
		expect(entryCreated({ '@self': { created: 'a' }, created: 'b' })).toBe('a')
		expect(entryCreated({ created: 'b' })).toBe('b')
		expect(entryCreated(null)).toBe('')
		expect(entryAuthor({ '@self': { owner: 'ann' } })).toBe('ann')
		expect(entryAuthor({})).toBe('')
	})

	it('sorts newest date first, then newest save, and limits', () => {
		const entries = [
			{ id: 1, date: '2026-11-01', created: '2026-11-01T08:00' },
			{ id: 2, date: '2026-11-02' },
			{ id: 3, date: '2026-11-01', '@self': { created: '2026-11-01T09:00' } },
			{ id: 4 },
		]
		expect(latestLogEntries(entries, 3).map((entry) => entry.id)).toEqual([2, 3, 1])
		expect(latestLogEntries(null)).toEqual([])
	})

	it('filters by a known type and ignores an unknown one', () => {
		const entries = [{ id: 1, type: 'issue', date: '1' }, { id: 2, type: 'meeting', date: '2' }]
		expect(filterLog(entries, 'meeting').map((entry) => entry.id)).toEqual([2])
		expect(filterLog(entries, 'bogus').map((entry) => entry.id)).toEqual([2, 1])
	})
})

describe('logEntryPayload edges', () => {
	it('defaults the type to issue, the status to open and keeps attendees only for meetings', () => {
		expect(logEntryPayload({ type: 'gossip', title: ' T ', attendees: ['a'], status: 'weird' }, 'p')).toEqual({
			title: 'T',
			project: 'p',
			type: 'issue',
			date: '',
			body: '',
			status: 'open',
			attendees: [],
			actions: [],
		})
	})

	it('deduplicates meeting attendees and drops empty ones', () => {
		const payload = logEntryPayload({ type: 'meeting', attendees: ['a', '', 'a', 'b'], status: 'closed', actions: ['x'] }, 'p')
		expect(payload).toMatchObject({ type: 'meeting', status: 'closed', attendees: ['a', 'b'], actions: ['x'] })
		expect(logEntryPayload(null, 'p')).toMatchObject({ title: '', type: 'issue', attendees: [], actions: [] })
	})
})

describe('actions edges', () => {
	it('lists only action tasks', () => {
		expect(actionTasks([{ issueType: 'action' }, { issueType: 'task' }, null])).toEqual([{ issueType: 'action' }])
		expect(actionTasks(null)).toEqual([])
	})

	it('has no column on a board without columns and copies only given fields', () => {
		expect(newActionTask({ title: ' Doen ' }, 'p', [], [])).toEqual({
			title: 'Doen',
			status: 'open',
			priority: 'normal',
			issueType: 'action',
			project: 'p',
			column: null,
		})
	})

	it('lands in the lowest-ordered column, below its cards, with its mapped status', () => {
		const columns = [{ id: 'c2', order: 2 }, { '@self': { id: 'c1' }, order: 1, status: 'in_progress' }]
		const tasks = [{ id: 't', column: 'c1', columnOrder: 1000 }, { id: 'u', column: 'c2', columnOrder: 50000 }]
		const task = newActionTask({ title: 'A', assignedTo: 'ann', dueDate: '2026-12-01' }, 'p', columns, tasks)
		expect(task).toMatchObject({ assignedTo: 'ann', dueDate: '2026-12-01', column: 'c1', status: 'in_progress' })
		expect(task.columnOrder).toBeGreaterThan(1000)
		expect(task.columnOrder).toBeLessThan(50000)
	})
})
