/**
 * Vitest unit tests for subtasks, the checklist, rollups and duplicating (tasks-subtasks-checklist).
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	addChecklistItem,
	checklistCount,
	duplicatePayload,
	moveChecklistItem,
	newSubtask,
	removeChecklistItem,
	subtaskProgress,
	subtaskRollup,
	toggleChecklistItem,
} from '../../src/utils/taskBreakdown.js'

const project = '11111111-1111-4111-8111-111111111111'
const parentId = '44444444-4444-4444-8444-444444444444'
const column = '22222222-2222-4222-8222-222222222222'

function taskValidator() {
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
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	return ajv.compile({ type: 'object', required: schema.required, properties, additionalProperties: false })
}

describe('checklist operations (scenario: tick a checklist item)', () => {
	const five = ['a', 'b', 'c', 'd', 'e'].map((id, i) => ({ id, text: `Step ${id}`, done: i < 2 }))

	it('ticks a third item and the card count reads 3/5', () => {
		const ticked = toggleChecklistItem(five, 'c')
		expect(checklistCount(ticked)).toBe('3/5')
		expect(five[2].done).toBe(false)
	})

	it('adds an unticked item with a fresh id, ignores blank text', () => {
		const list = addChecklistItem(five, '  Send the letter ', () => 'new-1')
		expect(list.at(-1)).toEqual({ id: 'new-1', text: 'Send the letter', done: false })
		expect(addChecklistItem(five, '   ', () => 'x')).toBe(five)
	})

	it('moves an item a step and removes one, and the edges do nothing', () => {
		expect(moveChecklistItem(five, 'b', -1).map((item) => item.id)).toEqual(['b', 'a', 'c', 'd', 'e'])
		expect(moveChecklistItem(five, 'a', -1)).toBe(five)
		expect(removeChecklistItem(five, 'c').map((item) => item.id)).toEqual(['a', 'b', 'd', 'e'])
		expect(checklistCount([])).toBe('')
	})

	it('writes a checklist the real task schema accepts', () => {
		const validate = taskValidator()
		const payload = { title: 'x', status: 'open', checklist: toggleChecklistItem(five, 'c') }
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})
})

describe('subtasks (scenario: add a subtask)', () => {
	const parent = { id: parentId, title: 'Prepare the council decision', project, column, priority: 'high' }

	it('creates a subtask in the parent project and lane, with parent set, and it validates', () => {
		const sub = newSubtask('  Collect the advice ', parent, [{ id: parentId, column, columnOrder: 1000 }])
		expect(sub).toEqual({ title: 'Collect the advice', status: 'open', priority: 'normal', project, parent: parentId, column, columnOrder: 2000 })
		expect(taskValidator()(sub)).toBe(true)
	})

	it('counts done subtasks and leaves cancelled ones out', () => {
		expect(subtaskProgress([{ status: 'open' }])).toEqual({ done: 0, total: 1 })
		expect(subtaskProgress([{ status: 'done' }, { status: 'open' }, { status: 'cancelled' }])).toEqual({ done: 1, total: 2 })
	})
})

describe('subtaskRollup (scenario: rollup on the parent)', () => {
	it('sums the subtask estimates and logged time', () => {
		const children = [{ id: 's1', estimatedDuration: 120 }, { id: 's2', estimatedDuration: 180 }]
		const entries = { s1: [{ duration: 90 }], s2: [] }
		expect(subtaskRollup(children, entries)).toEqual({ estimate: 300, logged: 90 })
		expect(subtaskRollup([], {})).toEqual({ estimate: 0, logged: 0 })
	})
})

describe('duplicatePayload (scenario: duplicate a task tree)', () => {
	const task = {
		id: 't1',
		title: 'Prepare',
		description: 'Why',
		status: 'in_progress',
		priority: 'high',
		labels: ['55555555-5555-4555-8555-555555555555'],
		project,
		column,
		columnOrder: 3000,
		checklist: [{ id: 'c1', text: 'One', done: true }],
		assignedTo: 'bram',
		sharedWith: ['anna'],
		dueDate: '2026-10-01',
		startDate: '2026-09-01',
		estimatedDuration: 60,
		key: 'VERG-4',
		reporter: 'dave',
		parent: null,
		completedAt: '2026-09-02',
	}

	it('copies the description, priority, labels and an unticked checklist, and nothing personal or dated', () => {
		const copy = duplicatePayload(task, 'Copy of {title}')
		expect(copy).toEqual({
			title: 'Copy of Prepare',
			description: 'Why',
			status: 'open',
			priority: 'high',
			labels: ['55555555-5555-4555-8555-555555555555'],
			project,
			column,
			columnOrder: 3000,
			checklist: [{ id: 'c1', text: 'One', done: false }],
		})
		expect(taskValidator()(copy)).toBe(true)
	})

	it('a copied subtask keeps its own title and goes under the new parent', () => {
		expect(duplicatePayload({ ...task, parent: 't1' }, '{title}', 'new-parent')).toMatchObject({ title: 'Prepare', parent: 'new-parent' })
	})
})
