/**
 * Vitest unit tests for the roadmap helpers (backlog-releases-roadmap): release
 * progress, the span of an epic, the chart rows, the pickers and the patches a
 * shipped release writes, each checked against the register where it is a
 * payload.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.2
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	epicChoices,
	epicPatch,
	epicSpan,
	releaseChoices,
	releasePayload,
	releaseProgress,
	roadmapLayout,
	shipPatches,
	sortReleases,
	unfinishedTasks,
} from '../../src/utils/roadmapHelpers.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

/**
 * Validate a payload against one register schema, with OpenRegister's nullable rule.
 *
 * @param {string} slug The schema.
 * @param {object} payload The payload.
 * @param {boolean} partial Whether to skip the required list (a PATCH body).
 * @return {Array|null} The errors, or null.
 */
function errors(slug, payload, partial = false) {
	const schema = register.components.schemas[slug]
	const properties = {}
	for (const [name, prop] of Object.entries(schema.properties)) {
		const { title, description, nullable, visible, $ref, ...rest } = prop
		if (nullable && rest.type) {
			rest.type = [rest.type, 'null']
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false, allErrors: true })
	const validate = ajv.compile({ type: 'object', required: partial ? [] : schema.required, properties, additionalProperties: false })
	return validate(payload) ? null : validate.errors
}

const tasks = [
	{ id: 't1', project: 'p1', release: 'r1', status: 'done' },
	{ id: 't2', project: 'p1', release: 'r1', status: 'done' },
	{ id: 't3', project: 'p1', release: 'r1', status: 'cancelled' },
	{ id: 't4', project: 'p1', release: 'r1', status: 'open' },
	{ id: 't5', project: 'p1', release: 'r1', status: 'in_progress' },
	{ id: 't6', project: 'p1', release: 'r2', status: 'open' },
]

describe('releaseProgress', () => {
	it('counts done and cancelled tasks out of all tasks of the release (scenario: progress counts done and cancelled tasks)', () => {
		expect(releaseProgress({ id: 'r1' }, tasks)).toEqual({ done: 3, total: 5 })
	})

	it('is 0 of 0 for a new release', () => {
		expect(releaseProgress({ id: 'r9' }, tasks)).toEqual({ done: 0, total: 0 })
	})

	it('reads a release reference that came back as an object', () => {
		expect(releaseProgress({ id: 'r2' }, [{ id: 'x', release: { id: 'r2' }, status: 'done' }])).toEqual({ done: 1, total: 1 })
	})
})

describe('epicSpan', () => {
	const linked = [
		{ id: 'a', epic: 'e1', startDate: '2026-10-12', dueDate: '2026-10-20' },
		{ id: 'b', epic: 'e1', startDate: '2026-10-05', dueDate: null },
		{ id: 'c', epic: 'e1', startDate: null, dueDate: '2026-11-20' },
		{ id: 'd', epic: 'e2', startDate: '2026-01-01', dueDate: '2026-01-02' },
	]

	it('uses the epic\'s own dates when it has them', () => {
		expect(epicSpan({ id: 'e1', startDate: '2026-09-01', dueDate: '2026-09-30' }, linked)).toEqual({ start: '2026-09-01', end: '2026-09-30' })
	})

	it('spans the earliest start to the latest due date of its tasks when it has none (scenario: epic without dates spans its tasks)', () => {
		expect(epicSpan({ id: 'e1' }, linked)).toEqual({ start: '2026-10-05', end: '2026-11-20' })
	})

	it('is null when no linked task has a date (scenario: epic without dated tasks is unscheduled)', () => {
		expect(epicSpan({ id: 'e3' }, [{ id: 'z', epic: 'e3' }])).toBeNull()
	})
})

describe('roadmapLayout', () => {
	it('puts epics as bars and releases as markers on one axis, and lists undated epics apart (scenario: the roadmap shows releases and epics over time)', () => {
		const epics = [{ id: 'e1', title: 'Self-service export', issueType: 'epic' }, { id: 'e3', title: 'Later', issueType: 'epic' }]
		const linked = [
			{ id: 'a', epic: 'e1', startDate: '2026-10-05', dueDate: '2026-11-01' },
			{ id: 'b', epic: 'e1', startDate: '2026-10-10', dueDate: '2026-11-20' },
		]
		const releases = [{ id: 'r1', title: 'Version 2.0', releaseDate: '2026-12-01' }, { id: 'r2', title: 'No date' }]
		const layout = roadmapLayout(epics, linked, releases, 10)

		expect(layout.bars).toHaveLength(1)
		expect(layout.bars[0]).toMatchObject({ id: 'e1', title: 'Self-service export', start: '2026-10-05', end: '2026-11-20', left: 0 })
		expect(layout.bars[0].width).toBe(47 * 10)
		expect(layout.markers).toEqual([expect.objectContaining({ id: 'r1', title: 'Version 2.0', date: '2026-12-01', left: 57 * 10 })])
		expect(layout.unscheduled.map((epic) => epic.id)).toEqual(['e3'])
		expect(layout.undatedReleases.map((release) => release.id)).toEqual(['r2'])
		expect(layout.chartWidth).toBe(58 * 10)
	})

	it('is empty without dated epics or releases', () => {
		const layout = roadmapLayout([], [], [], 10)
		expect(layout.bars).toEqual([])
		expect(layout.markers).toEqual([])
	})
})

