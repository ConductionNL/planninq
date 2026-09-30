/**
 * Vitest unit tests for cross-project views (boards-cross-project-board).
 *
 * @spec openspec/changes/boards-cross-project-board/tasks.md#task-2.2
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { emptyFilter } from '../../src/utils/boardFilter.js'
import {
	canManageView,
	MAX_VIEW_PROJECTS,
	mergeViewResults,
	pickableProjects,
	resolveViewMove,
	viewLanes,
	viewPayload,
	viewProblems,
} from '../../src/utils/projectsView.js'

const SERVERS = '11111111-1111-4111-8111-111111111111'
const WORKPLACE = '22222222-2222-4222-8222-222222222222'

const servers = { id: SERVERS, title: 'Servers', owner: 'anna', members: ['anna', 'ben'] }
const workplace = { id: WORKPLACE, title: 'Workplace', owner: 'carl', members: ['carl', 'anna'] }
const finance = { id: '33333333-3333-4333-8333-333333333333', title: 'Finance', owner: 'dora', members: ['dora'] }

const serverColumns = [
	{ id: 'done', title: 'Done', order: 2, type: 'done', status: 'done' },
	{ id: 'todo', title: 'To do', order: 0, type: 'active', status: 'open' },
	{ id: 'doing', title: 'Doing', order: 1, type: 'active', status: 'in_progress' },
	{ id: 'doing2', title: 'Doing too', order: 3, type: 'active', status: 'in_progress' },
]
const workplaceColumns = [
	{ id: 'w-open', title: 'Open', order: 0, type: 'active', status: 'open' },
	{ id: 'w-doing', title: 'Busy', order: 1, type: 'active', status: 'in_progress' },
	{ id: 'w-done', title: 'Closed', order: 2, type: 'done' },
]

const patch = { id: 't1', title: 'Patch mail server', project: SERVERS, status: 'open', column: 'todo', columnOrder: 1000 }
const backup = { id: 't2', title: 'Check backups', project: SERVERS, status: 'in_progress', column: 'doing', columnOrder: 1000, priority: 'high' }
const laptops = { id: 't3', title: 'Order laptops', project: WORKPLACE, status: 'open', column: 'w-open', columnOrder: 1000 }

describe('mergeViewResults', () => {
	it('merges tasks of all readable projects', () => {
		const merged = mergeViewResults([
			{ projectId: SERVERS, project: servers, tasks: [patch, backup] },
			{ projectId: WORKPLACE, project: workplace, tasks: [laptops] },
		])
		expect(merged.tasks.map((task) => task.id)).toEqual(['t1', 't2', 't3'])
		expect(merged.projectsById[SERVERS].title).toBe('Servers')
		expect(merged.hidden).toBe(0)
	})

	it('counts hidden and missing projects (scenario: a viewer outside a project sees the hidden-projects notice)', () => {
		const merged = mergeViewResults([
			{ projectId: SERVERS, project: null, tasks: [] },
			{ projectId: WORKPLACE, project: workplace, tasks: [laptops] },
			{ projectId: 'gone', project: null, tasks: [] },
		])
		expect(merged.hidden).toBe(2)
		expect(merged.tasks.map((task) => task.id)).toEqual(['t3'])
		expect(Object.keys(merged.projectsById)).toEqual([WORKPLACE])
	})

	it('drops a task that is not of the project it was read for, and duplicates', () => {
		const merged = mergeViewResults([
			{ projectId: SERVERS, project: servers, tasks: [patch, laptops, patch] },
		])
		expect(merged.tasks.map((task) => task.id)).toEqual(['t1'])
	})
})

describe('viewLanes', () => {
	it('groups tasks of two projects by status (scenario: the view shows tasks of two projects)', () => {
		const lanes = viewLanes([patch, backup, laptops], emptyFilter(), 'anna')
		expect(Object.keys(lanes)).toEqual(['open', 'in_progress', 'blocked', 'done', 'cancelled'])
		expect(lanes.open.map((task) => task.id)).toEqual(['t1', 't3'])
		expect(lanes.in_progress.map((task) => task.id)).toEqual(['t2'])
	})

	it('applies the board filter', () => {
		const filter = { ...emptyFilter(), priority: { op: 'is', values: ['high'] } }
		const lanes = viewLanes([patch, backup, laptops], filter, 'anna')
		expect(lanes.open).toEqual([])
		expect(lanes.in_progress.map((task) => task.id)).toEqual(['t2'])
	})
})

describe('resolveViewMove', () => {
	it('resolves the first column mapping the status (scenario: moving a card places it in its project\'s column)', () => {
		const result = resolveViewMove(patch, 'in_progress', serverColumns, [patch, backup])
		expect(result).toEqual({ ok: true, patch: { column: 'doing', columnOrder: 2000, status: 'in_progress' } })
	})

	it('moves to the done column by its type (scenario: the keyboard move works on the view)', () => {
		expect(resolveViewMove(laptops, 'done', workplaceColumns, [laptops])).toEqual({ ok: true, patch: { column: 'w-done', columnOrder: 1000, status: 'done' } })
	})

	it('no matching column refuses the move (scenario: a project without a matching column refuses the move)', () => {
		expect(resolveViewMove(laptops, 'blocked', workplaceColumns, [laptops])).toEqual({ ok: false })
		expect(resolveViewMove(laptops, 'open', [], [laptops])).toEqual({ ok: false })
	})
})

describe('saving a view', () => {
	// The payload is validated against the real `boardView` schema of
	// lib/Settings/planninq_register.json, prepared the way OpenRegister
	// prepares it (slug `$ref` dropped, x- keys dropped).
	const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
	const schema = register.components.schemas.boardView
	const properties = {}
	for (const [name, property] of Object.entries(schema.properties)) {
		const { $ref, visible, nullable, ...rest } = property
		for (const key of Object.keys(rest)) {
			if (key.startsWith('x-')) {
				delete rest[key]
			}
		}
		if (rest.items?.$ref) {
			const { $ref: itemRef, ...items } = rest.items
			rest.items = items
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	const validate = ajv.compile({ type: 'object', required: schema.required, properties })

	it('writes a valid view of two projects (scenario: a user saves a view of two projects)', () => {
		const payload = viewPayload({ title: ' IT operations ', projects: [SERVERS, WORKPLACE, SERVERS], members: ['ben', ''] })
		expect(payload).toEqual({ title: 'IT operations', projects: [SERVERS, WORKPLACE], members: ['ben'] })
		expect(viewProblems(payload)).toEqual([])
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('refuses no projects, too many projects and no name, as the schema does', () => {
		expect(viewProblems(viewPayload({ title: '', projects: [] }))).toEqual(['title', 'noProjects'])
		expect(validate({ title: 'Empty', projects: [] })).toBe(false)

		const many = Array.from({ length: MAX_VIEW_PROJECTS + 1 }, (_, index) => `00000000-0000-4000-8000-${String(index).padStart(12, '0')}`)
		expect(viewProblems(viewPayload({ title: 'All', projects: many }))).toEqual(['tooManyProjects'])
		expect(validate({ title: 'All', projects: many })).toBe(false)
		expect(validate({ title: 'All', projects: many.slice(0, MAX_VIEW_PROJECTS) })).toBe(true)
	})

	it('the project picker offers only member projects (scenario: the project picker offers only member projects)', () => {
		expect(pickableProjects([servers, workplace, finance], 'ben').map((project) => project.title)).toEqual(['Servers'])
		expect(pickableProjects([servers, workplace, finance], 'carl').map((project) => project.title)).toEqual(['Workplace'])
	})

	it('only the owner and admins manage a view (scenario: a view appears for the person it is shared with)', () => {
		const view = { id: 'v1', title: 'IT operations', owner: 'anna', members: ['ben'], projects: [SERVERS] }
		expect(canManageView(view, { uid: 'anna' })).toBe(true)
		expect(canManageView(view, { uid: 'ben' })).toBe(false)
		expect(canManageView(view, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canManageView(null, { uid: 'anna' })).toBe(false)
	})
})
