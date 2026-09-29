/**
 * Vitest tests for the projects store's duplicate and parent-delete actions
 * (tasks-subtasks-checklist), with the object store and fetch replaced.
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const collections = {}
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? m) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		registerObjectType: () => {},
		objectTypeRegistry: {},
		fetchCollection: async (schema, filters) => (filters._page > 1 ? [] : (collections[schema] || []).filter((row) => !filters.task || row.task === filters.task)),
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('projects store: duplicate and delete a task tree', () => {
	let store
	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		for (const key of Object.keys(collections)) {
			delete collections[key]
		}
	})

	it('copies the task, then each subtask under the copy (scenario: duplicate a task tree)', async () => {
		const created = []
		store.createTask = vi.fn(async (payload) => {
			created.push(payload)
			return { ...payload, id: `new-${created.length}` }
		})
		const task = { id: 't1', title: 'Prepare', project: 'p1', status: 'done', checklist: [{ id: 'c1', text: 'One', done: true }] }
		const copy = await store.duplicateTask(task, [{ id: 's1', title: 'Collect', project: 'p1', parent: 't1' }, { id: 's2', title: 'Send', project: 'p1', parent: 't1' }])

		expect(copy.id).toBe('new-1')
		expect(created[0]).toMatchObject({ title: 'Copy of Prepare', status: 'open', checklist: [{ id: 'c1', text: 'One', done: false }] })
		expect(created.slice(1).map((payload) => [payload.title, payload.parent])).toEqual([['Collect', 'new-1'], ['Send', 'new-1']])
	})

	it('keeps the subtasks as separate tasks (scenario: keep the subtasks)', async () => {
		store.updateTask = vi.fn(async (id, patch) => ({ id, ...patch }))
		store.deleteTask = vi.fn(async () => ({ deleted: true }))
		const result = await store.deleteTaskTree({ id: 't1' }, [{ id: 's1' }, { id: 's2' }], 'detach')

		expect(result).toEqual({ deleted: true })
		expect(store.updateTask.mock.calls).toEqual([['s1', { parent: null }], ['s2', { parent: null }]])
		expect(store.deleteTask.mock.calls).toEqual([['t1']])
	})

	it('deletes the subtasks first, and nothing when one has logged time', async () => {
		store.deleteTask = vi.fn(async () => ({ deleted: true }))
		expect(await store.deleteTaskTree({ id: 't1' }, [{ id: 's1' }], 'delete')).toEqual({ deleted: true })
		expect(store.deleteTask.mock.calls).toEqual([['s1'], ['t1']])

		store.deleteTask.mockClear()
		collections.plannedTimeEntry = [{ id: 'e1', task: 's1', duration: 30 }]
		expect(await store.deleteTaskTree({ id: 't1' }, [{ id: 's1' }], 'delete')).toEqual({ deleted: false, reason: 'has-time' })
		expect(store.deleteTask).not.toHaveBeenCalled()
	})
})
