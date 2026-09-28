/**
 * Vitest unit tests for the project overview, tabs and log helpers
 * (projects-overview-logs-risks).
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	actionTasks,
	filterLog,
	latestLogEntries,
	logEntryPayload,
	newActionTask,
	PROJECT_TABS,
	projectPeople,
	projectProgress,
} from '../../src/utils/projectOverview.js'

const project = '11111111-1111-4111-8111-111111111111'
const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
const manifest = JSON.parse(readFileSync(new URL('../../src/manifest.json', import.meta.url), 'utf8'))

/**
 * A validator for one register schema, the way OpenRegister reads it: the
 * `x-` annotations, `$ref` and `visible` are OpenRegister's, not JSON Schema's.
 *
 * @param {string} slug The schema slug.
 * @return {Function}
 */
function validatorFor(slug) {
	const schema = register.components.schemas[slug]
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
	return ajv.compile({ type: 'object', required: schema.required, properties })
}

describe('projectProgress', () => {
	it('counts done tasks against every task that is not cancelled (scenario: the overview shows where the project stands)', () => {
		const tasks = [
			...Array.from({ length: 4 }, (_, i) => ({ id: `d${i}`, status: 'done' })),
			...Array.from({ length: 5 }, (_, i) => ({ id: `o${i}`, status: i % 2 ? 'open' : 'in_progress' })),
			{ id: 'x', status: 'cancelled' },
		]
		expect(projectProgress(tasks)).toEqual({ done: 4, total: 9 })
	})

	it('reports no tasks for an empty project (scenario: an empty project says so)', () => {
		expect(projectProgress([])).toEqual({ done: 0, total: 0 })
	})
})

describe('projectPeople', () => {
	it('lists the owner first and each person once', () => {
		expect(projectPeople({ owner: 'carol', members: ['bob', 'carol', 'alice'] })).toEqual(['carol', 'bob', 'alice'])
	})
})

describe('project tabs', () => {
	it('each tab opens a manifest page under /projects/:id', () => {
		const pages = Object.fromEntries(manifest.pages.map((page) => [page.id, page]))
		for (const tab of PROJECT_TABS) {
			expect(pages[tab.route]?.route, tab.route).toMatch(/^\/projects\/:id(\/|$)/)
		}
		expect(PROJECT_TABS.map((tab) => tab.id)).toEqual(['overview', 'board', 'backlog', 'timeline', 'log'])
	})
})

describe('project log', () => {
	const entries = [
		{ id: 'a', type: 'issue', title: 'Old issue', date: '2026-09-01', '@self': { created: '2026-09-01T08:00:00Z' } },
		{ id: 'b', type: 'meeting', title: 'Kick-off', date: '2026-09-20', '@self': { created: '2026-09-20T09:00:00Z' } },
		{ id: 'c', type: 'decision', title: 'Go', date: '2026-09-20', '@self': { created: '2026-09-20T11:00:00Z' } },
		{ id: 'd', type: 'lesson', title: 'Plan buffer', date: '2026-09-10' },
	]

	it('puts the newest entry on top (scenario: recording a meeting)', () => {
		expect(filterLog(entries).map((entry) => entry.id)).toEqual(['c', 'b', 'd', 'a'])
	})

	it('the Meetings filter shows meetings and hides the other types', () => {
		expect(filterLog(entries, 'meeting').map((entry) => entry.id)).toEqual(['b'])
	})

	it('the overview reads the five newest entries', () => {
		const many = Array.from({ length: 8 }, (_, i) => ({ id: `e${i}`, type: 'issue', date: `2026-09-0${i + 1}` }))
		expect(latestLogEntries(many).map((entry) => entry.id)).toEqual(['e7', 'e6', 'e5', 'e4', 'e3'])
	})

	it('builds a meeting payload the real projectLogEntry schema accepts', () => {
		const validate = validatorFor('projectLogEntry')
		const payload = logEntryPayload({ type: 'meeting', title: ' Kick-off ', date: '2026-09-28', body: 'We agreed the plan.', attendees: ['ada', 'bob', 'ada'] }, project)
		expect(payload).toMatchObject({ title: 'Kick-off', type: 'meeting', attendees: ['ada', 'bob'], status: 'open' })
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('drops attendees from an entry that is not a meeting', () => {
		expect(logEntryPayload({ type: 'issue', title: 'X', date: '2026-09-28', attendees: ['ada'] }, project).attendees).toEqual([])
	})

	it('a log entry without a date is refused by the schema', () => {
		const validate = validatorFor('projectLogEntry')
		const payload = logEntryPayload({ type: 'issue', title: 'X' }, project)
		delete payload.date
		expect(validate(payload)).toBe(false)
	})
})

describe('actions', () => {
	const columns = [
		{ id: '22222222-2222-4222-8222-222222222222', title: 'In progress', order: 1, status: 'in_progress' },
		{ id: '22222222-2222-4222-8222-222222222221', title: 'To do', order: 0, status: 'open' },
	]
	const tasks = [
		{ id: 't1', column: '22222222-2222-4222-8222-222222222221', columnOrder: 1000 },
		{ id: 't2', column: '22222222-2222-4222-8222-222222222221', columnOrder: 3000, issueType: 'action' },
		{ id: 't3', column: '22222222-2222-4222-8222-222222222222', columnOrder: 5000 },
	]

	it('an action is a normal task at the bottom of the first board column (scenario: turning a meeting outcome into an action)', () => {
		const validate = validatorFor('task')
		const payload = newActionTask({ title: ' Send the planning to the steering group ', assignedTo: 'ada' }, project, columns, tasks)
		expect(payload).toMatchObject({
			title: 'Send the planning to the steering group',
			issueType: 'action',
			project,
			assignedTo: 'ada',
			column: '22222222-2222-4222-8222-222222222221',
			columnOrder: 4000,
			status: 'open',
		})
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('lands in the backlog when the project has no columns', () => {
		expect(newActionTask({ title: 'A' }, project, [], []).column).toBe(null)
	})

	it('the Actions filter lists the tasks with issueType action', () => {
		expect(actionTasks(tasks).map((task) => task.id)).toEqual(['t2'])
	})
})
