/**
 * Moving a task to another project (tasks-move-between-projects): the helpers
 * in src/utils/taskMove.js and the store's moveTaskToProject, with only fetch
 * and the Nextcloud globals stood in for.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { linksToRemove, movePatch, moveTargets, peopleToClear, timeLoggedElsewhere } = await import('../../src/utils/taskMove.js')
const { useProjectsStore } = await import('../../src/store/projects.js')

const VERGUNNINGEN = { id: 'p-verg', title: 'Vergunningen', owner: 'anna', members: ['bram', 'cees'] }
const HANDHAVING = { id: 'p-hand', title: 'Handhaving', owner: 'dirk', members: ['anna', 'bram'] }
const FINANCIEN = { id: 'p-fin', title: 'Financien', owner: 'eva', members: ['bram'], viewers: ['anna'] }
const ARCHIEF = { id: 'p-arch', title: 'Archief', owner: 'anna', status: 'archived' }
const GROUPED = { id: 'p-group', title: 'Afval', owner: 'eva', memberGroups: ['team-afval'] }

describe('moveTargets', () => {
	it('offers the open projects the person works on, except the task\'s own, by title', () => {
		const targets = moveTargets([HANDHAVING, VERGUNNINGEN, FINANCIEN, ARCHIEF, GROUPED, { title: 'no id' }], 'p-verg', 'anna', ['team-afval'])

		expect(targets.map((project) => project.title)).toEqual(['Afval', 'Handhaving'])
	})

	it('leaves out a project the person only views', () => {
		expect(moveTargets([FINANCIEN], 'p-verg', 'anna')).toEqual([])
	})
})

describe('peopleToClear and movePatch', () => {
	const task = { id: 't-1', project: 'p-verg', column: 'c-1', columnOrder: 3, assignedTo: 'cees', sharedWith: ['bram', 'anna', 'cees'] }

	it('names the people on the task who are not on the target', () => {
		expect(peopleToClear(task, HANDHAVING)).toEqual(['cees'])
		expect(peopleToClear({ assignedTo: '', sharedWith: null }, HANDHAVING)).toEqual([])
	})

	it('moves the task to the target backlog and clears the people who are not members', () => {
		expect(movePatch(task, HANDHAVING)).toEqual({
			project: 'p-hand',
			column: null,
			columnOrder: null,
			assignedTo: null,
			sharedWith: ['bram', 'anna'],
		})
	})

	it('leaves the people alone when they are all on the target', () => {
		expect(movePatch({ id: 't-2', assignedTo: 'bram', sharedWith: ['anna'] }, HANDHAVING)).toEqual({ project: 'p-hand', column: null, columnOrder: null })
	})
})

describe('linksToRemove', () => {
	it('names every link that has the task on either end', () => {
		const edges = [
			{ id: 'e-1', blocker: 't-1', blocked: 't-2' },
			{ id: 'e-2', blocker: 't-3', blocked: 't-1' },
			{ id: 'e-3', blocker: 't-2', blocked: 't-3' },
			null,
		]

		expect(linksToRemove({ id: 't-1' }, edges).map((edge) => edge.id)).toEqual(['e-1', 'e-2'])
		expect(linksToRemove({ id: 't-1' }, undefined)).toEqual([])
	})
})

describe('timeLoggedElsewhere', () => {
	it('sums the time booked under other projects, per project', () => {
		const entries = [
			{ project: 'p-verg', duration: 60 },
			{ project: 'p-verg', duration: 60 },
			{ project: 'p-hand', duration: 30 },
			{ project: 'p-old', duration: 0 },
			{ duration: 15 },
		]

		expect(timeLoggedElsewhere({ project: 'p-hand' }, entries)).toEqual([{ project: 'p-verg', minutes: 120 }])
		expect(timeLoggedElsewhere({ project: 'p-hand' }, null)).toEqual([])
	})
})

describe('moveTaskToProject', () => {
	let fetchMock

	beforeEach(() => {
		setActivePinia(createPinia())
		fetchMock = vi.fn()
		vi.stubGlobal('fetch', fetchMock)
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	/**
	 * A fetch answer.
	 *
	 * @param {boolean} ok - whether the write succeeded
	 * @param {object} body - the answer body
	 * @return {object} the response
	 */
	function answer(ok, body) {
		return { ok, status: ok ? 200 : 403, json: async () => body, text: async () => JSON.stringify(body) }
	}

	const task = { id: 't-1', title: 'Check the drawings', project: 'p-verg', column: 'c-1', assignedTo: 'cees', sharedWith: [] }
	const subtasks = [{ id: 's-1', project: 'p-verg', assignedTo: 'bram' }, { id: 's-2', project: 'p-verg', assignedTo: 'cees' }]

	it('PATCHes the task, then each subtask, into the target backlog', async () => {
		fetchMock.mockResolvedValue(answer(true, { project: 'p-hand' }))
		const store = useProjectsStore()
		store.activeTask = task

		const result = await store.moveTaskToProject(task, HANDHAVING, subtasks)

		expect(result.ok).toBe(true)
		expect(result.failed).toEqual([])
		expect(fetchMock.mock.calls.map(([url, init]) => [url, init.method, JSON.parse(init.body)])).toEqual([
			['/apps/openregister/api/objects/planninq/task/t-1', 'PATCH', { project: 'p-hand', column: null, columnOrder: null, assignedTo: null }],
			['/apps/openregister/api/objects/planninq/task/s-1', 'PATCH', { project: 'p-hand', column: null, columnOrder: null }],
			['/apps/openregister/api/objects/planninq/task/s-2', 'PATCH', { project: 'p-hand', column: null, columnOrder: null, assignedTo: null }],
		])
		expect(store.activeTask).toMatchObject({ id: 't-1', project: 'p-hand', column: null })
	})

	it('writes nothing more when the task itself is refused', async () => {
		fetchMock.mockResolvedValue(answer(false, { message: 'Not a member of the target' }))
		const store = useProjectsStore()

		const result = await store.moveTaskToProject(task, HANDHAVING, subtasks)

		expect(result).toEqual({ ok: false, failed: [] })
		expect(fetchMock).toHaveBeenCalledTimes(1)
		expect(store.error).toBe('Not a member of the target')
	})

	it('reports the subtasks that could not move', async () => {
		fetchMock
			.mockResolvedValueOnce(answer(true, {}))
			.mockResolvedValueOnce(answer(false, { message: 'no' }))
			.mockResolvedValueOnce(answer(true, {}))

		const result = await useProjectsStore().moveTaskToProject(task, HANDHAVING, subtasks)

		expect(result.ok).toBe(true)
		expect(result.failed).toEqual(['s-1'])
	})
})
