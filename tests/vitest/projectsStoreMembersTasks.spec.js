/**
 * The projects store's group membership writes, leaving a project, the task
 * counts, the board's task and label reads, the My tasks read and deleting
 * a task or a task tree. Only fetch, the object store and the Nextcloud
 * helpers are faked; fetchEvery, isMine, rolePatch and deleteRefusal are the
 * real ones.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/projects/spec.md
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const world = vi.hoisted(() => ({ uid: 'anna', rows: {}, reads: [], project: null }))

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => (world.uid ? { uid: world.uid } : null) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		registerObjectType: () => {},
		objectTypeRegistry: {},
		getError: () => null,
		fetchObject: async () => (world.project ? { ...world.project } : null),
		fetchCollection: async (schema, filters) => {
			world.reads.push([schema, filters])
			const rows = world.rows[schema]
			if (rows instanceof Error) {
				throw rows
			}
			return filters?._page > 1 ? [] : (rows ?? [])
		},
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

/**
 * A fetch answer.
 *
 * @param {boolean} ok Whether it succeeded.
 * @param {*} body The JSON body.
 * @param {number} status The HTTP status.
 * @return {object} The response.
 */
function answer(ok, body = {}, status = ok ? 200 : 400) {
	return { ok, status, json: async () => body, text: async () => JSON.stringify(body) }
}