describe('pickers', () => {
	const projectTasks = [
		{ id: 'e1', project: 'p1', issueType: 'epic', title: 'Self-service export' },
		{ id: 'e2', project: 'p2', issueType: 'epic', title: 'Elsewhere' },
		{ id: 't1', project: 'p1', issueType: 'task', title: 'Export to CSV' },
	]

	it('offers only this project\'s epics, never the task itself (scenario: another project\'s epic is not offered)', () => {
		expect(epicChoices({ id: 't1', project: 'p1' }, projectTasks).map((task) => task.id)).toEqual(['e1'])
		expect(epicChoices({ id: 'e1', project: 'p1', issueType: 'epic' }, projectTasks)).toEqual([])
	})

	it('offers only this project\'s planned releases, plus the current one (scenario: only the project\'s own releases are offered)', () => {
		const releases = [
			{ id: 'r1', project: 'p1', status: 'planned', title: 'Version 2.0', releaseDate: '2026-12-01' },
			{ id: 'r9', project: 'p2', status: 'planned', title: 'Version 9' },
			{ id: 'r0', project: 'p1', status: 'released', title: 'Version 1.0', releaseDate: '2026-06-01' },
		]
		expect(releaseChoices({ id: 't1', project: 'p1' }, releases).map((release) => release.id)).toEqual(['r1'])
		expect(releaseChoices({ id: 't1', project: { id: 'p1' }, release: 'r0' }, releases).map((release) => release.id)).toEqual(['r0', 'r1'])
	})

	it('refuses an epic of another project and an epic pointing at an epic (scenario: epic picker refuses another project\'s epic)', () => {
		expect(epicPatch({ id: 't1', project: 'p1' }, projectTasks[1])).toEqual({ ok: false, reason: 'other-project' })
		expect(epicPatch({ id: 'e1', project: 'p1', issueType: 'epic' }, { id: 'e4', project: 'p1', issueType: 'epic' })).toEqual({ ok: false, reason: 'epic-in-epic' })
		expect(epicPatch({ id: 't1', project: 'p1' }, { id: 't7', project: 'p1', issueType: 'task' })).toEqual({ ok: false, reason: 'not-an-epic' })
		expect(epicPatch({ id: 't1', project: 'p1' }, projectTasks[0])).toEqual({ ok: true, patch: { epic: 'e1' } })
		expect(epicPatch({ id: 't1', project: 'p1', epic: 'e1' }, null)).toEqual({ ok: true, patch: { epic: null } })
		expect(errors('task', { epic: 'e1' }, true)).toBeNull()
	})
})

describe('release payloads', () => {
	it('writes a new release with its project and status planned (scenario: a member creates a release)', () => {
		const payload = releasePayload({ title: ' Version 2.0 ', releaseDate: '2026-12-01', description: '' }, 'p1')
		expect(payload).toEqual({ title: 'Version 2.0', project: 'p1', releaseDate: '2026-12-01', startDate: null, description: '', status: 'planned' })
		expect(errors('projectRelease', payload)).toBeNull()
	})

	it('sorts releases by target date, undated last', () => {
		expect(sortReleases([{ id: 'b', releaseDate: '2026-12-01' }, { id: 'c' }, { id: 'a', releaseDate: '2026-10-01' }]).map((release) => release.id)).toEqual(['a', 'b', 'c'])
	})

	it('lists the tasks that are neither done nor cancelled', () => {
		expect(unfinishedTasks({ id: 'r1' }, tasks).map((task) => task.id)).toEqual(['t4', 't5'])
	})

	it('only sets the status when every task is finished (scenario: shipping with everything done)', () => {
		const now = new Date('2026-12-01T10:00:00Z')
		const plan = shipPatches({ id: 'r2' }, [{ id: 'x', release: 'r2', status: 'done' }], 'keep', null, now)
		expect(plan).toEqual({ release: { status: 'released', releasedAt: '2026-12-01T10:00:00.000Z' }, tasks: [] })
		expect(errors('projectRelease', plan.release, true)).toBeNull()
	})

	it('moves, clears or keeps the unfinished tasks as the member chose (scenario: shipping asks about unfinished tasks)', () => {
		const now = new Date('2026-12-01T10:00:00Z')
		expect(shipPatches({ id: 'r1' }, tasks, 'move', 'r2', now).tasks).toEqual([{ id: 't4', patch: { release: 'r2' } }, { id: 't5', patch: { release: 'r2' } }])
		expect(shipPatches({ id: 'r1' }, tasks, 'clear', null, now).tasks).toEqual([{ id: 't4', patch: { release: null } }, { id: 't5', patch: { release: null } }])
		expect(shipPatches({ id: 'r1' }, tasks, 'keep', null, now).tasks).toEqual([])
		expect(() => shipPatches({ id: 'r1' }, tasks, 'move', null, now)).toThrow()
		expect(errors('task', { release: 'r2' }, true)).toBeNull()
		expect(errors('task', { release: null }, true)).toBeNull()
	})
})
