/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the subtask and checklist helpers.
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { orderFor } from '../../src/utils/columnHelpers.js'
import {
	addChecklistItem,
	checklistCount,
	moveChecklistItem,
	newSubtask,
	subtaskProgress,
	subtaskRollup,
} from '../../src/utils/taskBreakdown.js'

afterEach(() => {
	vi.unstubAllGlobals()
})

describe('checklist ids', () => {
	it('uses crypto.randomUUID when there is one', () => {
		vi.stubGlobal('crypto', { randomUUID: () => 'uuid-1' })
		expect(addChecklistItem([], 'Bel klant')).toEqual([{ id: 'uuid-1', text: 'Bel klant', done: false }])
	})

	it('falls back to a generated id without crypto', () => {
		vi.stubGlobal('crypto', undefined)
		const [item] = addChecklistItem(null, 'Bel klant')
		expect(item.id).toMatch(/^c[0-9a-z]+$/)
		expect(item.text).toBe('Bel klant')
	})

	it('keeps the same list for blank or missing text', () => {
		const list = [{ id: 'a', text: 'x', done: false }]
		expect(addChecklistItem(list, '   ')).toBe(list)
		expect(addChecklistItem(list, undefined)).toBe(list)
	})
})

describe('moveChecklistItem edges', () => {
	const list = [{ id: 'a' }, { id: 'b' }]

	it('keeps the list for an unknown id and at both edges', () => {
		expect(moveChecklistItem(list, 'zz', 1)).toBe(list)
		expect(moveChecklistItem(list, 'a', -1)).toBe(list)
		expect(moveChecklistItem(list, 'b', 1)).toBe(list)
	})

	it('moves an item down', () => {
		expect(moveChecklistItem(list, 'a', 1).map((item) => item.id)).toEqual(['b', 'a'])
	})
})

describe('counts and rollups edges', () => {
	it('shows no count for an empty or missing checklist', () => {
		expect(checklistCount(null)).toBe('')
		expect(checklistCount([])).toBe('')
		expect(checklistCount([{ done: true }, { done: false }])).toBe('1/2')
	})

	it('skips cancelled subtasks and copes with a null list', () => {
		expect(subtaskProgress(null)).toEqual({ done: 0, total: 0 })
		expect(subtaskProgress([{ status: 'done' }, { status: 'cancelled' }, { status: 'open' }])).toEqual({ done: 1, total: 2 })
	})

	it('sums estimates and logged time, ignoring non-numbers and missing entries', () => {
		const children = [{ id: 't1', estimatedDuration: '30' }, { id: 't2' }, null]
		const entries = { t1: [{ duration: 10 }, { duration: 'x' }, null], t2: [{ duration: 5 }] }
		expect(subtaskRollup(children, entries)).toEqual({ estimate: 30, logged: 15 })
		expect(subtaskRollup(null, null)).toEqual({ estimate: 0, logged: 0 })
	})
})

describe('newSubtask edges', () => {
	it('takes the project id from an expanded project and lands last in the parent lane', () => {
		const parent = { id: 'p', project: { id: 'proj' }, column: 'col' }
		const tasks = [{ column: 'col', columnOrder: 3 }, { column: 'other', columnOrder: 99 }]
		const sub = newSubtask(' Sub ', parent, tasks)
		expect(sub).toMatchObject({ title: 'Sub', project: 'proj', parent: 'p', column: 'col' })
		expect(sub.columnOrder).toBe(orderFor([tasks[0]]))
		expect(sub.columnOrder).not.toBe(orderFor(tasks))
	})

	it('has no column and no lane without a parent column', () => {
		const sub = newSubtask(undefined, { id: 'p', project: 'proj' }, null)
		expect(sub).toMatchObject({ title: '', project: 'proj', parent: 'p', column: null })
	})
})