describe('projects store members and tasks', () => {
	let store
	let sent

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		world.uid = 'anna'
		world.rows = {}
		world.reads = []
		world.project = { id: 'p1', owner: 'anna', members: ['anna'], memberGroups: ['team'], '@self': { updated: 'v1' } }
		sent = []
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	/**
	 * Answer fetches in order, recording each request.
	 *
	 * @param {...object} answers The answers.
	 */
	function serve(...answers) {
		globalThis.fetch = vi.fn(async (url, init = {}) => {
			sent.push([url, init.method ?? 'GET', init.body ? JSON.parse(init.body) : null])
			const next = answers.shift()
			if (next instanceof Error) {
				throw next
			}
			return next ?? answer(true)
		})
	}

	it('adds a group to the member groups with the version it read, once', async () => {
		serve(answer(true, { id: 'p1', memberGroups: ['team', 'adviseurs'] }))

		const saved = await store.addMemberGroup('p1', 'adviseurs')
		expect(saved).toEqual({ id: 'p1', memberGroups: ['team', 'adviseurs'] })
		expect(sent).toEqual([[
			'/apps/openregister/api/objects/planninq/project/p1',
			'PATCH',
			{ memberGroups: ['team', 'adviseurs'], _expectedUpdated: 'v1' },
		]])

		const again = await store.addMemberGroup('p1', 'team')
		expect(again.id).toBe('p1')
		expect(sent).toHaveLength(1)
	})

	it('starts the member groups when the project has none', async () => {
		world.project = { id: 'p1', owner: 'anna', members: ['anna'] }
		serve(answer(true, { id: 'p1' }))

		await store.addMemberGroup('p1', 'team')
		expect(sent[0][2]).toEqual({ memberGroups: ['team'], _expectedUpdated: undefined })
	})

	it('removes only the named group from the member groups', async () => {
		world.project.memberGroups = ['team', 'adviseurs']
		serve(answer(true, { id: 'p1', memberGroups: ['adviseurs'] }))

		await store.removeMemberGroup('p1', 'team')
		expect(sent[0][2]).toEqual({ memberGroups: ['adviseurs'], _expectedUpdated: 'v1' })

		world.project = { id: 'p1', owner: 'anna' }
		serve(answer(true, { id: 'p1' }))
		await store.removeMemberGroup('p1', 'team')
		expect(sent[1][2]).toEqual({ memberGroups: [], _expectedUpdated: undefined })
	})

	it('writes no role change for a project it cannot read', async () => {
		world.project = null
		serve()

		expect(await store.addMemberGroup('p1', 'team')).toBe(null)
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('does not retry a role write refused for another reason than a conflict', async () => {
		serve(answer(false, { message: 'Forbidden' }, 403))

		expect(await store.removeMemberGroup('p1', 'team')).toBe(null)
		expect(sent).toHaveLength(1)
	})

	it('counts a member\'s tasks in a project, and zero when the read fails', async () => {
		world.rows.task = [{ id: 't1' }, { id: 't2' }]
		expect(await store.getMemberTaskCount('p1', 'bram')).toBe(2)
		expect(world.reads[0][0]).toBe('task')
		expect(world.reads[0][1]).toMatchObject({ project: 'p1', assignedTo: 'bram', _page: 1 })

		world.rows.task = new Error('down')
		expect(await store.getMemberTaskCount('p1', 'bram')).toBe(0)
	})

	it('counts a project\'s tasks, and zero when the read fails', async () => {
		world.rows.task = [{ id: 't1' }, { id: 't2' }, { id: 't3' }]
		expect(await store.getTaskCount('p1')).toBe(3)
		expect(world.reads[0][1]).toMatchObject({ project: 'p1' })
		expect(world.reads[0][1]).not.toHaveProperty('assignedTo')

		world.rows.task = new Error('down')
		expect(await store.getTaskCount('p1')).toBe(0)
	})

	it('leaves a project through the server and drops it from the list', async () => {
		store.projects = [{ id: 'p1' }, { id: 'p2' }]
		store.activeProject = { id: 'p1' }
		serve(answer(true, { id: 'p1', members: ['bram'] }))

		expect(await store.leaveProject('p1')).toEqual({ id: 'p1', members: ['bram'] })
		expect(sent).toEqual([['/apps/planninq/api/projects/p1/leave', 'POST', null]])
		expect(globalThis.fetch.mock.calls[0][1].headers).toEqual({ 'X-Test': '1' })
		expect(store.projects).toEqual([{ id: 'p2' }])
		expect(store.activeProject).toBe(null)
		expect(store.error).toBe(null)
		expect(store.loading).toBe(false)
	})

	it('keeps another active project when leaving one', async () => {
		store.projects = [{ id: 'p1' }, { id: 'p2' }]
		store.activeProject = { id: 'p2' }
		serve(answer(true, { id: 'p1' }))

		await store.leaveProject('p1')
		expect(store.activeProject).toEqual({ id: 'p2' })
	})

	it('keeps the project and records why when leaving is refused', async () => {
		store.projects = [{ id: 'p1' }]
		serve(
			answer(false, { error: 'Cannot leave as the last member' }, 422),
			{ ok: false, status: 500, json: async () => { throw new Error('not json') } },
			new Error('offline'),
			Object.assign(new Error(''), { message: '' }),
		)

		expect(await store.leaveProject('p1')).toBe(null)
		expect(store.error).toBe('Cannot leave as the last member')
		expect(store.projects).toEqual([{ id: 'p1' }])

		expect(await store.leaveProject('p1')).toBe(null)
		expect(store.error).toBe('leave-error')

		expect(await store.leaveProject('p1')).toBe(null)
		expect(store.error).toBe('offline')
		expect(store.loading).toBe(false)

		expect(await store.leaveProject('p1')).toBe(null)
		expect(store.error).toBe('leave-error')
	})

	it('reads a project\'s tasks for the board, and none when the read fails', async () => {
		world.rows.task = [{ id: 't1' }]
		expect(await store.fetchTasks('p1')).toEqual([{ id: 't1' }])
		expect(world.reads[0][1]).toMatchObject({ project: 'p1' })

		world.rows.task = new Error('down')
		expect(await store.fetchTasks('p1')).toEqual([])
		expect(console.error).toHaveBeenCalledWith('fetchTasks error:', world.rows.task)
	})

	it('reads every label without a filter, and none when the read fails', async () => {
		world.rows.label = [{ id: 'l1' }, { id: 'l2' }]
		expect(await store.fetchLabels()).toEqual([{ id: 'l1' }, { id: 'l2' }])
		expect(world.reads[0][0]).toBe('label')
		expect(Object.keys(world.reads[0][1]).sort()).toEqual(['_limit', '_page'])

		world.rows.label = new Error('down')
		expect(await store.fetchLabels()).toEqual([])
	})

	it('keeps only the user\'s own and shared tasks, and none without a user or on failure', async () => {
		world.rows.task = [
			{ id: 't1', assignedTo: 'anna' },
			{ id: 't2', assignedTo: 'bram' },
			{ id: 't3', assignedTo: 'bram', sharedWith: ['anna'] },
		]
		expect((await store.fetchMyTasks()).map((task) => task.id)).toEqual(['t1', 't3'])

		world.rows.task = new Error('down')
		expect(await store.fetchMyTasks()).toEqual([])

		world.uid = null
		world.reads = []
		expect(await store.fetchMyTasks()).toEqual([])
		expect(world.reads).toEqual([])
	})

	it('reads a project\'s releases, and none when the read fails', async () => {
		world.rows.projectRelease = [{ id: 'r1' }]
		expect(await store.fetchReleases('p1')).toEqual([{ id: 'r1' }])
		expect(world.reads[0][0]).toBe('projectRelease')

		world.rows.projectRelease = new Error('down')
		expect(await store.fetchReleases('p1')).toEqual([])
	})

	it('refuses to delete a task with logged time without asking the server', async () => {
		world.rows.plannedTimeEntry = [{ id: 'e1' }]
		serve()

		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'has-time' })
		expect(world.reads[0]).toEqual(['plannedTimeEntry', expect.objectContaining({ task: 't1' })])
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('deletes a task without time, and names the server\'s refusal', async () => {
		serve(
			answer(true),
			answer(false, { code: 'planninq-task-delete-not-allowed' }, 403),
			answer(false, { errors: { code: 'planninq-task-has-logged-time' } }, 409),
			{ ok: false, status: 500, json: async () => { throw new Error('not json') } },
			new Error('offline'),
		)

		expect(await store.deleteTask('t1')).toEqual({ deleted: true })
		expect(sent[0]).toEqual(['/apps/openregister/api/objects/planninq/task/t1', 'DELETE', null])
		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'not-allowed' })
		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'has-time' })
		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'failed' })
		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'failed' })
	})

	it('answers failed when the time entries cannot be read', async () => {
		world.rows.plannedTimeEntry = new Error('down')
		serve()

		expect(await store.deleteTask('t1')).toEqual({ deleted: false, reason: 'failed' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('deletes a task tree in the chosen mode and names a refusal', async () => {
		serve(
			answer(true),
			answer(false, { code: 'planninq-task-has-logged-time' }, 409),
			{ ok: false, status: 500, json: async () => { throw new Error('not json') } },
			new Error('offline'),
		)

		expect(await store.deleteTaskTree({ id: 't1' }, 'detach')).toEqual({ deleted: true })
		expect(sent[0]).toEqual(['/apps/planninq/api/tasks/t1?subtasks=detach', 'DELETE', null])
		expect(await store.deleteTaskTree({ id: 't1' }, 'a b')).toEqual({ deleted: false, reason: 'has-time' })
		expect(sent[1][0]).toBe('/apps/planninq/api/tasks/t1?subtasks=a%20b')
		expect(await store.deleteTaskTree({ id: 't1' }, 'delete')).toEqual({ deleted: false, reason: 'failed' })
		expect(await store.deleteTaskTree({ id: 't1' }, 'delete')).toEqual({ deleted: false, reason: 'failed' })
	})

	it('creates a task with defaults and answers null when refused', async () => {
		serve(answer(true, { id: 't9' }), answer(false, {}, 403), new Error('offline'))

		expect(await store.createTask({ title: 'Plan', project: 'p1' })).toEqual({ id: 't9' })
		expect(sent[0][0]).toBe('/apps/openregister/api/objects/planninq/task')
		expect(sent[0][1]).toBe('POST')
		expect(sent[0][2]).toMatchObject({ title: 'Plan', project: 'p1', status: 'open', priority: 'normal' })
		expect(await store.createTask({ title: 'Plan', project: 'p1' })).toBe(null)
		expect(await store.createTask({ title: 'Plan', project: 'p1' })).toBe(null)
	})
})
