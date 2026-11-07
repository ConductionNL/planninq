/**
 * Vitest unit tests for the backlog helpers (backlog-list).
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	backlogTasks,
	filterBacklog,
	moveToBacklogPatch,
	newBacklogTask,
	sortBacklog,
} from '../../src/utils/backlogHelpers.js'
import { buildMovePatch, orderPatchesForStep } from '../../src/utils/columnHelpers.js'

const project = '11111111-1111-4111-8111-111111111111'

describe('backlogTasks', () => {
	const tasks = [
		{ id: 'b1', title: 'B1', status: 'open', column: null, columnOrder: 2000 },
		{ id: 'b2', title: 'B2', status: 'in_progress', columnOrder: 1000 },
		{ id: 'c1', title: 'On board', status: 'open', column: 'col-1', columnOrder: 0 },
		{ id: 'd1', title: 'Finished', status: 'done', column: null },
		{ id: 'x1', title: 'Dropped', status: 'cancelled', column: null },
	]

	it('lists the column-less, not done tasks in rank order (scenario: open the backlog)', () => {
		expect(backlogTasks(tasks).map((t) => t.id)).toEqual(['b2', 'b1'])
	})

	it('shows cancelled tasks only under the Cancelled filter', () => {
		expect(backlogTasks(tasks, { cancelled: true }).map((t) => t.id)).toEqual(['x1'])
	})
})

describe('sortBacklog', () => {
	const tasks = [
		{ id: 'a', columnOrder: 1000, priority: 'low', dueDate: '2026-10-03', '@self': { created: '2026-09-03T10:00:00+00:00' } },
		{ id: 'b', columnOrder: 2000, priority: 'urgent', dueDate: '2026-10-01', '@self': { created: '2026-09-01T10:00:00+00:00' } },
		{ id: 'c', columnOrder: 3000, priority: 'normal', dueDate: '2026-10-02', '@self': { created: '2026-09-02T10:00:00+00:00' } },
		{ id: 'd', columnOrder: 4000, priority: 'high' },
	]

	it('sorts by due date, 1 2 3 October, undated last (scenario: sort by due date)', () => {
		expect(sortBacklog(tasks, 'due').map((t) => t.id)).toEqual(['b', 'c', 'a', 'd'])
	})

	it('sorts by priority, urgent first', () => {
		expect(sortBacklog(tasks, 'priority').map((t) => t.id)).toEqual(['b', 'd', 'c', 'a'])
	})

	it('sorts by creation date and by rank', () => {
		expect(sortBacklog(tasks, 'created').map((t) => t.id)).toEqual(['b', 'c', 'a', 'd'])
		expect(sortBacklog(tasks, 'rank').map((t) => t.id)).toEqual(['a', 'b', 'c', 'd'])
		expect(sortBacklog(tasks, 'nonsense').map((t) => t.id)).toEqual(['a', 'b', 'c', 'd'])
	})
})

describe('filterBacklog', () => {
	it('keeps only the chosen priority', () => {
		const tasks = [{ id: 'a', priority: 'high' }, { id: 'b', priority: 'low' }, { id: 'c' }]
		expect(filterBacklog(tasks, { priority: 'high' }).map((t) => t.id)).toEqual(['a'])
		expect(filterBacklog(tasks, { priority: '' }).map((t) => t.id)).toEqual(['a', 'b', 'c'])
		expect(filterBacklog(tasks, { priority: 'normal' }).map((t) => t.id)).toEqual(['c'])
	})
})

describe('ranking', () => {
	it('Move up on C twice gives C, A, B (scenario: rank with the keyboard)', () => {
		let list = [{ id: 'A', columnOrder: 1000 }, { id: 'B', columnOrder: 2000 }, { id: 'C', columnOrder: 3000 }]
		for (let i = 0; i < 2; i++) {
			const patches = orderPatchesForStep(list, list.find((t) => t.id === 'C'), -1)
			list = list.map((t) => ({ ...t, ...(patches.find((p) => p.id === t.id) || {}) }))
		}
		expect(sortBacklog(list, 'rank').map((t) => t.id)).toEqual(['C', 'A', 'B'])
	})
})

describe('moving between backlog and board', () => {
	it('plans a task at the bottom of the chosen lane with its status (scenario: plan a task)', () => {
		const lane = [{ id: 'x', columnOrder: 5000 }]
		expect(buildMovePatch({ id: 'doing', status: 'in_progress' }, lane)).toEqual({ column: 'doing', columnOrder: 6000, status: 'in_progress' })
	})

	it('takes a card off the board to the bottom of the backlog (scenario: take a card off the board)', () => {
		const backlog = [{ id: 'b', columnOrder: 3000 }]
		expect(moveToBacklogPatch(backlog)).toEqual({ column: null, columnOrder: 4000, status: 'open' })
	})
})

describe('newBacklogTask', () => {
	const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
	const schema = register.components.schemas.task
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

	it('creates a valid task last in the backlog, with no column (scenario: create into the backlog)', () => {
		const payload = newBacklogTask('  Check the zoning plan ', project, [{ id: 'a', columnOrder: 1000 }])
		expect(payload).toEqual({ title: 'Check the zoning plan', status: 'open', priority: 'normal', project, column: null, columnOrder: 2000 })
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('control: the validator refuses a task without a title', () => {
		expect(validate({ status: 'open', project })).toBe(false)
	})
})
