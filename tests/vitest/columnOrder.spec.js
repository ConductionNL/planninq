/**
 * Vitest tests for where a moved card lands in a lane (orderFor) and the
 * lane grouping's edge cases (groupTasksByColumn).
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { groupTasksByColumn, orderFor } from '../../src/utils/columnHelpers.js'

describe('orderFor', () => {
	const lane = [{ id: 'c', columnOrder: 3000 }, { id: 'a', columnOrder: 1000 }, { id: 'b', columnOrder: '2000' }]

	it.each([
		['bottom of a lane', lane, null, 4000],
		['bottom of an empty lane', [], null, 1000],
		['no lane at all', null, null, 1000],
		['in front of the first card', lane, { id: 'a' }, 500],
		['between two cards', lane, { id: 'c' }, 2500],
		['in front of a card that is not in the lane', lane, { id: 'x' }, 4000],
		['cards without an order', [{ id: 'p' }, { id: 'q', columnOrder: 'n/a' }], { id: 'q' }, 0],
		['bottom below a card without an order', [{ id: 'p' }], null, 1000],
	])('%s', (name, tasks, before, expected) => {
		expect(orderFor(tasks, before)).toBe(expected)
	})
})

describe('groupTasksByColumn edge cases', () => {
	it('keeps no card when there are no lanes, and skips empty entries', () => {
		expect(groupTasksByColumn([{ id: 'x', column: 'todo' }, null], [])).toEqual({})
	})

	it('sorts every lane, the first lane gets orphans in their order, and lanes follow the columns', () => {
		const columns = [{ id: 'done', order: 2 }, { id: 'todo', order: 1 }]
		const grouped = groupTasksByColumn([
			{ id: 'o2', column: 'gone', columnOrder: 5 },
			null,
			{ id: 'd1', column: 'done', columnOrder: 9 },
			{ id: 'o1', column: 'gone', columnOrder: 1 },
		], columns)
		expect(Object.keys(grouped)).toEqual(['todo', 'done'])
		expect(grouped.todo.map((task) => task.id)).toEqual(['o1', 'o2'])
		expect(grouped.done.map((task) => task.id)).toEqual(['d1'])
	})
})
