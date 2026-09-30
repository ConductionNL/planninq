/**
 * Vitest tests for the board filter model (boards-filters).
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import {
	activeDimensions,
	applySavedFilter,
	canManageSavedFilter,
	decodeFilter,
	dueValue,
	emptyFilter,
	encodeFilter,
	matchesFilter,
	normaliseFilter,
	savedFilterPayload,
	withFilterQuery,
} from '../../src/utils/boardFilter.js'

const TODAY = new Date('2026-09-30T10:00:00') // a Wednesday
const tasks = [
	{ id: 'u1', assignedTo: 'anna', priority: 'urgent', labels: ['jur'], dueDate: '2026-09-28', status: 'open' },
	{ id: 'u2', assignedTo: 'anna', priority: 'urgent', labels: [], dueDate: '2026-10-04', status: 'open' },
	{ id: 'n1', assignedTo: 'anna', priority: 'normal', labels: ['jur'], status: 'open' },
	{ id: 'n2', assignedTo: 'bram', priority: 'normal', labels: [], dueDate: '2026-10-20', status: 'open' },
	{ id: 'n3', assignedTo: '', priority: 'normal', labels: [], status: 'open' },
	{ id: 'n4', assignedTo: 'bram', labels: ['fin'], dueDate: '2026-09-01', status: 'done' },
	{ id: 'n5', priority: 'normal', labels: [], dueDate: '2026-09-30', status: 'open' },
]
const ids = (filter, uid = 'anna') => tasks.filter((task) => matchesFilter(task, filter, uid, TODAY)).map((task) => task.id)
const filter = (dimension, op, values) => ({ ...emptyFilter(), [dimension]: { op, values } })

describe('matchesFilter', () => {
	it('shows everything without a filter', () => {
		expect(ids(emptyFilter())).toHaveLength(7)
		expect(activeDimensions(emptyFilter())).toBe(0)
	})

	it('Priority is Urgent (scenario: only urgent work); an empty priority counts as normal', () => {
		expect(ids(filter('priority', 'is', ['urgent']))).toEqual(['u1', 'u2'])
		expect(ids(filter('priority', 'is', ['normal']))).toEqual(['n1', 'n2', 'n3', 'n4', 'n5'])
	})

	it('Assignee is Me (scenario: my tasks) and Unassigned', () => {
		expect(ids(filter('assignee', 'is', ['me']))).toEqual(['u1', 'u2', 'n1'])
		expect(ids(filter('assignee', 'is', ['me']), 'bram')).toEqual(['n2', 'n4'])
		expect(ids(filter('assignee', 'is', ['unassigned']))).toEqual(['n3', 'n5'])
		expect(ids(filter('assignee', 'is', ['bram', 'unassigned']))).toEqual(['n2', 'n3', 'n4', 'n5'])
	})

	it('Assignee is not Me keeps unassigned cards (scenario: everything not assigned to me)', () => {
		expect(ids(filter('assignee', 'isNot', ['me']))).toEqual(['n2', 'n3', 'n4', 'n5'])
	})

	it('filters by label, both ways', () => {
		expect(ids(filter('label', 'is', ['jur']))).toEqual(['u1', 'n1'])
		expect(ids(filter('label', 'isNot', ['jur', 'fin']))).toEqual(['u2', 'n2', 'n3', 'n5'])
	})

	it('filters by due date: overdue (open only), this week (today up to Sunday), no date', () => {
		expect(ids(filter('due', 'is', ['overdue']))).toEqual(['u1'])
		expect(ids(filter('due', 'is', ['thisWeek']))).toEqual(['u2', 'n5'])
		expect(ids(filter('due', 'is', ['none']))).toEqual(['n1', 'n3'])
		expect(dueValue(tasks[5], TODAY)).toBe('')
	})

	it('combines dimensions with and', () => {
		expect(ids({ ...filter('assignee', 'is', ['me']), priority: { op: 'isNot', values: ['urgent'] } })).toEqual(['n1'])
	})
})

describe('the page address (scenario: send the view to a colleague)', () => {
	it('round-trips through the query string', () => {
		const value = { ...filter('label', 'is', ['jur', 'fin']), priority: { op: 'isNot', values: ['low'] }, assignee: { op: 'is', values: ['me'] } }
		const query = encodeFilter(value)
		expect(query).toEqual({ assignee: 'me', label: 'jur,fin', 'priority!': 'low' })
		expect(decodeFilter(query)).toEqual(normaliseFilter(value))
	})

	it('keeps other query keys and drops the old filter', () => {
		expect(withFilterQuery({ view: 'list', 'label!': 'x', task: 't1' }, filter('priority', 'is', ['high']))).toEqual({ view: 'list', task: 't1', priority: 'high' })
	})

	it('ignores empty and unknown values', () => {
		expect(decodeFilter({ label: '', colour: 'red', 'due!': 'none,,none' })).toEqual({ ...emptyFilter(), due: { op: 'isNot', values: ['none'] } })
		expect(normaliseFilter({ priority: { op: 'weird', values: ['high', null] } }).priority).toEqual({ op: 'is', values: ['high'] })
	})
})

describe('saved filters (scenarios: a shared saved filter, a private saved filter)', () => {
	it('builds a payload that passes the real boardFilter schema', async () => {
		const { readFileSync } = await import('node:fs')
		const { default: Ajv } = await import('ajv')
		const { default: addFormats } = await import('ajv-formats')
		const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
		const schema = register.components.schemas.boardFilter
		const properties = Object.fromEntries(Object.entries(schema.properties).map(([name, { $ref, visible, ...rest }]) => [name, Object.fromEntries(Object.entries(rest).filter(([key]) => !key.startsWith('x-')))]))
		const ajv = new Ajv({ strict: false })
		addFormats(ajv)
		const validate = ajv.compile({ type: 'object', required: schema.required, properties })
		const payload = savedFilterPayload({ name: ' Overdue legal work ', shared: 1, filter: filter('due', 'is', ['overdue']), project: '00000000-0000-4000-8000-000000000001' })
		expect(payload).toEqual({ name: 'Overdue legal work', shared: true, project: '00000000-0000-4000-8000-000000000001', criteria: normaliseFilter(filter('due', 'is', ['overdue'])) })
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
		expect(validate({ ...payload, shared: 'yes' })).toBe(false)
	})

	it('only the owner or an admin renames or deletes a saved filter', () => {
		expect(canManageSavedFilter({ owner: 'anna' }, { uid: 'anna' })).toBe(true)
		expect(canManageSavedFilter({ owner: 'anna' }, { uid: 'bram' })).toBe(false)
		expect(canManageSavedFilter({ owner: 'anna' }, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canManageSavedFilter({ owner: 'anna' }, null)).toBe(false)
	})

	it('drops a deleted label when applied and says the filter changed', () => {
		const saved = { criteria: { label: { op: 'is', values: ['jur', 'gone'] }, priority: { op: 'is', values: ['high'] } } }
		const { filter: applied, changed } = applySavedFilter(saved, ['jur'])
		expect(applied.label.values).toEqual(['jur'])
		expect(applied.priority.values).toEqual(['high'])
		expect(changed).toBe(true)
		expect(applySavedFilter({ criteria: { label: { op: 'is', values: ['jur'] } } }, ['jur']).changed).toBe(false)
	})
})
