/**
 * Vitest unit tests for creating, editing and deleting a task (tasks-create-edit-delete).
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.1
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	canDeleteTask,
	deleteRefusal,
	descriptionExcerpt,
	editPatch,
	newLaneTask,
	withTaskDefaults,
} from '../../src/utils/taskEditing.js'

const project = '11111111-1111-4111-8111-111111111111'

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
			if (rest.enum) {
				rest.enum = [...rest.enum, null]
			}
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	return ajv.compile({ type: 'object', required: schema.required, properties, additionalProperties: false })
}

describe('withTaskDefaults (task 1.1)', () => {
	const validate = taskValidator()

	it('defaults status open and priority normal and keeps what was given (scenario: create a task from the board header)', () => {
		expect(withTaskDefaults({ title: 'Draft the permit letter', project })).toEqual({
			title: 'Draft the permit letter',
			status: 'open',
			priority: 'normal',
			project,
		})
		expect(withTaskDefaults({ title: 'x', project, status: 'in_progress', priority: 'high' })).toMatchObject({ status: 'in_progress', priority: 'high' })
	})

	it('control: the real task schema refuses a payload without a title', () => {
		expect(validate({ status: 'open', project })).toBe(false)
	})
})

describe('newLaneTask (task 3.1)', () => {
	const validate = taskValidator()
	const lane = { id: '22222222-2222-4222-8222-222222222222', title: 'In progress', order: 2, status: 'in_progress' }

	it('puts a quick-added task at the bottom of its lane with the lane status (scenario: quick add two tasks in a row)', () => {
		const first = newLaneTask({ title: ' Call the applicant ' }, project, lane, [{ id: 'a', columnOrder: 1000 }])
		expect(first).toEqual({ title: 'Call the applicant', status: 'in_progress', priority: 'normal', project, column: '22222222-2222-4222-8222-222222222222', columnOrder: 2000 })
		expect(validate(first), JSON.stringify(validate.errors)).toBe(true)

		const second = newLaneTask({ title: 'Check the drawings' }, project, lane, [{ id: 'a', columnOrder: 1000 }, { id: 'b', ...first }])
		expect(second.columnOrder).toBeGreaterThan(first.columnOrder)
	})

	it('keeps a Markdown description and a priority from the dialog, and the payload still validates', () => {
		const task = newLaneTask({ title: 'Draft the permit letter', description: '## Steps\n- one\n- two', priority: 'high' }, project, { id: '33333333-3333-4333-8333-333333333333', order: 1 }, [])
		expect(task).toMatchObject({ description: '## Steps\n- one\n- two', priority: 'high', status: 'open', column: '33333333-3333-4333-8333-333333333333', columnOrder: 1000 })
		expect(validate(task), JSON.stringify(validate.errors)).toBe(true)
	})

	it('carries the responsible person and the people it is shared with, and still validates (tasks-assignment-priority-labels)', () => {
		const task = newLaneTask({ title: 'Two people', assignedTo: 'bram', sharedWith: ['anna', 'bram'] }, project, null, [])
		expect(task).toMatchObject({ assignedTo: 'bram', sharedWith: ['anna'] })
		expect(validate(task), JSON.stringify(validate.errors)).toBe(true)
		expect(newLaneTask({ title: 'Nobody' }, project, null, [])).not.toHaveProperty('sharedWith')
	})

	it('without a lane, the task goes to the backlog', () => {
		expect(newLaneTask({ title: 'x' }, project, null, [])).toMatchObject({ column: null, status: 'open' })
	})
})

describe('editPatch (task 2.1)', () => {
	const task = { id: 't1', title: 'Draft the permit letter', description: '', status: 'open', priority: 'normal', project, reporter: 'dave' }

	it('sends only the changed field (scenario: rename a task)', () => {
		expect(editPatch(task, { title: ' Draft and send the permit letter ', description: '', status: 'open', priority: 'normal' }))
			.toEqual({ title: 'Draft and send the permit letter' })
	})

	it('sends nothing when nothing changed, and treats a missing description as empty', () => {
		expect(editPatch({ ...task, description: undefined }, { title: task.title, description: '', status: 'open', priority: 'normal' })).toEqual({})
	})

	it('sends a change of people (tasks-assignment-priority-labels)', () => {
		expect(editPatch({ ...task, assignedTo: '', sharedWith: [] }, { title: task.title, description: '', status: 'open', priority: 'normal', assignedTo: 'bram', sharedWith: ['anna'] }))
			.toEqual({ assignedTo: 'bram', sharedWith: ['anna'] })
	})

	it('sends a status and a description change', () => {
		expect(editPatch(task, { title: task.title, description: 'New', status: 'blocked', priority: 'normal' })).toEqual({ description: 'New', status: 'blocked' })
	})
})

describe('descriptionExcerpt (task 3.2)', () => {
	it('strips Markdown syntax', () => {
		expect(descriptionExcerpt('## Steps\n- Call the **applicant**\n- See [the plan](https://example.org)')).toBe('Steps Call the applicant See the plan')
	})

	it('cuts at 140 characters with an ellipsis', () => {
		const excerpt = descriptionExcerpt('a'.repeat(200))
		expect(excerpt.length).toBe(140)
		expect(excerpt.endsWith('…')).toBe(true)
	})

	it('shows script as text, not markup', () => {
		expect(descriptionExcerpt('<script>alert(1)</script>')).toBe('<script>alert(1)</script>')
		expect(descriptionExcerpt(null)).toBe('')
	})
})

describe('canDeleteTask (task 4.1)', () => {
	const task = { reporter: 'dave' }
	const owned = { owner: 'carol' }

	it('shows Delete task to the reporter, the project owner and an admin (scenario: a plain member cannot delete)', () => {
		expect(canDeleteTask(task, owned, { uid: 'dave' })).toBe(true)
		expect(canDeleteTask(task, owned, { uid: 'carol' })).toBe(true)
		expect(canDeleteTask(task, owned, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canDeleteTask(task, owned, { uid: 'bob' })).toBe(false)
		expect(canDeleteTask({ reporter: null }, owned, { uid: 'bob' })).toBe(false)
		expect(canDeleteTask(task, owned, null)).toBe(false)
	})
})

describe('deleteRefusal (task 1.2)', () => {
	it('maps the server codes to what the dialog says', () => {
		expect(deleteRefusal({ code: 'planninq-task-has-logged-time' })).toBe('has-time')
		expect(deleteRefusal({ errors: { code: 'planninq-task-delete-not-allowed' } })).toBe('not-allowed')
		expect(deleteRefusal({})).toBe('failed')
	})
})

describe('TaskDetail renders the description as Markdown (task 4.2)', () => {
	const source = readFileSync(new URL('../../src/views/TaskDetail.vue', import.meta.url), 'utf8')

	it('uses NcRichText with Markdown on, and never v-html (scenario: script in a description stays text)', () => {
		expect(source).toMatch(/<NcRichText[^>]*:text="task\.description"[^>]*:useMarkdown="true"/s)
		expect(source).not.toMatch(/v-html/)
	})
})
