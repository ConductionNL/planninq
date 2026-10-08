/**
 * Vitest unit tests for the board-column helpers (boards-configurable-columns).
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	boardListRows,
	buildMovePatch,
	canRemoveColumn,
	columnPayload,
	groupTasksByColumn,
	orderPatchesForStep,
	sortColumns,
	swapColumnPatches,
	wipState,
} from '../../src/utils/columnHelpers.js'

const columns = [
	{ id: 'done', title: 'Done', order: 3, type: 'done', status: 'done' },
	{ id: 'todo', title: 'To do', order: 0, type: 'active', status: 'open' },
	{ id: 'doing', title: 'In progress', order: 1, type: 'active', status: 'in_progress' },
	{ id: 'review', title: 'Review', order: 2, type: 'active', wipLimit: 2 },
]

describe('sortColumns', () => {
	it('orders lanes by their order field (scenario: a project with a Review column)', () => {
		expect(sortColumns(columns).map((c) => c.title)).toEqual(['To do', 'In progress', 'Review', 'Done'])
	})
})

describe('groupTasksByColumn', () => {
	it('places each task in the column it references, sorted by columnOrder', () => {
		const grouped = groupTasksByColumn([
			{ id: 'b', column: 'todo', columnOrder: 2000 },
			{ id: 'a', column: 'todo', columnOrder: 1000 },
			{ id: 'c', column: 'done', columnOrder: 0 },
		], columns)
		expect(Object.keys(grouped)).toEqual(['todo', 'doing', 'review', 'done'])
		expect(grouped.todo.map((t) => t.id)).toEqual(['a', 'b'])
		expect(grouped.done.map((t) => t.id)).toEqual(['c'])
		expect(grouped.doing).toEqual([])
	})

	it('leaves column-less tasks off the board: they are the backlog', () => {
		const grouped = groupTasksByColumn([{ id: 'x', column: null }, { id: 'y' }], columns)
		expect(Object.values(grouped).flat()).toEqual([])
	})

	it('puts a task whose column no longer exists in the first lane, so no card is lost', () => {
		const grouped = groupTasksByColumn([{ id: 'x', column: 'gone' }], columns)
		expect(grouped.todo.map((t) => t.id)).toEqual(['x'])
	})
})

describe('buildMovePatch', () => {
	it('drops a card at the bottom of a done lane with status done (scenario: drop a card on Done)', () => {
		const lane = [{ id: 'c', columnOrder: 4000 }]
		expect(buildMovePatch(columns[0], lane)).toEqual({ column: 'done', columnOrder: 5000, status: 'done' })
	})

	it('gives the lane its mapped status, and no status when the lane maps none', () => {
		expect(buildMovePatch(columns[2], [])).toEqual({ column: 'doing', columnOrder: 1000, status: 'in_progress' })
		expect(buildMovePatch(columns[3], [])).toEqual({ column: 'review', columnOrder: 1000 })
	})

	it('a done lane without a mapped status still finishes the task', () => {
		expect(buildMovePatch({ id: 'd2', type: 'done' }, []).status).toBe('done')
	})

	it('drops before a card by taking the midpoint', () => {
		const lane = [{ id: 'a', columnOrder: 1000 }, { id: 'b', columnOrder: 2000 }]
		expect(buildMovePatch(columns[1], lane, lane[1]).columnOrder).toBe(1500)
		expect(buildMovePatch(columns[1], lane, lane[0]).columnOrder).toBe(500)
	})
})

describe('orderPatchesForStep', () => {
	it('moves B above A with one write (scenario: reorder with the keyboard)', () => {
		const lane = [{ id: 'a', columnOrder: 1000 }, { id: 'b', columnOrder: 2000 }, { id: 'c', columnOrder: 3000 }]
		expect(orderPatchesForStep(lane, lane[1], -1)).toEqual([{ id: 'b', columnOrder: 0 }])
		expect(orderPatchesForStep(lane, lane[0], 1)).toEqual([{ id: 'a', columnOrder: 2500 }])
	})

	it('renumbers the lane when no integer fits between the neighbours', () => {
		const lane = [{ id: 'a', columnOrder: 1 }, { id: 'b', columnOrder: 2 }, { id: 'c', columnOrder: 3 }]
		expect(orderPatchesForStep(lane, lane[2], -1)).toEqual([
			{ id: 'a', columnOrder: 1000 },
			{ id: 'c', columnOrder: 2000 },
			{ id: 'b', columnOrder: 3000 },
		])
	})

	it('does nothing at the edge of the lane', () => {
		const lane = [{ id: 'a', columnOrder: 1000 }]
		expect(orderPatchesForStep(lane, lane[0], -1)).toEqual([])
	})
})

describe('wipState', () => {
	it('reads count over limit and warns past it (scenario: go over the limit)', () => {
		expect(wipState(4, 3)).toEqual({ text: '4 / 3', over: true })
		expect(wipState(3, 3)).toEqual({ text: '3 / 3', over: false })
	})

	it('shows only the count when the lane has no limit', () => {
		expect(wipState(4, null)).toEqual({ text: '4', over: false })
		expect(wipState(4, 0)).toEqual({ text: '4', over: false })
	})
})

describe('canRemoveColumn', () => {
	it('refuses to remove the last done column', () => {
		expect(canRemoveColumn(columns, columns[0])).toBe(false)
		expect(canRemoveColumn([...columns, { id: 'done2', type: 'done' }], columns[0])).toBe(true)
		expect(canRemoveColumn(columns, columns[3])).toBe(true)
	})
})

describe('swapColumnPatches', () => {
	it('moves a column one place left or right by swapping orders', () => {
		expect(swapColumnPatches(columns, columns[3], -1)).toEqual([
			{ id: 'review', order: 1 },
			{ id: 'doing', order: 2 },
		])
		expect(swapColumnPatches(columns, columns[1], -1)).toEqual([])
	})
})

describe('columnPayload', () => {
	// The payload is validated against the real `column` schema of
	// lib/Settings/planninq_register.json, prepared the way OpenRegister
	// prepares it (slug `$ref` dropped, `nullable` admits null).
	const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
	const schema = register.components.schemas.column
	const properties = {}
	for (const [name, property] of Object.entries(schema.properties)) {
		const { $ref, visible, nullable, ...rest } = property
		for (const key of Object.keys(rest)) {
			if (key.startsWith('x-')) {
				delete rest[key]
			}
		}
		if (nullable) {
			rest.type = [rest.type, 'null']
			if (rest.enum) {
				rest.enum = [...rest.enum, null]
			}
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	const validate = ajv.compile({ type: 'object', required: schema.required, properties })
	const project = '11111111-1111-4111-8111-111111111111'

	it('writes a valid column for a new lane (scenario: add and rename a column)', () => {
		const payload = { ...columnPayload({ title: ' Waiting ', wipLimit: '', color: '#aabbcc' }), project, order: 4 }
		expect(payload).toMatchObject({ title: 'Waiting', wipLimit: null, status: null, type: 'active', color: '#AABBCC' })
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('writes a valid done column with a limit', () => {
		const payload = { ...columnPayload({ title: 'Closed', wipLimit: '3', done: true }), project, order: 2 }
		expect(payload).toMatchObject({ wipLimit: 3, status: 'done', type: 'done' })
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('control: the validator refuses a status the schema does not know', () => {
		expect(validate({ title: 'x', project, order: 0, status: 'finished' })).toBe(false)
	})
})

describe('boardListRows', () => {
	it('lists the board\'s cards in lane order, then card order, with their column (scenario: switch to the list)', () => {
		const rows = boardListRows([
			{ id: 'd', column: 'done', columnOrder: 0 },
			{ id: 't2', column: 'todo', columnOrder: 2000 },
			{ id: 't1', column: 'todo', columnOrder: 1000 },
			{ id: 'b', column: null },
		], columns)
		expect(rows.map((row) => [row.task.id, row.column.title])).toEqual([['t1', 'To do'], ['t2', 'To do'], ['d', 'Done']])
	})
})
