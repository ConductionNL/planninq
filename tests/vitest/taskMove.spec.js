/**
 * Moving a task, or moving and copying a column, to another project
 * (tasks-move-between-projects): the pure helpers and the store actions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { columnCopyPayload, columnTaskCopyPayload, moveTaskPatch, peopleToClear, targetProjects, timeLoggedElsewhere } from '../../src/utils/taskMove.js'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')

const TARGET = { id: 'p2', title: 'Handhaving', owner: 'olga', members: ['olga', 'mies'] }
const TASK = { id: 't1', project: 'p1', column: 'c1', columnOrder: 5, phase: 'ph', assignedTo: 'bram', sharedWith: ['mies', 'bram'] }

describe('taskMove helpers', () => {
	it('offers only other projects the user is on', () => {
		const projects = [{ id: 'p1', title: 'A', owner: 'olga' }, TARGET, { id: 'p3', title: 'C', owner: 'x', members: ['y'] }]
		expect(targetProjects(projects, 'olga', 'p1')).toEqual([{ id: 'p2', label: 'Handhaving' }])
	})

	it('names the people who are not on the target project', () => {
		expect(peopleToClear(TASK, TARGET)).toEqual(['bram'])
	})

	it('moves to the backlog and clears project-bound fields and outsiders', () => {
		expect(moveTaskPatch(TASK, TARGET)).toEqual({
			project: 'p2',
			column: null,
			columnOrder: null,
			phase: null,
			epic: null,
			release: null,
			parent: null,
			assignedTo: null,
			sharedWith: ['mies'],
		})
	})

	it('a subtask keeps its parent', () => {
		expect('parent' in moveTaskPatch({ id: 's' }, TARGET, true)).toBe(false)
	})

	it('totals time booked on another project', () => {
		const entries = [{ project: 'p1', duration: 60 }, { project: 'p0', duration: 90 }, { project: 'p0', duration: 30 }]
		expect(timeLoggedElsewhere(entries, 'p1')).toEqual([{ projectId: 'p0', minutes: 120 }])
	})

	it('appends a copied column last and copies tasks open without people', () => {
		const copy = columnCopyPayload({ id: 'c1', title: 'Intake', project: 'p1', members: ['a'], order: 1 }, TARGET, [{ order: 3 }])
		expect(copy).toEqual({ title: 'Intake', order: 4, project: 'p2' })
		const task = columnTaskCopyPayload({ title: 'X', status: 'done', assignedTo: 'bram', columnOrder: 2 }, TARGET, 'c9')
		expect(task).toMatchObject({ status: 'open', project: 'p2', column: 'c9' })
		expect(task.assignedTo).toBeUndefined()
	})
})

describe('moveTaskToProject and copyColumnToProject', () => {
	let store
	let fetchMock

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		store.fetchColumns = vi.fn(async () => [{ order: 2 }])
		fetchMock = vi.fn(async (url, init) => ({ ok: true, json: async () => ({ id: 'new', ...JSON.parse(init.body) }) }))
		vi.stubGlobal('fetch', fetchMock)
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('PATCHes the parent, then each subtask, into the target project', async () => {
		await store.moveTaskToProject(TASK, [{ id: 's1', project: 'p1' }], TARGET)
		expect(fetchMock).toHaveBeenCalledTimes(2)
		expect(fetchMock.mock.calls[0][0]).toBe('/apps/openregister/api/objects/planninq/task/t1')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body).project).toBe('p2')
		expect(fetchMock.mock.calls[1][0]).toBe('/apps/openregister/api/objects/planninq/task/s1')
		expect(JSON.parse(fetchMock.mock.calls[1][1].body)).not.toHaveProperty('parent')
	})

	it('copies a column and its tasks without touching the original', async () => {
		await store.copyColumnToProject({ id: 'c1', title: 'Intake' }, [{ title: 'a' }, { title: 'b' }, { title: 'c' }], TARGET)
		const methods = fetchMock.mock.calls.map(([, init]) => init.method)
		expect(methods).toEqual(['POST', 'POST', 'POST', 'POST'])
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toMatchObject({ title: 'Intake', order: 3, project: 'p2' })
	})
})
